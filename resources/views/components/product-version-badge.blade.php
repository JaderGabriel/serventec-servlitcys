@props(['class' => ''])

@php
    $badge = \App\Support\Product\ProductVersion::badge();
@endphp

@if (($badge['version'] ?? '') !== '' || ($badge['release_tag'] ?? '') !== '')
    <span
        {{ $attributes->merge([
            'class' => 'serv-product-version serv-product-version--'.$badge['tone'].' '.$class,
        ]) }}
        title="{{ $badge['title'] }}"
    >
        <span class="serv-product-version__dot" aria-hidden="true"></span>
        <span class="serv-product-version__label">{{ $badge['display_label'] }}</span>
        @if (filled($badge['revision_label'] ?? null))
            <span class="serv-product-version__date">{{ $badge['revision_label'] }}</span>
        @endif
    </span>
@endif
