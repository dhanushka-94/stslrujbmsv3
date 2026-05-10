@extends('layouts.app')

@section('title', 'POS sales (source)')

@section('content')
    <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] mb-2 flex items-center gap-2">
        @include('components.icons', ['name' => 'briefcase', 'class' => 'w-7 h-7'])
        All POS sales
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
        Read-only from <span class="font-mono text-slate-600 dark:text-slate-300">{{ config('database.connections.source.database') ?: 'source DB' }}</span>
        — every <code class="text-xs bg-slate-100 dark:bg-slate-800 px-1 rounded">sma_sales</code> row, newest first. Search matches reference, customer, biller, notes, statuses, payment method, or numeric sale ID.
    </p>

    @if($error)
        <div class="mb-4 p-4 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200">{{ $error }}</div>
    @endif

    <form method="get" action="{{ route('settings.pos-sales') }}" class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1 min-w-0">
            <label for="q" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Search</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    @include('components.icons', ['name' => 'magnifying-glass', 'class' => 'w-5 h-5'])
                </span>
                <input type="search" name="q" id="q" value="{{ $q }}"
                    class="w-full rounded-lg border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-white dark:bg-slate-900 pl-10 pr-3 py-2 text-sm text-slate-900 dark:text-slate-100 shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-studio-primary)]/30"
                    placeholder="Reference, customer, ID, status, note…"
                    autocomplete="off">
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[var(--color-studio-primary)] px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-studio-primary)]/40">
                Search
            </button>
            @if($q !== '')
                <a href="{{ route('settings.pos-sales') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="text-left p-3 whitespace-nowrap">ID</th>
                        <th class="text-left p-3 whitespace-nowrap">Date</th>
                        <th class="text-left p-3 min-w-[10rem]">Reference</th>
                        <th class="text-left p-3 min-w-[8rem]">Customer</th>
                        <th class="text-right p-3 whitespace-nowrap">Grand total</th>
                        <th class="text-right p-3 whitespace-nowrap">Paid</th>
                        <th class="text-left p-3 whitespace-nowrap">Sale</th>
                        <th class="text-left p-3 whitespace-nowrap">Payment</th>
                        <th class="text-center p-3 whitespace-nowrap">POS</th>
                        <th class="text-left p-3 min-w-[8rem]">Due</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $row)
                        <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] align-top">
                            <td class="p-3 font-mono text-xs">{{ $row->id }}</td>
                            <td class="p-3 whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $row->date }}</td>
                            <td class="p-3 font-mono text-xs break-all">{{ $row->reference_no ?? '—' }}</td>
                            <td class="p-3">{{ $row->customer ?? '—' }}</td>
                            <td class="p-3 text-right font-mono whitespace-nowrap">{{ number_format((float) ($row->grand_total ?? 0), 2) }}</td>
                            <td class="p-3 text-right font-mono whitespace-nowrap">{{ number_format((float) ($row->paid ?? 0), 2) }}</td>
                            <td class="p-3"><span class="inline-block max-w-[8rem] truncate align-bottom" title="{{ $row->sale_status ?? '' }}">{{ $row->sale_status ?? '—' }}</span></td>
                            <td class="p-3">
                                <div class="max-w-[10rem]">
                                    <span class="block truncate" title="{{ $row->payment_status ?? '' }}">{{ $row->payment_status ?? '—' }}</span>
                                    @if(!empty($row->payment_method))
                                        <span class="block text-xs text-slate-500 truncate" title="{{ $row->payment_method }}">{{ $row->payment_method }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3 text-center">{{ isset($row->pos) && (int) $row->pos === 1 ? 'Y' : '—' }}</td>
                            <td class="p-3 text-xs text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                @php
                                    $dd = $row->due_date ?? null;
                                @endphp
                                @if($dd && $dd !== '0000-00-00 00:00:00' && !str_starts_with((string) $dd, '0000-00-00'))
                                    {{ $dd }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-8 text-center text-slate-500">
                                @if($q !== '')
                                    No sales match your search.
                                @else
                                    No sales returned (empty database or connection error).
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sales->hasPages())
            <div class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] px-4 py-3">
                {{ $sales->links() }}
            </div>
        @endif
    </div>
@endsection
