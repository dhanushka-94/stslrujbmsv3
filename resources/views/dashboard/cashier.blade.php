@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="mb-8 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <div class="relative px-5 py-6 sm:px-7 sm:py-7 bg-gradient-to-br from-emerald-500/[0.08] via-transparent to-slate-50/90 dark:from-emerald-400/[0.1] dark:to-slate-900/50">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Delivery</p>
                <h1 class="mt-1.5 flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/90 text-emerald-600 shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800/90 dark:text-emerald-400 dark:ring-slate-600">
                        @include('components.icons', ['name' => 'arrow-down-tray', 'class' => 'w-6 h-6'])
                    </span>
                    <span class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">Ready for delivery</span>
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ auth()->user()->name }}</span> — completed jobs appear here until they are marked delivered.
                </p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ now()->format('l, F j, Y') }}</p>
            </div>
        </div>
    </section>

    <div class="mb-8 grid grid-cols-2 gap-3 sm:gap-4">
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-blue-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/12 text-blue-700 dark:text-blue-300">
                @include('components.icons', ['name' => 'clock', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-blue-700 dark:text-blue-300 sm:text-3xl">{{ $readyCount ?? $readyForDelivery->count() }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ready now</p>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-emerald-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/12 text-emerald-700 dark:text-emerald-300">
                @include('components.icons', ['name' => 'check-circle', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300 sm:text-3xl">{{ $deliveredToday ?? 0 }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Your deliveries today</p>
        </div>
    </div>

    <div class="mb-8 flex flex-wrap gap-2 rounded-xl border border-[var(--color-studio-border)] bg-slate-50/90 p-3 shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/35 sm:gap-2.5 sm:p-4">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-studio-primary)] px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-95">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4 shrink-0'])
            View jobs
        </a>
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
            @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4 shrink-0'])
            My profile
        </a>
    </div>

    <div class="mb-3 flex flex-wrap items-end justify-between gap-3 border-b border-[var(--color-studio-border)] pb-3 dark:border-[var(--color-studio-dark-border)]">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100 sm:text-lg">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/12 text-emerald-800 dark:text-emerald-200">
                @include('components.icons', ['name' => 'arrow-down-tray', 'class' => 'w-5 h-5'])
            </span>
            Jobs to deliver
        </h2>
    </div>

    <ul class="space-y-3" role="list">
        @forelse($readyForDelivery as $job)
            <li>
                <div class="flex flex-col gap-3 rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.02] transition hover:border-emerald-500/30 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.03] sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="font-mono text-base font-semibold text-slate-900 dark:text-slate-100">{{ $job->ref_number }}</span>
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $job->customer_name ?? '—' }}</span>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            Editor: <span class="font-medium text-slate-600 dark:text-slate-300">{{ $job->editor?->name ?? '—' }}</span>
                        </p>
                    </div>
                    <a href="{{ route('jobs.show', $job) }}" class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-500 sm:self-center">
                        @include('components.icons', ['name' => 'arrow-down-tray', 'class' => 'w-4 h-4'])
                        Open &amp; deliver
                    </a>
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-[var(--color-studio-border)] bg-slate-50/50 px-6 py-12 text-center dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/25">
                @include('components.icons', ['name' => 'check-circle', 'class' => 'mx-auto h-10 w-10 text-slate-400 dark:text-slate-500'])
                <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">Nothing waiting for delivery</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Completed jobs will list here for handoff.</p>
            </li>
        @endforelse
    </ul>
@endsection
