@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-7 h-7'])
            My jobs
        </h1>
        <p class="mt-1 text-slate-600 dark:text-slate-400">Welcome, {{ auth()->user()->name }}. Manage your assigned jobs and take new ones.</p>
    </div>

    {{-- Quick stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-blue-600">{{ $myJobs->count() }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'clock', 'class' => 'w-4 h-4']) Ongoing</div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-amber-600">{{ $availableCount }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'sparkles', 'class' => 'w-4 h-4']) Available to take</div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-green-600">{{ $myCompletedCount ?? 0 }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'check-circle', 'class' => 'w-4 h-4']) Completed by you</div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
            Browse jobs
        </a>
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
            @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4'])
            My profile
        </a>
    </div>

    @if($availableCount > 0)
        <p class="mb-4 text-slate-600 dark:text-slate-400">{{ $availableCount }} job(s) available to take. <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1 text-[var(--color-studio-primary)] hover:underline font-medium">@include('components.icons', ['name' => 'hand-raised', 'class' => 'w-4 h-4']) Take a job</a></p>
    @endif

    <h2 class="text-lg font-medium mb-3 flex items-center gap-2">Ongoing jobs</h2>
    <div class="space-y-3">
        @forelse($myJobs as $job)
            <div class="flex items-center justify-between bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm hover:border-[var(--color-studio-primary)]/30 transition-colors">
                <div>
                    <span class="font-mono font-medium">{{ $job->ref_number }}</span>
                    <span class="text-slate-500 ml-2">{{ $job->customer_name ?? '—' }}</span>
                    <span class="ml-2 text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700">{{ $job->status }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-500">{{ $job->edits_count }} edit(s)</span>
                    <a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">@include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4']) Open</a>
                </div>
            </div>
        @empty
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6 text-center text-slate-500">
                You have no assigned jobs. <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1 text-[var(--color-studio-primary)] hover:underline font-medium">@include('components.icons', ['name' => 'hand-raised', 'class' => 'w-4 h-4']) Take a job</a>
            </div>
        @endforelse
    </div>
@endsection
