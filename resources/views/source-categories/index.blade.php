@extends('layouts.app')

@section('title', 'Categories (Source)')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
                @include('components.icons', ['name' => 'folder', 'class' => 'w-7 h-7'])
                Categories &amp; subcategories
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Read-only from POS database (studiosalaru_datadb).</p>
        </div>
        <a href="{{ route('source-products.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-[var(--color-studio-primary)]">
            @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
            Products
        </a>
    </div>

    @if($error)
        <div class="mb-4 p-4 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200">{{ $error }}</div>
    @endif

    <div class="space-y-6">
        @forelse($categories as $cat)
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                    <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $cat->code ?? '—' }}</span>
                    <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">{{ $cat->name ?? '—' }}</h2>
                </div>
                @if($cat->subcategories->isNotEmpty())
                    <ul class="divide-y divide-[var(--color-studio-border)] dark:divide-[var(--color-studio-dark-border)]">
                        @foreach($cat->subcategories as $sub)
                            <li class="px-4 py-2.5 flex items-center gap-3">
                                <span class="font-mono text-xs text-slate-500 dark:text-slate-400 w-24 shrink-0">{{ $sub->code ?? '—' }}</span>
                                <span class="text-slate-700 dark:text-slate-300">{{ $sub->name ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No subcategories</p>
                @endif
            </div>
        @empty
            <div class="bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] rounded-lg p-8 text-center text-slate-500">
                No categories found or source database not connected.
            </div>
        @endforelse
    </div>
@endsection
