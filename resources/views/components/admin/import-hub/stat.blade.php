@props([
    'label' => '',
    'value' => '—',
    'hint' => null,
    'tone' => 'neutral',
])

@php
    $tones = [
        'violet' => 'border-violet-200 dark:border-violet-800/60 bg-violet-50/30 dark:bg-violet-950/20',
        'emerald' => 'border-emerald-200/80 dark:border-emerald-800/50',
        'neutral' => 'border-gray-200 dark:border-gray-700',
    ];
    $labelTone = [
        'violet' => 'text-violet-800 dark:text-violet-300',
        'emerald' => 'text-gray-500 dark:text-gray-400',
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
