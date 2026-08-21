@props([
    'names' => [],
    'compact' => false,
])

@php
    $list = collect($names)
        ->map(fn ($n) => trim((string) $n))
        ->filter(fn ($n) => $n !== '')
        ->values()
        ->all();
@endphp

@if(count($list) > 0)
    <div {{ $attributes->class([
        'rounded-lg border border-slate-200/90 bg-slate-50/90 dark:border-slate-600/80 dark:bg-slate-800/50',
        'px-2.5 py-2' => $compact,
        'px-4 py-3 mt-0' => ! $compact,
    ]) }}>
        <p @class([
            'font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300',
            'text-[10px]' => $compact,
            'text-[11px] mb-2' => ! $compact,
        ])>All job items</p>
        <ol @class([
            'm-0 list-decimal space-y-0.5 pl-4 text-slate-800 dark:text-slate-100',
            'text-xs leading-snug' => $compact,
            'text-sm leading-relaxed' => ! $compact,
        ])>
            @foreach($list as $name)
                <li>{{ $name }}</li>
            @endforeach
        </ol>
    </div>
@endif
