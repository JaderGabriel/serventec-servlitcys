@props(['tab', 'label' => null])

@php
    $labels = \App\Support\Dashboard\AnalyticsTabCatalog::labels();
    $text = $label ?? ($labels[$tab] ?? $tab);
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => 'serv-inline-tab-link']) }}
    x-on:click="$dispatch('set-analytics-tab', '{{ $tab }}')"
>
    {{ $text }}
</button>
