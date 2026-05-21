@props(['icon' => null])

<a @click="$dispatch('close-dropdown')" {{ $attributes->merge(['class' => 'group mx-1 flex w-[calc(100%-0.5rem)] items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-start text-sm font-medium leading-snug text-gray-700 dark:text-gray-200 hover:bg-slate-50 dark:hover:bg-gray-800/90 focus:outline-none focus:bg-slate-50 dark:focus:bg-gray-800/90 transition-colors duration-150']) }}>
    @if ($icon)
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600 group-hover:bg-teal-50 group-hover:text-teal-700 dark:bg-gray-800 dark:text-gray-300 dark:group-hover:bg-teal-950/60 dark:group-hover:text-teal-300 transition-colors">
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        </span>
    @endif
    <span class="min-w-0 flex-1">{{ $slot }}</span>
</a>
