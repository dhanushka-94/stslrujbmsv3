@extends('layouts.app')

@section('title', 'Block products')

@section('content')
    <div class="max-w-3xl mx-auto">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
<h1 class="text-2xl font-semibold text-[var(--color-studio-primary)] dark:text-[var(--color-studio-accent)] flex items-center gap-2">
                @include('components.icons', ['name' => 'squares-2x2', 'class' => 'w-7 h-7'])
                Block products
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Hide specific products (job items) in all jobs</p>
        </div>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-[var(--color-studio-primary)] dark:hover:text-[var(--color-studio-accent)]">
            @include('components.icons', ['name' => 'arrow-left', 'class' => 'w-4 h-4'])
            Dashboard
        </a>
        </div>

        {{-- Info card --}}
        <div class="mb-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-600">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                <strong>System-wide:</strong> Any product you block here will be hidden as a job item in <strong>every job</strong>. Uncheck to show that product again.
            </p>
        </div>

        @if($productsByCategory->isEmpty())
            <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-6 text-amber-800 dark:text-amber-200">
                <p class="font-medium">No products available</p>
                <p class="text-sm mt-1 opacity-90">Configure the source database (<code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">DB_SOURCE_DATABASE</code> in .env) and ensure the POS has products.</p>
            </div>
        @else
            <form action="{{ route('settings.block-products.store') }}" method="POST" id="block-products-form">
                @csrf

                {{-- Quick actions --}}
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <button type="button" onclick="document.querySelectorAll('#block-products-form input[type=checkbox]').forEach(c => c.checked = true)" class="text-sm px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                        Select all
                    </button>
                    <button type="button" onclick="document.querySelectorAll('#block-products-form input[type=checkbox]').forEach(c => c.checked = false)" class="text-sm px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                        Clear all
                    </button>
                    <span class="text-xs text-slate-500 dark:text-slate-400 ml-auto">
                        <span id="blocked-count">{{ count($blockedProductIds) }}</span> blocked
                    </span>
                </div>

                {{-- Product cards by category --}}
                <div class="space-y-4 mb-8">
                    @foreach($productsByCategory as $group)
                        <div class="rounded-xl border border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)] bg-[var(--color-studio-bg-card)] dark:bg-[var(--color-studio-dark-card)] overflow-hidden shadow-sm">
                            <div class="px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-b border-[var(--color-studio-border)] dark:border-[var(--color-studio-dark-border)]">
                                <h2 class="font-semibold text-slate-800 dark:text-slate-100">{{ $group->category_name }}</h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ count($group->products) }} product(s)</p>
                            </div>
                            <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                @foreach($group->products as $product)
                                    <li class="px-4 py-2.5 flex items-center gap-3 bg-white dark:bg-slate-800/30 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" id="prod-{{ $product->id }}" class="rounded border-slate-300 dark:border-slate-600 text-[var(--color-studio-primary)] focus:ring-[var(--color-studio-primary)]"
                                            {{ in_array($product->id, $blockedProductIds, true) ? 'checked' : '' }}>
                                        <label for="prod-{{ $product->id }}" class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer select-none flex-1">{{ $product->name }}</label>
                                        @if($product->code)
                                            <span class="text-xs font-mono text-slate-400 dark:text-slate-500">{{ $product->code }}</span>
                                        @endif
                                        @if($product->subcategory_name)
                                            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $product->subcategory_name }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[var(--color-studio-primary)] hover:opacity-90 text-white font-medium text-sm shadow-sm">
                        @include('components.icons', ['name' => 'document-check', 'class' => 'w-4 h-4'])
                        Save blocked products
                    </button>
                    <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-[var(--color-studio-primary)]">@include('components.icons', ['name' => 'briefcase', 'class' => 'w-4 h-4']) View jobs</a>
                </div>
            </form>

            <script>
                document.getElementById('block-products-form')?.addEventListener('change', function () {
                    var n = this.querySelectorAll('input[type=checkbox]:checked').length;
                    var el = document.getElementById('blocked-count');
                    if (el) el.textContent = n;
                });
            </script>
        @endif
    </div>
@endsection
