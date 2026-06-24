@props(['icon' => null, 'active' => false])

@php
    $classes = ($active ?? false)
        ? 'bg-blue-50/90 text-blue-900 dark:bg-blue-950/50 dark:text-blue-100'
        : 'text-gray-600 hover:bg-slate-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800/80 dark:hover:text-gray-100';
@endphp

<a
    @click="$dispatch('close-dropdown')"
    {{ $attributes->merge(['class' => 'group flex w-full items-center gap-2 rounded-md px-2 py-1 text-start text-xs font-medium leading-snug transition-colors duration-150 '.$classes]) }}
>
    @if ($icon)
        <x-ui.icon :name="$icon" class="h-3.5 w-3.5 shrink-0 opacity-70 group-hover:opacity-100" />
    @endif
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
</a>
