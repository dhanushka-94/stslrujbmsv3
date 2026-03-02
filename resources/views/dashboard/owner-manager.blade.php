@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'dashboard', 'class' => 'w-7 h-7'])
            Dashboard
        </h1>
        <p class="mt-1 text-slate-600 dark:text-slate-400">Welcome back, {{ auth()->user()->name }}. Here’s an overview of jobs and activity.</p>
    </div>

    {{-- Quick actions --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
            View all jobs
        </a>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('activity-log.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                Activity log
            </a>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                @include('components.icons', ['name' => 'users', 'class' => 'w-4 h-4'])
                Users
            </a>
        @endif
        <a href="{{ route('settings.block-categories') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
            @include('components.icons', ['name' => 'archive', 'class' => 'w-4 h-4'])
            Block categories
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['total'] }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4']) Total jobs</div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-amber-600">{{ $stats['new'] }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'sparkles', 'class' => 'w-4 h-4']) New</div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-blue-600">{{ $stats['in_progress'] }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'clock', 'class' => 'w-4 h-4']) In progress</div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-slate-600">{{ $stats['completed'] }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'check-circle', 'class' => 'w-4 h-4']) Completed</div>
        </div>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
            <div class="text-2xl font-bold text-green-600">{{ $stats['delivered'] }}</div>
            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">@include('components.icons', ['name' => 'arrow-down-tray', 'class' => 'w-4 h-4']) Delivered</div>
        </div>
    </div>

    @if(isset($recentActivityCount) && $recentActivityCount > 0)
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">{{ $recentActivityCount }} activity entries in the last 7 days. <a href="{{ route('activity-log.index') }}" class="text-[var(--color-studio-primary)] hover:underline">View log</a></p>
    @endif

    {{-- Ongoing jobs – easy access --}}
    <h2 class="text-lg font-medium mb-3 flex items-center gap-2">
        @include('components.icons', ['name' => 'clock', 'class' => 'w-5 h-5'])
        Ongoing jobs
        <a href="{{ route('jobs.index', ['section' => 'ongoing']) }}" class="text-sm font-normal text-[var(--color-studio-primary)] hover:underline ml-2">View all</a>
    </h2>
    @if(isset($ongoingJobs) && $ongoingJobs->isNotEmpty())
        <div class="mb-8 bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="text-left p-3">Ref</th>
                        <th class="text-left p-3">Customer</th>
                        <th class="text-left p-3">Status</th>
                        <th class="text-left p-3">Editor(s)</th>
                        <th class="text-left p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ongoingJobs as $job)
                        <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="p-3 font-mono">{{ $job->ref_number }}</td>
                            <td class="p-3">{{ $job->customer_name ?? '—' }}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200">{{ $job->statusLabel() }}</span></td>
                            <td class="p-3">
                                @if($job->editor)
                                    {{ $job->editor->name }}
                                    @if($job->editors && $job->editors->count() > 1)
                                        <span class="text-slate-500">+{{ $job->editors->count() - 1 }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="p-3">
                                <a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-xs hover:opacity-90">@include('components.icons', ['name' => 'eye', 'class' => 'w-3.5 h-3.5']) View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mb-8 text-slate-500 dark:text-slate-400">No ongoing jobs. <a href="{{ route('jobs.index') }}" class="text-[var(--color-studio-primary)] hover:underline">View jobs</a></p>
    @endif

    <h2 class="text-lg font-medium mb-3 flex items-center gap-2">
        @include('components.icons', ['name' => 'briefcase', 'class' => 'w-5 h-5'])
        Recent jobs
    </h2>
    <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="text-left p-3">Ref</th>
                    <th class="text-left p-3">Customer</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Editor(s)</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentJobs as $job)
                    <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                        <td class="p-3 font-mono">{{ $job->ref_number }}</td>
                        <td class="p-3">{{ $job->customer_name ?? '—' }}</td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700">{{ $job->statusLabel() ?? $job->status }}</span></td>
                        <td class="p-3">
                            @if($job->editor)
                                {{ $job->editor->name }}
                                @if($job->editors && $job->editors->count() > 1)
                                    <span class="text-slate-500">+{{ $job->editors->count() - 1 }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="p-3"><a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1 text-[var(--color-studio-primary)] hover:underline">@include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4']) View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-slate-500">No jobs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3"><a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1.5 text-[var(--color-studio-primary)] hover:underline">@include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4']) View all jobs</a></p>
@endsection
