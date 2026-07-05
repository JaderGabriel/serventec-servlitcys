@props([
    'label' => '',
    'value' => '—',
    'hint' => null,
    'tone' => 'neutral',
])

@php
    $tones = [
        'amber' => 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-slate-800',
        'violet' => 'border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-slate-800',
        'emerald' => 'border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-slate-800',
        'sky' => 'border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-slate-800',
        'rose' => 'border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-slate-800',
        'indigo' => 'border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-slate-800',
        'slate' => 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800',
        'neutral' => 'border-gray-200 dark:border-gray-700 bg-white dark:bg-slate-800',
    ];
    $labelTone = [
        'amber' => 'text-amber-800 dark:text-amber-300',
        'violet' => 'text-violet-800 dark:text-violet-300',
        'emerald' => 'text-emerald-800 dark:text-emerald-300',
        'sky' => 'text-sky-800 dark:text-sky-300',
        'rose' => 'text-rose-800 dark:text-rose-300',
        'indigo' => 'text-indigo-800 dark:text-indigo-300',
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
