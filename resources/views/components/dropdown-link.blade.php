@props(['icon' => null])

<a {{ $attributes->merge(['class' => 'group flex w-full items-center gap-2 px-2 py-1 text-start text-xs leading-4 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 transition-colors duration-150 rounded-sm']) }}>
    @if ($icon)
        <span class="flex h-5 w-5 shrink-0 items-center justify-center text-gray-500 group-hover:text-teal-700 dark:text-gray-400 dark:group-hover:text-teal-300 transition-colors">
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        </span>
    @endif
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
</a>
