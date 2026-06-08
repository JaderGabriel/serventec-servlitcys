@props(['item' => [], 'compact' => false, 'icon' => null])

@php
    $tone = (string) ($item['tone'] ?? '');
    $label = (string) ($item['label'] ?? '');
    $icon = $icon ?? ($item['icon'] ?? null);
@endphp

@if ($compact)
    <span
        class="inline-flex items-center justify-center"
        title="{{ $label !== '' ? $label : ($item['description'] ?? '') }}"
    >
        @if ($icon)
            <span class="serv-rx-col-icon serv-rx-col-icon--{{ $tone }}" aria-hidden="true">
                <x-ui.icon :name="$icon" class="h-3 w-3" />
            </span>
        @else
            <span class="h-2.5 w-2.5 rounded-sm serv-rx-chip-swatch serv-rx-chip-swatch--{{ $tone }}" aria-hidden="true"></span>
        @endif
        <span class="sr-only">{{ $label }}</span>
    </span>
@else
    <span
        {{ $attributes->merge(['class' => \App\Support\Rx\RxColumnTone::chipClass($tone).' inline-flex max-w-full items-center gap-1.5']) }}
        title="{{ $item['description'] ?? '' }}"
    >
        @if ($icon)
            <span class="serv-rx-col-icon serv-rx-col-icon--{{ $tone }} shrink-0" aria-hidden="true">
                <x-ui.icon :name="$icon" class="h-3 w-3" />
            </span>
        @else
            <span class="h-2 w-2 rounded-sm shrink-0 serv-rx-chip-swatch serv-rx-chip-swatch--{{ $tone }}" aria-hidden="true"></span>
        @endif
        <span class="truncate">{{ $label }}</span>
    </span>
@endif
