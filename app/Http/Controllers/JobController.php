<?php

namespace App\Http\Controllers;

use App\Jobs\SyncJobsFromSourceJob;
use App\Models\ActivityLog;
use App\Services\SaleItemsJobEditsBuilder;
use App\Models\Job;
use App\Models\BlockedCategory;
use App\Models\BlockedProduct;
use App\Models\JobEdit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\View\View;

class JobController extends Controller
{
    public function syncFromSource(): RedirectResponse
    {
        if (! auth()->user()->isAdmin() && ! auth()->user()->isManager()) {
            abort(403);
        }

        SyncJobsFromSourceJob::dispatch();

        return redirect()->route('jobs.index')->with(
            'success',
            'Sync queued. It will run in the background—refresh the jobs list in a moment. (Run "php artisan queue:work" if sync does not run.)'
        );
    }

    /**
     * Create or fetch a Job record for a POS sale and redirect to the job details page.
     * Only called when a user explicitly chooses to work on a POS sale.
     */
    public function createFromSource(Request $request, int $saleId): RedirectResponse
    {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login');
        }
        if (! $user->canManageJobs() && ! $user->canTakeJob()) {
            abort(403);
        }

        $conn = 'source';
        $db = config("database.connections.{$conn}.database");
        if (empty($db)) {
            return redirect()->route('jobs.live')->with('error', 'Source database not configured. Check DB_SOURCE_DATABASE in .env.');
        }

        try {
            $sale = DB::connection($conn)
                ->table('sma_sales')
                ->where('id', $saleId)
                ->first();
        } catch (\Throwable $e) {
            return redirect()->route('jobs.live')->with('error', 'Cannot read source sale: ' . $e->getMessage());
        }

        if (! $sale) {
            return redirect()->route('jobs.live')->with('error', 'Sale not found in POS database.');
        }

        $refNumber = (string) ($sale->reference_no ?? '');
        if ($refNumber === '') {
            return redirect()->route('jobs.live')->with('error', 'Sale has no reference number and cannot be opened as a job.');
        }

        $dueDate = isset($sale->due_date) && $sale->due_date !== '0000-00-00'
            ? $sale->due_date
            : null;

        $paymentStatus = strtolower((string) ($sale->payment_status ?? ''));
        $isActive = in_array($paymentStatus, ['pending', 'due', 'partial', 'unpaid'], true);

        // Reuse an existing job if we have already opened this sale before.
        $job = Job::where('source_id', (string) $sale->id)
            ->orWhere('ref_number', $refNumber)
            ->first();

        if ($job) {
            $job->update([
                'customer_name' => $sale->customer ?? $job->customer_name,
                'due_date' => $dueDate,
                'is_active' => $isActive,
            ]);
        } else {
            $job = Job::create([
                'ref_number' => $refNumber,
                'source_id' => (string) $sale->id,
                'customer_name' => $sale->customer ?? null,
                'notes' => $sale->note ?? null,
                'due_date' => $dueDate,
                'is_active' => $isActive,
                'status' => Job::STATUS_NEW,
            ]);
        }

        // Ensure job items (edits) are present for this job based on POS sale items.
        try {
            $this->syncSaleItemsFromSource($conn, $job, (int) $sale->id);
        } catch (\Throwable $e) {
            Log::error('syncSaleItemsFromSource failed', [
                'job_id' => $job->id,
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
            ]);
            $msg = config('app.debug')
                ? $e->getMessage()
                : 'Could not sync line items from POS. Ensure migrations are run on the server, then try again.';

            return redirect()->route('jobs.live')->with('error', $msg);
        }

