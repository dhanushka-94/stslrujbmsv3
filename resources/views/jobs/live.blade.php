@extends('layouts.app')

@section('title', 'Job Pool')

@section('content')
    <section class="mb-6 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] sm:mb-8">
        <div class="relative px-5 py-5 sm:px-6 sm:py-6 bg-gradient-to-br from-amber-500/[0.07] via-transparent to-slate-50/90 dark:from-amber-400/[0.08] dark:to-slate-900/45">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">POS</p>
                    <h1 class="mt-1 flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/90 text-amber-600 shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800/90 dark:text-amber-400 dark:ring-slate-600 sm:h-11 sm:w-11">
                            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-6 h-6'])
                        </span>
                        <span class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">Job Pool</span>
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                        @if(($jobPoolMode ?? 'pos') === 'print_framing')
                            Jobs already in the system that need printing (edit done) or framing. Open a job to update print or framing status.
                        @else
                            Paid sales from the source database. Open a sale to create a job or jump to the jobs list.
                        @endif
                    </p>
                </div>
                <a href="{{ route('jobs.index') }}" class="inline-flex shrink-0 items-center gap-2 self-start rounded-lg border border-[var(--color-studio-border)] bg-white/90 px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-white dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/90">
                    @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
                    Jobs list
                </a>
            </div>
        </div>
    </section>

    <form method="GET" class="mb-6 flex flex-col gap-3 rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] sm:flex-row sm:flex-wrap sm:items-end sm:gap-4">
        <div class="min-w-0 flex-1 sm:max-w-xs">
            <label for="live-filter-ref" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Reference</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500">
                    @include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-4 h-4'])
                </span>
                <input id="live-filter-ref" type="text" name="ref" value="{{ $ref ?? '' }}" placeholder="Search ref…" autocomplete="off"
                    class="w-full rounded-lg border border-[var(--color-studio-border)] bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-[var(--color-studio-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-studio-primary)]/20 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-[var(--color-studio-accent)] dark:focus:ring-[var(--color-studio-accent)]/20">
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-studio-primary)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                @include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-4 h-4'])
                Apply filter
            </button>
            @if(filled($ref ?? null))
                <a href="{{ route('jobs.live') }}" class="inline-flex items-center rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">Clear</a>
            @endif
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <div class="overflow-x-auto">
        <table class="w-full table-fixed text-sm">
            <colgroup>
                <col class="w-[11%]">
                <col class="w-[14%]">
                <col class="w-[30%]">
                <col class="w-[15%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
            </colgroup>
            <thead class="border-b border-[var(--color-studio-border)] bg-slate-50/95 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/70 dark:text-slate-400">
            <tr>
                <th class="p-3 align-top">Ref</th>
                <th class="text-left p-3 align-top">Sale date (POS)</th>
                <th class="text-left p-3 align-top">Job items (from POS)</th>
                <th class="text-left p-3 align-top">Due date (POS)</th>
                <th class="text-left p-3 align-top">Due status</th>
                <th class="text-left p-3 align-top">In system</th>
                <th class="text-left p-3 align-top">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
            @forelse($sales as $sale)
                @php
                    $tz = config('app.timezone');
                    $dueAt = \Illuminate\Support\Carbon::parse($sale->due_date)->timezone($tz);
                    $job = $jobsBySourceId[(string) $sale->id] ?? null;
                    $items = $itemsBySaleId[$sale->id] ?? [];
                    $hasDue = !empty($sale->due_date) && $sale->due_date !== '0000-00-00';
                    $isOverdue = $hasDue && $dueAt->isPast();
                @endphp
                <tr class="{{ $isOverdue ? 'bg-rose-50/60 dark:bg-rose-900/20 border-l-4 border-l-rose-500 dark:border-l-rose-400' : 'bg-white dark:bg-slate-900' }}">
                    <td class="p-3 align-top font-mono break-all">{{ $sale->reference_no }}</td>
                    <td class="p-3 align-top">
                        @if(!empty($sale->date))
                            {{ \Illuminate\Support\Carbon::parse($sale->date)->timezone($tz)->format('M d, Y h:i A') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="p-3 align-top min-w-0">
                        @if(!empty($items))
                            <div class="max-h-64 overflow-y-auto overflow-x-hidden rounded-md border border-slate-200/80 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-800/40 p-2 shadow-inner">
                                <ul class="space-y-2 text-xs text-slate-700 dark:text-slate-200 list-none m-0 p-0">
                                @foreach($items as $idx => $name)
                                    <li class="break-words leading-snug border-b border-slate-200/60 dark:border-slate-600/60 pb-2 last:border-0 last:pb-0">
                                        <span class="text-slate-400 font-mono tabular-nums select-none">#{{ $idx + 1 }}</span>
                                        <span class="block pl-0 mt-0.5">{{ $name }}</span>
                                    </li>
                                @endforeach
                                </ul>
                            </div>
                        @else
                            <span class="text-xs text-slate-400">No items</span>
                        @endif
                    </td>
                    <td class="p-3 align-top">
                        @if($hasDue)
                            <span class="{{ $isOverdue ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-700 dark:text-slate-200' }}" title="Sri Lanka time ({{ $tz }})">
                                {{ $dueAt->format('M d, Y h:i A') }}
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="p-3 align-top">
                        @if($isOverdue)
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide bg-rose-200 text-rose-900 dark:bg-rose-900/60 dark:text-rose-100 ring-1 ring-inset ring-rose-400/50 dark:ring-rose-500/40" title="Due date/time has passed ({{ $tz }})">
                                @include('components.icons', ['name' => 'exclamation-triangle', 'class' => 'w-3.5 h-3.5 shrink-0'])
                                Due passed
                            </span>
                        @elseif($hasDue)
                            <span class="text-xs text-slate-500 dark:text-slate-400">Upcoming</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="p-3 align-top">
                        @if($job)
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium
                                @if($job->status === 'completed' || $job->status === 'delivered')
                                    bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200
                                @elseif($job->status === 'assigned' || $job->status === 'in_progress')
                                    bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200
                                @else
                                    bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200
                                @endif
                            ">
                                {{ $job->statusLabel() }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                @include('components.icons', ['name' => 'sparkles', 'class' => 'w-3 h-3'])
                                Not opened yet
                            </span>
                        @endif
                    </td>
                    <td class="p-3 align-top">
                        <div class="flex flex-wrap items-start gap-2">
                            @if($job)
                                <a href="{{ route('jobs.show', $job) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
                                    @include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4'])
                                    Open job
                                </a>
                            @elseif(($jobPoolMode ?? 'pos') !== 'print_framing')
                                <form action="{{ route('jobs.from-source', $sale->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700">
                                        @include('components.icons', ['name' => 'plus-circle', 'class' => 'w-4 h-4'])
                                        Open Job
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">Job record missing — contact admin.</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-14 text-center">
                        <div class="mx-auto inline-flex max-w-md flex-col items-center rounded-xl border border-dashed border-[var(--color-studio-border)] bg-slate-50/60 px-6 py-8 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/30">
                            @include('components.icons', ['name' => 'folder', 'class' => 'h-10 w-10 text-slate-400 dark:text-slate-500'])
                            <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">
                                @if(($jobPoolMode ?? 'pos') === 'print_framing')
                                    No jobs need printing or framing right now (or nothing matches this reference).
                                @else
                                    No sales match this filter
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                @if(($jobPoolMode ?? 'pos') === 'print_framing')
                                    Lines must be edit-done before print; framing lines need framing marked, or non-frame lines need print completed first.
                                @else
                                    Try another reference or clear the filter.
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

    <div class="mt-6 flex justify-end">
        {{ $sales->links() }}
    </div>
    <script>
        // Auto-refresh Job Pool every 60 seconds when tab is visible
        (function () {
            setInterval(function () {
                if (document.visibilityState === 'visible') {
                    window.location.reload();
                }
            }, 60000);
        })();
    </script>
@endsection

