@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'document-check', 'class' => 'w-7 h-7'])
            Print queue
        </h1>
        <p class="mt-1 text-slate-600 dark:text-slate-400">Welcome, {{ auth()->user()->name }}. Items waiting for print or in progress.</p>
    </div>

    {{-- Quick stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-amber-600">{{ $pendingCount ?? $editsPendingPrint->count() }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'clock', 'class' => 'w-4 h-4']) Pending print</div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-green-600">{{ $printedToday ?? 0 }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'check-circle', 'class' => 'w-4 h-4']) Marked printed today</div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
            All jobs
        </a>
        <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
            @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4'])
            My profile
        </a>
    </div>

    <h2 class="text-lg font-medium mb-3 flex items-center gap-2">Items in queue</h2>
    <div class="space-y-3">
        @forelse($editsPendingPrint as $edit)
            <div class="flex items-center justify-between bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
                <div>
                    <span class="font-mono">{{ $edit->job->ref_number }}</span>
                    <span class="text-slate-500 ml-2">– {{ $edit->name }}</span>
                    <span class="ml-2 text-xs px-2 py-0.5 rounded
                        @if($edit->print_status === 'printed') bg-green-100 dark:bg-green-900/30
                        @elseif($edit->print_status === 'sent_to_print') bg-blue-100 dark:bg-blue-900/30
                        @else bg-amber-100 dark:bg-amber-900/30
                        @endif">{{ $edit->print_status }}</span>
                </div>
                <a href="{{ route('jobs.show', $edit->job) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">@include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4']) View job</a>
            </div>
        @empty
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6 text-center text-slate-500">
                No items in print queue.
            </div>
        @endforelse
    </div>
@endsection
