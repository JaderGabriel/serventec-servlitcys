@props(['active' => false, 'icon' => null])

@php
$base = 'inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition';
$classes = $active
    ? $base.' ring-2 ring-indigo-500'
    : $base;
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" class="h-4 w-4 shrink-0 opacity-85" />
    @endif
    <span>{{ $slot }}</span>
</a>
