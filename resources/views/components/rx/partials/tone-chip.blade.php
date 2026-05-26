@props(['item' => []])

@php
    $tone = (string) ($item['tone'] ?? '');
@endphp

<span
    {{ $attributes->merge(['class' => \App\Support\Rx\RxColumnTone::chipClass($tone).' inline-flex max-w-full']) }}
    title="{{ $item['description'] ?? '' }}"
>
    <span class="h-2 w-2 rounded-sm shrink-0 serv-rx-chip-swatch serv-rx-chip-swatch--{{ $tone }}" aria-hidden="true"></span>
    <span class="truncate">{{ $item['label'] ?? '' }}</span>
</span>
