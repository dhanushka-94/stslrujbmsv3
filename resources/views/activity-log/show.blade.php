@extends('layouts.app')

@section('title', 'Activity log – ' . $activity_log->created_at->format('M d, Y H:i'))

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'document-check', 'class' => 'w-7 h-7'])
            Activity log detail
        </h1>
        <a href="{{ route('activity-log.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
            @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
            Back to activity log
        </a>
    </div>

    <div class="max-w-2xl space-y-6">
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-6 shadow-sm">
            <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-4">Details</h2>
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Time</dt>
                    <dd class="mt-0.5 text-slate-800 dark:text-slate-100">{{ $activity_log->created_at->format('l, F j, Y \a\t g:i:s A') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">User</dt>
                    <dd class="mt-0.5">
                        @if($activity_log->user)
                            <a href="{{ route('users.show', $activity_log->user) }}" class="text-[var(--color-studio-primary)] hover:underline">{{ $activity_log->user->name }}</a>
                            <span class="text-slate-500 text-sm">({{ $activity_log->user->email }})</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Action</dt>
                    <dd class="mt-0.5"><span class="px-2 py-1 rounded text-sm font-mono bg-slate-100 dark:bg-slate-700">{{ $activity_log->action }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Description</dt>
                    <dd class="mt-0.5 text-slate-800 dark:text-slate-100">{{ $activity_log->description ?? '—' }}</dd>
                </div>
                @if($relatedJob)
                    <div>
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Related job</dt>
                        <dd class="mt-0.5">
                            <a href="{{ route('jobs.show', $relatedJob) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
                                @include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4'])
                                Job {{ $relatedJob->ref_number }}
                            </a>
                            @if($relatedJob->customer_name)
                                <span class="ml-2 text-slate-600 dark:text-slate-400">({{ $relatedJob->customer_name }})</span>
                            @endif
                        </dd>
                    </div>
                @elseif($activity_log->subject_type === 'job' && $activity_log->subject_id)
                    <div>
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Related job</dt>
                        <dd class="mt-0.5 text-slate-500">Job ID {{ $activity_log->subject_id }} (job may have been removed)</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">IP address</dt>
                    <dd class="mt-0.5 font-mono text-sm text-slate-700 dark:text-slate-300">{{ $activity_log->ip_address ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Device / browser</dt>
                    <dd class="mt-0.5 text-sm text-slate-700 dark:text-slate-300 break-all">{{ $activity_log->user_agent ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
