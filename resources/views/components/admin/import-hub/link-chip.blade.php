@props(['tone' => 'indigo'])

@php
    use App\Support\Admin\AdminVisualCatalog;

    $classes = AdminVisualCatalog::chipClasses($tone);
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
