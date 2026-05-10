@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="mb-8 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <div class="relative px-5 py-6 sm:px-7 sm:py-7 bg-gradient-to-br from-blue-500/[0.08] via-transparent to-slate-50/90 dark:from-blue-400/[0.1] dark:to-slate-900/50">
            <div class="flex flex-col gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Print desk</p>
                    <h1 class="mt-1.5 flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/90 text-blue-600 shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800/90 dark:text-blue-400 dark:ring-slate-600">
                            @include('components.icons', ['name' => 'document-check', 'class' => 'w-6 h-6'])
                        </span>
                        <span class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">Print queue</span>
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ auth()->user()->name }}</span> — lines with Edit done that still need print status updates show below.
                    </p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ now()->format('l, F j, Y') }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mb-8 grid grid-cols-2 gap-3 sm:gap-4">
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-amber-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300">
                @include('components.icons', ['name' => 'clock', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300 sm:text-3xl">{{ $pendingCount ?? $editsPendingPrint->count() }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pending / sent</p>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.03] transition hover:border-emerald-500/25 hover:shadow-md dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.04] sm:p-5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/12 text-emerald-700 dark:text-emerald-300">
                @include('components.icons', ['name' => 'check-circle', 'class' => 'w-5 h-5'])
            </span>
            <p class="mt-3 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300 sm:text-3xl">{{ $printedToday ?? 0 }}</p>
            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Printed today</p>
        </div>
    </div>

    <div class="mb-8 flex flex-wrap gap-2 rounded-xl border border-[var(--color-studio-border)] bg-slate-50/90 p-3 shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/35 sm:gap-2.5 sm:p-4">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4 shrink-0'])
            All jobs
        </a>
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
            @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4 shrink-0'])
            My profile
        </a>
    </div>

    <div class="mb-3 flex flex-wrap items-end justify-between gap-3 border-b border-[var(--color-studio-border)] pb-3 dark:border-[var(--color-studio-dark-border)]">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100 sm:text-lg">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/12 text-amber-800 dark:text-amber-200">
                @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-5 h-5'])
            </span>
            Queue
        </h2>
    </div>

    <ul class="space-y-3" role="list">
        @forelse($editsPendingPrint as $edit)
            <li>
                <div class="flex flex-col gap-3 rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] p-4 shadow-sm ring-1 ring-slate-900/[0.02] dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)] dark:ring-white/[0.03] sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ $edit->job->ref_number }}</span>
                            <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:inline dark:bg-slate-600" aria-hidden="true"></span>
                            <span class="text-sm text-slate-600 dark:text-slate-300">{{ $edit->name }}</span>
                        </div>
                        <span @class([
                            'mt-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                            'bg-emerald-100 text-emerald-900 ring-emerald-200/80 dark:bg-emerald-900/35 dark:text-emerald-100 dark:ring-emerald-700/50' => $edit->print_status === 'printed',
                            'bg-blue-100 text-blue-900 ring-blue-200/80 dark:bg-blue-900/35 dark:text-blue-100 dark:ring-blue-700/50' => $edit->print_status === 'sent_to_print',
                            'bg-amber-100 text-amber-950 ring-amber-200/80 dark:bg-amber-900/35 dark:text-amber-100 dark:ring-amber-700/50' => ! in_array($edit->print_status, ['printed', 'sent_to_print'], true),
                        ])>{{ str_replace('_', ' ', $edit->print_status) }}</span>
                    </div>
                    <a href="{{ route('jobs.show', $edit->job) }}" class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-lg bg-[var(--color-studio-primary)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 sm:self-center">
                        @include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4'])
                        View job
                    </a>
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-[var(--color-studio-border)] bg-slate-50/50 px-6 py-12 text-center dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/25">
                @include('components.icons', ['name' => 'check-circle', 'class' => 'mx-auto h-10 w-10 text-emerald-500/80 dark:text-emerald-400/70'])
                <p class="mt-3 text-sm font-medium text-slate-600 dark:text-slate-300">Print queue is clear</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Nothing is waiting in Pending or Sent to print right now.</p>
            </li>
        @endforelse
    </ul>
@endsection
