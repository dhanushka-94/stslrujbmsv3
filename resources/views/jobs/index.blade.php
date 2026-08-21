@extends('layouts.app')

@section('title', 'Jobs')

@php
    $statusBadgeClass = [
        'new' => 'bg-blue-100 text-blue-900 ring-blue-200/80 dark:bg-blue-900/45 dark:text-blue-100 dark:ring-blue-700/50',
        'assigned' => 'bg-indigo-100 text-indigo-900 ring-indigo-200/80 dark:bg-indigo-900/45 dark:text-indigo-100 dark:ring-indigo-700/50',
        'in_progress' => 'bg-amber-100 text-amber-950 ring-amber-200/80 dark:bg-amber-900/40 dark:text-amber-100 dark:ring-amber-700/50',
        'completed' => 'bg-green-100 text-green-900 ring-green-200/80 dark:bg-green-900/40 dark:text-green-100 dark:ring-green-700/50',
        'delivered' => 'bg-emerald-100 text-emerald-900 ring-emerald-200/80 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-700/50',
    ];
    $activeSection = $section ?? 'ongoing';
@endphp

@section('content')
    <section class="mb-6 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] sm:mb-8">
        <div class="relative px-5 py-5 sm:px-6 sm:py-6 bg-gradient-to-br from-[var(--color-studio-primary)]/[0.08] via-transparent to-slate-50/90 dark:from-[var(--color-studio-accent)]/[0.1] dark:to-slate-900/45">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Operations</p>
                    <h1 class="mt-1 flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/90 text-[var(--color-studio-primary)] shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800/90 dark:text-[var(--color-studio-accent)] dark:ring-slate-600 sm:h-11 sm:w-11">
                            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-6 h-6'])
                        </span>
                        <span class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">Jobs</span>
        </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        Filter by reference, switch sections for workflow stage, and open a job to work line items.
                    </p>
                </div>
                @if(!auth()->user()->isDeliveryViewOnly())
                    <a href="{{ route('jobs.live') }}" class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-lg border border-[var(--color-studio-border)] bg-white/90 px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-white dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/90">
                        @include('components.icons', ['name' => 'bolt', 'class' => 'w-4 h-4 text-amber-600 dark:text-amber-400'])
                        Job Pool
                    </a>
                @endif
            </div>
        </div>
    </section>

    @php
        $jobsListSections = $jobsListSections ?? ['ongoing', 'edit_done', 'print_done', 'framing_done', 'completed', 'delivered', 'dismissed'];
    @endphp
    @if(!empty($deliveryJobsListOnly))
        <div class="mb-5 rounded-lg border border-emerald-200/90 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-800/50 dark:bg-emerald-950/30 dark:text-emerald-100">
            <span class="font-semibold">Completed jobs only</span> — jobs ready to mark as delivered. Other workflow stages are hidden for the Delivery role.
        </div>
    @endif
    @if(auth()->user()->isPrinter() && empty($deliveryJobsListOnly))
        <div class="mb-5 rounded-lg border border-blue-200/90 bg-blue-50/80 px-4 py-3 text-sm text-blue-950 dark:border-blue-800/50 dark:bg-blue-950/30 dark:text-blue-100">
            <span class="font-semibold">Print workflow only</span> — Job Pool and this list show jobs with edit+print or print-only categories (e.g. Media Print), not framing-only lines.
        </div>
    @endif
    @if(empty($deliveryJobsListOnly))
    <nav class="mb-6 flex flex-wrap gap-1.5 rounded-xl border border-[var(--color-studio-border)] bg-slate-50/90 p-1.5 shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/40 sm:gap-2 sm:p-2" aria-label="Job sections">
        <a href="{{ route('jobs.index', array_filter(['section' => 'ongoing', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" @class([
            'inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition sm:flex-none sm:px-4 sm:text-sm',
            'bg-[var(--color-studio-primary)] text-white shadow-sm ring-1 ring-black/5 dark:bg-[var(--color-studio-accent)] dark:text-slate-900 dark:ring-white/10' => $activeSection === 'ongoing',
            'text-slate-600 hover:bg-white/80 dark:text-slate-300 dark:hover:bg-slate-700/60' => $activeSection !== 'ongoing',
        ])>
            @include('components.icons', ['name' => 'clock', 'class' => 'w-4 h-4 shrink-0'])
            <span>Ongoing</span>
            <span class="tabular-nums opacity-80">({{ $ongoingCount ?? 0 }})</span>
        </a>
        @if(in_array('pos_updated', $jobsListSections, true))
        <a href="{{ route('jobs.index', array_filter(['section' => 'pos_updated', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" @class([
            'inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition sm:flex-none sm:px-4 sm:text-sm',
            'bg-orange-600 text-white shadow-sm ring-1 ring-black/5 dark:bg-orange-500 dark:text-orange-950' => $activeSection === 'pos_updated',
            'text-slate-600 hover:bg-white/80 dark:text-slate-300 dark:hover:bg-slate-700/60' => $activeSection !== 'pos_updated',
        ])>
            @include('components.icons', ['name' => 'exclamation-triangle', 'class' => 'w-4 h-4 shrink-0'])
            <span>POS updated</span>
            <span class="tabular-nums opacity-80">(<span id="pos-updated-tab-count">{{ $posUpdatedCount ?? 0 }}</span>)</span>
        </a>
        @endif
        @if(in_array('edit_done', $jobsListSections, true))
        <a href="{{ route('jobs.index', array_filter(['section' => 'edit_done', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" @class([
            'inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition sm:flex-none sm:px-4 sm:text-sm',
            'bg-[var(--color-studio-primary)] text-white shadow-sm ring-1 ring-black/5 dark:bg-[var(--color-studio-accent)] dark:text-slate-900 dark:ring-white/10' => $activeSection === 'edit_done',
            'text-slate-600 hover:bg-white/80 dark:text-slate-300 dark:hover:bg-slate-700/60' => $activeSection !== 'edit_done',
        ])>
            @include('components.icons', ['name' => 'pencil-square', 'class' => 'w-4 h-4 shrink-0'])
            <span>Edit done</span>
            <span class="tabular-nums opacity-80">({{ $editDoneCount ?? 0 }})</span>
        </a>
        @endif
        @if(in_array('print_done', $jobsListSections, true))
        <a href="{{ route('jobs.index', array_filter(['section' => 'print_done', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" @class([
            'inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition sm:flex-none sm:px-4 sm:text-sm',
            'bg-[var(--color-studio-primary)] text-white shadow-sm ring-1 ring-black/5 dark:bg-[var(--color-studio-accent)] dark:text-slate-900 dark:ring-white/10' => $activeSection === 'print_done',
            'text-slate-600 hover:bg-white/80 dark:text-slate-300 dark:hover:bg-slate-700/60' => $activeSection !== 'print_done',
        ])>
            @include('components.icons', ['name' => 'arrow-path', 'class' => 'w-4 h-4 shrink-0'])
            <span>Print done</span>
            <span class="tabular-nums opacity-80">({{ $printDoneCount ?? 0 }})</span>
        </a>
        @endif
        @if(in_array('framing_done', $jobsListSections, true))
        <a href="{{ route('jobs.index', array_filter(['section' => 'framing_done', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" @class([
            'inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition sm:flex-none sm:px-4 sm:text-sm',
            'bg-[var(--color-studio-primary)] text-white shadow-sm ring-1 ring-black/5 dark:bg-[var(--color-studio-accent)] dark:text-slate-900 dark:ring-white/10' => $activeSection === 'framing_done',
            'text-slate-600 hover:bg-white/80 dark:text-slate-300 dark:hover:bg-slate-700/60' => $activeSection !== 'framing_done',
        ])>
            @include('components.icons', ['name' => 'sparkles', 'class' => 'w-4 h-4 shrink-0'])
            <span>Framing done</span>
            <span class="tabular-nums opacity-80">({{ $framingDoneCount ?? 0 }})</span>
        </a>
        @endif
        <a href="{{ route('jobs.index', array_filter(['section' => 'completed', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" @class([
            'inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition sm:flex-none sm:px-4 sm:text-sm',
            'bg-[var(--color-studio-primary)] text-white shadow-sm ring-1 ring-black/5 dark:bg-[var(--color-studio-accent)] dark:text-slate-900 dark:ring-white/10' => $activeSection === 'completed',
            'text-slate-600 hover:bg-white/80 dark:text-slate-300 dark:hover:bg-slate-700/60' => $activeSection !== 'completed',
        ])>
            @include('components.icons', ['name' => 'check-circle', 'class' => 'w-4 h-4 shrink-0'])
            <span>Complete</span>
            <span class="tabular-nums opacity-80">({{ $completedCount ?? 0 }})</span>
        </a>
        <a href="{{ route('jobs.index', array_filter(['section' => 'delivered', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" @class([
            'inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition sm:flex-none sm:px-4 sm:text-sm',
            'bg-[var(--color-studio-primary)] text-white shadow-sm ring-1 ring-black/5 dark:bg-[var(--color-studio-accent)] dark:text-slate-900 dark:ring-white/10' => $activeSection === 'delivered',
            'text-slate-600 hover:bg-white/80 dark:text-slate-300 dark:hover:bg-slate-700/60' => $activeSection !== 'delivered',
        ])>
            @include('components.icons', ['name' => 'arrow-down-tray', 'class' => 'w-4 h-4 shrink-0'])
            <span>Delivered</span>
            <span class="tabular-nums opacity-80">({{ $deliveredCount ?? 0 }})</span>
        </a>
        <a href="{{ route('jobs.index', array_filter(['section' => 'dismissed', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" @class([
            'inline-flex flex-1 min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-xs font-semibold transition sm:flex-none sm:px-4 sm:text-sm',
            'bg-amber-600 text-white shadow-sm ring-1 ring-black/5 dark:bg-amber-500 dark:text-amber-950' => $activeSection === 'dismissed',
            'text-slate-600 hover:bg-white/80 dark:text-slate-300 dark:hover:bg-slate-700/60' => $activeSection !== 'dismissed',
        ])>
            @include('components.icons', ['name' => 'archive', 'class' => 'w-4 h-4 shrink-0'])
            <span>Dismissed</span>
            <span class="tabular-nums opacity-80">({{ $dismissedCount ?? 0 }})</span>
        </a>
    </nav>
    @endif

    @if(in_array('pos_updated', $jobsListSections, true) && $activeSection !== 'pos_updated')
        <div id="pos-updated-banner" class="mb-5 {{ (int) ($posUpdatedCount ?? 0) > 0 ? '' : 'hidden' }} rounded-lg border border-orange-200/90 bg-orange-50/80 px-4 py-3 text-sm text-orange-950 dark:border-orange-800/50 dark:bg-orange-950/30 dark:text-orange-100">
            <span class="font-semibold"><span id="pos-updated-banner-count">{{ (int) ($posUpdatedCount ?? 0) }}</span> POS bill(s) updated</span>
            after the job was opened.
            <a href="{{ route('jobs.index', array_filter(['section' => 'pos_updated', 'ref' => $ref, 'category' => $categoryFilter ?? null])) }}" class="ml-1 font-semibold underline">Open POS updated</a>
        </div>
    @endif
    @if($activeSection === 'pos_updated')
        <div class="mb-5 rounded-lg border border-orange-200/90 bg-orange-50/80 px-4 py-3 text-sm text-orange-950 dark:border-orange-800/50 dark:bg-orange-950/30 dark:text-orange-100">
            <span class="font-semibold">POS bill changed after this job was opened.</span>
            Open a job to see <strong>On job now</strong>, <strong>On POS now</strong>, and <strong>Pending to apply</strong> side by side.
            Use <strong>Apply pending updates</strong> only when you want the job lines to match the latest POS bill (progress is kept for matching lines).
        </div>
    @endif
    @if($activeSection === 'dismissed')
        <div class="mb-5 rounded-lg border border-amber-200/90 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-100">
            <span class="font-medium">Dismissed jobs</span> are hidden from Ongoing. Use <strong>Restore</strong> on a row to send it back to the main list.
        </div>
    @endif
    @if($activeSection === 'edit_done')
        <div class="mb-5 rounded-lg border border-indigo-200/90 bg-indigo-50/80 px-4 py-3 text-sm text-indigo-950 dark:border-indigo-800/50 dark:bg-indigo-950/30 dark:text-indigo-100">
            <strong>All photo lines edit-done, print waiting:</strong> every non-frame line is <strong>Edit done</strong> and at least one is still waiting on print. <strong>Or partial edit:</strong> at least one photo line is edit-done while another photo line is still being edited. <strong>Remaining</strong> counts open edits, print, and framing like other tabs; <strong>Line items</strong> shows each line’s status.
        </div>
    @endif
    @if($activeSection === 'print_done')
        <div class="mb-5 rounded-lg border border-sky-200/90 bg-sky-50/80 px-4 py-3 text-sm text-sky-950 dark:border-sky-800/50 dark:bg-sky-950/30 dark:text-sky-100">
            <strong>Edit / print lines:</strong> every edit+print category line is already <strong>Edit done</strong> with print <strong>Printed</strong> or <strong>Not required</strong>. Remaining work is usually <strong>framing</strong> on framing-only category lines (see <strong>Remaining</strong>).
        </div>
    @endif
    @if($activeSection === 'framing_done')
        <div class="mb-5 rounded-lg border border-teal-200/90 bg-teal-50/80 px-4 py-3 text-sm text-teal-950 dark:border-teal-800/50 dark:bg-teal-950/30 dark:text-teal-100">
            At least one <strong>framing-only</strong> category line is <strong>Done</strong>, and the job still has <strong>edit/print</strong> lines needing edit and/or print. <strong>Remaining</strong> breaks down done vs edit/print.
    </div>
    @endif

    <div class="mb-4 rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] px-4 py-3 shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <x-workflow-legend
            :active-category="$categoryFilter ?? null"
            filter-route="jobs.index"
            :filter-params="['section' => $activeSection, 'ref' => $ref ?? null]"
        />
    </div>

    <form method="GET" class="mb-6 flex flex-col gap-3 rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] sm:flex-row sm:flex-wrap sm:items-end sm:gap-4">
        <input type="hidden" name="section" value="{{ $activeSection }}">
        @if(filled($categoryFilter ?? null))
            <input type="hidden" name="category" value="{{ $categoryFilter }}">
        @endif
        <div class="min-w-0 flex-1 sm:max-w-xs">
            <label for="jobs-filter-ref" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Reference</label>
        <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500">
                    @include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-4 h-4'])
                </span>
                <input id="jobs-filter-ref" type="text" name="ref" value="{{ $ref ?? '' }}" placeholder="Search ref…" autocomplete="off"
                    class="w-full rounded-lg border border-[var(--color-studio-border)] bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[var(--color-studio-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-studio-primary)]/20 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-[var(--color-studio-accent)] dark:focus:ring-[var(--color-studio-accent)]/20">
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[var(--color-studio-primary)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-studio-primary)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">
                @include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-4 h-4'])
                Apply filter
            </button>
            @if(filled($ref ?? null) || filled($categoryFilter ?? null))
                <a href="{{ route('jobs.index', ['section' => $activeSection]) }}" class="inline-flex items-center justify-center rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">Clear</a>
            @endif
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[72rem] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-[var(--color-studio-border)] bg-slate-50/95 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/70 dark:text-slate-400">
                        <th class="whitespace-nowrap px-4 py-3">Ref</th>
                        <th class="min-w-[10rem] px-4 py-3">Staff note</th>
                        <th class="min-w-[14rem] px-4 py-3">Line items</th>
                        <th class="whitespace-nowrap px-4 py-3">Status</th>
                        <th class="whitespace-nowrap px-4 py-3">Due</th>
                        <th class="min-w-[7rem] px-4 py-3">Editors</th>
                        @if(in_array($activeSection, ['edit_done', 'print_done', 'framing_done'], true))
                            <th class="min-w-[9rem] px-4 py-3">Remaining</th>
                        @endif
                        <th class="min-w-[9rem] whitespace-nowrap px-4 py-3">POS / opened</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                @forelse($jobs as $job)
                    @php
                            $posDueRaw = ($job->source_id && isset($saleDueRawBySourceId[(string) $job->source_id]))
                                ? $saleDueRawBySourceId[(string) $job->source_id]
                                : null;
                            $dueAt = \App\Models\Job::resolveDueFromStoredAndPos($job->due_date, $posDueRaw);
                            $hasDue = $dueAt !== null;
                            $isOverdue = $hasDue && \App\Models\Job::isDueCarbonPastDeadline($dueAt, $job->isCompleted());
                        @endphp
                        <tr @class([
                            'transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-800/50',
                            'bg-rose-50/50 dark:bg-rose-950/20' => $isOverdue,
                        ])>
                            <td class="whitespace-nowrap px-4 py-3 font-mono font-medium text-slate-900 dark:text-slate-100">{{ $job->ref_number }}</td>
                            <td class="min-w-[10rem] max-w-xs px-4 py-3 align-top">
                                @php
                                    $staffNote = ($job->source_id && isset($saleStaffNoteBySourceId[(string) $job->source_id]))
                                        ? $saleStaffNoteBySourceId[(string) $job->source_id]
                                        : null;
                                @endphp
                                @if(filled($staffNote))
                                    <x-pos-staff-note :note="$staffNote" compact />
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="min-w-[12rem] max-w-md px-4 py-3 align-top">
                                @php
                                    $visibleEdits = $job->edits->filter(fn (\App\Models\JobEdit $e) => auth()->user()->isJobLineVisibleToMe($e))->values();
                                @endphp
                                @if($visibleEdits->isEmpty())
                                    <span class="text-xs text-slate-400">{{ $job->edits->isEmpty() ? 'No line items' : 'No tracked line items' }}</span>
                                @else
                                    <div class="max-h-64 overflow-y-auto overflow-x-hidden rounded-md border border-slate-200/80 bg-slate-50/80 p-2 shadow-inner dark:border-slate-600 dark:bg-slate-800/40">
                                        <ul class="m-0 list-none space-y-2 p-0">
                                            @foreach($visibleEdits as $edit)
                                                <x-workflow-line-item
                                                    :index="$loop->iteration"
                                                    :name="$edit->name ?: '—'"
                                                    :category-name="$edit->category_name"
                                                    :status-line="$edit->workflowStatusLineForJobList()"
                                                    compact
                                                />
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @php $sc = $statusBadgeClass[$job->status] ?? 'bg-slate-100 text-slate-800 ring-slate-200/80 dark:bg-slate-700 dark:text-slate-100 dark:ring-slate-600'; @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $sc }}">{{ $job->statusLabel() }}</span>
                                @if($activeSection === 'pos_updated' && isset($posUpdatedMetaByJobId[$job->id]))
                                    @php $meta = $posUpdatedMetaByJobId[$job->id]; @endphp
                                    <p class="mt-1 max-w-[16rem] text-[11px] leading-snug text-orange-700 dark:text-orange-300">
                                        {{ $meta['summary'] }}
                                        <span class="block tabular-nums text-orange-600/90 dark:text-orange-400/90">Job {{ $meta['job_line_count'] }} → POS {{ $meta['pos_line_count'] }} · {{ $meta['pending_count'] ?? 0 }} pending</span>
                                    </p>
                                    @php
                                        $tzMeta = config('app.timezone');
                                        $posTsMeta = ($job->source_id && isset(($salePosTimestampsBySourceId ?? [])[(string) $job->source_id]))
                                            ? $salePosTimestampsBySourceId[(string) $job->source_id]
                                            : ['created' => null, 'updated' => null];
                                        $billUpMeta = null;
                                        $billCrMeta = null;
                                        if (! empty($posTsMeta['updated'])) {
                                            try { $billUpMeta = \Illuminate\Support\Carbon::parse($posTsMeta['updated'])->timezone($tzMeta); } catch (\Throwable) { $billUpMeta = null; }
                                        }
                                        if (! empty($posTsMeta['created'])) {
                                            try { $billCrMeta = \Illuminate\Support\Carbon::parse($posTsMeta['created'])->timezone($tzMeta); } catch (\Throwable) { $billCrMeta = null; }
                                        }
                    @endphp
                                    <p class="mt-1 max-w-[16rem] text-[11px] tabular-nums leading-snug text-orange-800 dark:text-orange-200">
                                        <span class="font-semibold">Bill updated:</span> {{ $billUpMeta ? $billUpMeta->format('M j, Y g:i A') : '—' }}
                                        <span class="mt-0.5 block"><span class="font-semibold">Bill created:</span> {{ $billCrMeta ? $billCrMeta->format('M j, Y g:i A') : '—' }}</span>
                                    </p>
                                    <div class="mt-2 max-w-[18rem] space-y-1 text-[11px] leading-snug">
                                        @foreach(array_slice($meta['added'] ?? [], 0, 3) as $line)
                                            <p class="text-emerald-700 dark:text-emerald-400">+ {{ $line['name'] }}</p>
                                        @endforeach
                                        @foreach(array_slice($meta['removed'] ?? [], 0, 3) as $line)
                                            <p class="text-rose-700 dark:text-rose-400">− {{ $line['name'] }}</p>
                                        @endforeach
                                        @foreach(array_slice($meta['changed'] ?? [], 0, 2) as $chg)
                                            <p class="text-amber-800 dark:text-amber-300">~ {{ $chg['from']['name'] }} → {{ $chg['to']['name'] }}</p>
                                        @endforeach
                                    </div>
                                @endif
                        </td>
                            <td class="whitespace-nowrap px-4 py-3">
                            @if($hasDue)
                                    <span @class([
                                        'block tabular-nums',
                                        'font-semibold text-red-600 dark:text-red-400' => $isOverdue,
                                        'text-slate-700 dark:text-slate-200' => ! $isOverdue,
                                    ])>
                                        {{ $dueAt->format('M d, Y') }}
                                </span>
                                    @if(\App\Models\Job::dueCarbonHasAssignedTime($dueAt))
                                        <span @class([
                                            'block text-xs tabular-nums',
                                            'text-red-600/90 dark:text-red-400/90' => $isOverdue,
                                            'text-slate-500 dark:text-slate-400' => ! $isOverdue,
                                        ])>{{ $dueAt->format('g:i A') }}</span>
                                    @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                            @php
                                $allEditors = $job->editor ? collect([$job->editor])->merge($job->editors ?? collect())->unique('id') : ($job->editors ?? collect());
                                $editorNames = $allEditors->isEmpty() ? '—' : $allEditors->pluck('name')->join(', ');
                            @endphp
                                <span class="line-clamp-2 text-xs sm:text-sm">{{ $editorNames }}</span>
                            </td>
                            @if(in_array($activeSection, ['edit_done', 'print_done', 'framing_done'], true))
                                @php $prog = $job->workflowLineProgressSummary(); @endphp
                                <td class="px-4 py-3 text-xs leading-snug text-slate-600 dark:text-slate-300">
                                    @if($prog['relevant_total'] === 0)
                                        <span class="text-slate-400">No workflow lines</span>
                                    @elseif($prog['incomplete_total'] === 0)
                                        <span class="font-medium text-emerald-700 dark:text-emerald-400">0 left — ready to complete</span>
                                    @else
                                        <span class="block font-medium tabular-nums text-slate-800 dark:text-slate-100">{{ $prog['incomplete_total'] }} of {{ $prog['relevant_total'] }} lines left</span>
                                        @if($prog['pending_photo_edit'] > 0)
                                            <span class="mt-0.5 block text-slate-500 dark:text-slate-400">{{ $prog['pending_photo_edit'] }} edit</span>
                                        @endif
                                        @if($prog['pending_photo_print'] > 0)
                                            <span class="mt-0.5 block text-slate-500 dark:text-slate-400">{{ $prog['pending_photo_print'] }} print</span>
                                        @endif
                                        @if($prog['pending_framing'] > 0)
                                            <span class="mt-0.5 block text-slate-500 dark:text-slate-400">{{ $prog['pending_framing'] }} not done</span>
                                        @endif
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-3 text-xs leading-snug text-slate-600 dark:text-slate-400">
                                @php
                                    $tz = config('app.timezone');
                                    $posTs = ($job->source_id && isset(($salePosTimestampsBySourceId ?? [])[(string) $job->source_id]))
                                        ? $salePosTimestampsBySourceId[(string) $job->source_id]
                                        : ['created' => null, 'updated' => null];
                                    $posCreatedAt = null;
                                    $posEditedAt = null;
                                    if (! empty($posTs['created'])) {
                                        try {
                                            $posCreatedAt = \Illuminate\Support\Carbon::parse($posTs['created'])->timezone($tz);
                                        } catch (\Throwable) {
                                            $posCreatedAt = null;
                                        }
                                    }
                                    if (! empty($posTs['updated'])) {
                                        try {
                                            $posEditedAt = \Illuminate\Support\Carbon::parse($posTs['updated'])->timezone($tz);
                                        } catch (\Throwable) {
                                            $posEditedAt = null;
                                        }
                                    }
                                @endphp
                                <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bill created</span>
                                <span class="block tabular-nums text-slate-800 dark:text-slate-100">{{ $posCreatedAt ? $posCreatedAt->format('M d, Y g:i A') : '—' }}</span>
                                <span class="mt-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bill updated</span>
                                <span class="block tabular-nums text-slate-800 dark:text-slate-100">{{ $posEditedAt ? $posEditedAt->format('M d, Y g:i A') : '—' }}</span>
                                <span class="mt-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Job opened</span>
                                <span class="block tabular-nums">{{ $job->created_at->format('M d, Y') }}</span>
                        </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1 rounded-lg bg-[var(--color-studio-primary)] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:opacity-95 sm:text-sm">
                                        @include('components.icons', ['name' => 'eye', 'class' => 'w-3.5 h-3.5 sm:w-4 sm:h-4'])
                                    View
                                </a>
                                    @if($activeSection === 'pos_updated' && auth()->user()->canModifyJobWorkflow() && filled($job->source_id))
                                        <form action="{{ route('jobs.resync-from-pos', $job) }}" method="POST" class="inline" onsubmit="return confirm('Apply pending POS updates to this job? Progress is kept for matching POS lines. Lines removed in POS will be removed from the job.');">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-700 sm:text-sm">
                                                Apply pending
                                            </button>
                                        </form>
                                    @endif
                                    @if(auth()->user()->canTakeJob() && $job->status === 'new' && $activeSection !== 'dismissed')
                                    <form action="{{ route('jobs.take', $job) }}" method="POST" class="inline">
                                        @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 sm:text-sm">
                                                @include('components.icons', ['name' => 'hand-raised', 'class' => 'w-3.5 h-3.5 sm:w-4 sm:h-4'])
                                                Take
                                        </button>
                                    </form>
                                @endif
                                    @if($activeSection === 'new' && $job->status === 'new' && auth()->user()->canDismissNewJobs())
                                    <form action="{{ route('jobs.dismiss', $job) }}" method="POST" class="inline" onsubmit="return confirm('Dismiss this job? It will move to your Dismissed list.');">
                                        @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-600 sm:text-sm">
                                                @include('components.icons', ['name' => 'archive', 'class' => 'w-3.5 h-3.5 sm:w-4 sm:h-4'])
                                            Dismiss
                                        </button>
                                    </form>
                                @endif
                                    @if($activeSection === 'dismissed')
                                    <form action="{{ route('jobs.undismiss', $job) }}" method="POST" class="inline">
                                        @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 sm:text-sm">
                                                @include('components.icons', ['name' => 'arrow-uturn-left', 'class' => 'w-3.5 h-3.5 sm:w-4 sm:h-4'])
                                            Restore
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                        <tr>
                            <td colspan="{{ in_array($activeSection, ['edit_done', 'print_done', 'framing_done'], true) ? 9 : 8 }}" class="px-6 py-14 text-center">
                                <div class="mx-auto flex max-w-md flex-col items-center rounded-xl border border-dashed border-[var(--color-studio-border)] bg-slate-50/60 px-6 py-8 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/30">
                                    @include('components.icons', ['name' => 'folder', 'class' => 'h-10 w-10 text-slate-400 dark:text-slate-500'])
                                    <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">
                                        @if($activeSection === 'ongoing') No ongoing jobs
                                        @elseif($activeSection === 'edit_done') No jobs in Edit done
                                        @elseif($activeSection === 'print_done') No jobs in Print done
                                        @elseif($activeSection === 'framing_done') No jobs in Done (framing categories)
                                        @elseif($activeSection === 'completed') No complete jobs
                                        @elseif($activeSection === 'delivered') No delivered jobs
                                        @elseif($activeSection === 'dismissed') No dismissed jobs
                                        @else No jobs found
                                        @endif
                                    </p>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                        @if($activeSection === 'edit_done')
                                            Jobs appear here when all photo lines are edit-done with print still in progress, or when some photo lines are edit-done and others are not.
                                        @elseif($activeSection === 'print_done')
                                            Jobs appear here when photo lines are fully printed but framing or other work may still be open.
                                        @elseif($activeSection === 'framing_done')
                                            Jobs appear here when framing has started on a frame line and photo lines still need edit or print.
                                        @elseif(filled($ref ?? null))
                                            Try clearing the reference filter or another section.
                                        @else
                                            Sync from source or take a job from the Job Pool when new work arrives.
                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-6 flex justify-center border-t border-transparent pt-2 sm:justify-end">
        {{ $jobs->links() }}
    </div>
@endsection
