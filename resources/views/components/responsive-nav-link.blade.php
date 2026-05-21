@props(['active', 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'flex w-full items-center gap-2.5 ps-3 pe-4 py-2 border-l-4 border-teal-600 dark:border-teal-500 text-start text-base font-medium text-teal-900 dark:text-teal-200 bg-teal-50 dark:bg-teal-950/40 focus:outline-none transition duration-150 ease-in-out'
            : 'flex w-full items-center gap-2.5 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none focus:text-gray-800 dark:focus:text-gray-200 focus:bg-gray-50 dark:focus:bg-gray-700 focus:border-gray-300 dark:focus:border-gray-600 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" class="h-5 w-5 shrink-0 opacity-80" />
    @endif
    <span class="min-w-0 flex-1">{{ $slot }}</span>
</a>
