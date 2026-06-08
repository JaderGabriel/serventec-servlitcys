@props([
    'icon' => null,
    'title' => '',
    'subtitle' => null,
    'align' => 'left',
])

@php
    $alignClass = $align === 'right' ? 'text-right items-end' : 'text-left items-start';
@endphp

<div class="inline-flex flex-col gap-0.5 {{ $alignClass }}">
    <span class="inline-flex items-center gap-1.5">
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-3.5 w-3.5 shrink-0 opacity-80" />
        @endif
        <span>{{ $title }}</span>
    </span>
    @if ($subtitle)
        <span class="text-[10px] font-normal normal-case tracking-normal opacity-75">{{ $subtitle }}</span>
    @endif
</div>
