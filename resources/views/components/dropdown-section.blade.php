@props([
    'icon' => null,
    'tone' => 'slate',
])

@php
    $toneClasses = match ($tone) {
        'teal' => 'text-teal-800 dark:text-teal-200 bg-teal-50/80 dark:bg-teal-950/40',
        'indigo' => 'text-indigo-800 dark:text-indigo-200 bg-indigo-50/80 dark:bg-indigo-950/40',
        'amber' => 'text-amber-900 dark:text-amber-100 bg-amber-50/80 dark:bg-amber-950/40',
        default => 'text-gray-500 dark:text-gray-400 bg-gray-50/80 dark:bg-gray-800/50',
    };
    $iconTone = match ($tone) {
        'teal' => 'text-teal-600 dark:text-teal-400',
        'indigo' => 'text-indigo-600 dark:text-indigo-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div {{ $attributes->merge(['class' => 'px-3 py-2']) }}>
    <div class="flex items-center gap-2 rounded-md px-2 py-1.5 {{ $toneClasses }}">
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-4 w-4 shrink-0 {{ $iconTone }}" />
        @endif
        <p class="text-[11px] font-semibold uppercase tracking-wider">{{ $slot }}</p>
    </div>
</div>
