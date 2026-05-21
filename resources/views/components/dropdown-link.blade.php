@props(['icon' => null])

<a {{ $attributes->merge(['class' => 'group flex w-full items-center gap-2.5 px-3 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 transition duration-150 ease-in-out rounded-md mx-1']) }}>
    @if ($icon)
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 group-hover:bg-white group-hover:text-teal-700 dark:bg-gray-800 dark:text-gray-300 dark:group-hover:bg-gray-700 dark:group-hover:text-teal-300 transition-colors">
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        </span>
    @endif
    <span class="min-w-0 flex-1">{{ $slot }}</span>
</a>
