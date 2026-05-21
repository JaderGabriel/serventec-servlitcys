@props([
    'icon' => null,
    'tone' => 'slate',
])

@php
    $iconTone = match ($tone) {
        'teal' => 'text-teal-600 dark:text-teal-400',
        'indigo' => 'text-indigo-600 dark:text-indigo-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
    $labelTone = match ($tone) {
        'teal' => 'text-teal-800/90 dark:text-teal-300/90',
        'indigo' => 'text-indigo-800/90 dark:text-indigo-300/90',
        'amber' => 'text-amber-900/90 dark:text-amber-200/90',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5 ps-3 pe-3 pt-2 pb-0.5']) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" class="h-3.5 w-3.5 shrink-0 {{ $iconTone }}" />
    @endif
    <p class="text-[10px] font-semibold uppercase tracking-wide {{ $labelTone }}">{{ $slot }}</p>
</div>
