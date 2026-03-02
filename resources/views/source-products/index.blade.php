@extends('layouts.app')

@section('title', 'Products (Source)')

@section('content')
    <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] mb-2 flex items-center gap-2">
        @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-7 h-7'])
        Products with category &amp; subcategory
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Read-only from studiosalaru_datadb.</p>

    @if($error)
        <div class="mb-4 p-4 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200">{{ $error }}</div>
    @endif

    <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="text-left p-3">Code</th>
                        <th class="text-left p-3">Product name</th>
                        <th class="text-left p-3">Category</th>
                        <th class="text-left p-3">Subcategory</th>
                        <th class="text-right p-3">Price</th>
                        <th class="text-right p-3">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                        <tr class="border-t border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                            <td class="p-3 font-mono">{{ $p->code ?? '—' }}</td>
                            <td class="p-3">{{ $p->name ?? '—' }}</td>
                            <td class="p-3">{{ $p->category_name ?? '—' }}</td>
                            <td class="p-3">{{ $p->subcategory_name ?? '—' }}</td>
                            <td class="p-3 text-right">{{ $p->price !== null ? number_format((float) $p->price, 2) : '—' }}</td>
                            <td class="p-3 text-right">{{ $p->quantity !== null ? number_format((float) $p->quantity, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-slate-500">No products or source DB not connected.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
