@props(['item' => [], 'compact' => false])

@php
    $tone = (string) ($item['tone'] ?? '');
    $label = (string) ($item['label'] ?? '');
@endphp

@if ($compact)
    <span
        class="inline-flex items-center justify-center"
        title="{{ $label !== '' ? $label : ($item['description'] ?? '') }}"
    >
        <span class="h-2.5 w-2.5 rounded-sm serv-rx-chip-swatch serv-rx-chip-swatch--{{ $tone }}" aria-hidden="true"></span>
        <span class="sr-only">{{ $label }}</span>
    </span>
@else
    <span
        {{ $attributes->merge(['class' => \App\Support\Rx\RxColumnTone::chipClass($tone).' inline-flex max-w-full']) }}
        title="{{ $item['description'] ?? '' }}"
    >
        <span class="h-2 w-2 rounded-sm shrink-0 serv-rx-chip-swatch serv-rx-chip-swatch--{{ $tone }}" aria-hidden="true"></span>
        <span class="truncate">{{ $label }}</span>
    </span>
@endif
