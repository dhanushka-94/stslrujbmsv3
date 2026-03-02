@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'arrow-down-tray', 'class' => 'w-7 h-7'])
            Ready for delivery
        </h1>
        <p class="mt-1 text-slate-600 dark:text-slate-400">Welcome, {{ auth()->user()->name }}. Jobs completed and waiting to be delivered.</p>
    </div>

    {{-- Quick stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-blue-600">{{ $readyCount ?? $readyForDelivery->count() }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'clock', 'class' => 'w-4 h-4']) Ready now</div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-green-600">{{ $deliveredToday ?? 0 }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'check-circle', 'class' => 'w-4 h-4']) Delivered by you today</div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
            View jobs
        </a>
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
            @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4'])
            My profile
        </a>
    </div>

    <h2 class="text-lg font-medium mb-3 flex items-center gap-2">Jobs to deliver</h2>
    <div class="space-y-3">
        @forelse($readyForDelivery as $job)
            <div class="flex items-center justify-between bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm hover:border-[var(--color-studio-primary)]/30 transition-colors">
                <div>
                    <span class="font-mono font-medium">{{ $job->ref_number }}</span>
                    <span class="text-slate-500 ml-2">{{ $job->customer_name ?? '—' }}</span>
                    <span class="ml-2 text-xs text-slate-500">Editor: {{ $job->editor?->name ?? '—' }}</span>
                </div>
                <a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">@include('components.icons', ['name' => 'arrow-down-tray', 'class' => 'w-4 h-4']) Deliver</a>
            </div>
        @empty
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6 text-center text-slate-500">
                No jobs ready for delivery.
            </div>
        @endforelse
    </div>
@endsection
