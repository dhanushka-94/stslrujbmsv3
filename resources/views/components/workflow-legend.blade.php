@props([
    'activeCategory' => null,
    'filterRoute' => null,
    'filterParams' => [],
])

@php
    $entries = \App\Support\CategoryAppearance::legendEntries();
    $activeCategory = filled($activeCategory) ? (string) $activeCategory : null;
    $canFilter = filled($filterRoute);
    $baseParams = is_array($filterParams) ? $filterParams : [];
@endphp
<details {{ $attributes->merge(['class' => 'group rounded-lg border border-slate-200/90 bg-slate-50/60 dark:border-slate-600/70 dark:bg-slate-800/35']) }} @if($activeCategory) open @endif>
    <summary class="cursor-pointer list-none px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 [&::-webkit-details-marker]:hidden">
        <span class="inline-flex flex-wrap items-center gap-2">
            <span class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Category colors</span>
            <span class="text-slate-400 dark:text-slate-500">({{ count($entries) }} categories)</span>
            @if($activeCategory)
                <span class="rounded-full bg-[var(--color-studio-primary)]/10 px-2 py-0.5 text-[10px] font-semibold text-[var(--color-studio-primary)] dark:bg-[var(--color-studio-accent)]/15 dark:text-[var(--color-studio-accent)]">
                    Filter: {{ \App\Support\CategoryAppearance::labelForCanonicalKey($activeCategory) ?? $activeCategory }}
                </span>
            @endif
            <span class="text-slate-400 group-open:rotate-180 transition-transform" aria-hidden="true">▾</span>
        </span>
    </summary>
    <div class="border-t border-slate-200/80 px-3 py-2.5 dark:border-slate-600/70" role="list" aria-label="Category color legend">
        @if($canFilter)
            <p class="mb-2 text-[10px] text-slate-500 dark:text-slate-400">Click a category to filter this list. Click again to clear.</p>
        @endif
        <div class="flex flex-wrap gap-1.5">
            @foreach($entries as $entry)
                @php
                    $isActive = $activeCategory === $entry['key'];
                    $href = null;
                    if ($canFilter) {
                        $params = $baseParams;
                        if ($isActive) {
                            unset($params['category']);
                        } else {
                            $params['category'] = $entry['key'];
                        }
                        $href = route($filterRoute, array_filter(
                            $params,
                            fn ($v) => $v !== null && $v !== ''
                        ));
                    }
                @endphp
                @if($href)
                    <a
                        href="{{ $href }}"
                        role="listitem"
                        @class([
                            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-studio-primary)]/40',
                            $entry['badge'],
                            'ring-2 ring-offset-1 ring-[var(--color-studio-primary)] dark:ring-[var(--color-studio-accent)] dark:ring-offset-slate-900' => $isActive,
                            'hover:brightness-95 dark:hover:brightness-110' => ! $isActive,
                        ])
                        title="{{ $isActive ? 'Clear filter: '.$entry['label'] : 'Filter by '.$entry['label'] }}"
                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                    >{{ $entry['label'] }}</a>
                @else
                    <span role="listitem" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium {{ $entry['badge'] }}" title="{{ $entry['label'] }}">
                        {{ $entry['label'] }}
                    </span>
                @endif
            @endforeach
        </div>
    </div>
</details>
