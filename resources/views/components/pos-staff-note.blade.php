@props([
    'note' => null,
    'compact' => false,
])

@php
    $text = \App\Models\Job::normalizePosStaffNote($note);
@endphp

@if(filled($text))
    <div {{ $attributes->class([
        'rounded-lg border border-violet-200/80 bg-violet-50/60 text-violet-950 dark:border-violet-800/50 dark:bg-violet-950/25 dark:text-violet-100',
        'px-2.5 py-2 text-xs' => $compact,
        'px-3 py-2.5 text-sm' => ! $compact,
    ]) }}>
        <span class="font-semibold text-violet-900 dark:text-violet-200">Staff note</span>
        <p @class([
            'mt-1 whitespace-pre-wrap text-violet-950/95 dark:text-violet-50/95',
            'line-clamp-3' => $compact,
        ])>{{ $text }}</p>
    </div>
@endif
