@props([
    'variant' => 'secondary',
])

@php
    $classes = match ($variant) {
        'primary' => 'inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 shadow-sm',
        default => 'inline-flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium border border-amber-300 dark:border-amber-700 bg-amber-50 text-amber-950 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-950/60',
    };
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => $classes]) }}
    x-on:click="$dispatch('open-modal', 'funding-loss-conditions')"
>
    <svg class="w-4 h-4 shrink-0 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
    </svg>
    {{ $slot->isEmpty() ? __('Condições de perda de recursos') : $slot }}
</button>
