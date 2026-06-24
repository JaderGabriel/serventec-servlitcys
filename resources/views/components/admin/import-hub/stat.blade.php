@props([
    'label' => '',
    'value' => '—',
    'hint' => null,
    'tone' => 'neutral',
])

@php
    $tones = [
        'amber' => 'border-amber-200/90 dark:border-amber-800/50 bg-amber-50/40 dark:bg-amber-950/20',
        'violet' => 'border-violet-200 dark:border-violet-800/60 bg-violet-50/30 dark:bg-violet-950/20',
        'emerald' => 'border-emerald-200/80 dark:border-emerald-800/50 bg-emerald-50/30 dark:bg-emerald-950/15',
        'sky' => 'border-sky-200/80 dark:border-sky-800/50 bg-sky-50/30 dark:bg-sky-950/15',
        'rose' => 'border-rose-200/80 dark:border-rose-800/50 bg-rose-50/30 dark:bg-rose-950/15',
        'indigo' => 'border-sky-200/80 dark:border-sky-800/50',
        'slate' => 'border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/30',
        'neutral' => 'border-gray-200 dark:border-gray-700',
    ];
    $labelTone = [
        'amber' => 'text-amber-800 dark:text-amber-300',
        'violet' => 'text-violet-800 dark:text-violet-300',
        'emerald' => 'text-emerald-800 dark:text-emerald-300',
        'sky' => 'text-sky-800 dark:text-sky-300',
        'rose' => 'text-rose-800 dark:text-rose-300',
        'indigo' => 'text-sky-800 dark:text-sky-300',
        'slate' => 'text-slate-600 dark:text-slate-400',
        'neutral' => 'text-gray-500 dark:text-gray-400',
    ];
    $box = $tones[$tone] ?? $tones['neutral'];
    $labelClass = $labelTone[$tone] ?? $labelTone['neutral'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border p-4 '.$box]) }}>
    <p class="text-[11px] font-semibold uppercase {{ $labelClass }}">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</p>
    @if (filled($hint))
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $hint }}</p>
    @endif
    @isset($footer)
        <div class="mt-2">{{ $footer }}</div>
    @endisset
</div>
