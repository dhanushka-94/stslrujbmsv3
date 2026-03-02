@extends('layouts.app')

@section('title', 'Activity log')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'document-check', 'class' => 'w-7 h-7'])
            Activity log
        </h1>
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
            @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
            Users
        </a>
    </div>

    <form method="GET" class="mb-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600 flex flex-wrap items-end gap-4">
        <div>
            <label for="user_id" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">User</label>
            <select name="user_id" id="user_id" class="px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-sm">
                <option value="">All users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="action" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Action</label>
            <select name="action" id="action" class="px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-sm">
                <option value="">All actions</option>
                @foreach($actions as $a)
                    <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="date_from" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">From date</label>
            <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-sm">
        </div>
        <div>
            <label for="date_to" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">To date</label>
            <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="px-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 text-sm">
        </div>
        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded bg-slate-600 hover:bg-slate-700 text-white text-sm">
            @include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-4 h-4'])
            Filter
        </button>
        <a href="{{ route('activity-log.index') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Clear</a>
    </form>

    <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="text-left p-3">Time</th>
                        <th class="text-left p-3">User</th>
                        <th class="text-left p-3">Action</th>
                        <th class="text-left p-3">Description</th>
                        <th class="text-left p-3 w-28">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="p-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $log->created_at->format('M d, Y H:i') }}</td>
                            <td class="p-3">
                                @if($log->user)
                                    <a href="{{ route('users.show', $log->user) }}" class="text-[var(--color-studio-primary)] hover:underline">{{ $log->user->name }}</a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700 font-mono">{{ $log->action }}</span></td>
                            <td class="p-3 text-slate-700 dark:text-slate-300 max-w-xs truncate" title="{{ $log->description ?? '' }}">{{ $log->description ?? '—' }}</td>
                            <td class="p-3">
                                <a href="{{ route('activity-log.show', $log) }}" class="inline-flex items-center gap-1 px-2 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-xs hover:opacity-90">@include('components.icons', ['name' => 'eye', 'class' => 'w-3.5 h-3.5']) View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Use <strong>View</strong> to see full details, IP, device/browser, and related job (if any).</p>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
