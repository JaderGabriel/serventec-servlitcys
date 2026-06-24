@props([
    'icon' => null,
    'tone' => 'slate',
    'open' => false,
])

@php
    $iconTone = match ($tone) {
        'blue' => 'text-blue-600 dark:text-blue-400',
        'violet' => 'text-violet-600 dark:text-violet-400',
        'sky' => 'text-sky-600 dark:text-sky-400',
        default => 'text-slate-500 dark:text-slate-400',
    };
    $labelTone = match ($tone) {
        'blue' => 'text-blue-900 dark:text-blue-200',
        'violet' => 'text-violet-900 dark:text-violet-200',
        'sky' => 'text-sky-900 dark:text-sky-200',
        default => 'text-slate-700 dark:text-slate-300',
    };
@endphp

<div
    x-data="{ subOpen: @js((bool) $open) }"
    {{ $attributes->merge(['class' => 'mx-1']) }}
>
    <button
        type="button"
        @click.stop="subOpen = ! subOpen"
        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-slate-50 dark:hover:bg-gray-800/80 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
        :aria-expanded="subOpen"
    >
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-3.5 w-3.5 shrink-0 {{ $iconTone }}" />
        @endif
        <span class="min-w-0 flex-1 text-[11px] font-semibold uppercase tracking-wide {{ $labelTone }}">{{ $slot }}</span>
        <svg
            class="h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform duration-150"
            :class="{ 'rotate-180': subOpen }"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>
    <div
        x-show="subOpen"
        x-cloak
        class="border-s-2 border-slate-200/90 ms-3.5 mb-0.5 space-y-0.5 ps-1.5 dark:border-gray-600/90"
    >
        {{ $links ?? '' }}
    </div>
</div>
