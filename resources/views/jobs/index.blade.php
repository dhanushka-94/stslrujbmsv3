@extends('layouts.app')

@section('title', 'Jobs')

@php
    $statusBadgeClass = [
        'new' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200',
        'assigned' => 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-200',
        'in_progress' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200',
        'completed' => 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200',
        'delivered' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200',
    ];
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-7 h-7'])
            Jobs
        </h1>
        <a href="{{ route('jobs.live') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-100 dark:hover:bg-slate-700">
            @include('components.icons', ['name' => 'bolt', 'class' => 'w-4 h-4'])
            Go to Job Pool
        </a>
    </div>

    {{-- Tabs: Ongoing, Completed, Dismissed (only started jobs) --}}
    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('jobs.index', ['section' => 'ongoing', 'ref' => $ref]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded text-sm font-medium {{ ($section ?? 'ongoing') === 'ongoing' ? 'bg-[var(--color-studio-primary)] text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
            @include('components.icons', ['name' => 'clock', 'class' => 'w-4 h-4'])
            Ongoing <span class="opacity-80">({{ $ongoingCount ?? 0 }})</span>
        </a>
        <a href="{{ route('jobs.index', ['section' => 'completed', 'ref' => $ref]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded text-sm font-medium {{ ($section ?? '') === 'completed' ? 'bg-[var(--color-studio-primary)] text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
            @include('components.icons', ['name' => 'check-circle', 'class' => 'w-4 h-4'])
            Completed <span class="opacity-80">({{ $completedCount ?? 0 }})</span>
        </a>
        <a href="{{ route('jobs.index', ['section' => 'dismissed', 'ref' => $ref]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded text-sm font-medium {{ ($section ?? '') === 'dismissed' ? 'bg-amber-600 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">
            @include('components.icons', ['name' => 'archive', 'class' => 'w-4 h-4'])
            Dismissed <span class="opacity-80">({{ $dismissedCount ?? 0 }})</span>
        </a>
    </div>

    @if(($section ?? '') === 'dismissed')
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">These jobs are hidden from your Ongoing list. Use <strong>Restore to job list</strong> to show them again.</p>
    @endif

    <form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
        <input type="hidden" name="section" value="{{ $section ?? 'ongoing' }}">
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">@include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-4 h-4'])</span>
            <input type="text" name="ref" value="{{ $ref ?? '' }}" placeholder="Ref number" class="pl-9 pr-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 w-48">
        </div>
        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm">Filter</button>
    </form>

    <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="text-left p-3">Ref</th>
                    <th class="text-left p-3">Customer</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Due date</th>
                    <th class="text-left p-3">Editors</th>
                    <th class="text-left p-3">Edits</th>
                    <th class="text-left p-3">Created</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                    @php
                        $hasDue = $job->due_date !== null;
                        $isOverdue = $hasDue && $job->due_date->isPast() && ! $job->isCompleted();
                    @endphp
                    <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] hover:bg-slate-50 dark:hover:bg-slate-800/60 {{ $isOverdue ? 'bg-rose-50/40 dark:bg-rose-900/10' : '' }}">
                        <td class="p-3 font-mono">{{ $job->ref_number }}</td>
                        <td class="p-3">{{ $job->customer_name ?? '—' }}</td>
                        <td class="p-3">
                            @php $sc = $statusBadgeClass[$job->status] ?? 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300'; @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium {{ $sc }}">{{ $job->statusLabel() }}</span>
                        </td>
                        <td class="p-3">
                            @if($hasDue)
                                <span class="{{ $isOverdue ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-700 dark:text-slate-200' }}">
                                    {{ $job->due_date->format('M d, Y') }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @php
                                $allEditors = $job->editor ? collect([$job->editor])->merge($job->editors ?? collect())->unique('id') : ($job->editors ?? collect());
                                $editorNames = $allEditors->isEmpty() ? '—' : $allEditors->pluck('name')->join(', ');
                            @endphp
                            {{ $editorNames }}
                        </td>
                        <td class="p-3">{{ $job->edits_count }}</td>
                        <td class="p-3">{{ $job->created_at->format('M d, Y') }}</td>
                        <td class="p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
                                    @include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4'])
                                    View
                                </a>
                                @if(auth()->user()->canTakeJob() && $job->status === 'new' && $section !== 'dismissed')
                                    <form action="{{ route('jobs.take', $job) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700">
                                            @include('components.icons', ['name' => 'hand-raised', 'class' => 'w-4 h-4'])
                                            Take job
                                        </button>
                                    </form>
                                @endif
                                @if($section === 'new' && $job->status === 'new' && (auth()->user()->isEditor() || auth()->user()->canManageJobs()))
                                    <form action="{{ route('jobs.dismiss', $job) }}" method="POST" class="inline" onsubmit="return confirm('Dismiss this job? It will move to your Dismissed list.');">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-amber-500 text-white text-sm hover:bg-amber-600">
                                            @include('components.icons', ['name' => 'archive', 'class' => 'w-4 h-4'])
                                            Dismiss
                                        </button>
                                    </form>
                                @endif
                                @if($section === 'dismissed')
                                    <form action="{{ route('jobs.undismiss', $job) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700">
                                            @include('components.icons', ['name' => 'arrow-uturn-left', 'class' => 'w-4 h-4'])
                                            Restore
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center text-slate-500">
                        @if(($section ?? 'new') === 'new') No new jobs.
                        @elseif(($section ?? '') === 'ongoing') No ongoing jobs.
                        @elseif(($section ?? '') === 'completed') No completed jobs.
                        @elseif(($section ?? '') === 'dismissed') No dismissed jobs.
                        @else No jobs found.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $jobs->links() }}</div>
@endsection
