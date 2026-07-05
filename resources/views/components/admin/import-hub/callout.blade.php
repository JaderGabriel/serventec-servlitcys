@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $styles = [
        'info' => 'border-slate-200 bg-slate-50 text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100',
        'accent' => 'border-violet-200 bg-violet-50 text-violet-950 dark:border-violet-800 dark:bg-slate-800 dark:text-violet-100',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-slate-800 dark:text-amber-100',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-slate-800 dark:text-rose-100',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-slate-800 dark:text-emerald-100',
    ];
    $box = $styles[$variant] ?? $styles['info'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm '.$box]) }}>
    @if (filled($title))
        <p class="font-semibold">{{ $title }}</p>
    @endif
    <div @class(['text-xs leading-relaxed', 'mt-2' => filled($title)])>{{ $slot }}</div>
</div>
