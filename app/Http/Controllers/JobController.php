<?php

namespace App\Http\Controllers;

use App\Jobs\SyncJobsFromSourceJob;
use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobEdit;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $isActive = in_array($paymentStatus, ['pending', 'due', 'partial'], true);

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
        $this->syncSaleItemsFromSource($conn, $job, (int) $sale->id);

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

        $rows = [];
        foreach ($items as $i => $item) {
            $name = trim((string) ($item->product_name ?? ''));
            if ($name === '') {
                $name = 'Item ' . ($i + 1);
            }
            $productId = isset($item->product_id) ? (int) $item->product_id : null;
            $categoryName = null;
            $subcategoryName = null;
            $sourceCategoryId = null;
            if ($productId > 0) {
                $product = DB::connection($conn)->table('sma_products')->where('id', $productId)->first(['id', 'category_id', 'subcategory_id']);
                if ($product) {
                    $catId = (int) ($product->category_id ?? 0);
                    if ($catId > 0) {
                        $sourceCategoryId = $catId;
                        $cat = DB::connection($conn)->table('sma_categories')->where('id', $catId)->value('name');
                        $categoryName = $cat;
                    }
                    $subId = (int) ($product->subcategory_id ?? 0);
                    if ($subId > 0) {
                        $sub = DB::connection($conn)->table('sma_categories')->where('id', $subId)->value('name');
                        $subcategoryName = $sub;
                    }
                }
            }
            $rows[] = [
                'name' => $name,
                'source_product_id' => $productId ?: null,
                'category_name' => $categoryName,
                'subcategory_name' => $subcategoryName,
                'source_category_id' => $sourceCategoryId,
            ];
        }

        if (empty($rows)) {
            if ($job->edits()->count() === 0) {
                $job->edits()->create([
                    'name' => 'Edit 1',
                    'sort_order' => 0,
                    'edit_status' => JobEdit::EDIT_STATUS_PENDING,
                    'print_status' => JobEdit::PRINT_STATUS_PENDING,
                ]);
            }
            return;
        }

        $existing = $job->edits()->orderBy('sort_order')->get();
        foreach ($rows as $sortOrder => $row) {
            $edit = $existing->firstWhere('sort_order', $sortOrder);
            $payload = [
                'name' => $row['name'],
                'source_product_id' => $row['source_product_id'],
                'category_name' => $row['category_name'],
                'subcategory_name' => $row['subcategory_name'],
                'source_category_id' => $row['source_category_id'] ?? null,
                'sort_order' => $sortOrder,
                'edit_status' => $edit ? $edit->edit_status : JobEdit::EDIT_STATUS_PENDING,
                'print_status' => $edit ? $edit->print_status : JobEdit::PRINT_STATUS_PENDING,
            ];
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
                // For editors: only hide jobs that this editor is already on.
                $startedJobQuery->where(function ($q) use ($user) {
                    $q->where('assigned_editor_id', $user->id)
                        ->orWhereHas('editors', fn ($qq) => $qq->where('user_id', $user->id));
                });
            }

            $usedSourceIds = $startedJobQuery
                ->pluck('source_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $query = DB::connection($conn)
                ->table('sma_sales')
                ->where('pos', 1)
                // Job Pool: show only Paid + Partial payments from POS.
                ->whereIn('payment_status', ['paid', 'partial', 'Paid', 'Partial']);

            if (! empty($usedSourceIds)) {
                $query->whereNotIn('id', $usedSourceIds);
            }

            if ($ref) {
                $query->where('reference_no', 'like', '%' . $ref . '%');
            }

            // Only count and fetch once to avoid re-building the query.
            $total = (clone $query)->count();

            $sales = $query
                // Order by POS sale date so newest orders appear first.
                ->orderByDesc('date')
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

        return view('jobs.live', [
            'sales' => $paginator,
            'jobsBySourceId' => $jobsBySourceId,
            'ref' => $ref,
        ]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = auth()->user();
        $section = $request->input('section', 'ongoing');
        $ref = $request->input('ref');

        $baseQuery = fn () => Job::with(['editor', 'editors'])->withCount('edits')
            ->when($ref, fn ($q) => $q->where('ref_number', 'like', '%' . $ref . '%'));

        // Dismissed list: jobs this user has dismissed (for all roles – tab shown to everyone)
        $dismissedQuery = $baseQuery()->whereHas('dismissedByUsers', fn ($q) => $q->where('user_id', $user->id));

        if ($user->isEditor()) {
            // Ongoing: only started jobs (no pure 'new' jobs here – those stay in Job Pool)
            $ongoingQuery = $baseQuery()->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS])
                ->where(function ($q) use ($user) {
                    $q->where('assigned_editor_id', $user->id)
                        ->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id));
                });
            $completedQuery = $baseQuery()->whereIn('status', [Job::STATUS_COMPLETED, Job::STATUS_DELIVERED])
                ->where(function ($q) use ($user) {
                    $q->where('assigned_editor_id', $user->id)
                        ->orWhereHas('editors', fn ($q) => $q->where('user_id', $user->id));
                });
        } else {
            $ongoingQuery = $baseQuery()->whereIn('status', [Job::STATUS_ASSIGNED, Job::STATUS_IN_PROGRESS]);
            $completedQuery = $baseQuery()->whereIn('status', [Job::STATUS_COMPLETED, Job::STATUS_DELIVERED]);
        }

        $ongoingCount = $ongoingQuery->count();
        $completedCount = $completedQuery->count();
        $dismissedCount = $dismissedQuery->count();

        if ($section === 'dismissed') {
            $jobs = $dismissedQuery->latest()->paginate(15)->withQueryString();
        } else {
            $jobs = match ($section) {
                'completed' => $completedQuery->latest()->paginate(15)->withQueryString(),
                default => $ongoingQuery->latest()->paginate(15)->withQueryString(), // default: ongoing
            };
        }

        return view('jobs.index', compact('jobs', 'section', 'ongoingCount', 'completedCount', 'dismissedCount', 'ref'));
    }

    public function show(Job $job): View
    {
        $job->load(['edits' => fn ($q) => $q->with('claimedByUser'), 'editor', 'editors', 'deliveredByUser']);
        $this->enrichEditsWithCategoryFromSource($job);
        $editorsAvailable = \App\Models\User::where('role', \App\Models\User::ROLE_EDITOR)->orderBy('name')->get();
        $jobActivityLog = \App\Models\ActivityLog::where('subject_type', 'job')
            ->where('subject_id', $job->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return view('jobs.show', compact('job', 'editorsAvailable', 'jobActivityLog'));
    }

    /**
     * When job edits have null category/subcategory, fetch from POS DB by source_product_id or by product name and set for display.
     */
    private function enrichEditsWithCategoryFromSource(Job $job): void
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
        $job->update($valid);
        ActivityLog::log('job_status_updated', 'Updated job ' . $job->ref_number . ' status to ' . $valid['status'], 'job', $job->id);
        return redirect()->back()->with('success', 'Job status updated.');
    }

    public function claimEdit(Job $job, JobEdit $edit): RedirectResponse
    {
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if ($edit->claimed_by_user_id !== null && $edit->claimed_by_user_id != auth()->id()) {
            return redirect()->back()->with('error', 'This item is already being edited by someone else.');
        }
        $edit->update([
            'claimed_by_user_id' => auth()->id(),
            'edit_status' => JobEdit::EDIT_STATUS_IN_PROGRESS,
        ]);
        ActivityLog::log('job_edit_claimed', 'Started editing "' . $edit->name . '" on job ' . $job->ref_number, 'job', $job->id);
        return redirect()->back()->with('success', 'You are now editing this item.');
    }

    public function confirmCustomer(Job $job, JobEdit $edit): RedirectResponse
    {
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        $edit->update(['customer_confirmed_at' => now()]);
        ActivityLog::log('job_edit_customer_confirmed', 'Marked customer confirmed for "' . $edit->name . '" on job ' . $job->ref_number, 'job', $job->id);
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
        $edit->update(['customer_confirmed_at' => null]);
        ActivityLog::log('job_edit_customer_unconfirmed', 'Reverted customer confirm for "' . $edit->name . '" on job ' . $job->ref_number . ' (Admin)', 'job', $job->id);
        return redirect()->back()->with('success', 'Customer confirm reverted. Item must be confirmed again before send to print.');
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

        $edit->update([
            'edit_status' => $valid['edit_status'],
            'completed_at' => $valid['edit_status'] === JobEdit::EDIT_STATUS_COMPLETED ? now() : null,
        ]);
        if ($job->fresh()->allEditsCompleted()) {
            $job->update(['status' => Job::STATUS_COMPLETED]);
        }
        ActivityLog::log('job_edit_status_updated', 'Updated item "' . $edit->name . '" on job ' . $job->ref_number . ' to ' . $valid['edit_status'], 'job', $job->id);
        return redirect()->back()->with('success', 'Edit status updated.');
    }

    public function updatePrintStatus(Request $request, Job $job, JobEdit $edit): RedirectResponse
    {
        if (! auth()->user()->canUpdatePrintStatus()) {
            abort(403);
        }
        if ($edit->studio_job_id != $job->id) {
            abort(404);
        }
        if (! auth()->user()->canEditJobItem($edit)) {
            abort(403);
        }
        if (! $edit->isCustomerConfirmed() && ! auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Customer must confirm this item before it can be sent to print.');
        }
        $valid = $request->validate([
            'print_status' => 'required|in:not_required,pending,sent_to_print,printed',
        ]);
        $edit->update($valid);
        ActivityLog::log('job_print_status_updated', 'Updated print status for item on job ' . $job->ref_number, 'job', $job->id);
        return redirect()->back()->with('success', 'Print status updated.');
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
        if (! auth()->user()->canAddOrRemoveEditorsOn($job)) {
            abort(403);
        }
        $valid = $request->validate(['user_id' => 'required|exists:users,id']);
        $user = \App\Models\User::findOrFail($valid['user_id']);
        if ($user->role !== \App\Models\User::ROLE_EDITOR) {
            return redirect()->back()->with('error', 'User must be an Editor.');
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
        if (! auth()->user()->canAddOrRemoveEditorsOn($job)) {
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
        if (! $user->isEditor() && ! $user->canManageJobs()) {
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
}
