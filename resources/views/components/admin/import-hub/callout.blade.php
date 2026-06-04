@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $styles = [
        'info' => 'border-slate-200/90 bg-slate-50/90 text-slate-900 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-100',
        'accent' => 'border-violet-200 bg-violet-50/60 text-violet-950 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-100',
        'warning' => 'border-amber-200/90 bg-amber-50/90 text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/25 dark:text-amber-100',
        'danger' => 'border-rose-200/90 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100',
        'success' => 'border-emerald-200/90 bg-emerald-50/90 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100',
    ];
    $box = $styles[$variant] ?? $styles['info'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm '.$box]) }}>
    @if (filled($title))
        <p class="font-semibold">{{ $title }}</p>
    @endif
    <div @class(['text-xs leading-relaxed', 'mt-2' => filled($title)])>{{ $slot }}</div>
</div>
