@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'document-check', 'class' => 'w-7 h-7'])
            Reports
        </h1>
        <p class="mt-1 text-slate-600 dark:text-slate-400">View your report, activity log, and user reports.</p>
    </div>

    <div class="grid gap-6 md:grid-cols-2 max-w-3xl">
        {{-- My report --}}
        <a href="{{ route('users.report', auth()->user()) }}" class="block p-6 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] hover:border-[var(--color-studio-primary)]/50 shadow-sm transition-colors">
            <div class="flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-[var(--color-studio-primary)]/10 text-[var(--color-studio-primary)]">
                    @include('components.icons', ['name' => 'users', 'class' => 'w-6 h-6'])
                </span>
                <div>
                    <h2 class="font-medium text-slate-800 dark:text-slate-100">My report</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Your activity, jobs involved, and summary stats.</p>
                </div>
            </div>
        </a>

        @if(auth()->user()->isAdmin() || auth()->user()->isManager())
            {{-- Editor time report --}}
            <a href="{{ route('reports.editor-time') }}" class="block p-6 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] hover:border-[var(--color-studio-primary)]/50 shadow-sm transition-colors">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-[var(--color-studio-primary)]/10 text-[var(--color-studio-primary)]">
                        @include('components.icons', ['name' => 'clock', 'class' => 'w-6 h-6'])
                    </span>
                    <div>
                        <h2 class="font-medium text-slate-800 dark:text-slate-100">Editor time report</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Estimated time (workload) per editor with date/time log.</p>
                    </div>
                </div>
            </a>
        @endif

        @if(auth()->user()->isAdmin())
            {{-- Activity log --}}
            <a href="{{ route('activity-log.index') }}" class="block p-6 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] hover:border-[var(--color-studio-primary)]/50 shadow-sm transition-colors">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-[var(--color-studio-primary)]/10 text-[var(--color-studio-primary)]">
                        @include('components.icons', ['name' => 'document-check', 'class' => 'w-6 h-6'])
                    </span>
                    <div>
                        <h2 class="font-medium text-slate-800 dark:text-slate-100">Activity log</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">All user activity with filters.</p>
                    </div>
                </div>
            </a>
        @endif
    </div>

    @if(auth()->user()->isAdmin() && $users->isNotEmpty())
        <div class="mt-8 max-w-3xl">
            <h2 class="text-lg font-medium mb-3 flex items-center gap-2">
                @include('components.icons', ['name' => 'users', 'class' => 'w-5 h-5'])
                User reports
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Open a report for any user.</p>
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th class="text-left p-3">Name</th>
                            <th class="text-left p-3">Email</th>
                            <th class="text-left p-3">Role</th>
                            <th class="text-left p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $u)
                            <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                                <td class="p-3 font-medium">{{ $u->name }}</td>
                                <td class="p-3 text-slate-600 dark:text-slate-400">{{ $u->email }}</td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700">{{ $u->roleLabel() }}</span></td>
                                <td class="p-3">
                                    <a href="{{ route('users.report', $u) }}" class="inline-flex items-center gap-1 text-[var(--color-studio-primary)] hover:underline">
                                        @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                                        View report
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
