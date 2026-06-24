@props(['active', 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'flex w-full items-center gap-3 ps-3 pe-4 py-2 border-l-4 border-blue-600 dark:border-blue-500 text-start text-sm font-medium text-blue-900 dark:text-blue-100 bg-blue-50/90 dark:bg-blue-950/40 focus:outline-none transition-colors duration-150'
            : 'flex w-full items-center gap-3 ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800/70 focus:outline-none focus:text-gray-900 dark:focus:text-gray-100 focus:bg-gray-50 dark:focus:bg-gray-800/70 transition-colors duration-150';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <span @class([
            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
            'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' => $active ?? false,
            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => ! ($active ?? false),
        ])>
            <x-ui.icon :name="$icon" class="h-[1.125rem] w-[1.125rem]" />
        </span>
    @endif
    <span class="min-w-0 flex-1 leading-snug">{{ $slot }}</span>
</a>
