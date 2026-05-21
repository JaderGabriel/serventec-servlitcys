@props(['active', 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center gap-1.5 px-1 pt-1 border-b-2 serv-nav-link-active text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center gap-1.5 px-1 pt-1 border-b-2 serv-nav-link-idle text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" class="h-4 w-4 shrink-0 opacity-90" />
    @endif
    <span>{{ $slot }}</span>
</a>
