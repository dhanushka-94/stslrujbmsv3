@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="mb-8 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <div class="relative px-5 py-6 sm:px-7 sm:py-7 bg-gradient-to-br from-[var(--color-studio-primary)]/[0.09] via-transparent to-slate-50/90 dark:from-[var(--color-studio-accent)]/[0.12] dark:to-slate-900/50">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Overview</p>
                    <h1 class="mt-1.5 flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/90 text-[var(--color-studio-primary)] shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800/90 dark:text-[var(--color-studio-accent)] dark:ring-slate-600">
                            @include('components.icons', ['name' => 'dashboard', 'class' => 'w-6 h-6'])
                        </span>
                        <span class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">Dashboard</span>
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        Welcome back, <span class="font-medium text-slate-800 dark:text-slate-200">{{ auth()->user()->name }}</span>.
                        Track job flow, assignments, and recent activity at a glance.
                    </p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ now()->format('l, F j, Y') }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mb-8 flex flex-wrap gap-2 rounded-xl border border-[var(--color-studio-border)] bg-slate-50/90 p-3 shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/35 sm:gap-2.5 sm:p-4">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-studio-primary)] px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-studio-primary)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4 shrink-0'])
            View all jobs
        </a>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('activity-log.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
                @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4 shrink-0'])
                Activity log
            </a>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
                @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4 shrink-0'])
                Users
            </a>
        @endif
        <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
            @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-4 h-4 shrink-0'])
            Reports
        </a>
        <a href="{{ route('settings.block-categories') }}" class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
            @include('components.icons', ['name' => 'archive', 'class' => 'w-4 h-4 shrink-0'])
            Block categories
        </a>
    </div>

    <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-[var(--color-studio-primary)]/20 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] dark:hover:border-[var(--color-studio-accent)]/25 sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-500/10 text-slate-700 dark:text-slate-200">
                @include('components.icons', ['name' => 'briefcase', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900 dark:text-slate-50 sm:text-3xl">{{ $stats['total'] }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total jobs</p>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-amber-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300">
                @include('components.icons', ['name' => 'sparkles', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300 sm:text-3xl">{{ $stats['new'] }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">New</p>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-blue-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/12 text-blue-700 dark:text-blue-300">
                @include('components.icons', ['name' => 'clock', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-blue-700 dark:text-blue-300 sm:text-3xl">{{ $stats['in_progress'] }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">In progress</p>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-slate-400/40 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-400/15 text-slate-700 dark:text-slate-200">
                @include('components.icons', ['name' => 'check-circle', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-slate-800 dark:text-slate-100 sm:text-3xl">{{ $stats['completed'] }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Completed</p>
        </div>
        <div class="relative col-span-2 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-emerald-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:col-span-1 sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/12 text-emerald-700 dark:text-emerald-300">
                @include('components.icons', ['name' => 'arrow-down-tray', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300 sm:text-3xl">{{ $stats['delivered'] }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Delivered</p>
        </div>
    </div>

    @if(isset($recentActivityCount) && $recentActivityCount > 0)
        <div class="mb-8 flex flex-wrap items-center gap-2 rounded-lg border border-indigo-200/80 bg-indigo-50/80 px-4 py-3 text-sm text-indigo-950 dark:border-indigo-800/60 dark:bg-indigo-950/35 dark:text-indigo-100">
            @include('components.icons', ['name' => 'bolt', 'class' => 'w-5 h-5 shrink-0 text-indigo-600 dark:text-indigo-300'])
            <span><span class="font-semibold tabular-nums">{{ $recentActivityCount }}</span> activity entries in the last 7 days.</span>
            <a href="{{ route('activity-log.index') }}" class="font-medium text-indigo-700 underline decoration-indigo-300 underline-offset-2 hover:text-indigo-900 dark:text-indigo-200 dark:hover:text-white">Open activity log</a>
        </div>
    @endif

    <div class="mb-3 flex flex-wrap items-end justify-between gap-3 border-b border-[var(--color-studio-border)] pb-3 dark:border-[var(--color-studio-dark-border)]">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100 sm:text-lg">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-700 dark:text-blue-300">
                @include('components.icons', ['name' => 'clock', 'class' => 'w-5 h-5'])
            </span>
            Ongoing jobs
        </h2>
        <a href="{{ route('jobs.index', ['section' => 'ongoing']) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-studio-primary)] hover:underline dark:text-[var(--color-studio-accent)]">
            View all ongoing
            @include('components.icons', ['name' => 'arrow-path', 'class' => 'w-4 h-4'])
        </a>
    </div>

    @if(isset($ongoingJobs) && $ongoingJobs->isNotEmpty())
        <div class="mb-10 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead class="border-b border-[var(--color-studio-border)] bg-slate-50/95 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Reference</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Editors</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach($ongoingJobs as $job)
                            <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono font-medium text-slate-900 dark:text-slate-100">{{ $job->ref_number }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-900 ring-1 ring-inset ring-blue-200/70 dark:bg-blue-900/40 dark:text-blue-100 dark:ring-blue-700/50">{{ $job->statusLabel() }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                    @if($job->editor)
                                        {{ $job->editor->name }}
                                        @if($job->editors && $job->editors->count() > 1)
                                            <span class="text-slate-400 dark:text-slate-500">+{{ $job->editors->count() - 1 }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-studio-primary)] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:opacity-95 dark:shadow-none">
                                        @include('components.icons', ['name' => 'eye', 'class' => 'w-3.5 h-3.5'])
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="mb-10 rounded-xl border border-dashed border-[var(--color-studio-border)] bg-slate-50/50 px-6 py-10 text-center dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/25">
            @include('components.icons', ['name' => 'folder', 'class' => 'mx-auto h-10 w-10 text-slate-400 dark:text-slate-500'])
            <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">No ongoing jobs right now</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Open the jobs list to assign work or sync from source.</p>
            <a href="{{ route('jobs.index') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-studio-primary)] hover:underline dark:text-[var(--color-studio-accent)]">Browse jobs</a>
        </div>
    @endif

    <div class="mb-3 flex flex-wrap items-end justify-between gap-3 border-b border-[var(--color-studio-border)] pb-3 dark:border-[var(--color-studio-dark-border)]">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100 sm:text-lg">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-500/10 text-slate-700 dark:text-slate-200">
                @include('components.icons', ['name' => 'briefcase', 'class' => 'w-5 h-5'])
            </span>
            Recent jobs
        </h2>
    </div>

    <div class="overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[32rem] text-sm">
                <thead class="border-b border-[var(--color-studio-border)] bg-slate-50/95 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Editors</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse($recentJobs as $job)
                        <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono font-medium text-slate-900 dark:text-slate-100">{{ $job->ref_number }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800 ring-1 ring-inset ring-slate-200/80 dark:bg-slate-700 dark:text-slate-100 dark:ring-slate-600">{{ $job->statusLabel() ?? $job->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                @if($job->editor)
                                    {{ $job->editor->name }}
                                    @if($job->editors && $job->editors->count() > 1)
                                        <span class="text-slate-400 dark:text-slate-500">+{{ $job->editors->count() - 1 }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1.5 font-medium text-[var(--color-studio-primary)] hover:underline dark:text-[var(--color-studio-accent)]">
                                    @include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4'])
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                @include('components.icons', ['name' => 'folder', 'class' => 'mx-auto mb-2 h-9 w-9 opacity-60'])
                                <span class="block text-sm">No jobs in the system yet.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-4">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--color-studio-primary)] hover:underline dark:text-[var(--color-studio-accent)]">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
            View all jobs
        </a>
    </p>
@endsection
