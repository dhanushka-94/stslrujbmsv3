@extends('layouts.app')

@section('title', 'Job ' . $job->ref_number)

@section('content')
    @php
        $globalBlockedCategoryIds = \App\Models\BlockedCategory::blockedCategoryIds();
        $globalBlockedProductIds = \App\Models\BlockedProduct::blockedProductIds();
        $currentUser = auth()->user();
        $allowedCategoryIds = $currentUser->scopedCategoryIdsForJobLineTable();

        $visibleEdits = $job->edits->filter(function ($e) use ($globalBlockedCategoryIds, $globalBlockedProductIds, $allowedCategoryIds) {
            $catId = $e->source_category_id ? (int) $e->source_category_id : null;
            $productId = $e->source_product_id ? (int) $e->source_product_id : null;

            $categoryBlocked = $catId !== null && in_array($catId, $globalBlockedCategoryIds, true);
            $productBlocked = $productId !== null && in_array($productId, $globalBlockedProductIds, true);

            $categoryAllowedForEditor = empty($allowedCategoryIds) || $catId === null || in_array($catId, $allowedCategoryIds, true);

            return ! $categoryBlocked && ! $productBlocked && $categoryAllowedForEditor;
        });

        $blockedEdits = $job->edits->filter(function ($e) use ($globalBlockedCategoryIds, $globalBlockedProductIds) {
            $catId = $e->source_category_id ? (int) $e->source_category_id : null;
            $productId = $e->source_product_id ? (int) $e->source_product_id : null;
            $categoryBlocked = $catId !== null && in_array($catId, $globalBlockedCategoryIds, true);
            $productBlocked = $productId !== null && in_array($productId, $globalBlockedProductIds, true);
            return $categoryBlocked || $productBlocked;
        });

        $bulkCanPrint = $currentUser->canUpdatePrintStatus();
        $bulkCanFramingDone = $currentUser->isFraming() || $currentUser->isAdmin();
        $bulkCanFramingClear = $currentUser->isAdmin() || $currentUser->isManager();
        $bulkCanTime = $currentUser->isAdmin() || $currentUser->isManager() || $currentUser->isEditor();
        $bulkHasNonFrameLines = $visibleEdits->contains(fn ($e) => ! $e->isFrameCategoryLine());
        $bulkCanEditorPipeline = $currentUser->isEditor() || $currentUser->isAdmin() || $currentUser->isManager();
        $showBulkJobItemsBar = $visibleEdits->isNotEmpty() && (
            $bulkCanPrint
            || $bulkCanFramingDone
            || $bulkCanFramingClear
            || ($bulkCanTime && $bulkHasNonFrameLines)
            || ($bulkCanEditorPipeline && $bulkHasNonFrameLines)
        );

        $lineWorkflowComplete = fn ($e) => $e->isFrameCategoryLine()
            ? $e->framing_done_at !== null
            : ($e->edit_done_at && in_array($e->print_status, ['printed', 'not_required'], true));

        $totalItems = $visibleEdits->count();
        $jobComplete = $totalItems > 0 && $visibleEdits->every($lineWorkflowComplete);

        $nonFrameVisible = $visibleEdits->filter(fn ($e) => ! $e->isFrameCategoryLine());
        $frameVisible = $visibleEdits->filter(fn ($e) => $e->isFrameCategoryLine());
        $nonFrameCount = $nonFrameVisible->count();
        $editDoneCount = $nonFrameVisible->filter(fn ($e) => $e->edit_done_at)->count();
        $editingComplete = $nonFrameCount === 0 || ($nonFrameCount > 0 && $editDoneCount === $nonFrameCount);

        $editDoneItemsForPrint = $nonFrameVisible->whereNotNull('edit_done_at');
        $editDoneForPrintCount = $editDoneItemsForPrint->count();
        $printedOrNotRequiredCount = $editDoneItemsForPrint->filter(fn ($e) => in_array($e->print_status, ['printed', 'not_required'], true))->count();
        $printingNotStarted = $nonFrameCount > 0 && $editDoneForPrintCount === 0;
        $printingComplete = $nonFrameCount === 0 || $editDoneForPrintCount === 0
            || $printedOrNotRequiredCount === $editDoneForPrintCount;

        $framingLinesComplete = $frameVisible->isEmpty()
            || $frameVisible->every(fn ($e) => $e->framing_done_at !== null);

        $snapshotLineState = function (\App\Models\JobEdit $e): string {
            if ($e->isFrameCategoryLine()) {
                return $e->framing_done_at ? 'Framing complete' : 'Awaiting framing';
            }
            if ($e->edit_done_at) {
                return in_array($e->print_status, ['printed', 'not_required'], true)
                    ? 'Edit & print complete'
                    : 'Awaiting print';
            }
            if ($e->claimed_by_user_id) {
                return 'In edit';
            }

            return 'Not started';
        };

        $snapshotPrintLabel = function (\App\Models\JobEdit $e): string {
            if ($e->isFrameCategoryLine()) {
                return '—';
            }

            return match ($e->print_status) {
                'not_required' => 'Not required',
                'pending' => 'Pending',
                'sent_to_print' => 'Sent to print',
                'printed' => 'Printed',
                default => (string) $e->print_status,
            };
        };

        $snapshotFramingLabel = function (\App\Models\JobEdit $e): string {
            if ($e->framing_done_at) {
                return 'Done ' . $e->framing_done_at->format('M j, H:i');
            }
            if ($e->isFrameCategoryLine()) {
                return 'Pending';
            }

            return '—';
        };

        $activityActionClasses = function (string $action): string {
            return match (true) {
                str_contains($action, 'framing') => 'bg-teal-100 text-teal-900 dark:bg-teal-900/40 dark:text-teal-100 border-teal-200 dark:border-teal-800',
                str_contains($action, 'print') => 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100 border-blue-200 dark:border-blue-800',
                str_contains($action, 'edit') || str_contains($action, 'customer') || str_contains($action, 'sent_to') => 'bg-indigo-100 text-indigo-900 dark:bg-indigo-900/40 dark:text-indigo-100 border-indigo-200 dark:border-indigo-800',
                str_contains($action, 'job_taken') || str_contains($action, 'editor') => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100 border-amber-200 dark:border-amber-800',
                str_contains($action, 'deliver') || str_contains($action, 'completed') => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100 border-emerald-200 dark:border-emerald-800',
                default => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border-slate-200 dark:border-slate-600',
            };
        };

        $activityLogByDay = collect($jobActivityLog ?? [])->groupBy(fn ($log) => $log->created_at->format('Y-m-d'));

        $dueOverdue = $job->isDueOverdue($jobComplete);

        $jobStatusLabel = match ($job->status) {
            'new' => 'New',
            'assigned' => 'Assigned',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'delivered' => 'Delivered',
            default => (string) $job->status,
        };
        $jobStatusBadgeClass = match ($job->status) {
            'new' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 ring-1 ring-inset ring-blue-200/80 dark:ring-blue-700/60',
            'assigned' => 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-200 ring-1 ring-inset ring-indigo-200/80 dark:ring-indigo-700/60',
            'in_progress' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 ring-1 ring-inset ring-amber-200/80 dark:ring-amber-700/60',
            'completed' => 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200 ring-1 ring-inset ring-green-200/80 dark:ring-green-700/60',
            'delivered' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 ring-1 ring-inset ring-emerald-200/80 dark:ring-emerald-700/60',
            default => 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 ring-1 ring-inset ring-slate-200 dark:ring-slate-600',
        };
    @endphp

    <section class="mb-8 rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden">
        <div class="relative px-5 py-5 sm:px-6 sm:py-6 bg-gradient-to-br from-[var(--color-studio-primary)]/[0.07] via-transparent to-slate-50/80 dark:from-[var(--color-studio-accent)]/[0.08] dark:to-slate-900/40">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Job detail</p>
                    <h1 class="mt-1.5 flex flex-wrap items-center gap-2.5 text-2xl sm:text-3xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/90 shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800/90 dark:ring-slate-600">
                            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-6 h-6'])
                        </span>
                        <span class="font-mono tracking-tight text-slate-900 dark:text-slate-100">{{ $job->ref_number }}</span>
                    </h1>
                    @if(filled($job->customer_name))
                        <p class="mt-2 text-base text-slate-700 dark:text-slate-200">{{ $job->customer_name }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200/90 bg-white/80 px-3 py-1 text-sm text-slate-700 shadow-sm dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-200">
                            @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-4 h-4 text-slate-500 dark:text-slate-400'])
                            {{ $totalItems }} {{ $totalItems === 1 ? 'line' : 'lines' }} visible
                        </span>
                        @if($job->due_date)
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm shadow-sm {{ $dueOverdue ? 'border-amber-300/90 bg-amber-50 text-amber-950 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-100' : 'border-slate-200/90 bg-white/80 text-slate-700 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-200' }}">
                                @include('components.icons', ['name' => 'clock', 'class' => 'w-4 h-4 shrink-0 ' . ($dueOverdue ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500 dark:text-slate-400')])
                                Due {{ $job->due_date->format('M j, Y') }}@if($job->dueHasTimeAssigned())<span class="text-slate-500 dark:text-slate-400"> · </span><span class="font-medium tabular-nums">{{ $job->due_date->format('g:i A') }}</span>@endif
                                @if($dueOverdue)
                                    <span class="rounded bg-amber-600/15 px-1.5 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">Overdue</span>
                                @endif
                            </span>
                        @endif
                    </div>
                </div>
                <a href="{{ route('jobs.index') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-studio-primary)] focus-visible:ring-offset-2 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus-visible:ring-offset-slate-900">
                    @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                    Back to jobs
                </a>
            </div>
        </div>
    </section>

    <div class="grid gap-6 md:grid-cols-2 mb-8">
        <div class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden flex flex-col">
            <div class="flex items-center gap-2 border-b border-slate-200/80 bg-slate-50/90 px-4 py-3 dark:border-slate-700/80 dark:bg-slate-800/50">
                @include('components.icons', ['name' => 'document-check', 'class' => 'w-5 h-5 text-slate-500 dark:text-slate-400'])
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Details</h2>
            </div>
            <div class="p-4 sm:p-5 space-y-5 flex-1 flex flex-col">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Reference</dt>
                        <dd class="mt-1 font-mono text-base font-semibold text-slate-900 dark:text-slate-100">{{ $job->ref_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Due date</dt>
                        <dd class="mt-1 text-base font-medium text-slate-900 dark:text-slate-100">
                            {{ $job->due_date ? $job->due_date->format('M j, Y') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Due time</dt>
                        <dd class="mt-1 text-base font-medium text-slate-900 dark:text-slate-100 tabular-nums">
                            @if($job->due_date && $job->dueHasTimeAssigned())
                                {{ $job->due_date->format('g:i A') }}
                            @else
                                <span class="text-slate-500 dark:text-slate-400 font-normal">Not assigned</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="rounded-lg border border-slate-200/90 bg-slate-50/80 p-4 dark:border-slate-600/80 dark:bg-slate-800/40">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3">Line workflow</p>
                    <ul class="space-y-3 text-sm">
                        <li class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                            <span class="shrink-0 text-slate-600 dark:text-slate-300 font-medium">Editing</span>
                            <span class="min-w-0 text-right sm:text-right">
                                @if($totalItems === 0)
                                    <span class="inline-flex rounded-md bg-slate-200/90 px-2 py-0.5 text-xs font-medium text-slate-800 dark:bg-slate-700 dark:text-slate-100">No items</span>
                                @elseif($nonFrameCount === 0)
                                    <span class="inline-flex rounded-md bg-slate-200/90 px-2 py-0.5 text-xs font-medium text-slate-800 dark:bg-slate-700 dark:text-slate-100">N/A</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1 sm:mt-0.5">FRAME-only — no editor steps</span>
                                @elseif($editingComplete)
                                    <span class="inline-flex rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white dark:bg-emerald-500 dark:text-emerald-950">Complete</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $editDoneCount }} / {{ $nonFrameCount }} non-frame Edit Done</span>
                                @else
                                    <span class="inline-flex rounded-md bg-amber-500 px-2 py-0.5 text-xs font-medium text-white dark:bg-amber-400 dark:text-amber-950">In progress</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $editDoneCount }} / {{ $nonFrameCount }} non-frame Edit Done</span>
                                @endif
                            </span>
                        </li>
                        <li class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3 pt-2 border-t border-slate-200/70 dark:border-slate-600/60">
                            <span class="shrink-0 text-slate-600 dark:text-slate-300 font-medium">Printing</span>
                            <span class="min-w-0 text-right sm:text-right">
                                @if($nonFrameCount === 0)
                                    <span class="inline-flex rounded-md bg-slate-200/90 px-2 py-0.5 text-xs font-medium text-slate-800 dark:bg-slate-700 dark:text-slate-100">N/A</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1 sm:mt-0.5">FRAME-only — no print</span>
                                @elseif($printingNotStarted)
                                    <span class="inline-flex rounded-md bg-slate-200/90 px-2 py-0.5 text-xs font-medium text-slate-800 dark:bg-slate-700 dark:text-slate-100">Not started</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">Complete editing first</span>
                                @elseif($printingComplete)
                                    <span class="inline-flex rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white dark:bg-emerald-500 dark:text-emerald-950">Complete</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $printedOrNotRequiredCount }} / {{ $editDoneForPrintCount }} printed or not required</span>
                                @else
                                    <span class="inline-flex rounded-md bg-amber-500 px-2 py-0.5 text-xs font-medium text-white dark:bg-amber-400 dark:text-amber-950">In progress</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $printedOrNotRequiredCount }} / {{ $editDoneForPrintCount }} printed or not required</span>
                                @endif
                            </span>
                        </li>
                        @if($frameVisible->isNotEmpty())
                        <li class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3 pt-2 border-t border-slate-200/70 dark:border-slate-600/60">
                            <span class="shrink-0 text-slate-600 dark:text-slate-300 font-medium">Framing (FRAME)</span>
                            <span class="min-w-0 text-right sm:text-right">
                                @if($framingLinesComplete)
                                    <span class="inline-flex rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white dark:bg-emerald-500 dark:text-emerald-950">Complete</span>
                                @else
                                    <span class="inline-flex rounded-md bg-amber-500 px-2 py-0.5 text-xs font-medium text-white dark:bg-amber-400 dark:text-amber-950">In progress</span>
                                @endif
                                <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $frameVisible->whereNotNull('framing_done_at')->count() }} / {{ $frameVisible->count() }} done</span>
                            </span>
                        </li>
                        @endif
                        <li class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3 pt-2 border-t border-slate-200/70 dark:border-slate-600/60">
                            <span class="shrink-0 text-slate-600 dark:text-slate-300 font-medium">Job lines</span>
                            <span class="min-w-0 text-right sm:text-right">
                                @if($jobComplete)
                                    <span class="inline-flex rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white dark:bg-emerald-500 dark:text-emerald-950">Complete</span>
                                @else
                                    <span class="inline-flex rounded-md bg-slate-600 px-2 py-0.5 text-xs font-medium text-white dark:bg-slate-500 dark:text-slate-900">In progress</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">FRAME → framing; other lines → edit + print</span>
                                @endif
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="pt-1 border-t border-slate-200/80 dark:border-slate-700/80">
                    <div class="flex items-center gap-2 mb-2">
                        @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4 text-slate-500 dark:text-slate-400'])
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Editors</span>
                    </div>
                    @if($job->editors->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($job->editors as $ed)
                                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white pl-2.5 pr-1 py-0.5 text-sm text-slate-800 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                    <span class="truncate max-w-[10rem] sm:max-w-[14rem]">{{ $ed->name }}</span>
                                    @if(auth()->user()->canAddOrRemoveEditorsOn())
                                        <form action="{{ route('jobs.editors.remove', [$job, $ed]) }}" method="POST" class="inline shrink-0" onsubmit="return confirm('Remove this editor?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-full p-1 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40" title="Remove editor">✕</button>
                                        </form>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">—</p>
                    @endif
                    @if(auth()->user()->canAddOrRemoveEditorsOn() && $editorsAvailable->diff($job->editors)->isNotEmpty())
                        <form action="{{ route('jobs.editors.add', $job) }}" method="POST" class="mt-3 flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <select name="user_id" class="min-h-[2.5rem] flex-1 rounded-lg border border-[var(--color-studio-border)] bg-white px-3 py-2 text-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800">
                                @foreach($editorsAvailable->diff($job->editors) as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            <button type="submit" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg bg-[var(--color-studio-primary)] px-4 text-sm font-medium text-white shadow-sm hover:opacity-95">Add editor</button>
                        </form>
                    @endif
                </div>

                @if($job->delivered_at)
                    <div class="rounded-lg border border-emerald-200/90 bg-emerald-50/70 px-3 py-2.5 text-sm dark:border-emerald-800/50 dark:bg-emerald-950/30">
                        <span class="font-medium text-emerald-900 dark:text-emerald-100">Delivered</span>
                        <span class="text-emerald-800/90 dark:text-emerald-200/90"> {{ $job->delivered_at->format('M j, Y H:i') }} ({{ $job->delivery_method }}) by {{ $job->deliveredByUser?->name ?? '—' }}</span>
                    </div>
                @endif
                @if($job->notes)
                    <div class="rounded-lg border border-amber-200/80 bg-amber-50/50 px-3 py-2.5 text-sm text-amber-950 dark:border-amber-800/40 dark:bg-amber-950/25 dark:text-amber-100">
                        <span class="font-semibold text-amber-900 dark:text-amber-200">Notes</span>
                        <p class="mt-1 whitespace-pre-wrap text-amber-950/95 dark:text-amber-50/95">{{ $job->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden flex flex-col">
            <div class="flex items-center gap-2 border-b border-slate-200/80 bg-slate-50/90 px-4 py-3 dark:border-slate-700/80 dark:bg-slate-800/50">
                @include('components.icons', ['name' => 'bolt', 'class' => 'w-5 h-5 text-slate-500 dark:text-slate-400'])
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Actions &amp; status</h2>
            </div>
            <div class="p-4 sm:p-5 space-y-4 flex-1 flex flex-col">
                <div class="space-y-3">
                    @if(auth()->user()->canTakeJob() && $job->status === 'new')
                        <form action="{{ route('jobs.take', $job) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full rounded-lg bg-green-600 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Take this job</button>
                        </form>
                    @endif
                    @if($job->status === 'new' && auth()->user()->canDismissNewJobs())
                        @if($job->isDismissedBy(auth()->user()))
                            <form action="{{ route('jobs.undismiss', $job) }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-green-600 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Restore to job list</button>
                            </form>
                        @else
                            <form action="{{ route('jobs.dismiss', $job) }}" method="POST" onsubmit="return confirm('Dismiss this job? It will move to your Dismissed list and will not show on the main job list until you restore it.');">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border-2 border-amber-500 py-2.5 text-sm font-semibold text-amber-700 transition hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Dismiss job</button>
                            </form>
                        @endif
                    @endif
                </div>

                <div class="rounded-lg border border-slate-200/90 bg-slate-50/60 p-4 dark:border-slate-600/70 dark:bg-slate-800/35">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Job status <span class="font-normal normal-case text-slate-400 dark:text-slate-500">(auto)</span></div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-3.5 py-1.5 text-xs font-semibold {{ $jobStatusBadgeClass }}">{{ $jobStatusLabel }}</span>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Updates when the job is taken, editing starts, and when every line is finished (edit + print, or framing for FRAME lines).</p>
                </div>

                @if(auth()->user()->canDeliver() && $job->status === 'completed')
                    <form action="{{ route('jobs.deliver', $job) }}" method="POST" class="flex flex-col gap-2 rounded-lg border border-green-200/90 bg-green-50/40 p-3 dark:border-green-800/50 dark:bg-green-950/20 sm:flex-row sm:items-stretch">
                        @csrf
                        <select name="delivery_method" required class="min-h-[2.5rem] flex-1 rounded-lg border border-[var(--color-studio-border)] bg-white px-3 py-2 text-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800">
                            <option value="online">Online</option>
                            <option value="walkin">Walk-in</option>
                            <option value="courier">Courier</option>
                        </select>
                        <button type="submit" class="inline-flex min-h-[2.5rem] items-center justify-center rounded-lg bg-green-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 sm:shrink-0">Mark delivered</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <section class="mb-10 mt-2 {{ $showBulkJobItemsBar ? 'pb-24 sm:pb-20' : '' }}" aria-labelledby="job-items-heading">
        <div class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-slate-200/80 bg-gradient-to-r from-slate-50/95 to-slate-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700/80 dark:from-slate-800/80 dark:to-slate-900/40">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800 dark:ring-slate-600">
                        @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-5 h-5 text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]'])
                    </span>
                    <div class="min-w-0">
                        <h2 id="job-items-heading" class="text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">Line items</h2>
                        <p class="mt-0.5 text-xs leading-snug text-slate-500 dark:text-slate-400">POS sync · category &amp; subcategory per row · scroll horizontally on small screens</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200/90 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600">{{ $visibleEdits->count() }} shown</span>
                    @if($blockedEdits->count() > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200/90 dark:bg-amber-950/50 dark:text-amber-100 dark:ring-amber-800/60">{{ $blockedEdits->count() }} blocked</span>
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[80rem] border-collapse text-sm">
                    <thead class="sticky top-0 z-20 border-b border-slate-200/90 bg-slate-100/95 backdrop-blur-md dark:border-slate-700 dark:bg-slate-800/95">
                        <tr>
                            @if($showBulkJobItemsBar)
                            <th scope="col" class="w-10 whitespace-nowrap px-1 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                <span class="sr-only">Select</span>
                                <input type="checkbox" id="job-bulk-select-all" class="h-4 w-4 rounded border-slate-300 text-[var(--color-studio-primary)] focus:ring-[var(--color-studio-primary)] dark:border-slate-600 dark:bg-slate-800" title="Select all rows">
                            </th>
                            @endif
                            <th scope="col" class="w-11 whitespace-nowrap px-2 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">#</th>
                            <th scope="col" class="min-w-[11rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Item</th>
                            <th scope="col" class="min-w-[7rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Category</th>
                            <th scope="col" class="min-w-[7rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Subcategory</th>
                            <th scope="col" class="min-w-[6rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Editing by</th>
                            <th scope="col" class="min-w-[8rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Est. time</th>
                            <th scope="col" class="min-w-[9rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Editor status</th>
                            <th scope="col" class="min-w-[10rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Editor actions</th>
                            <th scope="col" class="min-w-[9rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Printer status</th>
                            <th scope="col" class="min-w-[9rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Printer actions</th>
                            <th scope="col" class="min-w-[7rem] whitespace-nowrap px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Framing</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                @forelse($visibleEdits as $edit)
                    @php
                        $isFrameLine = $edit->isFrameCategoryLine();
                        $canEdit = auth()->user()->canEditJobItem($edit);
                        $isAdmin = auth()->user()->isAdmin();
                        $isClaimedByMe = $edit->claimed_by_user_id == auth()->id();
                        $isClaimedByOther = $edit->claimed_by_user_id !== null && !$isClaimedByMe;
                        $canStartEditing = $canEdit && $edit->claimed_by_user_id === null && !$isAdmin;
                        $canChangeStatus = $canEdit && ($isClaimedByMe || $isClaimedByOther || $isAdmin);
                        $canUseEditorActions = $canChangeStatus && $canEdit && $edit->estimated_minutes !== null;

                        // 5 steps only: 1 In Edit, 2 N# Sent to Customer Review, 3 N# Re-Edit, 4 Customer Confirm, 5 Edit Done. One highlighted.
                        $currentStep = 'in_edit';
                        if ($edit->edit_done_at) {
                            $currentStep = 'edit_done';
                        } elseif ($edit->isCustomerConfirmed()) {
                            $currentStep = 'customer_confirm';
                        } elseif ($edit->reedit_count > 0 && $edit->reedit_count >= $edit->sent_to_customer_count) {
                            $currentStep = 'reedit';
                        } elseif ($edit->sent_to_customer_count > 0) {
                            $currentStep = 'sent_to_customer';
                        }
                    @endphp
                    <tr @class(
                        $isFrameLine
                            ? 'border-l-[3px] border-l-teal-500 bg-teal-50/40 transition-colors dark:border-l-teal-400 dark:bg-teal-950/25'
                            : 'transition-colors even:bg-slate-50/50 hover:bg-slate-50/90 dark:even:bg-slate-800/25 dark:hover:bg-slate-800/35'
                    )>
                        @if($showBulkJobItemsBar)
                        <td class="px-1 py-2.5 align-middle text-center">
                            <input type="checkbox" name="edit_ids[]" value="{{ $edit->id }}" form="job-edits-bulk-form" class="job-bulk-row-cb h-4 w-4 rounded border-slate-300 text-[var(--color-studio-primary)] focus:ring-[var(--color-studio-primary)] dark:border-slate-600 dark:bg-slate-800">
                        </td>
                        @endif
                        <td class="px-2 py-2.5 align-top text-center">
                            <span class="inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded-md bg-slate-200/90 text-[11px] font-bold tabular-nums text-slate-600 ring-1 ring-slate-300/50 dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-600/60">{{ $loop->iteration }}</span>
                        </td>
                        <td class="px-3 py-2.5 align-top">
                            <div class="flex flex-col gap-1">
                                @if($isFrameLine)
                                    <span class="inline-flex w-fit items-center rounded-md bg-teal-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-teal-900 ring-1 ring-teal-200/80 dark:bg-teal-900/50 dark:text-teal-100 dark:ring-teal-700/60">Frame</span>
                                @endif
                                <span class="font-semibold leading-snug text-slate-900 dark:text-slate-100">{{ $edit->name }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 align-top text-slate-600 dark:text-slate-400">
                            <span class="line-clamp-2">{{ $edit->category_name ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-2.5 align-top text-slate-600 dark:text-slate-400">
                            <span class="line-clamp-2">{{ $edit->subcategory_name ?? '—' }}</span>
                        </td>
                        <td class="px-3 py-2.5 align-top text-sm">
                            @if($isFrameLine)
                                <span class="text-slate-400">—</span>
                            @elseif($edit->claimedByUser)
                                <span class="text-slate-700 dark:text-slate-300">{{ $edit->claimedByUser->name }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs align-top">
                            @if($isFrameLine)
                                <span class="text-teal-800 dark:text-teal-200/90 font-medium">Framing only</span>
                                <span class="text-slate-500 dark:text-slate-400 block mt-0.5">No editor / print</span>
                            @else
                            @php
                                $presets = [10, 20, 30, 45, 60];
                            @endphp
                            @if($canStartEditing)
                                <form action="{{ route('jobs.edits.claim', [$job, $edit]) }}" method="POST" class="space-y-1">
                                    @csrf
                                    <div class="flex flex-wrap items-center gap-1">
                                        <select name="estimated_minutes" required class="text-xs px-2 py-1 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
                                            <option value="" disabled {{ old('estimated_minutes', '') === '' || old('estimated_minutes') === null ? 'selected' : '' }}>Select minutes</option>
                                            @foreach($presets as $p)
                                                <option value="{{ $p }}" {{ old('estimated_minutes', '') == $p ? 'selected' : '' }}>{{ $p }} min</option>
                                            @endforeach
                                            <option value="custom">Custom</option>
                                        </select>
                                        <input type="number" name="custom_minutes" min="1" max="999" placeholder="min" class="w-14 text-xs px-2 py-1 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800" title="When Custom selected">
                                    </div>
                                    <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-green-600 text-white hover:bg-green-700 mt-1">Start editing</button>
                                </form>
                            @elseif($canChangeStatus && $canEdit)
                                @if($edit->estimated_minutes !== null)
                                    <span class="font-medium">{{ $edit->estimated_minutes }} min</span>
                                    @if($edit->estimated_minutes_at)
                                        <span class="text-slate-500 dark:text-slate-400 block">{{ $edit->estimated_minutes_at->format('M j, H:i') }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                                @if(auth()->user()->canSetOrChangeJobEditEstimatedMinutes($edit))
                                    <form action="{{ route('jobs.edits.estimated-minutes', [$job, $edit]) }}" method="POST" class="mt-1 flex flex-wrap items-center gap-1">
                                        @csrf
                                        <select name="estimated_minutes" required class="text-xs px-2 py-1 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
                                            <option value="" disabled {{ $edit->estimated_minutes === null ? 'selected' : '' }}>Select minutes</option>
                                            @foreach($presets as $p)
                                                <option value="{{ $p }}" {{ $edit->estimated_minutes == $p ? 'selected' : '' }}>{{ $p }} min</option>
                                            @endforeach
                                            <option value="custom" {{ $edit->estimated_minutes && ! in_array($edit->estimated_minutes, $presets, true) ? 'selected' : '' }}>Custom</option>
                                        </select>
                                        <input type="number" name="custom_minutes" min="1" max="999" value="{{ $edit->estimated_minutes && !in_array($edit->estimated_minutes, $presets) ? $edit->estimated_minutes : '' }}" placeholder="min" class="w-14 text-xs px-2 py-1 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800">
                                        <button type="submit" class="text-xs px-2 py-1 rounded bg-slate-200 dark:bg-slate-600 hover:bg-slate-300 dark:hover:bg-slate-500">Set</button>
                                    </form>
                                @elseif($edit->estimated_minutes !== null)
                                    <p class="mt-1 max-w-[14rem] text-[10px] leading-snug text-slate-500 dark:text-slate-400">Only Admin or Manager can change estimated time after it is set.</p>
                                @endif
                            @else
                                @if($edit->estimated_minutes !== null)
                                    <span class="font-medium">{{ $edit->estimated_minutes }} min</span>
                                    @if($edit->estimated_minutes_at)
                                        <span class="text-slate-500 dark:text-slate-400 block">{{ $edit->estimated_minutes_at->format('M j, H:i') }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            @endif
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs align-top">
                            @if($isFrameLine)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-teal-100 dark:bg-teal-900/30 text-teal-900 dark:text-teal-100">FRAME — framing only</span>
                            @else
                            <div class="flex flex-col gap-0.5">
                                <div>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                        {{ $currentStep === 'in_edit' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-100' }}">
                                        In Edit
                                    </span>
                                    @if($edit->claimed_at)
                                        <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->claimed_at->format('M j, H:i') }}</span>
                                    @endif
                                </div>
                                @if($edit->sent_to_customer_count > 0)
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                            {{ $currentStep === 'sent_to_customer' ? 'bg-blue-600 text-white dark:bg-blue-300 dark:text-blue-900' : 'bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200' }}">
                                            {{ $edit->sent_to_customer_count }}# Sent to Customer Review
                                        </span>
                                        @if($edit->sent_to_customer_at)
                                            <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->sent_to_customer_at->format('M j, H:i') }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if($edit->reedit_count > 0)
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                            {{ $currentStep === 'reedit' ? 'bg-amber-500 text-white dark:bg-amber-300 dark:text-amber-900' : 'bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200' }}">
                                            {{ $edit->reedit_count }}# Re-Edit
                                        </span>
                                        @if($edit->reedit_at)
                                            <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->reedit_at->format('M j, H:i') }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if($edit->isCustomerConfirmed())
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                            {{ $currentStep === 'customer_confirm' ? 'bg-green-600 text-white dark:bg-green-300 dark:text-green-900' : 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200' }}">
                                            Customer Confirm
                                        </span>
                                        <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->customer_confirmed_at->format('M j, H:i') }}</span>
                                    </div>
                                @endif
                                @if($edit->edit_done_at)
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                            {{ $currentStep === 'edit_done' ? 'bg-emerald-600 text-white dark:bg-emerald-300 dark:text-emerald-900' : 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200' }}">
                                            Edit Done
                                        </span>
                                        <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->edit_done_at->format('M j, H:i') }}</span>
                                    </div>
                                @endif
                            </div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 align-top">
                            @if($isFrameLine)
                                <span class="text-xs text-slate-400">—</span>
                            @elseif($canStartEditing)
                                <span class="text-slate-500 dark:text-slate-400 text-xs">Set est. time &amp; Start editing in Est. time column</span>
                            @elseif($canChangeStatus)
                                @if($canEdit)
                                    @if($canUseEditorActions)
                                        <form action="{{ route('jobs.edits.sent-to-customer', [$job, $edit]) }}" method="POST" class="inline-block mb-1 mr-1">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800/40">
                                                {{ $edit->sent_to_customer_count + 1 }}# Sent to Customer Review
                                            </button>
                                        </form>
                                        <form action="{{ route('jobs.edits.reedit', [$job, $edit]) }}" method="POST" class="inline-block mb-1 mr-1">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-800/40">
                                                {{ $edit->reedit_count + 1 }}# Re-Edit
                                            </button>
                                        </form>
                                        <form action="{{ route('jobs.edits.customer-confirm', [$job, $edit]) }}" method="POST" class="inline-block mb-1 mr-1">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-800/40">
                                                Customer Confirm
                                            </button>
                                        </form>
                                        @if(!$edit->edit_done_at)
                                            <form action="{{ route('jobs.edits.edit-done', [$job, $edit]) }}" method="POST" class="inline-block mb-1 mr-1">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 hover:bg-emerald-200 dark:hover:bg-emerald-800/40">
                                                    Edit Done
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <p class="text-xs text-amber-800 dark:text-amber-200/90 max-w-[14rem]">Set estimated time in the <span class="font-medium">Est. time</span> column first, then you can use these actions.</p>
                                    @endif
                                @endif
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs align-top">
                            @if($isFrameLine)
                                <span class="text-slate-500 dark:text-slate-400">Not applicable</span>
                            @elseif($edit->edit_done_at)
                                <div class="flex flex-col gap-0.5">
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                            {{ $edit->print_status === 'not_required' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-100' }}">
                                            Not required
                                        </span>
                                        @if($edit->print_status === 'not_required' && $edit->print_status_at)
                                            <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->print_status_at->format('M j, H:i') }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                            {{ $edit->print_status === 'pending' ? 'bg-amber-500 text-white dark:bg-amber-300 dark:text-amber-900' : 'bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200' }}">
                                            Pending
                                        </span>
                                        @if($edit->print_status === 'pending' && $edit->print_status_at)
                                            <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->print_status_at->format('M j, H:i') }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                            {{ $edit->print_status === 'sent_to_print' ? 'bg-blue-600 text-white dark:bg-blue-300 dark:text-blue-900' : 'bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200' }}">
                                            Sent to print
                                        </span>
                                        @if($edit->print_status === 'sent_to_print' && $edit->print_status_at)
                                            <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->print_status_at->format('M j, H:i') }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded
                                            {{ $edit->print_status === 'printed' ? 'bg-emerald-600 text-white dark:bg-emerald-300 dark:text-emerald-900' : 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200' }}">
                                            Printed
                                        </span>
                                        @if($edit->print_status === 'printed' && $edit->print_status_at)
                                            <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $edit->print_status_at->format('M j, H:i') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 align-top">
                            @if($isFrameLine)
                                <span class="text-xs text-slate-400">—</span>
                            @elseif($edit->edit_done_at && auth()->user()->canUpdatePrintStatus())
                                <div class="flex flex-wrap gap-1">
                                    <form action="{{ route('jobs.edits.print-status', [$job, $edit]) }}" method="POST" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="print_status" value="not_required">
                                        <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600">
                                            Not required
                                        </button>
                                    </form>
                                    <form action="{{ route('jobs.edits.print-status', [$job, $edit]) }}" method="POST" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="print_status" value="pending">
                                        <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-800/40">
                                            Pending
                                        </button>
                                    </form>
                                    <form action="{{ route('jobs.edits.print-status', [$job, $edit]) }}" method="POST" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="print_status" value="sent_to_print">
                                        <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 hover:bg-blue-200 dark:hover:bg-blue-800/40">
                                            Sent to print
                                        </button>
                                    </form>
                                    <form action="{{ route('jobs.edits.print-status', [$job, $edit]) }}" method="POST" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="print_status" value="printed">
                                        <button type="submit" class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 hover:bg-emerald-200 dark:hover:bg-emerald-800/40">
                                            Printed
                                        </button>
                                    </form>
                                </div>
                            @else
                                @if(!$edit->edit_done_at)
                                    <span class="text-xs text-slate-400" title="Mark Edit Done first">Edit must be done first</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs align-top">
                            @if($edit->framing_done_at)
                                <div>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-teal-600 text-white dark:bg-teal-300 dark:text-teal-900">Framing done</span>
                                    <span class="text-slate-500 dark:text-slate-400 block mt-0.5">{{ $edit->framing_done_at->format('M j, H:i') }}</span>
                                    @if(auth()->user()->isAdmin() || auth()->user()->isManager())
                                        <form action="{{ route('jobs.edits.framing-done-clear', [$job, $edit]) }}" method="POST" class="mt-1" onsubmit="return confirm('Clear framing done for this item?');">
                                            @csrf
                                            <button type="submit" class="text-xs text-slate-500 hover:text-red-600 underline">Clear</button>
                                        </form>
                                    @endif
                                </div>
                            @elseif((auth()->user()->isFraming() || auth()->user()->isAdmin()) && !$edit->framing_done_at)
                                @php
                                    $framingReady = $isFrameLine || $edit->print_status === \App\Models\JobEdit::PRINT_STATUS_PRINTED;
                                @endphp
                                <form action="{{ route('jobs.edits.framing-done', [$job, $edit]) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button
                                        type="submit"
                                        @unless($framingReady) disabled @endunless
                                        class="inline-flex items-center gap-1 text-sm px-3 py-1.5 rounded border border-teal-200 dark:border-teal-800 bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-200 hover:bg-teal-200 dark:hover:bg-teal-800/40 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none disabled:hover:bg-teal-100 dark:disabled:hover:bg-teal-900/30"
                                        title="{{ $framingReady ? 'Mark framing complete for this item' : 'Available after print status is Printed (not required for FRAME category)' }}"
                                    >
                                        Framing done
                                    </button>
                                </form>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showBulkJobItemsBar ? 12 : 11 }}" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                        <span class="mx-auto inline-flex max-w-md flex-col items-center gap-2 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-6 py-5 dark:border-slate-600 dark:bg-slate-800/40">
                            @include('components.icons', ['name' => 'folder', 'class' => 'w-8 h-8 text-slate-400 dark:text-slate-500'])
                            <span class="font-medium text-slate-600 dark:text-slate-300">No line items to show</span>
                            <span class="text-xs leading-relaxed">Unblock categories or products in settings if items are hidden.</span>
                        </span>
                    </td></tr>
                @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    @if($showBulkJobItemsBar)
        <form id="job-edits-bulk-form" action="{{ route('jobs.edits.bulk', $job) }}" method="POST" class="fixed bottom-0 left-0 right-0 z-30 border-t border-slate-200/90 bg-white/95 px-3 py-2.5 shadow-[0_-6px_24px_rgba(0,0,0,0.08)] backdrop-blur-md dark:border-slate-700 dark:bg-slate-900/95 dark:shadow-[0_-6px_24px_rgba(0,0,0,0.35)]">
            @csrf
            <div class="mx-auto flex max-w-6xl flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3">
                <div class="flex min-w-0 flex-1 flex-col gap-1 text-sm text-slate-700 dark:text-slate-200">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">Bulk</span>
                        <span id="job-bulk-count" class="tabular-nums font-medium text-slate-600 dark:text-slate-300">0 selected</span>
                    </div>
                    <p class="max-w-xl text-[11px] leading-snug text-slate-500 dark:text-slate-400">Applies only to lines that qualify (e.g. print after Edit Done; editor steps need est. time and a claimed line unless you are Admin). Estimated minutes: each line can be set once by editors; only Admin/Manager can change them later (including bulk). Other selected lines are skipped.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400 sm:text-sm">
                        <span class="shrink-0">Action</span>
                        <select name="action" id="job-bulk-action" class="min-w-[11rem] rounded-lg border border-[var(--color-studio-border)] bg-white px-2.5 py-2 text-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-100">
                            @if($bulkCanPrint)
                                <option value="print_status">Set print status…</option>
                            @endif
                            @if($bulkCanTime && $bulkHasNonFrameLines)
                                <option value="set_estimated_minutes">Set estimated time</option>
                                <option value="claim_start">Claim &amp; start editing</option>
                            @endif
                            @if($bulkCanEditorPipeline && $bulkHasNonFrameLines)
                                <option value="sent_to_customer">Editor: sent to customer (next #)</option>
                                <option value="reedit">Editor: re-edit (next #)</option>
                                <option value="customer_confirm">Editor: customer confirm</option>
                                <option value="edit_done">Editor: mark edit done</option>
                            @endif
                            @if($bulkCanFramingDone)
                                <option value="framing_done">Mark framing done</option>
                            @endif
                            @if($bulkCanFramingClear)
                                <option value="framing_clear">Clear framing done</option>
                            @endif
                        </select>
                    </label>
                    <label id="job-bulk-print-wrap" class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400 sm:text-sm">
                        <span class="shrink-0">Print</span>
                        <select name="print_status" id="job-bulk-print-status" class="min-w-[10rem] rounded-lg border border-[var(--color-studio-border)] bg-white px-2.5 py-2 text-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-100">
                            <option value="" disabled selected>Choose status</option>
                            <option value="not_required">Not required</option>
                            <option value="pending">Pending</option>
                            <option value="sent_to_print">Sent to print</option>
                            <option value="printed">Printed</option>
                        </select>
                    </label>
                    @if($bulkCanTime && $bulkHasNonFrameLines)
                    <div id="job-bulk-time-wrap" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400 sm:text-sm">
                            <span class="shrink-0">Minutes</span>
                            <select name="bulk_estimated_mode" id="job-bulk-estimated-mode" class="min-w-[9rem] rounded-lg border border-[var(--color-studio-border)] bg-white px-2.5 py-2 text-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-100">
                                <option value="" disabled selected>Select…</option>
                                @foreach([10, 20, 30, 45, 60] as $preset)
                                    <option value="{{ $preset }}">{{ $preset }} min</option>
                                @endforeach
                                <option value="custom">Custom</option>
                            </select>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-400 sm:text-sm">
                            <span class="shrink-0">Custom</span>
                            <input type="number" name="bulk_custom_minutes" id="job-bulk-custom-minutes" min="1" max="999" placeholder="min" class="w-20 rounded-lg border border-[var(--color-studio-border)] bg-white px-2 py-2 text-sm tabular-nums dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-100" disabled title="When Custom is selected">
                        </label>
                    </div>
                    @endif
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="job-bulk-clear" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">Clear</button>
                        <button type="submit" id="job-bulk-apply" class="rounded-lg bg-[var(--color-studio-primary)] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-40" disabled>Apply</button>
                    </div>
                </div>
            </div>
        </form>
        <script>
            (function () {
                var form = document.getElementById('job-edits-bulk-form');
                if (!form) return;
                var cbs = function () { return document.querySelectorAll('.job-bulk-row-cb'); };
                var countEl = document.getElementById('job-bulk-count');
                var applyBtn = document.getElementById('job-bulk-apply');
                var clearBtn = document.getElementById('job-bulk-clear');
                var selectAll = document.getElementById('job-bulk-select-all');
                var actionSel = document.getElementById('job-bulk-action');
                var printWrap = document.getElementById('job-bulk-print-wrap');
                var printSel = document.getElementById('job-bulk-print-status');
                var timeWrap = document.getElementById('job-bulk-time-wrap');
                var modeSel = document.getElementById('job-bulk-estimated-mode');
                var customIn = document.getElementById('job-bulk-custom-minutes');

                function selectedCount() {
                    var n = 0;
                    cbs().forEach(function (cb) { if (cb.checked) n++; });
                    return n;
                }
                function syncCount() {
                    var n = selectedCount();
                    if (countEl) countEl.textContent = n + ' selected';
                    if (applyBtn) applyBtn.disabled = n === 0;
                    if (selectAll) {
                        var all = cbs();
                        selectAll.indeterminate = n > 0 && n < all.length;
                        selectAll.checked = all.length > 0 && n === all.length;
                    }
                }
                function syncCustomMinutesEnabled() {
                    if (!modeSel || !customIn) return;
                    var custom = modeSel.value === 'custom';
                    customIn.disabled = !custom;
                    if (!custom) customIn.value = '';
                }
                function syncBulkFieldVisibility() {
                    if (!actionSel) return;
                    var a = actionSel.value;
                    var isPrint = a === 'print_status';
                    var isTime = a === 'set_estimated_minutes' || a === 'claim_start';
                    if (printWrap && printSel) {
                        printWrap.classList.toggle('hidden', !isPrint);
                        printSel.disabled = !isPrint;
                        if (!isPrint) printSel.selectedIndex = 0;
                    }
                    if (timeWrap && modeSel) {
                        timeWrap.classList.toggle('hidden', !isTime);
                        modeSel.disabled = !isTime;
                        if (!isTime) {
                            modeSel.selectedIndex = 0;
                            if (customIn) customIn.value = '';
                        }
                        syncCustomMinutesEnabled();
                    }
                }
                cbs().forEach(function (cb) { cb.addEventListener('change', syncCount); });
                if (selectAll) {
                    selectAll.addEventListener('change', function () {
                        var on = selectAll.checked;
                        cbs().forEach(function (cb) { cb.checked = on; });
                        syncCount();
                    });
                }
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        cbs().forEach(function (cb) { cb.checked = false; });
                        if (selectAll) selectAll.checked = false;
                        syncCount();
                    });
                }
                if (actionSel) actionSel.addEventListener('change', syncBulkFieldVisibility);
                if (modeSel) modeSel.addEventListener('change', syncCustomMinutesEnabled);
                syncBulkFieldVisibility();
                syncCount();

                form.addEventListener('submit', function (e) {
                    if (selectedCount() === 0) {
                        e.preventDefault();
                        return;
                    }
                    if (actionSel && actionSel.value === 'print_status') {
                        if (!printSel || !printSel.value) {
                            e.preventDefault();
                            return;
                        }
                    }
                    if (actionSel && (actionSel.value === 'set_estimated_minutes' || actionSel.value === 'claim_start')) {
                        if (!modeSel) {
                            e.preventDefault();
                            return;
                        }
                        var mv = modeSel.value;
                        if (!mv) {
                            e.preventDefault();
                            return;
                        }
                        if (mv === 'custom' && (!customIn || !customIn.value || parseInt(customIn.value, 10) < 1)) {
                            e.preventDefault();
                            return;
                        }
                    }
                    if (actionSel && actionSel.value === 'framing_clear') {
                        if (!confirm('Clear framing done for the selected lines?')) e.preventDefault();
                    }
                });
            })();
        </script>
    @endif

    @if($blockedEdits->count() > 0)
        <details class="mt-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 rounded-lg overflow-hidden">
            <summary class="p-3 cursor-pointer text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                Show blocked items ({{ $blockedEdits->count() }}) – categories or products you chose to hide
            </summary>
            <div class="border-t border-slate-200 dark:border-slate-600">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 dark:bg-slate-800">
                        <tr>
                            <th class="text-left p-3">Item (name)</th>
                            <th class="text-left p-3">Category</th>
                            <th class="text-left p-3">Subcategory</th>
                            <th class="text-left p-3">Edit status</th>
                            <th class="text-left p-3">Print status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($blockedEdits as $edit)
                            <tr class="border-t border-slate-200 dark:border-slate-600">
                                <td class="p-3 font-medium text-slate-500">{{ $edit->name }}</td>
                                <td class="p-3 text-slate-400">{{ $edit->category_name ?? '—' }}</td>
                                <td class="p-3 text-slate-400">{{ $edit->subcategory_name ?? '—' }}</td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-200 dark:bg-slate-700">{{ $edit->edit_status }}</span></td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-200 dark:bg-slate-700">{{ $edit->print_status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    {{-- Line snapshot + Activity log (fresh from DB each load; snapshot matches workflow columns above) --}}
    <div class="mt-10 grid lg:grid-cols-2 gap-6 items-start">
        <div class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden order-2 lg:order-1">
            <div class="px-4 py-3 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] flex flex-wrap items-center justify-between gap-2 bg-slate-50/80 dark:bg-slate-800/40">
                <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-5 h-5 text-[var(--color-studio-primary)]'])
                    Line snapshot
                </h2>
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $visibleEdits->count() }} line(s)</span>
            </div>
            <div class="max-h-[22rem] overflow-y-auto overflow-x-auto">
                @if($visibleEdits->isEmpty())
                    <p class="p-4 text-sm text-slate-500">No items to show.</p>
                @else
                    <table class="w-full text-sm min-w-[32rem]">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 sticky top-0 z-10">
                            <tr>
                                <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-300">Item</th>
                                <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-300">Category</th>
                                <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-300">State</th>
                                <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-300">Print</th>
                                <th class="text-left p-3 font-medium text-slate-600 dark:text-slate-300">Framing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($visibleEdits as $edit)
                                <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                    <td class="p-3 font-medium text-slate-800 dark:text-slate-100">{{ $edit->name }}</td>
                                    <td class="p-3 text-slate-600 dark:text-slate-400">{{ $edit->category_name ?? '—' }}</td>
                                    <td class="p-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-md text-xs font-medium bg-violet-50 text-violet-900 dark:bg-violet-900/30 dark:text-violet-100 border border-violet-100 dark:border-violet-800">
                                            {{ $snapshotLineState($edit) }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span class="text-xs font-medium
                                            @if($edit->print_status === 'printed') text-emerald-700 dark:text-emerald-300
                                            @elseif($edit->print_status === 'sent_to_print') text-blue-700 dark:text-blue-300
                                            @elseif($edit->print_status === 'pending') text-amber-700 dark:text-amber-300
                                            @else text-slate-600 dark:text-slate-400
                                            @endif">{{ $snapshotPrintLabel($edit) }}</span>
                                    </td>
                                    <td class="p-3 text-xs text-slate-600 dark:text-slate-400">{{ $snapshotFramingLabel($edit) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <p class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400 border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">Updates every time you reload or after an action. FRAME lines only use framing.</p>
        </div>

        <div class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] shadow-sm overflow-hidden order-1 lg:order-2">
            <div class="px-4 py-3 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] flex flex-wrap items-center justify-between gap-2 bg-slate-50/80 dark:bg-slate-800/40">
                <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    @include('components.icons', ['name' => 'document-check', 'class' => 'w-5 h-5 text-[var(--color-studio-primary)]'])
                    Activity log
                </h2>
                <span class="text-xs text-slate-500 dark:text-slate-400">Newest first</span>
            </div>
            <div class="max-h-[28rem] overflow-y-auto">
                @forelse($activityLogByDay as $day => $logs)
                    <div class="px-4 py-2 bg-slate-100/90 dark:bg-slate-800/80 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] sticky top-0 z-10">
                        {{ \Carbon\Carbon::parse($day)->format('l, M j, Y') }}
                    </div>
                    <ul class="relative pl-2">
                        @foreach($logs as $log)
                            <li class="relative flex gap-3 px-4 py-3 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] last:border-0">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[var(--color-studio-primary)] ring-4 ring-[var(--color-studio-primary)]/15" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1 pb-1">
                                    <div class="flex flex-wrap items-center gap-2 gap-y-1">
                                        <time class="text-xs tabular-nums font-medium text-slate-600 dark:text-slate-300 shrink-0" datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->timezone(config('app.timezone'))->toIso8601String() }}">{{ $log->created_at->timezone(config('app.timezone'))->format('M j, Y') }} · {{ $log->created_at->timezone(config('app.timezone'))->format('H:i:s') }}</time>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide border {{ $activityActionClasses($log->action) }}">{{ str_replace('_', ' ', $log->action) }}</span>
                                        @if($log->user)
                                            <span class="text-xs text-slate-600 dark:text-slate-300"><span class="text-slate-400">by</span> {{ $log->user->name }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1.5 text-sm text-slate-700 dark:text-slate-200 leading-snug">{{ $log->description ?? '—' }}</p>
                                </div>
                                @if(auth()->user()->isAdmin())
                                    <a href="{{ route('activity-log.show', $log) }}" class="shrink-0 self-start text-xs px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-600 text-[var(--color-studio-primary)] hover:bg-slate-50 dark:hover:bg-slate-700/50 font-medium">View</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @empty
                    <p class="p-6 text-sm text-slate-500 text-center">No activity recorded for this job yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
