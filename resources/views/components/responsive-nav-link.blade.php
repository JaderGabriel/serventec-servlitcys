@props(['active', 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'flex w-full items-center gap-2 ps-3 pe-3 py-1.5 border-l-[3px] border-teal-600 dark:border-teal-500 text-start text-sm font-medium text-teal-900 dark:text-teal-200 bg-teal-50/80 dark:bg-teal-950/40 focus:outline-none transition-colors duration-150'
            : 'flex w-full items-center gap-2 ps-3 pe-3 py-1.5 border-l-[3px] border-transparent text-start text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/60 focus:outline-none focus:text-gray-800 dark:focus:text-gray-200 focus:bg-gray-50 dark:focus:bg-gray-800/60 transition-colors duration-150';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <span @class([
            'flex h-5 w-5 shrink-0 items-center justify-center',
            'text-teal-700 dark:text-teal-300' => $active ?? false,
            'text-gray-500 dark:text-gray-400' => ! ($active ?? false),
        ])>
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        </span>
    @endif
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
</a>
