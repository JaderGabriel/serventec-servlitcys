@props(['active' => false, 'icon' => null])

@php
$base = 'inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg border border-transparent text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 hover:bg-white/80 dark:hover:text-slate-100 dark:hover:bg-slate-800/60 transition';
$classes = $active
    ? $base.' serv-tab--active border-blue-200 bg-white text-blue-900 shadow-sm dark:border-blue-800 dark:bg-slate-900 dark:text-blue-100'
    : $base;
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" class="h-4 w-4 shrink-0 opacity-85" />
    @endif
    <span>{{ $slot }}</span>
</a>
