@props([
    'title',
    'tone' => 'teal',
])

@php
    $panelClass = match ($tone) {
        'rose' => 'serv-panel serv-panel--rose',
        'sky' => 'serv-panel serv-panel--sky',
        'amber' => 'serv-panel serv-panel--amber',
        'emerald' => 'serv-panel serv-panel--emerald',
        default => 'serv-panel serv-panel--info',
    };
    $titleClass = match ($tone) {
        'rose' => 'text-rose-950 dark:text-rose-100',
        'sky' => 'text-sky-950 dark:text-sky-100',
        'amber' => 'text-amber-950 dark:text-amber-100',
        'emerald' => 'text-emerald-950 dark:text-emerald-100',
        default => 'text-teal-950 dark:text-teal-100',
    };
    $bodyClass = match ($tone) {
        'rose' => 'text-rose-900/95 dark:text-rose-200/95',
        'sky' => 'text-sky-900/95 dark:text-sky-200/95',
        'amber' => 'text-amber-900/95 dark:text-amber-200/95',
        'emerald' => 'text-emerald-900/95 dark:text-emerald-200/95',
        default => 'text-teal-900/95 dark:text-teal-200/95',
    };
    $metaClass = match ($tone) {
        'rose' => 'text-rose-800/90 dark:text-rose-300/90',
        'sky' => 'text-sky-800/90 dark:text-sky-300/90',
        'amber' => 'text-amber-800/90 dark:text-amber-300/90',
        'emerald' => 'text-emerald-800/90 dark:text-emerald-300/90',
        default => 'text-teal-800/90 dark:text-teal-300/90',
    };
@endphp

<div {{ $attributes->merge(['class' => $panelClass.' px-4 py-3 text-sm space-y-2 flex-1 min-w-0']) }}>
    <h2 class="font-semibold font-display {{ $titleClass }}">{{ $title }}</h2>
    @if (! $slot->isEmpty())
        <div class="leading-relaxed {{ $bodyClass }}">{{ $slot }}</div>
    @endif
    @if (isset($meta) && ! $meta->isEmpty())
        <p class="text-xs {{ $metaClass }}">{{ $meta }}</p>
    @endif
</div>
