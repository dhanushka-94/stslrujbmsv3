<?php

namespace App\Http\Controllers;

use App\Jobs\SyncJobsFromSourceJob;
use App\Models\ActivityLog;
use App\Services\SaleItemsJobEditsBuilder;
use App\Models\Job;
use App\Models\BlockedCategory;
use App\Models\BlockedProduct;
use App\Models\JobEdit;
use App\Models\JobPosApplyHistory;
use App\Models\User;
use App\Support\CategoryAppearance;
use App\Support\CategoryWorkflow;
use App\Support\JobPoolEligibility;
use App\Support\PosJobLineDrift;
use App\Support\PosUpdatedBadge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        if (! $user->canOpenJobFromPool()) {
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

        $user = auth()->user();
        if ($user && $user->canTakeJob()) {
            $job->loadMissing(['editors', 'edits']);
            $isAlreadyOnJob = $user->isAssignedToStudioJob($job);
            $editableLineCount = $job->edits
                ->filter(fn (JobEdit $edit) => $edit->needsEditWorkflow())
                ->count();
            $canJoinExistingMultiEditJob = ! $isAlreadyOnJob
                && $editableLineCount > 1
                && in_array($job->status, [Job::STATUS_NEW, Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS], true);

            if ($canJoinExistingMultiEditJob) {
                $job->editors()->syncWithoutDetaching([$user->id]);
                if (! $job->assigned_editor_id) {
                    $job->update(['assigned_editor_id' => $user->id]);
                }
                if ($job->status === Job::STATUS_NEW) {
                    $job->update(['status' => Job::STATUS_ASSIGNED]);
                }
                ActivityLog::log(
                    'job_editor_added_from_pool',
                    'Joined job ' . $job->ref_number . ' from Job Pool as an additional editor',
                    'job',
                    $job->id
                );
                $job->refresh();

        return redirect()->route('jobs.show', $job)
                    ->with('success', 'You joined this multi-line job and can start editing assigned lines.');
            }
        }

        $openMessage = match (true) {
            $user && $user->role === User::ROLE_FRAMING
                => 'Job opened from Job Pool. Take this job before marking framing items done.',
            $user && $user->role === User::ROLE_PRINTER_FRAMING
                => 'Job opened from Job Pool. Take this job before updating print or framing status.',
            default => 'Job opened from Job Pool. You can now Take or Dismiss this job.',
        };

        return redirect()->route('jobs.show', $job)->with('success', $openMessage);
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
        $usedEditIds = [];
        foreach ($rows as $sortOrder => $row) {
            $saleItemId = isset($row['source_sale_item_id']) ? (int) $row['source_sale_item_id'] : 0;
            $unitIndex = (int) ($row['source_quantity_unit_index'] ?? 1);

            $edit = null;
            if ($saleItemId > 0) {
                $edit = $existing->first(function ($e) use ($saleItemId, $unitIndex, $usedEditIds) {
                    if (in_array($e->id, $usedEditIds, true)) {
                        return false;
                    }
                    if ((int) ($e->source_sale_item_id ?? 0) !== $saleItemId) {
                        return false;
                    }
                    $editUnit = (int) ($e->source_quantity_unit_index ?? 1);

                    return $editUnit === $unitIndex;
                });
            }
            if (! $edit) {
                $edit = $existing->first(function ($e) use ($sortOrder, $usedEditIds) {
                    return ! in_array($e->id, $usedEditIds, true) && (int) $e->sort_order === (int) $sortOrder;
                });
            }

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
                $usedEditIds[] = $edit->id;
            } else {
                $created = $job->edits()->create($payload);
                $usedEditIds[] = $created->id;
            }
        }
        if ($usedEditIds !== []) {
            $job->edits()->whereNotIn('id', $usedEditIds)->delete();
        } else {
            $maxOrder = count($rows) - 1;
            $job->edits()->where('sort_order', '>', $maxOrder)->delete();
        }
    }

    public function resyncFromPos(Job $job): RedirectResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->canModifyJobWorkflow()) {
            abort(403);
        }
        if (empty($job->source_id)) {
            return redirect()->route('jobs.show', $job)->with('error', 'This job is not linked to a POS sale.');
        }

        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return redirect()->route('jobs.show', $job)->with('error', 'Source database not configured.');
        }

        try {
            $sale = DB::connection($conn)->table('sma_sales')->where('id', (int) $job->source_id)->first();
        } catch (\Throwable $e) {
            return redirect()->route('jobs.show', $job)->with('error', 'Cannot read POS sale: '.$e->getMessage());
        }

        if (! $sale) {
            return redirect()->route('jobs.show', $job)->with('error', 'Linked POS sale was not found.');
        }

        $job->load(['edits' => fn ($q) => $q->orderBy('sort_order')]);
        $beforeRows = PosJobLineDrift::rowsFromJob($job);

        try {
            $saleItems = DB::connection($conn)
                ->table('sma_sale_items')
                ->where('sale_id', (int) $sale->id)
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            return redirect()->route('jobs.show', $job)->with('error', 'Cannot read POS sale items: '.$e->getMessage());
        }

        $posRows = PosJobLineDrift::rowsFromPosSaleItems($conn, $saleItems);
        $cmpBefore = PosJobLineDrift::compare($job, $posRows);

        $dueDate = isset($sale->due_date) && $sale->due_date !== '0000-00-00'
            ? $sale->due_date
            : null;
        $paymentStatus = strtolower((string) ($sale->payment_status ?? ''));
        $isActive = in_array($paymentStatus, ['pending', 'due', 'partial', 'unpaid'], true);

        $job->update([
            'customer_name' => $sale->customer ?? $job->customer_name,
            'due_date' => $dueDate,
            'is_active' => $isActive,
            'notes' => $sale->note ?? $job->notes,
        ]);

        try {
            $this->syncSaleItemsFromSource($conn, $job, (int) $sale->id);
        } catch (\Throwable $e) {
            Log::error('resyncFromPos failed', [
                'job_id' => $job->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('jobs.show', $job)->with('error', 'Could not sync lines from POS.');
        }

        $job->refresh();
        $job->load(['edits' => fn ($q) => $q->orderBy('sort_order')]);
        $afterRows = PosJobLineDrift::rowsFromJob($job);

        $posCreatedRaw = $this->normalizePosTimestampRaw($sale->date ?? null);
        $posUpdatedRaw = $this->normalizePosTimestampRaw($sale->updated_at ?? null);

        try {
            if (Schema::hasTable('job_pos_apply_histories')) {
                JobPosApplyHistory::create([
                    'studio_job_id' => $job->id,
                    'applied_by' => auth()->id(),
                    'applied_at' => now(),
                    'pos_sale_created_at' => $posCreatedRaw,
                    'pos_sale_updated_at' => $posUpdatedRaw,
                    'summary' => $cmpBefore['summary'] ?? 'POS lines applied',
                    'previous_lines' => array_map(fn (array $r) => PosJobLineDrift::displayRow($r), $beforeRows),
                    'new_lines' => array_map(fn (array $r) => PosJobLineDrift::displayRow($r), $afterRows),
                    'added' => $cmpBefore['added'] ?? [],
                    'removed' => $cmpBefore['removed'] ?? [],
                    'changed' => $cmpBefore['changed'] ?? [],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not store POS apply history', [
                'job_id' => $job->id,
                'message' => $e->getMessage(),
            ]);
        }

        ActivityLog::log(
            'job_resync_from_pos',
            'Applied POS updates on job '.$job->ref_number.' ('.$cmpBefore['summary'].')',
            'job',
            $job->id
        );

        PosUpdatedBadge::bumpVersion();

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Pending POS updates applied. Previous and new lines are saved on this job with dates.');
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
        if ($user->isDeliveryViewOnly()) {
            return redirect()->route('jobs.index', ['section' => 'completed'])->with(
                'error',
                'Job Pool is not available for Delivery. Use the Completed jobs list to mark delivery.'
            );
        }
        $ref = $request->input('ref');
        $categoryFilter = $this->resolvedCategoryFilterKey($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;

        $conn = 'source';
        $db = config("database.connections.{$conn}.database");
        if (empty($db)) {
            return redirect()->route('jobs.index')->with('error', 'Source database not configured. Check DB_SOURCE_DATABASE in .env.');
        }

        try {
            // Exclude POS sales that already have a STARTED job (editor: only their jobs; others: any started job).
            // Printer / framing / printer+framing: show every eligible POS sale (opened or not).
            $usedSourceIds = [];
            if (! $user->usesDedicatedPrintFramingJobPool()) {
            $startedJobQuery = Job::whereNotNull('source_id')
                ->whereIn('status', [
                    Job::STATUS_ASSIGNED,
                    Job::STATUS_IN_PROGRESS,
                    Job::STATUS_COMPLETED,
                    Job::STATUS_DELIVERED,
                ]);

                if ($user->isEditor()) {
                $startedJobQuery->where(function ($q) use ($user) {
                    $q->where('assigned_editor_id', $user->id)
                        ->orWhereHas('editors', fn ($qq) => $qq->where('user_id', $user->id));
                });
            }

            $usedSourceIds = $startedJobQuery
                ->pluck('source_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            }

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

            if ($categoryFilter !== null) {
                $this->applyCanonicalCategoryFilterToPosSalesQuery($query, $categoryFilter, $conn);
            }

            // Hide sales with no eligible POS lines (and no eligible studio lines for printer/framing pool).
            JobPoolEligibility::constrainSalesQuery($query, $user, $conn);

            // Only count and fetch once to avoid re-building the query.
            $total = (clone $query)->count();

            $sales = $query
                // Soonest due date/time first, then earliest sale date, then sale id.
                ->orderBy('due_date')
                ->orderBy('date')
                ->orderBy('id')
                ->forPage($page, $perPage)
                ->get([
                    'id',
                    'reference_no',
                    'customer',
                    'payment_status',
                    'due_date',
                    'date',
                    'updated_at',
                    'staff_note',
                ]);
        } catch (\Throwable $e) {
            return redirect()->route('jobs.index')->with('error', 'Cannot read source sales: ' . $e->getMessage());
        }

        // Map any existing local jobs by source_id so we can show status / links.
        $sourceIds = $sales->pluck('id')->map(fn ($id) => (string) $id)->all();

        $jobsBySourceId = Job::with(['editor', 'editors', 'edits'])
            ->whereIn('source_id', $sourceIds)
            ->get()
            ->keyBy('source_id');

        // Load eligible sale items from POS; fall back to studio job_edits when pool row qualifies via opened job only.
        $itemsBySaleId = collect();
        $saleIds = $sales->pluck('id')->all();
        if ($saleIds !== []) {
        try {
                $itemsBySaleId = DB::connection($conn)
                ->table('sma_sale_items as si')
                    ->whereIn('si.sale_id', $saleIds)
                ->orderBy('si.id')
                    ->leftJoin('sma_products as p', 'si.product_id', '=', 'p.id')
                    ->get(['si.id', 'si.sale_id', 'si.product_name', 'si.quantity', 'si.product_id'])
                ->groupBy('sale_id')
                    ->map(function ($group) use ($conn, $user) {
                        return JobPoolEligibility::eligiblePosItemLines($conn, $group->all(), $user);
                });
        } catch (\Throwable $e) {
            // If POS items cannot be read, just leave itemsBySaleId empty.
            }

            foreach ($sales as $sale) {
                $saleId = (int) $sale->id;
                $lines = $itemsBySaleId->get($saleId, []);
                if ($lines !== []) {
                    continue;
                }

                $job = $jobsBySourceId->get((string) $saleId);
                if ($job) {
                    $editLines = JobPoolEligibility::eligibleJobEditLines($job, $user);
                    if ($editLines !== []) {
                        $itemsBySaleId->put($saleId, $editLines);
                    }
                }
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

        $allItemNamesBySaleId = $user->canSeeFullJobItemNamesSummary()
            ? $this->buildAllItemNamesBySaleIdForJobPool($conn, $sales, $jobsBySourceId)
            : [];

        return view('jobs.live', [
            'sales' => $paginator,
            'jobsBySourceId' => $jobsBySourceId,
            'itemsBySaleId' => $itemsBySaleId,
            'allItemNamesBySaleId' => $allItemNamesBySaleId,
            'ref' => $ref,
            'categoryFilter' => $categoryFilter,
            'jobPoolMode' => 'pos',
        ]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = auth()->user();
        $ref = $request->input('ref');
        $categoryFilter = $this->resolvedCategoryFilterKey($request);
        $deliveryJobsListOnly = $user->isDeliveryViewOnly();
        $section = $request->input('section', $deliveryJobsListOnly ? 'completed' : 'ongoing');

        $allowedSections = $user->allowedJobsListSections()
            ?? ['ongoing', 'pos_updated', 'edit_done', 'print_done', 'framing_done', 'completed', 'delivered', 'dismissed'];
        if (! in_array($section, $allowedSections, true)) {
            return redirect()->route('jobs.index', array_filter([
                'section' => $allowedSections[0],
                'ref' => $ref,
                'category' => $categoryFilter,
            ]));
        }

        $baseQuery = function () use ($ref, $categoryFilter) {
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
                        'source_sale_item_id',
                        'source_quantity_unit_index',
                        'source_quantity_unit_total',
                        'sort_order',
                    ])
                    ->orderBy('sort_order')])
                ->withCount('edits')
                ->when($ref, fn ($qq) => $qq->where('ref_number', 'like', '%' . $ref . '%'))
                ->when($categoryFilter !== null, function ($qq) use ($categoryFilter) {
                    $this->applyCanonicalCategoryFilterToJobsQuery($qq, $categoryFilter);
                });
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
            $printFramingQueueIds = Job::applyJobPoolGateForDedicatedPool(
                Job::queryDedicatedPrintFramingJobPool($user),
                $user
            )->pluck('id')->all();
        }

        /** @var callable(): Builder $scopedBaseForTabs */
        $scopedBaseForTabs = fn () => $baseQuery();

        if ($user->isEditor()) {
            // Ongoing: started jobs with any line still needing work (FRAME → framing; others → edit + print).
            $ongoingQuery = $scopeUserJobsIfEditor($baseQuery()
                ->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS]));
            $this->scopeJobsWhereAnyEditIncomplete($ongoingQuery, $printedTerminal, $user);
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
            $this->scopeJobsWhereAnyEditIncomplete($ongoingQuery, $printedTerminal, $user);
            $editDoneTabQuery = fn () => $this->buildEditDoneTabQuery($scopePrintFraming($baseQuery()), $printedTerminal);
            $completedQuery = $baseQuery()->where('status', Job::STATUS_COMPLETED);
            $deliveredQuery = $baseQuery()->where('status', Job::STATUS_DELIVERED);
            $scopedBaseForTabs = fn () => $this->scopeDedicatedPoolJobsForWorkflowTabs($baseQuery(), $user, $printFramingQueueIds);
        } else {
            $ongoingQuery = $baseQuery()
                ->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS]);
            $this->scopeJobsWhereAnyEditIncomplete($ongoingQuery, $printedTerminal, $user);
            $editDoneTabQuery = fn () => $this->buildEditDoneTabQuery($scopeUserJobsIfEditor($baseQuery()), $printedTerminal);
            $completedQuery = $baseQuery()->where('status', Job::STATUS_COMPLETED);
            $deliveredQuery = $baseQuery()->where('status', Job::STATUS_DELIVERED);
            $scopedBaseForTabs = fn () => $scopeUserJobsIfEditor($baseQuery());
        }

        $printDoneTabQuery = fn () => $this->buildPrintDoneTabQuery($scopedBaseForTabs(), $printedTerminal);
        $framingDoneTabQuery = fn () => $this->buildFramingDoneTabQuery($scopedBaseForTabs(), $printedTerminal);

        $posUpdatedDriftedIds = [];
        $posUpdatedCount = 0;
        if (in_array('pos_updated', $allowedSections, true)) {
            if ($section === 'pos_updated') {
                $refreshed = PosUpdatedBadge::countAndIds($user, allowRefresh: true);
                $posUpdatedDriftedIds = $refreshed['ids'];
                if ($ref || $categoryFilter !== null) {
                    $filterQ = Job::query()->whereIn('id', $posUpdatedDriftedIds ?: [0]);
                    if ($ref) {
                        $filterQ->where('ref_number', 'like', '%'.$ref.'%');
                    }
                    if ($categoryFilter !== null) {
                        $this->applyCanonicalCategoryFilterToJobsQuery($filterQ, $categoryFilter);
                    }
                    $posUpdatedDriftedIds = $filterQ->pluck('id')->map(fn ($id) => (int) $id)->all();
                }
                $posUpdatedCount = count($posUpdatedDriftedIds);
            } else {
                $posUpdatedCount = PosUpdatedBadge::cachedCount($user);
                $posUpdatedDriftedIds = PosUpdatedBadge::cachedIds($user);
            }
        }

        $ongoingCount = $ongoingQuery->count();
        $editDoneCount = $editDoneTabQuery()->count();
        $printDoneCount = $printDoneTabQuery()->count();
        $framingDoneCount = $framingDoneTabQuery()->count();
        $completedCount = $completedQuery->count();
        $deliveredCount = $deliveredQuery->count();
        $dismissedCount = $dismissedQuery->count();

        $posUpdatedMetaByJobId = [];
        if ($section === 'pos_updated') {
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 15;
            $pageIds = array_slice($posUpdatedDriftedIds, ($page - 1) * $perPage, $perPage);
            $jobsById = $pageIds === []
                ? collect()
                : $baseQuery()->whereIn('id', $pageIds)->get()->keyBy('id');
            $pageJobs = collect($pageIds)->map(fn ($id) => $jobsById->get($id))->filter()->values();
            $posUpdatedMetaByJobId = $this->posUpdatedMetaForJobs($pageJobs);
            $jobs = new LengthAwarePaginator(
                $pageJobs,
                $posUpdatedCount,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        } elseif ($section === 'dismissed') {
            $jobs = $this->paginateJobsBySoonestDue($dismissedQuery);
        } elseif ($section === 'edit_done') {
            $jobs = $this->paginateJobsBySoonestDue($editDoneTabQuery());
        } elseif ($section === 'print_done') {
            $jobs = $this->paginateJobsBySoonestDue($printDoneTabQuery());
        } elseif ($section === 'framing_done') {
            $jobs = $this->paginateJobsBySoonestDue($framingDoneTabQuery());
        } elseif ($section === 'completed') {
            $jobs = $this->paginateJobsBySoonestDue($completedQuery);
        } elseif ($section === 'delivered') {
            $jobs = $this->paginateJobsBySoonestDue($deliveredQuery);
        } else {
            // default: ongoing — soonest due date/time first
            $jobs = $this->paginateJobsBySoonestDue($ongoingQuery);
        }

        $posSaleListMeta = $this->fetchPosSaleListMetaBySourceIds(
            $jobs->getCollection()->pluck('source_id')->filter()->unique()
        );
        $saleDueRawBySourceId = $posSaleListMeta['due'];
        $saleStaffNoteBySourceId = $posSaleListMeta['staff_note'];
        $salePosTimestampsBySourceId = $posSaleListMeta['timestamps'];

        $jobsListSections = $user->allowedJobsListSections()
            ?? ['ongoing', 'pos_updated', 'edit_done', 'print_done', 'framing_done', 'completed', 'delivered', 'dismissed'];

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
            'posUpdatedCount',
            'posUpdatedMetaByJobId',
            'ref',
            'saleDueRawBySourceId',
            'saleStaffNoteBySourceId',
            'salePosTimestampsBySourceId',
            'deliveryJobsListOnly',
            'jobsListSections',
            'categoryFilter'
        ));
    }

    /**
     * Lightweight JSON badge for POS-updated count.
     * Uses cache when fresh; otherwise scans once and caches (called after page paint via JS).
     */
    public function posUpdatedCount(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $allowed = $user->allowedJobsListSections()
            ?? ['ongoing', 'pos_updated', 'edit_done', 'print_done', 'framing_done', 'completed', 'delivered', 'dismissed'];
        if (! in_array('pos_updated', $allowed, true)) {
            return response()->json(['count' => 0, 'cached' => true]);
        }

        $force = $request->boolean('refresh');
        if ($force || ! PosUpdatedBadge::hasFreshCache($user)) {
            $result = PosUpdatedBadge::refresh($user);

            return response()->json([
                'count' => $result['count'],
                'cached' => false,
            ]);
        }

        return response()->json([
            'count' => PosUpdatedBadge::cachedCount($user),
            'cached' => true,
        ]);
    }

    public function show(Job $job): Response|RedirectResponse
    {
        $user = auth()->user();
        if ($user && ! $user->canViewJobDetail($job)) {
            return redirect()->route('jobs.index', ['section' => 'completed'])
                ->with('error', 'Delivery can only open completed jobs ready for delivery.');
        }

        $job->refresh();
        $job->load(['editor', 'editors', 'deliveredByUser']);
        $job->load([
            'edits' => fn ($q) => $q->with('claimedByUser')->orderBy('sort_order'),
        ]);
        $this->applyPosDueDateFromSourceToJob($job);
        $this->enrichEditsWithCategoryFromSource($job);
        $this->maybeAutoCompleteJob($job);
        $posStaffNote = $this->fetchPosSaleStaffNoteForJob($job);
        $posSaleTimestamps = $this->fetchPosSaleTimestampsForJob($job);
        $posSaleMissing = false;
        $posLineDrift = null;
        if (filled($job->source_id) && ! empty(config('database.connections.source.database'))) {
            try {
                $posSaleMissing = PosJobLineDrift::saleMissingForJob($job, 'source');
                if (! $posSaleMissing) {
                    $saleItems = DB::connection('source')
                        ->table('sma_sale_items')
                        ->where('sale_id', (int) $job->source_id)
                        ->orderBy('id')
                        ->get(['id', 'sale_id', 'product_id', 'product_name', 'quantity']);
                    $posLineDrift = PosJobLineDrift::compare(
                        $job,
                        PosJobLineDrift::lightweightRowsFromPosSaleItems($saleItems)
                    );
                }
            } catch (\Throwable) {
                $posSaleMissing = false;
                $posLineDrift = null;
            }
        }
        $editorsAvailable = \App\Models\User::whereIn('role', \App\Models\User::rolesAssignableAsJobEditors())->orderBy('name')->get();
        $jobActivityLog = \App\Models\ActivityLog::where('subject_type', 'job')
            ->where('subject_id', $job->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(150)
            ->get();
        $posApplyHistories = collect();
        if (Schema::hasTable('job_pos_apply_histories')) {
            $posApplyHistories = $job->posApplyHistories()->with('appliedByUser')->limit(20)->get();
        }

        return response()
            ->view('jobs.show', compact(
                'job',
                'editorsAvailable',
                'jobActivityLog',
                'posStaffNote',
                'posLineDrift',
                'posSaleTimestamps',
                'posSaleMissing',
                'posApplyHistories'
            ))
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

                foreach ($job->edits as $edit) {
            if (filled($edit->category_name)) {
                        continue;
                    }

            try {
                $rawProductId = isset($edit->source_product_id) ? (int) $edit->source_product_id : null;
                if (! SaleItemsJobEditsBuilder::isLinkableSourceProductId($rawProductId)) {
                    $rawProductId = null;
                }

                $meta = SaleItemsJobEditsBuilder::resolveCategoryMetadata(
                    $conn,
                    (string) $edit->name,
                    $rawProductId
                );

                if ($meta['category_name'] === null) {
                    continue;
                }

                if (! filled($edit->category_name)) {
                    $edit->category_name = $meta['category_name'];
                }
                if (! filled($edit->subcategory_name) && $meta['subcategory_name'] !== null) {
                    $edit->subcategory_name = $meta['subcategory_name'];
                }
                if ($edit->source_category_id === null && $meta['source_category_id'] !== null) {
                    $edit->source_category_id = $meta['source_category_id'];
                }
                if ($edit->source_product_id === null && $meta['source_product_id'] !== null) {
                    $edit->source_product_id = $meta['source_product_id'];
                }
            } catch (\Throwable) {
                // Keep job page usable if POS enrichment fails for one line
            }
        }
    }

    /** Persist category fields looked up from POS so the job page and filters stay in sync across requests. */
    private function persistEnrichedEditCategories(Job $job): void
    {
            foreach ($job->edits as $edit) {
            if ($edit->isDirty(['category_name', 'subcategory_name', 'source_category_id', 'source_product_id'])) {
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
        $user = auth()->user();
        if (! $user->canTakeJob()) {
            abort(403);
        }
        if ($user->isEditor() && $job->status !== Job::STATUS_NEW) {
            return redirect()->route('jobs.show', $job)->with('error', 'Job is already assigned.');
        }
        if ($user->usesPoolTakeJobWorkflow() && $user->isAssignedToStudioJob($job)) {
            return redirect()->route('jobs.show', $job)->with('info', 'You are already on this job.');
        }
        $job->editors()->syncWithoutDetaching([$user->id]);
        if (! $job->assigned_editor_id) {
            $job->update(['assigned_editor_id' => $user->id]);
        }
        if ($job->status === Job::STATUS_NEW) {
        $job->update(['status' => Job::STATUS_ASSIGNED]);
        }
        $logAction = $user->usesPoolTakeJobWorkflow() ? 'job_taken_pool_worker' : 'job_taken';
        $logLabel = match ($user->role) {
            User::ROLE_FRAMING => 'Framing took/joined job ' . $job->ref_number,
            User::ROLE_PRINTER_FRAMING => 'Printer+Framing took/joined job ' . $job->ref_number,
            default => 'Took job ' . $job->ref_number,
        };
        ActivityLog::log($logAction, $logLabel, 'job', $job->id);

        $success = match ($user->role) {
            User::ROLE_FRAMING => 'Job is now assigned to you. You can mark framing items done.',
            User::ROLE_PRINTER_FRAMING => 'Job is now assigned to you. You can update print and framing status.',
            default => 'Job assigned to you.',
        };

        return redirect()->route('jobs.show', $job)->with('success', $success);
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
                    'Complete every line item first: framing-only categories need Framing done; edit/print categories need Edit done and print (Printed or Not required).'
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
     * Admin/Manager only: revert customer confirm so the item can be re-confirmed.
     */
    public function unconfirmCustomer(Job $job, JobEdit $edit): RedirectResponse
    {
        if (! auth()->user()->canManageWorkflowReversals()) {
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
        $user = auth()->user();
        if (! $user->canMarkFramingDone($edit)) {
            if (! $edit->needsDoneWorkflow()) {
                return redirect()->back()->with('error', 'This category does not use the Done step on this line.');
            }
            if ($user->framingMustTakeJobBeforeWorkOn($job)) {
                $message = $user->role === User::ROLE_PRINTER_FRAMING
                    ? 'Take this job first before updating print or framing status.'
                    : 'Take this job first before marking framing items done.';

                return redirect()->back()->with('error', $message);
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

        return redirect()->back()->with('success', 'Marked done for this item.');
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

        return redirect()->back()->with('success', 'Done cleared for this item.');
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
        if ($edit->hasEditDone()) {
            return redirect()->back()->with('error', 'This item is already Edit Done. Only Admin or Manager can clear it.');
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

    public function clearEditDone(Job $job, JobEdit $edit): RedirectResponse
    {
        if (! auth()->user()->canRevertEditDoneOnJobEdit($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if (! $this->updateJobEditStrict($edit, [
            'edit_done_at' => null,
            'edit_status' => JobEdit::EDIT_STATUS_IN_PROGRESS,
            'completed_at' => null,
        ])) {
            return $this->redirectJobEditMigration();
        }
        $this->maybeReopenJobIfIncomplete($job->fresh());
        ActivityLog::log(
            'job_edit_done_cleared',
            'Edit Done cleared: "' . $edit->name . '" on job ' . $job->ref_number . ' (Admin/Manager)',
            'job',
            $job->id
        );

        return redirect()->back()->with('success', 'Edit Done cleared for this item.');
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

        if ($edit->hasEditDone()
            && $valid['edit_status'] !== JobEdit::EDIT_STATUS_COMPLETED
            && ! $user->canManageWorkflowReversals()
        ) {
            return redirect()->back()->with('error', 'Only Admin or Manager can revert Edit Done.');
        }

        $payload = [
            'edit_status' => $valid['edit_status'],
            'completed_at' => $valid['edit_status'] === JobEdit::EDIT_STATUS_COMPLETED ? now() : null,
        ];
        if ($valid['edit_status'] !== JobEdit::EDIT_STATUS_COMPLETED && $user->canManageWorkflowReversals()) {
            $payload['edit_done_at'] = null;
        }

        if (! $this->updateJobEditStrict($edit, $payload)) {
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
        $user = auth()->user();
        if (! $user->canUpdatePrintStatus()) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if ($user->framingMustTakeJobBeforeWorkOn($job)) {
            return redirect()->back()->with('error', 'Take this job first before updating print or framing status.');
        }
        if (! $user->canApplyPrintStatusToJobEdit($edit)) {
            abort(403);
        }
        $valid = $request->validate([
            'print_status' => 'required|in:not_required,pending,sent_to_print,printed',
        ]);
        if (! auth()->user()->canSetPrintStatusOnJobEdit($edit, $valid['print_status'])) {
            $message = match (true) {
                $edit->print_status === JobEdit::PRINT_STATUS_PRINTED
                    && $valid['print_status'] !== JobEdit::PRINT_STATUS_PRINTED
                    => 'Printed cannot be changed except by Admin or Manager.',
                $valid['print_status'] === JobEdit::PRINT_STATUS_NOT_REQUIRED
                    && ! JobEdit::allowsNotRequiredFrom($edit->print_status)
                    => 'Not required cannot be set after Sent to print or Printed. Contact Admin or Manager.',
                JobEdit::isTerminalPrintStatus($edit->print_status)
                    && ! JobEdit::isTerminalPrintStatus($valid['print_status'])
                    => 'Only Admin or Manager can reverse Print Done.',
                default => 'You cannot update print status on this line.',
            };

            return redirect()->back()->with('error', $message);
        }
        if (! $this->updateJobEditStrict($edit, [
            'print_status' => $valid['print_status'],
            'print_status_at' => now(),
        ])) {
            return $this->redirectJobEditMigration();
        }
        if (JobEdit::isTerminalPrintStatus($valid['print_status'])) {
        $this->maybeAutoCompleteJob($job->fresh());
        } else {
            $this->maybeReopenJobIfIncomplete($job->fresh());
        }
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
            if (! $user->isFraming() && ! $user->isAdmin() && ! $user->isManager()) {
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
                        $reason = $this->bulkPrintStatusSkipReason($edit, $user, $validated['print_status']);
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
            'framing_done' => 'Done',
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

        return $job->edits->filter(function (JobEdit $e) use ($user) {
            if (! $user) {
                return ! $e->isGloballyHiddenFromStudioWorkflow();
            }

            return $user->isJobLineVisibleToMe($e);
        });
    }

    private function bulkPrintStatusSkipReason(JobEdit $edit, User $user, ?string $newStatus = null): ?string
    {
        if ($newStatus === null) {
            return ! $user->canApplyPrintStatusToJobEdit($edit) ? 'not eligible' : null;
        }

        if (! $user->canSetPrintStatusOnJobEdit($edit, $newStatus)) {
            if ($edit->print_status === JobEdit::PRINT_STATUS_PRINTED
                && $newStatus !== JobEdit::PRINT_STATUS_PRINTED) {
                return 'Printed — Admin/Manager only to change';
            }
            if ($newStatus === JobEdit::PRINT_STATUS_NOT_REQUIRED
                && ! JobEdit::allowsNotRequiredFrom($edit->print_status)) {
                return 'Not required not allowed after print started';
            }
            if (JobEdit::isTerminalPrintStatus($edit->print_status)
                && ! JobEdit::isTerminalPrintStatus($newStatus)) {
                return 'Only Admin/Manager can reverse Print Done';
            }

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
        if (! $edit->needsEditWorkflow()) {
            return 'This line does not use the editor workflow';
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
        if (! $edit->needsEditWorkflow()) {
            return 'This line does not use the editor workflow';
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

    /** Block photo-editor steps (claim, edit done, etc.) on done-only and print-only lines. */
    private function rejectIfNonEditorWorkflow(JobEdit $edit): ?RedirectResponse
    {
        if ($edit->isDoneOnlyWorkflow()) {
            return redirect()->back()->with(
                'error',
                'This category uses Done only — no editor steps on this line.'
            );
        }
        if ($edit->isPrintOnlyWorkflow()) {
            return redirect()->back()->with(
                'error',
                'This category uses print only — use the printer status buttons on this line.'
            );
        }

        return null;
    }

    /** @deprecated Use rejectIfNonEditorWorkflow() */
    private function rejectIfFrameOnlyEditorFlow(JobEdit $edit): ?RedirectResponse
    {
        return $this->rejectIfNonEditorWorkflow($edit);
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

    /**
     * Line-level POS vs job diff for a small page of jobs (Jobs → POS updated).
     *
     * @param  \Illuminate\Support\Collection<int, Job>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function posUpdatedMetaForJobs(\Illuminate\Support\Collection $jobs): array
    {
        if ($jobs->isEmpty()) {
            return [];
        }

        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return [];
        }

        $saleIds = $jobs->pluck('source_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        if ($saleIds === []) {
            return [];
        }

        try {
            $grouped = DB::connection($conn)
                ->table('sma_sale_items')
                ->whereIn('sale_id', $saleIds)
                ->orderBy('id')
                ->get(['id', 'sale_id', 'product_id', 'product_name', 'quantity'])
                ->groupBy('sale_id');
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($jobs as $job) {
            $items = $grouped->get((int) $job->source_id, collect());
            $cmp = PosJobLineDrift::compare($job, PosJobLineDrift::lightweightRowsFromPosSaleItems($items->all()));
            $out[$job->id] = [
                'job_line_count' => $cmp['job_line_count'],
                'pos_line_count' => $cmp['pos_line_count'],
                'summary' => $cmp['summary'],
                'pending_count' => $cmp['pending_count'],
                'changed' => $cmp['changed'],
                'added' => $cmp['added'],
                'removed' => $cmp['removed'],
                'job_lines' => $cmp['job_lines'],
                'pos_lines' => $cmp['pos_lines'],
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $sourceIds
     * @return array{due: array<string, string>, staff_note: array<string, string>, timestamps: array<string, array{created: ?string, updated: ?string}>}
     */
    private function fetchPosSaleListMetaBySourceIds(\Illuminate\Support\Collection $sourceIds): array
    {
        $empty = ['due' => [], 'staff_note' => [], 'timestamps' => []];
        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return $empty;
        }

        $ids = $sourceIds->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        if ($ids === []) {
            return $empty;
        }

        try {
            $rows = DB::connection($conn)->table('sma_sales')->whereIn('id', $ids)->get([
                'id',
                'due_date',
                'staff_note',
                'date',
                'updated_at',
            ]);
        } catch (\Throwable) {
            return $empty;
        }

        $due = [];
        $staff = [];
        $timestamps = [];
        foreach ($rows as $r) {
            $key = (string) (int) $r->id;
            $dueRaw = $r->due_date ?? null;
            if ($dueRaw !== null && $dueRaw !== '') {
                $s = (string) $dueRaw;
                if ($s !== '0000-00-00' && $s !== '0000-00-00 00:00:00') {
                    $due[$key] = $s;
                }
            }
            $note = Job::normalizePosStaffNote($r->staff_note ?? null);
            if ($note !== null) {
                $staff[$key] = $note;
            }
            $timestamps[$key] = [
                'created' => $this->normalizePosTimestampRaw($r->date ?? null),
                'updated' => $this->normalizePosTimestampRaw($r->updated_at ?? null),
            ];
        }

        return ['due' => $due, 'staff_note' => $staff, 'timestamps' => $timestamps];
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

    /**
     * @return array{created: ?string, updated: ?string}
     */
    private function fetchPosSaleTimestampsForJob(Job $job): array
    {
        $empty = ['created' => null, 'updated' => null];
        if (empty($job->source_id)) {
            return $empty;
        }
        $map = $this->fetchPosSaleTimestampsBySourceIds(collect([(string) $job->source_id]));

        return $map[(string) (int) $job->source_id] ?? $empty;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $sourceIds
     * @return array<string, array{created: ?string, updated: ?string}>
     */
    private function fetchPosSaleTimestampsBySourceIds(\Illuminate\Support\Collection $sourceIds): array
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
            $rows = DB::connection($conn)->table('sma_sales')->whereIn('id', $ids)->get(['id', 'date', 'updated_at']);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(string) (int) $r->id] = [
                'created' => $this->normalizePosTimestampRaw($r->date ?? null),
                'updated' => $this->normalizePosTimestampRaw($r->updated_at ?? null),
            ];
        }

        return $out;
    }

    private function normalizePosTimestampRaw(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') {
            return null;
        }

        return $s;
    }

    private function fetchPosSaleStaffNoteForJob(Job $job): ?string
    {
        if (empty($job->source_id)) {
            return null;
        }

        $conn = 'source';
        if (empty(config("database.connections.{$conn}.database"))) {
            return null;
        }

        try {
            $v = DB::connection($conn)->table('sma_sales')->where('id', (int) $job->source_id)->value('staff_note');
        } catch (\Throwable) {
            return null;
        }

        return Job::normalizePosStaffNote($v);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $sourceIds
     * @return array<string, string>
     */
    private function fetchPosSaleStaffNoteBySourceIds(\Illuminate\Support\Collection $sourceIds): array
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
            $rows = DB::connection($conn)->table('sma_sales')->whereIn('id', $ids)->get(['id', 'staff_note']);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $note = Job::normalizePosStaffNote($r->staff_note ?? null);
            if ($note !== null) {
                $out[(string) (int) $r->id] = $note;
            }
        }

        return $out;
    }

    /**
     * Every line name on each Job Pool sale (opened job → all job_edits; otherwise all POS lines).
     *
     * @param  \Illuminate\Support\Collection<int, object>|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $sales
     * @param  \Illuminate\Support\Collection<string, Job>  $jobsBySourceId
     * @return array<int, list<string>>
     */
    private function buildAllItemNamesBySaleIdForJobPool(string $conn, $sales, \Illuminate\Support\Collection $jobsBySourceId): array
    {
        $out = [];
        $saleIdsNeedingPos = [];

        foreach ($sales as $sale) {
            $saleId = (int) $sale->id;
            $job = $jobsBySourceId->get((string) $saleId);
            if ($job && $job->edits->isNotEmpty()) {
                $out[$saleId] = $job->edits
                    ->sortBy('sort_order')
                    ->map(fn (JobEdit $edit) => trim((string) ($edit->name ?? '')) ?: '—')
                    ->values()
                    ->all();

                continue;
            }

            $saleIdsNeedingPos[] = $saleId;
        }

        if ($saleIdsNeedingPos === []) {
            return $out;
        }

        try {
            $grouped = DB::connection($conn)
                ->table('sma_sale_items')
                ->whereIn('sale_id', $saleIdsNeedingPos)
                ->orderBy('id')
                ->get()
                ->groupBy('sale_id');
        } catch (\Throwable) {
            return $out;
        }

        foreach ($saleIdsNeedingPos as $saleId) {
            $items = $grouped->get($saleId, collect());
            if ($items->isEmpty()) {
                continue;
            }
            $rows = SaleItemsJobEditsBuilder::rowsFromSaleItems($conn, $items->all());
            $names = array_map(fn (array $row) => trim((string) ($row['name'] ?? '')) ?: '—', $rows);
            if ($names !== []) {
                $out[$saleId] = $names;
            }
        }

        return $out;
    }

    /**
     * Paginate Jobs list by soonest due. Falls back to local due_date if POS cross-db ORDER BY fails on the server.
     */
    private function paginateJobsBySoonestDue(Builder $query): LengthAwarePaginator
    {
        try {
            return Job::orderJobPoolByPosDueDate((clone $query))->paginate(15)->withQueryString();
        } catch (\Throwable $e) {
            Log::warning('Jobs list POS due-date sort failed; using studio_jobs.due_date', [
                'message' => $e->getMessage(),
            ]);

            $table = (new Job)->getTable();

            return (clone $query)
                ->reorder()
                ->orderBy($table.'.due_date')
                ->orderBy($table.'.created_at')
                ->orderBy($table.'.id')
                ->paginate(15)
                ->withQueryString();
        }
    }

    private function resolvedCategoryFilterKey(Request $request): ?string
    {
        $key = trim((string) $request->input('category', ''));
        if ($key === '' || ! CategoryAppearance::isValidCanonicalKey($key)) {
            return null;
        }

        return $key;
    }

    private function applyCanonicalCategoryFilterToJobsQuery(Builder $query, string $canonicalKey): void
    {
        $names = CategoryAppearance::normalizedNamesForCanonicalKey($canonicalKey);
        if ($names === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('edits', function ($q) use ($names) {
            $q->where(function ($inner) use ($names) {
                foreach ($names as $name) {
                    $inner->orWhereRaw('UPPER(TRIM(category_name)) = ?', [$name]);
                }
            });
        });
    }

    /**
     * Restrict a sma_sales query to sales that include at least one product in the given POS category.
     */
    private function applyCanonicalCategoryFilterToPosSalesQuery($query, string $canonicalKey, string $conn): void
    {
        $names = CategoryAppearance::normalizedNamesForCanonicalKey($canonicalKey);
        if ($names === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $query->whereExists(function ($q) use ($names, $placeholders) {
            $q->select(DB::raw(1))
                ->from('sma_sale_items as si_cat')
                ->join('sma_products as p_cat', 'si_cat.product_id', '=', 'p_cat.id')
                ->join('sma_categories as c_cat', 'p_cat.category_id', '=', 'c_cat.id')
                ->whereColumn('si_cat.sale_id', 'sma_sales.id')
                ->whereRaw('UPPER(TRIM(c_cat.name)) IN ('.$placeholders.')', $names);
        });
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
        if (! $user->jobPoolShowsFramingQueue()) {
            return $poolJobIds === []
                ? $query->whereRaw('0 = 1')
                : $query->whereIn('id', $poolJobIds);
        }

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
                        CategoryWorkflow::scopeDoneOnlyCategory($ed);
                        $allowed = $user->assignedCategoryIds();
                        if ($allowed !== []) {
                            $ed->where(function ($cat) use ($allowed) {
                                $cat->whereNull('source_category_id')
                                    ->orWhereIn('source_category_id', $allowed);
                            });
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
    private function scopeJobsWhereAnyEditIncomplete(Builder $query, array $printedTerminal, ?User $user = null): void
    {
        $query->where(function ($outer) use ($printedTerminal, $user) {
            $outer->whereDoesntHave('edits')
                ->orWhereHas('edits', function ($eq) use ($printedTerminal, $user) {
                    if ($user && $user->isPrinter()) {
                        $eq->where(function ($inner) use ($printedTerminal) {
                            CategoryWorkflow::scopePrintOnlyCategory($inner);
                            $inner->whereNotIn('print_status', $printedTerminal);
                        })->orWhere(function ($inner) use ($printedTerminal) {
                            CategoryWorkflow::scopeEditPrintCategory($inner);
                            $inner->whereNotNull('edit_done_at')
                                ->whereNotIn('print_status', $printedTerminal);
                        });

                        return;
                    }

                    if ($user && $user->role === User::ROLE_FRAMING) {
                        $eq->where(function ($inner) {
                            CategoryWorkflow::scopeDoneOnlyCategory($inner);
                            $inner->whereNull('framing_done_at');
                        });

                        return;
                    }

                    $eq->where(function ($inner) {
                        CategoryWorkflow::scopeDoneOnlyCategory($inner);
                        $inner->whereNull('framing_done_at');
                    })->orWhere(function ($inner) use ($printedTerminal) {
                        CategoryWorkflow::scopePrintOnlyCategory($inner);
                        $inner->whereNotIn('print_status', $printedTerminal);
                    })->orWhere(function ($inner) use ($printedTerminal) {
                        CategoryWorkflow::scopeEditPrintCategory($inner);
                        $inner->where(function ($x) use ($printedTerminal) {
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
                            CategoryWorkflow::scopeEditPrintCategory($q);
                            $q->whereNull('edit_done_at');
                        })
                        ->whereHas('edits', function ($q) use ($printedTerminal) {
                            CategoryWorkflow::scopeEditPrintCategory($q);
                            $q->whereNotNull('edit_done_at')
                                ->whereNotIn('print_status', $printedTerminal);
                        });
                })->orWhere(function (Builder $partialPhotoEdit) {
                    $partialPhotoEdit
                        ->whereHas('edits', function ($q) {
                            CategoryWorkflow::scopeEditPrintCategory($q);
                            $q->whereNotNull('edit_done_at');
                        })
                        ->whereHas('edits', function ($q) {
                            CategoryWorkflow::scopeEditPrintCategory($q);
                            $q->whereNull('edit_done_at');
                        });
                });
            });

        return $query;
    }

    private function buildPrintDoneTabQuery(Builder $query, array $printedTerminal): Builder
    {
        $query->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS])
            ->whereHas('edits', function ($q) {
                CategoryWorkflow::scopePrintWorkflowCategory($q);
            })
            ->whereDoesntHave('edits', function ($q) use ($printedTerminal) {
                $q->where(function ($lines) use ($printedTerminal) {
                    $lines->where(function ($printOnly) use ($printedTerminal) {
                        CategoryWorkflow::scopePrintOnlyCategory($printOnly);
                        $printOnly->whereNotIn('print_status', $printedTerminal);
                    })->orWhere(function ($editPrint) use ($printedTerminal) {
                        CategoryWorkflow::scopeEditPrintCategory($editPrint);
                        $editPrint->where(function ($x) use ($printedTerminal) {
                            $x->whereNull('edit_done_at')
                                ->orWhereNotIn('print_status', $printedTerminal);
                        });
                    });
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
                CategoryWorkflow::scopeDoneOnlyCategory($q);
                $q->whereNotNull('framing_done_at');
            })
            ->where(function ($outer) use ($printedTerminal) {
                $outer->whereHas('edits', function ($q) use ($printedTerminal) {
                    CategoryWorkflow::scopePrintOnlyCategory($q);
                    $q->whereNotIn('print_status', $printedTerminal);
                })->orWhereHas('edits', function ($q) use ($printedTerminal) {
                    CategoryWorkflow::scopeEditPrintCategory($q);
                    $q->where(function ($x) use ($printedTerminal) {
                        $x->whereNull('edit_done_at')
                            ->orWhereNotIn('print_status', $printedTerminal);
                    });
                })->orWhereHas('edits', function ($q) {
                    CategoryWorkflow::scopeDoneOnlyCategory($q);
                    $q->whereNull('framing_done_at');
                });
            });

        return $query;
    }
}
