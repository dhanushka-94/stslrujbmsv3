@props([
    'name',
    'categoryName' => null,
    'profile' => null,
    'statusLine' => null,
    'index' => null,
    'compact' => false,
])

@php
    $tokens = \App\Support\CategoryAppearance::tokensForCategoryName($categoryName);
@endphp

<li {{ $attributes->merge(['class' => 'rounded-md px-2.5 py-2 ' . $tokens['row'] . ($compact ? ' text-xs' : '')]) }}>
    <div class="flex flex-wrap items-center gap-1.5">
        @if($index !== null)
            <span class="font-mono tabular-nums text-slate-400 select-none text-[11px]">#{{ $index }}</span>
        @endif
        @if(filled($categoryName))
            <span class="inline-flex shrink-0 max-w-[14rem] truncate rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $tokens['pill'] }}" title="{{ $categoryName }}">
                {{ $categoryName }}
            </span>
        @else
            <span class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $tokens['badge'] }}">
                {{ $tokens['label'] }}
            </span>
        @endif
        <span class="inline-flex shrink-0 rounded px-1 py-0.5 text-[9px] font-medium uppercase tracking-wide text-slate-500 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/50 dark:text-slate-400 dark:ring-slate-600/80">
            {{ $tokens['short'] }}
        </span>
    </div>
    <span class="mt-1 block break-words font-medium text-slate-900 dark:text-slate-100 leading-snug">{{ $name ?: '—' }}</span>
    @if(filled($statusLine))
        <span class="mt-0.5 block text-[11px] leading-snug text-slate-600 dark:text-slate-400">{{ $statusLine }}</span>
    @endif
</li>
