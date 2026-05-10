@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="mb-8 overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
        <div class="relative px-5 py-6 sm:px-7 sm:py-7 bg-gradient-to-br from-[var(--color-studio-primary)]/[0.09] via-transparent to-slate-50/90 dark:from-[var(--color-studio-accent)]/[0.12] dark:to-slate-900/50">
            <h1 class="flex flex-wrap items-center gap-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/90 text-[var(--color-studio-primary)] shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800/90 dark:text-[var(--color-studio-accent)] dark:ring-slate-600">
                    @include('components.icons', ['name' => 'dashboard', 'class' => 'w-6 h-6'])
                </span>
                <span class="text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)]">Dashboard</span>
            </h1>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                Welcome, <span class="font-medium text-slate-800 dark:text-slate-200">{{ auth()->user()->name }}</span>. Use the shortcuts below to move around the app.
            </p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ now()->format('l, F j, Y') }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-lg">
        <div class="overflow-hidden rounded-xl border border-[var(--color-studio-border)] bg-[var(--color-studio-bg-card)] shadow-sm dark:border-[var(--color-studio-dark-border)] dark:bg-[var(--color-studio-dark-card)]">
            <div class="border-b border-[var(--color-studio-border)] bg-slate-50/90 px-5 py-4 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800/50">
                <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800 dark:text-slate-100">
                    @include('components.icons', ['name' => 'bolt', 'class' => 'w-5 h-5 text-amber-600 dark:text-amber-400'])
                    Quick links
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Jump to the main areas you can access.</p>
            </div>
            <div class="flex flex-col gap-2 p-5">
                <a href="{{ route('jobs.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[var(--color-studio-primary)] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                    @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4 shrink-0'])
                    View jobs
                </a>
                <a href="{{ route('profile.show') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-[var(--color-studio-border)] bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-[var(--color-studio-dark-border)] dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700/80">
                    @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4 shrink-0'])
                    My profile
                </a>
            </div>
        </div>
    </div>
@endsection
