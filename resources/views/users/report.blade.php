@extends('layouts.app')

@section('title', 'Report – ' . $user->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'document-check', 'class' => 'w-7 h-7'])
            {{ $user->id === auth()->id() ? 'My report' : 'User report' }}: {{ $user->name }}
        </h1>
        <div class="flex items-center gap-2">
            @if($user->id === auth()->id())
                <a href="{{ route('profile.show') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
                    @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                    Profile
                </a>
            @else
                <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
                    @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
                    Users
                </a>
                <a href="{{ route('users.show', $user) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-[var(--color-studio-border)] text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                    @include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4'])
                    View user
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- User summary --}}
        <div class="lg:col-span-1">
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6 shadow-sm">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-4">User</h2>
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $user->name }}</p>
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ $user->email }}</p>
                <p class="mt-2"><span class="px-2 py-0.5 rounded text-sm bg-slate-100 dark:bg-slate-700">{{ $user->roleLabel() }}</span></p>
                <p class="mt-2">
                    @if($user->isActive())
                        <span class="inline-flex items-center gap-1 text-sm text-green-700 dark:text-green-400">@include('components.icons', ['name' => 'check-circle', 'class' => 'w-4 h-4']) Active</span>
                    @else
                        <span class="inline-flex items-center gap-1 text-sm text-slate-500">@include('components.icons', ['name' => 'no-symbol', 'class' => 'w-4 h-4']) Inactive</span>
                    @endif
                </p>
            </div>

            {{-- Stats --}}
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
                    <div class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['jobs_involved'] }}</div>
                    <div class="text-xs text-slate-500">Jobs involved</div>
                </div>
                <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm">
                    <div class="text-xl font-bold text-green-600">{{ $stats['jobs_completed'] }}</div>
                    <div class="text-xs text-slate-500">Completed</div>
                </div>
                @if($stats['jobs_delivered_by'] > 0)
                    <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm col-span-2">
                        <div class="text-xl font-bold text-blue-600">{{ $stats['jobs_delivered_by'] }}</div>
                        <div class="text-xs text-slate-500">Jobs delivered by this user</div>
                    </div>
                @endif
                <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-4 shadow-sm col-span-2">
                    <div class="text-xl font-bold text-slate-600">{{ $stats['activity_count'] }}</div>
                    <div class="text-xs text-slate-500">Total activity entries</div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            {{-- Recent jobs --}}
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden shadow-sm">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400 px-4 pt-4 pb-2 flex items-center gap-2">
                    @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
                    Recent jobs (as editor / assigned)
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th class="text-left p-3">Ref</th>
                                <th class="text-left p-3">Customer</th>
                                <th class="text-left p-3">Status</th>
                                <th class="text-left p-3">Items</th>
                                <th class="text-left p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jobsAsEditor as $job)
                                <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                                    <td class="p-3 font-mono">{{ $job->ref_number }}</td>
                                    <td class="p-3">{{ $job->customer_name ?? '—' }}</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700">{{ $job->statusLabel() }}</span></td>
                                    <td class="p-3">{{ $job->edits_count ?? 0 }}</td>
                                    <td class="p-3"><a href="{{ route('jobs.show', $job) }}" class="text-[var(--color-studio-primary)] hover:underline">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-4 text-center text-slate-500">No jobs.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent activity --}}
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden shadow-sm">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400 px-4 pt-4 pb-2 flex items-center gap-2">
                    @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                    Recent activity
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th class="text-left p-3">Time</th>
                                <th class="text-left p-3">Action</th>
                                <th class="text-left p-3">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentActivity as $log)
                                <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                                    <td class="p-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $log->created_at->format('M d, H:i') }}</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded text-xs font-mono bg-slate-100 dark:bg-slate-700">{{ $log->action }}</span></td>
                                    <td class="p-3">{{ $log->description ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-4 text-center text-slate-500">No activity yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($stats['activity_count'] > 30 && auth()->user()->isAdmin())
                    <p class="px-4 pb-4 text-xs text-slate-500">Showing latest 30 of {{ $stats['activity_count'] }}. <a href="{{ route('activity-log.index', ['user_id' => $user->id]) }}" class="text-[var(--color-studio-primary)] hover:underline">View full activity log</a></p>
                @endif
            </div>
        </div>
    </div>
@endsection
