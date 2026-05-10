@extends('layouts.app')

@section('title', ! empty($framingDashboard) && $framingDashboard ? 'Framing — Dashboard' : 'Dashboard')

@section('content')
    <section class="mb-8 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <div class="relative px-5 py-6 sm:px-7 sm:py-7 bg-gradient-to-br from-[var(--color-studio-primary)]/[0.09] via-transparent to-slate-50/90 dark:from-[var(--color-studio-accent)]/[0.12] dark:to-slate-900/50">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        @if(! empty($framingDashboard) && $framingDashboard)
                            Framing
                        @else
                            Your workspace
                        @endif
                    </p>
                    <h1 class="mt-1.5 flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/90 text-[var(--color-studio-primary)] shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800/90 dark:text-[var(--color-studio-accent)] dark:ring-slate-600">
                            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-6 h-6'])
                        </span>
                        <span class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">
                            @if(! empty($framingDashboard) && $framingDashboard)
                                Framing workspace
                            @else
                                My jobs
                            @endif
                        </span>
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        @if(! empty($framingDashboard) && $framingDashboard)
                            Hi <span class="font-medium text-slate-800 dark:text-slate-200">{{ auth()->user()->name }}</span> — open <a href="{{ route('jobs.live') }}" class="font-medium text-[var(--color-studio-primary)] underline decoration-slate-300 underline-offset-2 dark:text-[var(--color-studio-accent)]">Job Pool</a> for work ready for framing. On <strong>Jobs</strong>, use <strong>Print done</strong> and <strong>Framing done</strong> to see mixed jobs (photo vs frame progress) and the <strong>Remaining</strong> column on those tabs.
                        @else
                            Hi <span class="font-medium text-slate-800 dark:text-slate-200">{{ auth()->user()->name }}</span> — take open jobs, claim line items, and move work through edit and customer steps from here.
                        @endif
                    </p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ now()->format('l, F j, Y') }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-blue-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/12 text-blue-700 dark:text-blue-300">
                @include('components.icons', ['name' => 'clock', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-blue-700 dark:text-blue-300 sm:text-3xl">{{ $myJobs->count() }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ongoing</p>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-amber-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300">
                @include('components.icons', ['name' => 'sparkles', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300 sm:text-3xl">{{ $availableCount }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Available</p>
        </div>
        <div class="relative col-span-2 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-emerald-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:col-span-1 sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/12 text-emerald-700 dark:text-emerald-300">
                @include('components.icons', ['name' => 'check-circle', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300 sm:text-3xl">{{ $myCompletedCount ?? 0 }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Done by you</p>
        </div>
    </div>

    <div class="mb-8 flex flex-wrap gap-2 rounded-xl border border-[var(--color-studio-border)] bg-slate-50/90 p-3 shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/35 sm:gap-2.5 sm:p-4">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-studio-primary)] px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-studio-primary)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4 shrink-0'])
            Browse jobs
        </a>
        @if(! empty($framingDashboard) && $framingDashboard)
            <a href="{{ route('jobs.index', ['section' => 'print_done']) }}" class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-white px-4 py-2.5 text-sm font-medium text-sky-900 shadow-sm transition hover:bg-sky-50 dark:border-sky-800 dark:bg-slate-800 dark:text-sky-100 dark:hover:bg-slate-700/80">
                @include('components.icons', ['name' => 'arrow-path', 'class' => 'w-4 h-4 shrink-0'])
                Print done
            </a>
            <a href="{{ route('jobs.index', ['section' => 'framing_done']) }}" class="inline-flex items-center gap-2 rounded-lg border border-teal-200 bg-white px-4 py-2.5 text-sm font-medium text-teal-900 shadow-sm transition hover:bg-teal-50 dark:border-teal-800 dark:bg-slate-800 dark:text-teal-100 dark:hover:bg-slate-700/80">
                @include('components.icons', ['name' => 'sparkles', 'class' => 'w-4 h-4 shrink-0'])
                Framing done
            </a>
        @endif
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
            @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4 shrink-0'])
            My profile
        </a>
    </div>

    @if($availableCount > 0)
        <div class="mb-8 flex flex-wrap items-center gap-2 rounded-lg border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-100">
            @include('components.icons', ['name' => 'hand-raised', 'class' => 'w-5 h-5 shrink-0 text-amber-600 dark:text-amber-300'])
            <span><span class="font-semibold tabular-nums">{{ $availableCount }}</span> job(s) are open to take.</span>
            <a href="{{ route('jobs.index') }}" class="font-medium text-amber-900 underline decoration-amber-300 underline-offset-2 hover:text-amber-950 dark:text-amber-200 dark:hover:text-white">Take a job</a>
        </div>
    @endif

    <div class="mb-3 flex flex-wrap items-end justify-between gap-3 border-b border-[var(--color-studio-border)] pb-3 dark:border-[var(--color-studio-dark-border)]">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100 sm:text-lg">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-700 dark:text-indigo-300">
                @include('components.icons', ['name' => 'pencil-square', 'class' => 'w-5 h-5'])
            </span>
            Ongoing jobs
        </h2>
    </div>

    <ul class="space-y-3" role="list">
        @forelse($myJobs as $job)
            <li>
                <a href="{{ route('jobs.show', $job) }}" class="group flex flex-col gap-3 rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.02] transition hover:border-[var(--color-studio-primary)]/35 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.03] dark:hover:border-[var(--color-studio-accent)]/35 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="font-mono text-base font-semibold text-slate-900 dark:text-slate-100">{{ $job->ref_number }}</span>
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $job->customer_name ?? '—' }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-200/80 dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-600">{{ $job->status }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $job->edits_count }} line item(s)</span>
                        </div>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-lg bg-[var(--color-studio-primary)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition group-hover:opacity-95 sm:self-center dark:shadow-none">
                        @include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4'])
                        Open job
                    </span>
                </a>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-[var(--color-studio-border)] bg-slate-50/50 px-6 py-12 text-center dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/25">
                @include('components.icons', ['name' => 'folder', 'class' => 'mx-auto h-10 w-10 text-slate-400 dark:text-slate-500'])
                <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">No assigned jobs yet</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">When you take or are added to a job, it will show up here.</p>
                <a href="{{ route('jobs.index') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-studio-primary)] hover:underline dark:text-[var(--color-studio-accent)]">
                    @include('components.icons', ['name' => 'hand-raised', 'class' => 'w-4 h-4'])
                    Take a job
                </a>
            </li>
        @endforelse
    </ul>
@endsection
