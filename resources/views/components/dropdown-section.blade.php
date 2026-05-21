@props([
    'icon' => null,
    'tone' => 'slate',
])

@php
    $iconTone = match ($tone) {
        'teal' => 'text-teal-600 dark:text-teal-400',
        'violet' => 'text-violet-600 dark:text-violet-400',
        'sky' => 'text-sky-600 dark:text-sky-400',
        'indigo' => 'text-indigo-600 dark:text-indigo-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        default => 'text-slate-500 dark:text-slate-400',
    };
    $labelTone = match ($tone) {
        'teal' => 'text-teal-900 dark:text-teal-200',
        'violet' => 'text-violet-900 dark:text-violet-200',
        'sky' => 'text-sky-900 dark:text-sky-200',
        'indigo' => 'text-indigo-900 dark:text-indigo-200',
        'amber' => 'text-amber-950 dark:text-amber-100',
        default => 'text-slate-600 dark:text-slate-400',
    };
@endphp

<div {{ $attributes->merge(['class' => 'px-3 pt-2.5 pb-1']) }}>
    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider {{ $labelTone }}">
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-4 w-4 shrink-0 {{ $iconTone }}" />
        @endif
        <span>{{ $slot }}</span>
    </p>
</div>