        ActivityLog::log(
            'job_created_from_pos',
            'Opened POS sale ' . $refNumber . ' as job from Job Pool',
            'job',
            $job->id
        );

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Job opened from Job Pool. You can now Take or Dismiss this job.');
    }

    /**
     * Load sale items from the POS database for a single sale and mirror them into this job's edits.
     * This is a per-job version of the console sync used when opening a job from the Job Pool.
     */
    private function syncSaleItemsFromSource(string $conn, Job $job, int $saleId): void
    {
        try {
            $items = DB::connection($conn)
                ->table('sma_sale_items')
                ->where('sale_id', $saleId)
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            return;
        }

        $rows = SaleItemsJobEditsBuilder::rowsFromSaleItems($conn, $items);

        if (empty($rows)) {
            if ($job->edits()->count() === 0) {
                $job->edits()->create(JobEdit::attributesForExistingColumns([
                    'name' => 'Edit 1',
                    'sort_order' => 0,
                    'edit_status' => JobEdit::EDIT_STATUS_PENDING,
                    'print_status' => JobEdit::PRINT_STATUS_PENDING,
                ]));
            }
            return;
        }

        $existing = $job->edits()->orderBy('sort_order')->get();
        foreach ($rows as $sortOrder => $row) {
            $edit = $existing->firstWhere('sort_order', $sortOrder);
            $payload = JobEdit::attributesForExistingColumns([
                'name' => $row['name'],
                'source_product_id' => $row['source_product_id'],
                'category_name' => $row['category_name'],
                'subcategory_name' => $row['subcategory_name'],
                'source_category_id' => $row['source_category_id'] ?? null,
                'source_sale_item_id' => $row['source_sale_item_id'] ?? null,
                'source_quantity_unit_index' => $row['source_quantity_unit_index'] ?? null,
                'source_quantity_unit_total' => $row['source_quantity_unit_total'] ?? null,
                'sort_order' => $sortOrder,
                'edit_status' => $edit ? $edit->edit_status : JobEdit::EDIT_STATUS_PENDING,
                'print_status' => $edit ? $edit->print_status : JobEdit::PRINT_STATUS_PENDING,
            ]);
            if ($edit) {
                $edit->update($payload);
            } else {
                $job->edits()->create($payload);
            }
        }
        $maxOrder = count($rows) - 1;
        $job->edits()->where('sort_order', '>', $maxOrder)->delete();
    }

    /**
     * Live view of POS sales (source database) with optional links into the Job system.
     * This does NOT copy all POS rows into our DB – it reads directly from the source DB
     * and only creates a Job when the user chooses to open/work on a sale.
     */
    public function live(Request $request): View|RedirectResponse
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $ref = $request->input('ref');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;

        $conn = 'source';
        $db = config("database.connections.{$conn}.database");
        if (empty($db)) {
            return redirect()->route('jobs.index')->with('error', 'Source database not configured. Check DB_SOURCE_DATABASE in .env.');
        }

        if ($user->usesDedicatedPrintFramingJobPool()) {
            return $this->liveDedicatedPrintFramingPool($request, $user, $conn, $ref, $page, $perPage);
        }

        try {
            // Exclude POS sales that already have a STARTED job for this user (editor),
            // or for anyone (for non-editors). "Started" = assigned, in_progress, completed, delivered.
            $startedJobQuery = Job::whereNotNull('source_id')
                ->whereIn('status', [
                    Job::STATUS_ASSIGNED,
                    Job::STATUS_IN_PROGRESS,
                    Job::STATUS_COMPLETED,
                    Job::STATUS_DELIVERED,
                ]);

            if ($user->isEditor()) {
                // For editors: only hide POS rows where this user already has a started job.
                $startedJobQuery->where(function ($q) use ($user) {
                    $q->where('assigned_editor_id', $user->id)
                        ->orWhereHas('editors', fn ($qq) => $qq->where('user_id', $user->id));
                });
            }

            $usedSourceIds = $startedJobQuery
                ->pluck('source_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $tz = config('app.timezone');
            $minSaleDate = Carbon::parse(Job::SOURCE_JOB_POOL_MIN_SALE_DATE, $tz)->startOfDay();

            $query = DB::connection($conn)
                ->table('sma_sales')
                ->where('pos', 1)
                // Job Pool: paid, partial, and unpaid/due/pending POS sales (see Job::SOURCE_JOB_POOL_PAYMENT_STATUSES).
                ->whereIn('payment_status', Job::SOURCE_JOB_POOL_PAYMENT_STATUSES)
                // POS sale on or after SOURCE_JOB_POOL_MIN_SALE_DATE (app timezone).
                ->where('date', '>=', $minSaleDate)
                // Omit sales with no usable due date in POS. Calendar-only dues are often stored as midnight — include those.
                ->whereNotNull('due_date')
                ->where('due_date', '<>', '0000-00-00')
                ->where('due_date', '<>', '0000-00-00 00:00:00');

            if (! empty($usedSourceIds)) {
                $query->whereNotIn('id', $usedSourceIds);
            }

            if ($ref) {
                $query->where('reference_no', 'like', '%' . $ref . '%');
            }

            // For editors with category restrictions: only include sales that have at least
            // one item in one of their allowed categories.
            if ($user->isEditor()) {
                $allowed = $user->assignedCategoryIds();
                if (! empty($allowed)) {
                    $query->whereExists(function ($q) use ($allowed, $conn) {
                        $q->select(DB::raw(1))
                            ->from('sma_sale_items as si')
                            ->leftJoin('sma_products as p', 'si.product_id', '=', 'p.id')
                            ->whereColumn('si.sale_id', 'sma_sales.id')
                            ->whereIn('p.category_id', $allowed);
                    });
                }
            }

            // Only count and fetch once to avoid re-building the query.
            $total = (clone $query)->count();

            $sales = $query
                // Soonest due date/time first, then earliest sale date.
                ->orderBy('due_date')
                ->orderBy('date')
                ->forPage($page, $perPage)
                ->get([
                    'id',
                    'reference_no',
                    'customer',
                    'payment_status',
                    'due_date',
                    'date',
                ]);
        } catch (\Throwable $e) {
            return redirect()->route('jobs.index')->with('error', 'Cannot read source sales: ' . $e->getMessage());
        }

        // Map any existing local jobs by source_id so we can show status / links.
        $sourceIds = $sales->pluck('id')->map(fn ($id) => (string) $id)->all();

        $jobsBySourceId = Job::with(['editor', 'editors'])
            ->whereIn('source_id', $sourceIds)
            ->get()
            ->keyBy('source_id');

        // Load sale items (job items) from POS for all listed sales so we can show them in Job Pool.
        // For editors with category restrictions, only include items in their allowed categories.
        $itemsBySaleId = collect();
        $saleIds = $sales->pluck('id')->all();
        if ($saleIds !== []) {
            try {
                $itemsQuery = DB::connection($conn)
                    ->table('sma_sale_items as si')
                    ->whereIn('si.sale_id', $saleIds)
                    ->orderBy('si.id')
                    ->leftJoin('sma_products as p', 'si.product_id', '=', 'p.id');

                // If editor has specific allowed categories, filter to those.
                if ($user->isEditor()) {
                    $allowed = $user->assignedCategoryIds();
                    if (! empty($allowed)) {
                        $itemsQuery->whereIn('p.category_id', $allowed);
                    }
                }

                $itemsBySaleId = $itemsQuery
                    ->get(['si.sale_id', 'si.product_name', 'si.quantity', 'si.product_id'])
                    ->groupBy('sale_id')
                    ->map(function ($group) use ($conn) {
                        return SaleItemsJobEditsBuilder::namesForJobPool($conn, $group->all());
                    });
            } catch (\Throwable $e) {
                // If POS items cannot be read, just leave itemsBySaleId empty.
            }
        }

        $paginator = new LengthAwarePaginator(
            $sales,
            $total,
            $perPage,
            $page,
            [
                'path' => route('jobs.live'),
                'query' => $request->query(),
            ]
        );

        // Mark Job Pool as checked "now" for this user (used for notifications).
        if ($user && Schema::hasColumn('users', 'job_pool_last_checked_at')) {
            try {
                $user->forceFill(['job_pool_last_checked_at' => now()])->save();
            } catch (\Throwable) {
                // Avoid 500 if DB is out of sync with migrations.
            }
        }

        return view('jobs.live', [
            'sales' => $paginator,
            'jobsBySourceId' => $jobsBySourceId,
            'itemsBySaleId' => $itemsBySaleId,
            'ref' => $ref,
            'jobPoolMode' => 'pos',
        ]);
    }

    /**
     * Job Pool for dedicated printer / framing roles: existing jobs only, with at least one line needing print (after edit done) or framing.
     */
    private function liveDedicatedPrintFramingPool(Request $request, User $user, string $conn, ?string $ref, int $page, int $perPage): View|RedirectResponse
    {
        try {
            $jobsQuery = Job::queryDedicatedPrintFramingJobPool($user);
            $jobsQuery = Job::applyJobPoolEligiblePosSaleExists($jobsQuery);
            if ($ref) {
                $jobsQuery->where('studio_jobs.ref_number', 'like', '%' . $ref . '%');
            }
            $jobsQuery = Job::orderJobPoolByPosDueDate($jobsQuery);

            $total = $jobsQuery->count();
            $jobs = $jobsQuery->forPage($page, $perPage)->get();

            $sourceIds = $jobs->pluck('source_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

            $posRows = collect();
            if ($sourceIds !== []) {
                $posRows = DB::connection($conn)->table('sma_sales')->whereIn('id', $sourceIds)->get()->keyBy('id');
            }

            $salesCollection = $jobs->map(function (Job $job) use ($posRows) {
                $row = $posRows->get((int) $job->source_id);
                if ($row) {
                    return $row;
                }

                return (object) [
                    'id' => (int) $job->source_id,
                    'reference_no' => $job->ref_number,
                    'customer' => $job->customer_name,
                    'payment_status' => '',
                    'due_date' => $job->due_date?->format('Y-m-d H:i:s'),
                    'date' => $job->created_at?->format('Y-m-d H:i:s'),
                ];
            });

            $paginator = new LengthAwarePaginator(
                $salesCollection,
                $total,
                $perPage,
                $page,
                [
                    'path' => route('jobs.live'),
                    'query' => $request->query(),
                ]
            );

            $jobsBySourceId = $jobs->keyBy(fn (Job $j) => (string) $j->source_id);

            $itemsBySaleId = collect();
            if ($sourceIds !== []) {
                try {
                    $itemsQuery = DB::connection($conn)
                        ->table('sma_sale_items as si')
                        ->whereIn('si.sale_id', $sourceIds)
                        ->orderBy('si.id')
                        ->leftJoin('sma_products as p', 'si.product_id', '=', 'p.id');

                    if ($user->isEditor()) {
                        $allowed = $user->assignedCategoryIds();
                        if (! empty($allowed)) {
                            $itemsQuery->whereIn('p.category_id', $allowed);
                        }
                    }

                    $itemsBySaleId = $itemsQuery
                        ->get(['si.sale_id', 'si.product_name', 'si.quantity', 'si.product_id'])
                        ->groupBy('sale_id')
                        ->map(function ($group) use ($conn) {
                            return SaleItemsJobEditsBuilder::namesForJobPool($conn, $group->all());
                        });
                } catch (\Throwable) {
                    // leave empty
                }
            }
        } catch (\Throwable $e) {
            return redirect()->route('jobs.index')->with('error', 'Cannot load print/framing job pool: ' . $e->getMessage());
        }

        if ($user && Schema::hasColumn('users', 'job_pool_last_checked_at')) {
            try {
                $user->forceFill(['job_pool_last_checked_at' => now()])->save();
            } catch (\Throwable) {
            }
        }

        return view('jobs.live', [
            'sales' => $paginator,
            'jobsBySourceId' => $jobsBySourceId,
            'itemsBySaleId' => $itemsBySaleId,
            'ref' => $ref,
            'jobPoolMode' => 'print_framing',
        ]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = auth()->user();
        $section = $request->input('section', 'ongoing');
        $ref = $request->input('ref');

        $allowedSections = ['ongoing', 'edit_done', 'print_done', 'framing_done', 'completed', 'delivered', 'dismissed'];
        if (! in_array($section, $allowedSections, true)) {
            $section = 'ongoing';
        }

        $baseQuery = function () use ($ref) {
            return Job::with(['editor', 'editors'])
                ->with(['edits' => fn ($eq) => $eq
                    ->select([
                        'id',
                        'studio_job_id',
                        'name',
                        'category_name',
                        'edit_done_at',
                        'print_status',
                        'framing_done_at',
                        'source_category_id',
                        'source_product_id',
                        'sort_order',
                    ])
                    ->orderBy('sort_order')])
                ->withCount('edits')
                ->when($ref, fn ($qq) => $qq->where('ref_number', 'like', '%' . $ref . '%'));
        };

        $scopeUserJobsIfEditor = function ($query) use ($user) {
            if (! $user->isEditor()) {
                return $query;
            }

            return $query->where(function ($q) use ($user) {
                $q->where('assigned_editor_id', $user->id)
                    ->orWhereHas('editors', fn ($q2) => $q2->where('user_id', $user->id));
            });
        };

        $printedTerminal = [JobEdit::PRINT_STATUS_PRINTED, JobEdit::PRINT_STATUS_NOT_REQUIRED];

        // Dismissed list: jobs this user has dismissed (for all roles – tab shown to everyone)
        $dismissedQuery = $baseQuery()->whereHas('dismissedByUsers', fn ($q) => $q->where('user_id', $user->id));

        $printFramingQueueIds = null;
        if ($user->usesDedicatedPrintFramingJobPool() && ! $user->isEditor()) {
            $printFramingQueueIds = Job::applyJobPoolEligiblePosSaleExists(
                Job::queryDedicatedPrintFramingJobPool($user)
            )->pluck('id')->all();
        }

        /** @var callable(): Builder $scopedBaseForTabs */
        $scopedBaseForTabs = fn () => $baseQuery();

        if ($user->isEditor()) {
            // Ongoing: started jobs with any line still needing work (FRAME → framing; others → edit + print).
            $ongoingQuery = $scopeUserJobsIfEditor($baseQuery()
                ->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS]));
            $this->scopeJobsWhereAnyEditIncomplete($ongoingQuery, $printedTerminal);
            $editDoneTabQuery = fn () => $this->buildEditDoneTabQuery($scopeUserJobsIfEditor($baseQuery()), $printedTerminal);
            $completedQuery = $baseQuery()->where('status', Job::STATUS_COMPLETED)
                ->where(function ($q) use ($user) {
                    $q->where('assigned_editor_id', $user->id)
                        ->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id));
                });
            $deliveredQuery = $baseQuery()->where('status', Job::STATUS_DELIVERED)
                ->where(function ($q) use ($user) {
                    $q->where('assigned_editor_id', $user->id)
                        ->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id));
                });
            $scopedBaseForTabs = fn () => $scopeUserJobsIfEditor($baseQuery());
        } elseif ($printFramingQueueIds !== null) {
            $scopePrintFraming = fn ($query) => $query->whereIn('id', $printFramingQueueIds);
            $ongoingQuery = $scopePrintFraming($baseQuery()
                ->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS]));
            $this->scopeJobsWhereAnyEditIncomplete($ongoingQuery, $printedTerminal);
            $editDoneTabQuery = fn () => $this->buildEditDoneTabQuery($scopePrintFraming($baseQuery()), $printedTerminal);
            $completedQuery = $baseQuery()->where('status', Job::STATUS_COMPLETED);
            $deliveredQuery = $baseQuery()->where('status', Job::STATUS_DELIVERED);
            $scopedBaseForTabs = fn () => $this->scopeDedicatedPoolJobsForWorkflowTabs($baseQuery(), $user, $printFramingQueueIds);
        } else {
            $ongoingQuery = $baseQuery()
                ->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS]);
            $this->scopeJobsWhereAnyEditIncomplete($ongoingQuery, $printedTerminal);
            $editDoneTabQuery = fn () => $this->buildEditDoneTabQuery($scopeUserJobsIfEditor($baseQuery()), $printedTerminal);
            $completedQuery = $baseQuery()->where('status', Job::STATUS_COMPLETED);
            $deliveredQuery = $baseQuery()->where('status', Job::STATUS_DELIVERED);
            $scopedBaseForTabs = fn () => $scopeUserJobsIfEditor($baseQuery());
        }

        $printDoneTabQuery = fn () => $this->buildPrintDoneTabQuery($scopedBaseForTabs(), $printedTerminal);
        $framingDoneTabQuery = fn () => $this->buildFramingDoneTabQuery($scopedBaseForTabs(), $printedTerminal);

        $ongoingCount = $ongoingQuery->count();
        $editDoneCount = $editDoneTabQuery()->count();
        $printDoneCount = $printDoneTabQuery()->count();
        $framingDoneCount = $framingDoneTabQuery()->count();
        $completedCount = $completedQuery->count();
        $deliveredCount = $deliveredQuery->count();
        $dismissedCount = $dismissedQuery->count();

        if ($section === 'dismissed') {
            $jobs = $dismissedQuery->latest()->paginate(15)->withQueryString();
        } elseif ($section === 'edit_done') {
            $jobs = $editDoneTabQuery()->latest()->paginate(15)->withQueryString();
        } elseif ($section === 'print_done') {
            $jobs = $printDoneTabQuery()->latest()->paginate(15)->withQueryString();
        } elseif ($section === 'framing_done') {
            $jobs = $framingDoneTabQuery()->latest()->paginate(15)->withQueryString();
        } elseif ($section === 'completed') {
            $jobs = $completedQuery->latest()->paginate(15)->withQueryString();
        } elseif ($section === 'delivered') {
            $jobs = $deliveredQuery->latest()->paginate(15)->withQueryString();
        } else {
            // default: ongoing
            $jobs = $ongoingQuery->latest()->paginate(15)->withQueryString();
        }

        $saleDueRawBySourceId = $this->fetchPosSaleDueDateRawBySourceIds(
            $jobs->getCollection()->pluck('source_id')->filter()->unique()
        );

        return view('jobs.index', compact(
            'jobs',
            'section',
            'ongoingCount',
            'editDoneCount',
            'printDoneCount',
            'framingDoneCount',
            'completedCount',
            'deliveredCount',
            'dismissedCount',
            'ref',
            'saleDueRawBySourceId'
        ));
    }

    public function show(Job $job): Response
    {
        $job->refresh();
        $job->load(['editor', 'editors', 'deliveredByUser']);
        $job->load([
            'edits' => fn ($q) => $q->with('claimedByUser')->orderBy('sort_order'),
        ]);
        $this->applyPosDueDateFromSourceToJob($job);
        $this->enrichEditsWithCategoryFromSource($job);
        $this->maybeAutoCompleteJob($job);
        $editorsAvailable = \App\Models\User::whereIn('role', \App\Models\User::rolesAssignableAsJobEditors())->orderBy('name')->get();
        $jobActivityLog = \App\Models\ActivityLog::where('subject_type', 'job')
            ->where('subject_id', $job->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(150)
            ->get();

        return response()
            ->view('jobs.show', compact('job', 'editorsAvailable', 'jobActivityLog'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    /**
     * When job edits have null category/subcategory, fetch from POS DB by source_product_id or by product name and set for display.
     */
    private function enrichEditsWithCategoryFromSource(Job $job): void
    {
        try {
            $this->enrichEditsWithCategoryFromSourceInner($job);
        } finally {
            $this->persistEnrichedEditCategories($job);
        }
    }

    private function enrichEditsWithCategoryFromSourceInner(Job $job): void
    {
        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return;
        }
        $db = \Illuminate\Support\Facades\DB::connection($conn);

        // 1) Enrich by source_product_id
        $ids = $job->edits
            ->filter(fn ($e) => ! empty($e->source_product_id) && ($e->category_name === null || $e->subcategory_name === null))
            ->pluck('source_product_id')
            ->unique()
            ->values()
            ->all();
        if (! empty($ids)) {
            try {
                $rows = $db->table('sma_products as p')
                    ->leftJoin('sma_categories as cat', 'p.category_id', '=', 'cat.id')
                    ->leftJoin('sma_categories as sub', 'p.subcategory_id', '=', 'sub.id')
                    ->whereIn('p.id', $ids)
                    ->select(['p.id', 'p.category_id as source_category_id', 'cat.name as category_name', 'sub.name as subcategory_name'])
                    ->get()
                    ->keyBy('id');
                foreach ($job->edits as $edit) {
                    if (empty($edit->source_product_id)) {
                        continue;
                    }
                    $row = $rows->get($edit->source_product_id);
                    if ($row) {
                        if ($edit->category_name === null && $row->category_name !== null) {
                            $edit->category_name = $row->category_name;
                        }
                        if ($edit->subcategory_name === null && $row->subcategory_name !== null) {
                            $edit->subcategory_name = $row->subcategory_name;
                        }
                        if ($edit->source_category_id === null && ! empty($row->source_category_id)) {
                            $edit->source_category_id = (int) $row->source_category_id;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 2) Fallback: enrich by product name for edits still missing category
        $names = $job->edits
            ->filter(fn ($e) => ($e->category_name === null || $e->subcategory_name === null) && trim((string) $e->name) !== '')
            ->map(fn ($e) => trim($e->name))
            ->unique()
            ->values()
            ->all();
        if (empty($names)) {
            return;
        }
        try {
            $byName = $db->table('sma_products as p')
                ->leftJoin('sma_categories as cat', 'p.category_id', '=', 'cat.id')
                ->leftJoin('sma_categories as sub', 'p.subcategory_id', '=', 'sub.id')
                ->where(function ($q) use ($names) {
                    foreach ($names as $n) {
                        $q->orWhereRaw('TRIM(p.name) = ?', [$n]);
                    }
                })
                ->select(['p.name', 'p.id', 'p.category_id as source_category_id', 'cat.name as category_name', 'sub.name as subcategory_name'])
                ->get()
                ->keyBy(fn ($r) => trim((string) $r->name));
            foreach ($job->edits as $edit) {
                $key = trim((string) $edit->name);
                if ($key === '') {
                    continue;
                }
                $row = $byName->get($key);
                if ($row) {
                    if ($edit->category_name === null && $row->category_name !== null) {
                        $edit->category_name = $row->category_name;
                    }
                    if ($edit->subcategory_name === null && $row->subcategory_name !== null) {
                        $edit->subcategory_name = $row->subcategory_name;
                    }
                    if ($edit->source_category_id === null && ! empty($row->source_category_id)) {
                        $edit->source_category_id = (int) $row->source_category_id;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /** Persist category fields looked up from POS so the job page and filters stay in sync across requests. */
    private function persistEnrichedEditCategories(Job $job): void
    {
        foreach ($job->edits as $edit) {
            if ($edit->isDirty(['category_name', 'subcategory_name', 'source_category_id'])) {
                try {
                    $edit->save();
                } catch (\Throwable) {
                    // Avoid breaking the page if DB rejects a write
                }
            }
        }
    }

    public function take(Job $job): RedirectResponse
    {
        if (! auth()->user()->canTakeJob()) {
            abort(403);
        }
        if ($job->status !== Job::STATUS_NEW) {
            return redirect()->route('jobs.show', $job)->with('error', 'Job is already assigned.');
        }
        $job->editors()->syncWithoutDetaching([auth()->id()]);
        if (! $job->assigned_editor_id) {
            $job->update(['assigned_editor_id' => auth()->id()]);
        }
        $job->update(['status' => Job::STATUS_ASSIGNED]);
        ActivityLog::log('job_taken', 'Took job ' . $job->ref_number, 'job', $job->id);
        return redirect()->route('jobs.show', $job)->with('success', 'Job assigned to you.');
    }

    public function updateStatus(Request $request, Job $job): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->canManageJobs()) {
            abort(403);
        }
        $valid = $request->validate(['status' => 'required|in:assigned,in_progress,completed']);

        if ($valid['status'] === 'completed') {
            if (! $this->jobEditsFullyComplete($job)) {
                return redirect()->back()->with(
                    'error',
                    'Complete every line item first: FRAME category lines need Framing done only; all other lines need Edit done and print (Printed or Not required).'
                );
            }
        }

        $job->update($valid);
        ActivityLog::log('job_status_updated', 'Updated job ' . $job->ref_number . ' status to ' . $valid['status'], 'job', $job->id);
        return redirect()->back()->with('success', 'Job status updated.');
    }

    public function claimEdit(Request $request, Job $job, JobEdit $edit): RedirectResponse
    {
        if ($response = $this->rejectIfFrameOnlyEditorFlow($edit)) {
            return $response;
        }
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if ($edit->claimed_by_user_id !== null && $edit->claimed_by_user_id != auth()->id()) {
            return redirect()->back()->with('error', 'This item is already being edited by someone else.');
        }
        $minutes = $request->input('estimated_minutes');
        if ($minutes === 'custom' || $minutes === '' || $minutes === null) {
            $minutes = $request->input('custom_minutes');
        }
        if ($minutes !== null && $minutes !== '') {
            $minutes = (int) $minutes;
            if ($minutes < 1) {
                $minutes = null;
            } elseif ($minutes > 999) {
                $minutes = 999;
            }
        } else {
            $minutes = null;
        }
        if ($minutes === null) {
            return redirect()->back()->with('error', 'Set estimated time (minutes) before starting editing this item.');
        }
        $user = auth()->user();
        $finalMinutes = $minutes;
        if (! $user->canSetOrChangeJobEditEstimatedMinutes($edit)) {
            $finalMinutes = (int) $edit->estimated_minutes;
        }
        $bumpEstimatedAt = $user->canSetOrChangeJobEditEstimatedMinutes($edit)
            || $edit->estimated_minutes === null
            || (int) $edit->estimated_minutes !== $finalMinutes;
        $payload = [
            'claimed_by_user_id' => auth()->id(),
            'edit_status' => JobEdit::EDIT_STATUS_IN_PROGRESS,
            'claimed_at' => now(),
            'estimated_minutes' => $finalMinutes,
        ];
        if ($bumpEstimatedAt) {
            $payload['estimated_minutes_at'] = now();
        }
        $safePayload = $this->filterJobEditAttributes($payload);
        if ($safePayload === []) {
            return $this->redirectJobEditMigration();
        }
        $edit->update($safePayload);
        if ($job->status === Job::STATUS_ASSIGNED) {
            $job->update(['status' => Job::STATUS_IN_PROGRESS]);
        }
        ActivityLog::log('job_edit_claimed', 'In Edit: "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'), 'job', $job->id);
        if ($bumpEstimatedAt) {
            ActivityLog::log('job_edit_estimated_minutes', 'Est. time ' . $finalMinutes . ' min for "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'), 'job', $job->id);
        }
        return redirect()->back()->with('success', 'You are now editing this item.');
    }

    public function updateEstimatedMinutes(Request $request, Job $job, JobEdit $edit): RedirectResponse
    {
        if ($response = $this->rejectIfFrameOnlyEditorFlow($edit)) {
            return $response;
        }
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        $minutes = $request->input('estimated_minutes');
        if ($minutes === 'custom' || $minutes === '' || $minutes === null) {
            $minutes = $request->input('custom_minutes');
        }
        if ($minutes !== null && $minutes !== '') {
            $minutes = (int) $minutes;
            if ($minutes < 1) {
                $minutes = null;
            } elseif ($minutes > 999) {
                $minutes = 999;
            }
        } else {
            $minutes = null;
        }
        if ($minutes === null) {
            return redirect()->back()->with('error', 'Choose a valid estimated time (minutes). It cannot be empty for active items.');
        }
        $user = auth()->user();
        if (! $user->canSetOrChangeJobEditEstimatedMinutes($edit)) {
            return redirect()->back()->with(
                'error',
                'Estimated time can only be set once per line. Only Admin or Manager can change it after that.'
            );
        }
        $safePayload = $this->filterJobEditAttributes([
            'estimated_minutes' => $minutes,
            'estimated_minutes_at' => now(),
        ]);
        if ($safePayload !== []) {
            $edit->update($safePayload);
        }
        ActivityLog::log('job_edit_estimated_minutes', 'Est. time ' . $minutes . ' min for "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'), 'job', $job->id);
        return redirect()->back()->with('success', 'Estimated time updated.');
    }

    public function confirmCustomer(Job $job, JobEdit $edit): RedirectResponse
    {
        if ($response = $this->rejectIfFrameOnlyEditorFlow($edit)) {
            return $response;
        }
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if ($response = $this->ensureEstimatedMinutesSet($edit)) {
            return $response;
        }
        if (! $this->updateJobEditStrict($edit, ['customer_confirmed_at' => now()])) {
            return $this->redirectJobEditMigration();
        }
        ActivityLog::log('job_edit_customer_confirmed', 'Customer Confirm: "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'), 'job', $job->id);
        return redirect()->back()->with('success', 'Customer confirmed. This item can now be sent to print.');
    }

    /**
     * Admin only: revert customer confirm so the item can be re-confirmed (editors cannot reverse).
     */
    public function unconfirmCustomer(Job $job, JobEdit $edit): RedirectResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if (! $this->updateJobEditStrict($edit, ['customer_confirmed_at' => null])) {
            return $this->redirectJobEditMigration();
        }
        ActivityLog::log('job_edit_customer_unconfirmed', 'Reverted customer confirm for "' . $edit->name . '" on job ' . $job->ref_number . ' (Admin)', 'job', $job->id);
        return redirect()->back()->with('success', 'Customer confirm reverted. Item must be confirmed again before send to print.');
    }

    public function markSentToCustomer(Job $job, JobEdit $edit): RedirectResponse
    {
        if ($response = $this->rejectIfFrameOnlyEditorFlow($edit)) {
            return $response;
        }
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if ($response = $this->ensureEstimatedMinutesSet($edit)) {
            return $response;
        }
        if (! Schema::hasColumn('job_edits', 'sent_to_customer_count')
            || ! Schema::hasColumn('job_edits', 'sent_to_customer_at')) {
            return $this->redirectJobEditMigration();
        }
        $edit->increment('sent_to_customer_count');
        $edit->update(['sent_to_customer_at' => now()]);
        $edit->refresh();
        ActivityLog::log('job_edit_sent_to_customer', $edit->sent_to_customer_count . '# Sent to Customer Review: "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'), 'job', $job->id);
        return redirect()->back()->with('success', 'Marked as sent to customer.');
    }

    public function markReEdit(Job $job, JobEdit $edit): RedirectResponse
    {
        if ($response = $this->rejectIfFrameOnlyEditorFlow($edit)) {
            return $response;
        }
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if ($response = $this->ensureEstimatedMinutesSet($edit)) {
            return $response;
        }
        if (! Schema::hasColumn('job_edits', 'reedit_count')
            || ! Schema::hasColumn('job_edits', 'reedit_at')) {
            return $this->redirectJobEditMigration();
        }
        $edit->increment('reedit_count');
        $edit->update(['reedit_at' => now()]);
        $edit->refresh();
        ActivityLog::log('job_edit_reedit', $edit->reedit_count . '# Re-Edit: "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'), 'job', $job->id);
        return redirect()->back()->with('success', 'Marked as re-edit.');
    }

    public function markFramingDone(Job $job, JobEdit $edit): RedirectResponse
    {
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if (! auth()->user()->canMarkFramingDone($edit)) {
            if (auth()->user()->isFraming()
                && $edit->framing_done_at === null
                && ! $edit->isFrameCategoryLine()
                && $edit->print_status !== JobEdit::PRINT_STATUS_PRINTED) {
                return redirect()->back()->with('error', 'Framing can only be marked after the item is Printed.');
            }
            abort(403);
        }
        if (! $this->updateJobEditStrict($edit, ['framing_done_at' => now()])) {
            return $this->redirectJobEditMigration();
        }
        ActivityLog::log(
            'job_edit_framing_done',
            'Framing done: "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'),
            'job',
            $job->id
        );
        $this->maybeAutoCompleteJob($job->fresh());

        return redirect()->back()->with('success', 'Framing marked done for this item.');
    }

    public function unmarkFramingDone(Job $job, JobEdit $edit): RedirectResponse
    {
        if (! auth()->user()->isAdmin() && ! auth()->user()->isManager()) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if (! $this->updateJobEditStrict($edit, ['framing_done_at' => null])) {
            return $this->redirectJobEditMigration();
        }
        ActivityLog::log(
            'job_edit_framing_cleared',
            'Framing done cleared: "' . $edit->name . '" on job ' . $job->ref_number . ' (Admin/Manager)',
            'job',
            $job->id
        );
        $this->maybeReopenJobIfIncomplete($job->fresh());

        return redirect()->back()->with('success', 'Framing done cleared for this item.');
    }

    public function stepBackEditorStatus(Job $job, JobEdit $edit): RedirectResponse
    {
        abort(403);
    }

    public function markEditDone(Job $job, JobEdit $edit): RedirectResponse
    {
        if ($response = $this->rejectIfFrameOnlyEditorFlow($edit)) {
            return $response;
        }
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if ($response = $this->ensureEstimatedMinutesSet($edit)) {
            return $response;
        }
        if (! $this->updateJobEditStrict($edit, [
            'edit_status' => JobEdit::EDIT_STATUS_COMPLETED,
            'completed_at' => now(),
            'edit_done_at' => now(),
        ])) {
            return $this->redirectJobEditMigration();
        }
        $this->maybeAutoCompleteJob($job->fresh());
        ActivityLog::log('job_edit_done', 'Edit Done: "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'), 'job', $job->id);
        return redirect()->back()->with('success', 'Edit marked as done.');
    }

    public function updateEditStatus(Request $request, Job $job, JobEdit $edit): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->canManageJobs()) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if (! $user->canEditJobItem($edit)) {
            abort(403);
        }
        $valid = $request->validate([
            'edit_status' => 'required|in:pending,in_progress,completed',
        ]);

        // If this item is already completed, only Admin is allowed to revert it back to another status.
        if ($edit->edit_status === JobEdit::EDIT_STATUS_COMPLETED
            && $valid['edit_status'] !== JobEdit::EDIT_STATUS_COMPLETED
            && ! $user->isAdmin()
        ) {
            return redirect()->back()->with('error', 'Only Admin can revert a completed item.');
        }

        if (! $this->updateJobEditStrict($edit, [
            'edit_status' => $valid['edit_status'],
            'completed_at' => $valid['edit_status'] === JobEdit::EDIT_STATUS_COMPLETED ? now() : null,
        ])) {
            return $this->redirectJobEditMigration();
        }
        if ($job->fresh()->allEditsCompleted()) {
            $job->update(['status' => Job::STATUS_COMPLETED]);
        }
        ActivityLog::log('job_edit_status_updated', 'Updated item "' . $edit->name . '" on job ' . $job->ref_number . ' to ' . $valid['edit_status'], 'job', $job->id);
        return redirect()->back()->with('success', 'Edit status updated.');
    }

    public function updatePrintStatus(Request $request, Job $job, JobEdit $edit): RedirectResponse
    {
        if ($response = $this->rejectIfFrameOnlyEditorFlow($edit)) {
            return $response;
        }
        if (! auth()->user()->canUpdatePrintStatus()) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if (! auth()->user()->canApplyPrintStatusToJobEdit($edit)) {
            abort(403);
        }
        $valid = $request->validate([
            'print_status' => 'required|in:not_required,pending,sent_to_print,printed',
        ]);
        if (! $this->updateJobEditStrict($edit, [
            'print_status' => $valid['print_status'],
            'print_status_at' => now(),
        ])) {
            return $this->redirectJobEditMigration();
        }
        $this->maybeAutoCompleteJob($job->fresh());
        $label = match ($valid['print_status']) {
            'not_required' => 'Not required',
            'pending' => 'Pending',
            'sent_to_print' => 'Sent to print',
            'printed' => 'Printed',
            default => $valid['print_status'],
        };
        ActivityLog::log('job_print_status_updated', $label . ': "' . $edit->name . '" on job ' . $job->ref_number . ' at ' . now()->format('Y-m-d H:i'), 'job', $job->id);
        return redirect()->back()->with('success', 'Print status updated.');
    }

    /**
     * Apply the same rules as single-item print / framing actions to many lines at once.
     */
    public function bulkEdits(Request $request, Job $job): RedirectResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'required|in:print_status,framing_done,framing_clear,set_estimated_minutes,claim_start,sent_to_customer,reedit,customer_confirm,edit_done',
            'edit_ids' => 'required|array|min:1|max:200',
            'edit_ids.*' => 'integer|exists:job_edits,id',
            'print_status' => 'nullable|in:not_required,pending,sent_to_print,printed',
            'bulk_estimated_mode' => 'nullable|string|max:32',
            'bulk_custom_minutes' => 'nullable|integer|min:1|max:999',
        ]);

        if ($validated['action'] === 'print_status') {
            if (empty($validated['print_status'])) {
                return redirect()->back()->with('error', 'Choose a print status for bulk update.');
            }
            if (! $user->canUpdatePrintStatus()) {
                abort(403);
            }
        } elseif ($validated['action'] === 'framing_done') {
            if (! $user->isFraming() && ! $user->isAdmin()) {
                abort(403);
            }
        } elseif ($validated['action'] === 'framing_clear') {
            if (! $user->isAdmin() && ! $user->isManager()) {
                abort(403);
            }
        } elseif (in_array($validated['action'], ['set_estimated_minutes', 'claim_start'], true)) {
            if (! $user->isAdmin() && ! $user->isManager() && ! $user->isEditor()) {
                abort(403);
            }
        } elseif (in_array($validated['action'], ['sent_to_customer', 'reedit', 'customer_confirm', 'edit_done'], true)) {
            if (! $user->isAdmin() && ! $user->isManager() && ! $user->isEditor()) {
                abort(403);
            }
        }

        if ($validated['action'] === 'sent_to_customer'
            && (! Schema::hasColumn('job_edits', 'sent_to_customer_count') || ! Schema::hasColumn('job_edits', 'sent_to_customer_at'))) {
            return $this->redirectJobEditMigration();
        }
        if ($validated['action'] === 'reedit'
            && (! Schema::hasColumn('job_edits', 'reedit_count') || ! Schema::hasColumn('job_edits', 'reedit_at'))) {
            return $this->redirectJobEditMigration();
        }

        $bulkMinutes = null;
        if (in_array($validated['action'], ['set_estimated_minutes', 'claim_start'], true)) {
            $bulkMinutes = $this->resolveBulkMinutesFromRequest($request);
            if ($bulkMinutes === null) {
                return redirect()->back()->with('error', 'Choose or enter estimated minutes (1–999) for bulk time actions.');
            }
        }

        $visible = $this->editsVisibleToCurrentUser($job);
        $visibleById = $visible->keyBy(fn (JobEdit $e) => (int) $e->getKey());

        $ids = array_values(array_unique(array_map('intval', $validated['edit_ids'])));
        $appliedNames = [];
        $ineligibleSelected = 0;
        $invalidSelection = 0;

        try {
            DB::transaction(function () use ($job, $user, $validated, $visibleById, $ids, &$appliedNames, &$ineligibleSelected, &$invalidSelection, $bulkMinutes) {
                foreach ($ids as $id) {
                    $edit = $visibleById->get((int) $id);
                    if (! $edit || (int) $edit->studio_job_id !== (int) $job->id) {
                        $invalidSelection++;

                        continue;
                    }

                    if ($validated['action'] === 'print_status') {
                        $reason = $this->bulkPrintStatusSkipReason($edit, $user);
                        if ($reason !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        $newStatus = $validated['print_status'];
                        if ($edit->print_status === $newStatus) {
                            $ineligibleSelected++;

                            continue;
                        }
                        if (! $this->updateJobEditStrict($edit, [
                            'print_status' => $newStatus,
                            'print_status_at' => now(),
                        ])) {
                            throw new \RuntimeException('job_edits_migration_required');
                        }
                        $appliedNames[] = $edit->name;

                        continue;
                    }

                    if ($validated['action'] === 'framing_done') {
                        if ($edit->framing_done_at !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        if (! $user->canMarkFramingDone($edit)) {
                            $ineligibleSelected++;

                            continue;
                        }
                        if (! $this->updateJobEditStrict($edit, ['framing_done_at' => now()])) {
                            throw new \RuntimeException('job_edits_migration_required');
                        }
                        $appliedNames[] = $edit->name;

                        continue;
                    }

                    if ($validated['action'] === 'set_estimated_minutes') {
                        if (! $user->canSetOrChangeJobEditEstimatedMinutes($edit)) {
                            $ineligibleSelected++;

                            continue;
                        }
                        $reason = $this->bulkSetEstimatedMinutesSkipReason($edit, $user);
                        if ($reason !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        if ((int) $edit->estimated_minutes === $bulkMinutes && $edit->estimated_minutes_at !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        $safePayload = $this->filterJobEditAttributes([
                            'estimated_minutes' => $bulkMinutes,
                            'estimated_minutes_at' => now(),
                        ]);
                        if ($safePayload === []) {
                            throw new \RuntimeException('job_edits_migration_required');
                        }
                        $edit->update($safePayload);
                        $appliedNames[] = $edit->name;

                        continue;
                    }

                    if ($validated['action'] === 'claim_start') {
                        $reason = $this->bulkClaimStartSkipReason($edit, $user);
                        if ($reason !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        $finalMinutes = $user->canSetOrChangeJobEditEstimatedMinutes($edit)
                            ? $bulkMinutes
                            : (int) $edit->estimated_minutes;
                        $bumpEstimatedAt = $user->canSetOrChangeJobEditEstimatedMinutes($edit)
                            || $edit->estimated_minutes === null
                            || (int) $edit->estimated_minutes !== $finalMinutes;
                        $payload = [
                            'claimed_by_user_id' => $user->id,
                            'edit_status' => JobEdit::EDIT_STATUS_IN_PROGRESS,
                            'claimed_at' => now(),
                            'estimated_minutes' => $finalMinutes,
                        ];
                        if ($bumpEstimatedAt) {
                            $payload['estimated_minutes_at'] = now();
                        }
                        $safePayload = $this->filterJobEditAttributes($payload);
                        if ($safePayload === []) {
                            throw new \RuntimeException('job_edits_migration_required');
                        }
                        $edit->update($safePayload);
                        $job->refresh();
                        if ($job->status === Job::STATUS_ASSIGNED) {
                            $job->update(['status' => Job::STATUS_IN_PROGRESS]);
                        }
                        $appliedNames[] = $edit->name;

                        continue;
                    }

                    if ($validated['action'] === 'sent_to_customer') {
                        $reason = $this->bulkEditorActionCommonSkipReason($edit, $user);
                        if ($reason !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        $edit->increment('sent_to_customer_count');
                        $edit->update(['sent_to_customer_at' => now()]);
                        $edit->refresh();
                        $appliedNames[] = $edit->name;

                        continue;
                    }

                    if ($validated['action'] === 'reedit') {
                        $reason = $this->bulkEditorActionCommonSkipReason($edit, $user);
                        if ($reason !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        $edit->increment('reedit_count');
                        $edit->update(['reedit_at' => now()]);
                        $edit->refresh();
                        $appliedNames[] = $edit->name;

                        continue;
                    }

                    if ($validated['action'] === 'customer_confirm') {
                        $reason = $this->bulkEditorActionCommonSkipReason($edit, $user);
                        if ($reason !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        if ($edit->isCustomerConfirmed()) {
                            $ineligibleSelected++;

                            continue;
                        }
                        if (! $this->updateJobEditStrict($edit, ['customer_confirmed_at' => now()])) {
                            throw new \RuntimeException('job_edits_migration_required');
                        }
                        $appliedNames[] = $edit->name;

                        continue;
                    }

                    if ($validated['action'] === 'edit_done') {
                        $reason = $this->bulkEditorActionCommonSkipReason($edit, $user);
                        if ($reason !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        if ($edit->edit_done_at !== null) {
                            $ineligibleSelected++;

                            continue;
                        }
                        if (! $this->updateJobEditStrict($edit, [
                            'edit_status' => JobEdit::EDIT_STATUS_COMPLETED,
                            'completed_at' => now(),
                            'edit_done_at' => now(),
                        ])) {
                            throw new \RuntimeException('job_edits_migration_required');
                        }
                        $appliedNames[] = $edit->name;

                        continue;
                    }

                    if ($validated['action'] !== 'framing_clear') {
                        continue;
                    }

                    if ($edit->framing_done_at === null) {
                        $ineligibleSelected++;

                        continue;
                    }
                    if (! $this->updateJobEditStrict($edit, ['framing_done_at' => null])) {
                        throw new \RuntimeException('job_edits_migration_required');
                    }
                    $appliedNames[] = $edit->name;
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'job_edits_migration_required') {
                return $this->redirectJobEditMigration();
            }
            throw $e;
        }

        $jobFresh = $job->fresh();
        if (in_array($validated['action'], ['print_status', 'framing_done', 'edit_done'], true)) {
            $this->maybeAutoCompleteJob($jobFresh);
        }
        if ($validated['action'] === 'framing_clear') {
            $this->maybeReopenJobIfIncomplete($job->fresh());
        }

        $actionLabel = match ($validated['action']) {
            'print_status' => 'Print → ' . match ($validated['print_status']) {
                'not_required' => 'Not required',
                'pending' => 'Pending',
                'sent_to_print' => 'Sent to print',
                'printed' => 'Printed',
                default => (string) $validated['print_status'],
            },
            'framing_done' => 'Framing done',
            'framing_clear' => 'Framing cleared',
            'set_estimated_minutes' => 'Est. time → ' . (string) $bulkMinutes . ' min',
            'claim_start' => 'Claim & start → ' . (string) $bulkMinutes . ' min',
            'sent_to_customer' => 'Sent to customer',
            'reedit' => 'Re-edit',
            'customer_confirm' => 'Customer confirm',
            'edit_done' => 'Edit done',
            default => $validated['action'],
        };

        $appliedCount = count($appliedNames);

        if ($appliedCount > 0) {
            ActivityLog::log(
                'job_edits_bulk',
                'Bulk (' . $actionLabel . '): ' . $appliedCount . ' item(s) on job ' . $job->ref_number
                    . ($appliedCount <= 5 ? ' — ' . implode(', ', $appliedNames) : ''),
                'job',
                $job->id
            );
        }

        $parts = [];
        if ($appliedCount > 0) {
            $parts[] = 'Updated ' . $appliedCount . ' eligible line(s).';
        }
        if ($ineligibleSelected > 0) {
            $parts[] = $ineligibleSelected . ' selected line(s) were not eligible for this action and were left unchanged.';
        }
        if ($invalidSelection > 0) {
            $parts[] = $invalidSelection . ' selection(s) were not part of this job list.';
        }

        if ($appliedCount > 0) {
            return redirect()->back()->with('success', implode(' ', $parts));
        }

        $err = 'No eligible lines in your selection for this action.';
        if ($ineligibleSelected > 0) {
            $err .= ' (' . $ineligibleSelected . ' line(s) did not qualify.)';
        }
        if ($invalidSelection > 0) {
            $err .= ' (' . $invalidSelection . ' not on this job.)';
        }

        return redirect()->back()->with('error', $err);
    }

    private function editsVisibleToCurrentUser(Job $job): Collection
    {
        $job->loadMissing('edits');
        $user = auth()->user();
        $globalBlockedCategoryIds = BlockedCategory::blockedCategoryIds();
        $globalBlockedProductIds = BlockedProduct::blockedProductIds();
        $allowedCategoryIds = $user ? $user->scopedCategoryIdsForJobLineTable() : [];

        return $job->edits->filter(function (JobEdit $e) use ($globalBlockedCategoryIds, $globalBlockedProductIds, $allowedCategoryIds) {
            $catId = $e->source_category_id ? (int) $e->source_category_id : null;
            $productId = $e->source_product_id ? (int) $e->source_product_id : null;

            $categoryBlocked = $catId !== null && in_array($catId, $globalBlockedCategoryIds, true);
            $productBlocked = $productId !== null && in_array($productId, $globalBlockedProductIds, true);

            $categoryAllowedForEditor = $allowedCategoryIds === [] || $catId === null || in_array($catId, $allowedCategoryIds, true);

            return ! $categoryBlocked && ! $productBlocked && $categoryAllowedForEditor;
        });
    }

    private function bulkPrintStatusSkipReason(JobEdit $edit, User $user): ?string
    {
        if (! $user->canApplyPrintStatusToJobEdit($edit)) {
            return 'not eligible';
        }

        return null;
    }

    private function resolveBulkMinutesFromRequest(Request $request): ?int
    {
        $mode = $request->input('bulk_estimated_mode');
        if ($mode === 'custom' || $mode === '' || $mode === null) {
            $mode = $request->input('bulk_custom_minutes');
        }
        if ($mode !== null && $mode !== '') {
            $minutes = (int) $mode;
            if ($minutes < 1) {
                return null;
            }
            if ($minutes > 999) {
                $minutes = 999;
            }

            return $minutes;
        }

        return null;
    }

    private function bulkTimeCommonSkipReason(JobEdit $edit, User $user): ?string
    {
        if ($edit->isFrameCategoryLine()) {
            return 'FRAME line — no editor time';
        }
        if (! $user->canEditJobItem($edit)) {
            return 'No permission for this line';
        }
        if ($edit->edit_done_at) {
            return 'Edit already done';
        }

        return null;
    }

    private function bulkSetEstimatedMinutesSkipReason(JobEdit $edit, User $user): ?string
    {
        $r = $this->bulkTimeCommonSkipReason($edit, $user);
        if ($r !== null) {
            return $r;
        }
        if ($edit->claimed_by_user_id === null && ! $user->isAdmin() && ! $user->isManager()) {
            return 'Not claimed yet — use Claim & start with time';
        }

        return null;
    }

    private function bulkClaimStartSkipReason(JobEdit $edit, User $user): ?string
    {
        $r = $this->bulkTimeCommonSkipReason($edit, $user);
        if ($r !== null) {
            return $r;
        }
        if ($edit->claimed_by_user_id !== null && (int) $edit->claimed_by_user_id !== (int) $user->id) {
            return 'Claimed by another user';
        }
        if (! $user->canSetOrChangeJobEditEstimatedMinutes($edit) && $edit->estimated_minutes !== null) {
            if ($edit->claimed_by_user_id !== null && (int) $edit->claimed_by_user_id === (int) $user->id) {
                return 'Estimated time already set — only Admin/Manager can change it';
            }
        }

        return null;
    }

    /**
     * Same gates as per-row Sent to customer / Re-edit / Customer confirm / Edit done (job detail).
     */
    private function bulkEditorActionCommonSkipReason(JobEdit $edit, User $user): ?string
    {
        if ($edit->isFrameCategoryLine()) {
            return 'FRAME line — no editor flow';
        }
        if (! $user->canEditJobItem($edit)) {
            return 'No permission for this line';
        }
        if ($edit->estimated_minutes === null) {
            return 'Set estimated time first';
        }
        if (! $user->isAdmin() && $edit->claimed_by_user_id === null) {
            return 'Line not claimed yet';
        }

        return null;
    }

    public function deliver(Request $request, Job $job): RedirectResponse
    {
        if (! auth()->user()->canDeliver()) {
            abort(403);
        }
        $valid = $request->validate([
            'delivery_method' => 'required|in:online,walkin,courier',
        ]);
        $job->update([
            'status' => Job::STATUS_DELIVERED,
            'delivered_at' => now(),
            'delivery_method' => $valid['delivery_method'],
            'delivered_by' => auth()->id(),
        ]);
        ActivityLog::log('job_delivered', 'Marked job ' . $job->ref_number . ' as delivered (' . $valid['delivery_method'] . ')', 'job', $job->id);
        return redirect()->back()->with('success', 'Job marked as delivered.');
    }

    public function addEditor(Request $request, Job $job): RedirectResponse
    {
        if (! auth()->user()->canAddOrRemoveEditorsOn()) {
            abort(403);
        }
        $valid = $request->validate(['user_id' => 'required|exists:users,id']);
        $user = \App\Models\User::findOrFail($valid['user_id']);
        if (! in_array($user->role, \App\Models\User::rolesAssignableAsJobEditors(), true)) {
            return redirect()->back()->with('error', 'Only Editor, Editor + Printer, or Editor + Printer + Framing users can be assigned as photo editors on a job.');
        }
        $job->editors()->syncWithoutDetaching([$user->id]);
        if (! $job->assigned_editor_id) {
            $job->update(['assigned_editor_id' => $user->id]);
        }
        ActivityLog::log('job_editor_added', 'Added ' . $user->name . ' as editor to job ' . $job->ref_number, 'job', $job->id);
        return redirect()->back()->with('success', $user->name . ' added as editor.');
    }

    public function removeEditor(Job $job, \App\Models\User $editor): RedirectResponse
    {
        if (! auth()->user()->canAddOrRemoveEditorsOn()) {
            abort(403);
        }
        $job->editors()->detach($editor->id);
        if ($job->assigned_editor_id === $editor->id) {
            $first = $job->editors()->first();
            $job->update(['assigned_editor_id' => $first?->id]);
        }
        ActivityLog::log('job_editor_removed', 'Removed ' . $editor->name . ' from job ' . $job->ref_number, 'job', $job->id);
        return redirect()->back()->with('success', 'Editor removed.');
    }

    public function dismiss(Job $job): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->canDismissNewJobs()) {
            abort(403);
        }
        if ($job->status !== Job::STATUS_NEW) {
            return redirect()->back()->with('error', 'Only new jobs can be dismissed.');
        }
        $job->dismissedByUsers()->syncWithoutDetaching([$user->id]);
        ActivityLog::log('job_dismissed', 'Dismissed job ' . $job->ref_number, 'job', $job->id);
        return redirect()->route('jobs.index', ['section' => 'dismissed'])->with('success', 'Job moved to your Dismissed list. It will not show in the main job list until you restore it.');
    }

    public function undismiss(Job $job): RedirectResponse
    {
        $job->dismissedByUsers()->detach(auth()->id());
        ActivityLog::log('job_undismissed', 'Restored job ' . $job->ref_number . ' to job list', 'job', $job->id);
        return redirect()->route('jobs.index', ['section' => 'new'])->with('success', 'Job restored to the job list (New).');
    }

    /** Editor line actions require estimated time (Est. time column) to be set. */
    private function ensureEstimatedMinutesSet(JobEdit $edit): ?RedirectResponse
    {
        if ($edit->estimated_minutes === null) {
            return redirect()->back()->with(
                'error',
                'Set estimated time for this line item (Est. time column) before using Sent to Customer, Re-Edit, Customer Confirm, or Edit Done.'
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterJobEditAttributes(array $attributes): array
    {
        return JobEdit::attributesForExistingColumns($attributes);
    }

    /**
     * Update only if every attribute column exists on job_edits (avoids SQL errors on unmigrated servers).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function updateJobEditStrict(JobEdit $edit, array $attributes): bool
    {
        $safe = $this->filterJobEditAttributes($attributes);
        if ($safe === [] || count($safe) !== count($attributes)) {
            return false;
        }
        $edit->update($safe);

        return true;
    }

    private function redirectJobEditMigration(): RedirectResponse
    {
        return redirect()->back()->with(
            'error',
            'Database is missing required job_edits columns. Please run: php artisan migrate'
        );
    }

    /**
     * If every line is complete for its workflow (FRAME = framing done; others = edit done + print), set job status to completed.
     */
    private function maybeAutoCompleteJob(Job $job): void
    {
        if ($job->status === Job::STATUS_COMPLETED || $job->status === Job::STATUS_DELIVERED) {
            return;
        }
        if (! $this->jobEditsFullyComplete($job)) {
            return;
        }
        $job->update(['status' => Job::STATUS_COMPLETED]);
        ActivityLog::log(
            'job_auto_completed',
            'Job ' . $job->ref_number . ' auto-marked complete (all line items finished for their workflow).',
            'job',
            $job->id
        );
    }

    private function rejectIfFrameOnlyEditorFlow(JobEdit $edit): ?RedirectResponse
    {
        if ($edit->isFrameCategoryLine()) {
            return redirect()->back()->with(
                'error',
                'FRAME category items only use Framing done — no editor or print steps on those lines.'
            );
        }

        return null;
    }

    /**
     * Align `studio_jobs.due_date` with POS and set the in-memory value so detail matches Job Pool
     * (avoids DATE-column truncation and stale copies).
     */
    private function applyPosDueDateFromSourceToJob(Job $job): void
    {
        $posRaw = $this->fetchPosSaleDueDateRawForJob($job);
        $resolved = Job::resolveDueFromStoredAndPos($job->due_date, $posRaw);
        if (! $resolved) {
            return;
        }

        if ($posRaw !== null
            && (! $job->due_date || $job->due_date->format('Y-m-d H:i:s') !== $resolved->format('Y-m-d H:i:s'))) {
            try {
                $job->forceFill(['due_date' => $resolved])->saveQuietly();
            } catch (\Throwable $e) {
                Log::debug('applyPosDueDateFromSourceToJob: could not persist due_date', [
                    'job_id' => $job->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $job->setAttribute('due_date', $resolved);
    }

    private function fetchPosSaleDueDateRawForJob(Job $job): ?string
    {
        if (empty($job->source_id)) {
            return null;
        }

        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return null;
        }

        try {
            $v = DB::connection($conn)->table('sma_sales')->where('id', (int) $job->source_id)->value('due_date');
            if ($v === null || $v === '') {
                return null;
            }
            $s = (string) $v;
            if ($s === '0000-00-00' || $s === '0000-00-00 00:00:00') {
                return null;
            }

            return $s;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $sourceIds
     * @return array<string, string>
     */
    private function fetchPosSaleDueDateRawBySourceIds(\Illuminate\Support\Collection $sourceIds): array
    {
        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return [];
        }

        $ids = $sourceIds->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        try {
            $rows = DB::connection($conn)->table('sma_sales')->whereIn('id', $ids)->get(['id', 'due_date']);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if ($r->due_date === null || $r->due_date === '') {
                continue;
            }
            $s = (string) $r->due_date;
            if ($s === '0000-00-00' || $s === '0000-00-00 00:00:00') {
                continue;
            }
            $out[(string) (int) $r->id] = $s;
        }

        return $out;
    }

    private function jobEditsFullyComplete(Job $job): bool
    {
        return $job->allEditsCompleted();
    }

    private function maybeReopenJobIfIncomplete(Job $job): void
    {
        if ($job->status !== Job::STATUS_COMPLETED) {
            return;
        }
        if ($this->jobEditsFullyComplete($job)) {
            return;
        }
        $job->update(['status' => Job::STATUS_IN_PROGRESS]);
        ActivityLog::log(
            'job_reopened',
            'Job ' . $job->ref_number . ' moved back to In progress — a line item is no longer complete for its workflow.',
            'job',
            $job->id
        );
    }

    /**
     * Dedicated printer/framing Job Pool IDs, plus jobs with FRAME lines visible to this user's category allowlist.
     * Ensures Print done / Framing done tabs (and Remaining column) are not empty when the pool query misses
     * mixed jobs (e.g. framing started while photo lines are still in edit/print).
     *
     * @param  list<int>  $poolJobIds
     */
    private function scopeDedicatedPoolJobsForWorkflowTabs(Builder $query, User $user, array $poolJobIds): Builder
    {
        return $query->where(function (Builder $outer) use ($user, $poolJobIds) {
            if ($poolJobIds !== []) {
                $outer->whereIn('id', $poolJobIds);
            }
            $outer->orWhere(function (Builder $sub) use ($user) {
                $sub->whereIn('status', [
                    Job::STATUS_ASSIGNED,
                    Job::STATUS_IN_PROGRESS,
                    Job::STATUS_COMPLETED,
                ])
                    ->whereHas('edits', function ($ed) use ($user) {
                        $ed->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) = ?', ['FRAME']);
                        $allowed = $user->assignedCategoryIds();
                        if ($allowed !== []) {
                            $ed->whereNotNull('source_category_id')
                                ->whereIn('source_category_id', $allowed);
                        }
                    });
            });
        });
    }

    /**
     * Jobs that still have at least one line needing work (FRAME → framing; others → edit + print).
     *
     * @param  list<string>  $printedTerminal
     */
    private function scopeJobsWhereAnyEditIncomplete(Builder $query, array $printedTerminal): void
    {
        $query->where(function ($outer) use ($printedTerminal) {
            $outer->whereDoesntHave('edits')
                ->orWhereHas('edits', function ($eq) use ($printedTerminal) {
                    $eq->where(function ($inner) {
                        $inner->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) = ?', ['FRAME'])
                            ->whereNull('framing_done_at');
                    })->orWhere(function ($inner) use ($printedTerminal) {
                        $inner->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) <> ?', ['FRAME'])
                            ->where(function ($x) use ($printedTerminal) {
                                $x->whereNull('edit_done_at')
                                    ->orWhereNotIn('print_status', $printedTerminal);
                            });
                    });
                });
        });
    }

    /**
     * Assigned / in progress: every non-FRAME line is edit-done with print Printed or Not required; job still has other work (e.g. FRAME framing).
     */
    /**
     * Edit done tab: (1) every non-FRAME line is edit-done and at least one still waiting on print, or
     * (2) partial photo edit — at least one non-FRAME edit-done and at least one non-FRAME still not edit-done.
     *
     * @param  list<string>  $printedTerminal
     */
    private function buildEditDoneTabQuery(Builder $query, array $printedTerminal): Builder
    {
        $query->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS])
            ->where(function (Builder $outer) use ($printedTerminal) {
                $outer->where(function (Builder $allEditedPrintPending) use ($printedTerminal) {
                    $allEditedPrintPending
                        ->whereDoesntHave('edits', function ($q) {
                            $q->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) <> ?', ['FRAME'])
                                ->whereNull('edit_done_at');
                        })
                        ->whereHas('edits', function ($q) use ($printedTerminal) {
                            $q->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) <> ?', ['FRAME'])
                                ->whereNotNull('edit_done_at')
                                ->whereNotIn('print_status', $printedTerminal);
                        });
                })->orWhere(function (Builder $partialPhotoEdit) {
                    $partialPhotoEdit
                        ->whereHas('edits', function ($q) {
                            $q->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) <> ?', ['FRAME'])
                                ->whereNotNull('edit_done_at');
                        })
                        ->whereHas('edits', function ($q) {
                            $q->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) <> ?', ['FRAME'])
                                ->whereNull('edit_done_at');
                        });
                });
            });

        return $query;
    }

    private function buildPrintDoneTabQuery(Builder $query, array $printedTerminal): Builder
    {
        $query->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS])
            ->whereHas('edits', function ($q) {
                $q->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) <> ?', ['FRAME']);
            })
            ->whereDoesntHave('edits', function ($q) use ($printedTerminal) {
                $q->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) <> ?', ['FRAME'])
                    ->where(function ($x) use ($printedTerminal) {
                        $x->whereNull('edit_done_at')
                            ->orWhereNotIn('print_status', $printedTerminal);
                    });
            });
        $this->scopeJobsWhereAnyEditIncomplete($query, $printedTerminal);

        return $query;
    }

    /**
     * At least one FRAME line has framing done, and at least one non-FRAME line still needs edit and/or print; not delivered.
     *
     * @param  list<string>  $printedTerminal
     */
    private function buildFramingDoneTabQuery(Builder $query, array $printedTerminal): Builder
    {
        $query->whereIn('status', [
            Job::STATUS_ASSIGNED,
            Job::STATUS_IN_PROGRESS,
            Job::STATUS_COMPLETED,
        ])
            ->whereHas('edits', function ($q) {
                $q->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) = ?', ['FRAME'])
                    ->whereNotNull('framing_done_at');
            })
            ->whereHas('edits', function ($q) use ($printedTerminal) {
                $q->whereRaw('UPPER(TRIM(IFNULL(category_name, ""))) <> ?', ['FRAME'])
                    ->where(function ($x) use ($printedTerminal) {
                        $x->whereNull('edit_done_at')
                            ->orWhereNotIn('print_status', $printedTerminal);
                    });
            });

        return $query;
    }
}
