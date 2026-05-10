@extends('layouts.app')

@section('title', 'Editor time report')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'clock', 'class' => 'w-7 h-7'])
            Editor time report
        </h1>
        <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
            @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
            Back to Reports
        </a>
    </div>

    <p class="text-slate-600 dark:text-slate-400 mb-6">Estimated time (workload) per editor: total minutes and detail by job item. Date/time is logged when set.</p>

    {{-- Filters --}}
    <form method="GET" action="{{ route('reports.editor-time') }}" class="mb-6 p-4 rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] flex flex-wrap items-end gap-4">
        <div>
            <label for="editor_id" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Editor</label>
            <select name="editor_id" id="editor_id" class="text-sm rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 px-3 py-2">
                <option value="">All editors</option>
                @foreach($editors as $e)
                    <option value="{{ $e->id }}" {{ $filterEditorId == $e->id ? 'selected' : '' }}>{{ $e->name }} ({{ $e->roleLabel() }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">From date</label>
            <input type="date" name="from" id="from" value="{{ $filterFrom }}" class="text-sm rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 px-3 py-2">
        </div>
        <div>
            <label for="to" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">To date</label>
            <input type="date" name="to" id="to" value="{{ $filterTo }}" class="text-sm rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 px-3 py-2">
        </div>
        <button type="submit" class="px-4 py-2 rounded bg-[var(--color-studio-primary)] text-white text-sm font-medium hover:opacity-90">Apply</button>
    </form>

    {{-- Summary by editor --}}
    <div class="mb-8">
        <h2 class="text-lg font-medium text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
            @include('components.icons', ['name' => 'users', 'class' => 'w-5 h-5'])
            Summary per editor
        </h2>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="text-left p-3">Editor</th>
                        <th class="text-left p-3">Role</th>
                        <th class="text-right p-3">Total est. (min)</th>
                        <th class="text-right p-3">Items</th>
                        <th class="text-right p-3">Est. hours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary as $row)
                        <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                            <td class="p-3 font-medium">{{ $row['user']->name }}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-700">{{ $row['user']->roleLabel() }}</span></td>
                            <td class="p-3 text-right">{{ number_format($row['total_minutes']) }}</td>
                            <td class="p-3 text-right">{{ $row['item_count'] }}</td>
                            <td class="p-3 text-right text-slate-600 dark:text-slate-400">{{ $row['total_minutes'] > 0 ? number_format($row['total_minutes'] / 60, 1) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-500 dark:text-slate-400">No editors or no estimated time recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Detail: each item with estimated time --}}
    <div>
        <h2 class="text-lg font-medium text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
            @include('components.icons', ['name' => 'document-check', 'class' => 'w-5 h-5'])
            Detail (all items with estimated time)
        </h2>
        <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="text-left p-3">Editor</th>
                        <th class="text-left p-3">Job</th>
                        <th class="text-left p-3">Item</th>
                        <th class="text-right p-3">Est. (min)</th>
                        <th class="text-left p-3">Set at (date/time)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($detail as $edit)
                        <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                            <td class="p-3 font-medium">{{ $edit->claimedByUser?->name ?? '—' }}</td>
                            <td class="p-3">
                                @if($edit->job)
                                    <a href="{{ route('jobs.show', $edit->job) }}" class="text-[var(--color-studio-primary)] hover:underline">{{ $edit->job->ref_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="p-3 text-slate-700 dark:text-slate-300">{{ $edit->name }}</td>
                            <td class="p-3 text-right">{{ $edit->estimated_minutes }}</td>
                            <td class="p-3 text-slate-600 dark:text-slate-400">{{ $edit->estimated_minutes_at ? $edit->estimated_minutes_at->format('Y-m-d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-500 dark:text-slate-400">No items with estimated time in this range.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
