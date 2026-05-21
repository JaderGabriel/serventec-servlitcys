@props([
    'title',
    'tone' => 'teal',
])

@php
    $panelClass = match ($tone) {
        'rose' => 'serv-panel serv-panel--rose',
        default => 'serv-panel serv-panel--info',
    };
    $titleClass = match ($tone) {
        'rose' => 'text-rose-950 dark:text-rose-100',
        default => 'text-teal-950 dark:text-teal-100',
    };
    $bodyClass = match ($tone) {
        'rose' => 'text-rose-900/95 dark:text-rose-200/95',
        default => 'text-teal-900/95 dark:text-teal-200/95',
    };
    $metaClass = match ($tone) {
        'rose' => 'text-rose-800/90 dark:text-rose-300/90',
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
