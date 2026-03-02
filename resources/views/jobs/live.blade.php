@extends('layouts.app')

@section('title', 'Job Pool')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
            @include('components.icons', ['name' => 'briefcase', 'class' => 'w-7 h-7'])
            Job Pool
        </h1>
        <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] text-sm hover:bg-slate-100 dark:hover:bg-slate-700">
            @include('components.icons', ['name' => 'list-bullet', 'class' => 'w-4 h-4'])
            Go to Jobs page
        </a>
    </div>

    {{-- Intentionally minimal header – no extra explanatory text or counters as per user request --}}

    <form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                @include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-4 h-4'])
            </span>
            <input type="text" name="ref" value="{{ $ref ?? '' }}" placeholder="Ref number"
                   class="pl-9 pr-3 py-2 rounded border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-800 w-48">
        </div>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm">
            Filter
        </button>
    </form>

    <div
        class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
            <tr>
                <th class="text-left p-3">Ref</th>
                <th class="text-left p-3">Customer</th>
                <th class="text-left p-3">Payment status (POS)</th>
                <th class="text-left p-3">Sale date (POS)</th>
                <th class="text-left p-3">Due date (POS)</th>
                <th class="text-left p-3">In system</th>
                <th class="text-left p-3">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($sales as $sale)
                @php
                    $job = $jobsBySourceId[(string) $sale->id] ?? null;
                    $payment = strtolower((string) ($sale->payment_status ?? ''));
                    $paymentLabel = $payment !== '' ? ucfirst($payment) : '—';
                    $paymentClass = match($payment) {
                        'pending', 'due', 'partial' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200',
                        'paid' => 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200',
                        default => 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300',
                    };
                    $hasDue = !empty($sale->due_date) && $sale->due_date !== '0000-00-00';
                    $isOverdue = $hasDue && \Illuminate\Support\Carbon::parse($sale->due_date)->isPast();
                @endphp
                <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] {{ $isOverdue ? 'bg-rose-50/60 dark:bg-rose-900/20' : 'bg-white dark:bg-slate-900' }}">
                    <td class="p-3 font-mono">{{ $sale->reference_no }}</td>
                    <td class="p-3">{{ $sale->customer ?? '—' }}</td>
                    <td class="p-3">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium {{ $paymentClass }}">
                            {{ $paymentLabel }}
                        </span>
                    </td>
                    <td class="p-3">
                        @if(!empty($sale->date))
                            {{ \Illuminate\Support\Carbon::parse($sale->date)->format('M d, Y h:i A') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="p-3">
                        @if($hasDue)
                            <span class="{{ $isOverdue ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-700 dark:text-slate-200' }}">
                                {{ \Illuminate\Support\Carbon::parse($sale->due_date)->format('M d, Y') }}
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="p-3">
                        @if($job)
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium
                                @if($job->status === 'completed' || $job->status === 'delivered')
                                    bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200
                                @elseif($job->status === 'assigned' || $job->status === 'in_progress')
                                    bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200
                                @else
                                    bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200
                                @endif
                            ">
                                {{ $job->statusLabel() }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                @include('components.icons', ['name' => 'sparkles', 'class' => 'w-3 h-3'])
                                Not opened yet
                            </span>
                        @endif
                    </td>
                    <td class="p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($job)
                                <a href="{{ route('jobs.show', $job) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-[var(--color-studio-primary)] text-white text-sm hover:opacity-90">
                                    @include('components.icons', ['name' => 'eye', 'class' => 'w-4 h-4'])
                                    Open job
                                </a>
                            @else
                                <form action="{{ route('jobs.from-source', $sale->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700">
                                        @include('components.icons', ['name' => 'plus-circle', 'class' => 'w-4 h-4'])
                                        Open Job
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-6 text-center text-slate-500">
                        No sales found in POS for this filter.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $sales->links() }}
    </div>
    <script>
        // Auto-refresh Job Pool every 60 seconds when tab is visible
        (function () {
            setInterval(function () {
                if (document.visibilityState === 'visible') {
                    window.location.reload();
                }
            }, 60000);
        })();
    </script>
@endsection

