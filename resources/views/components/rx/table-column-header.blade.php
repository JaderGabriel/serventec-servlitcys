@props([
    'icon' => null,
    'title' => '',
    'subtitle' => null,
    'align' => 'left',
    'tone' => 'neutral',
])

@php
    $alignClass = $align === 'right' ? 'text-right items-end' : 'text-left items-start';
    $iconRowClass = $align === 'right' ? 'flex-row-reverse' : '';
@endphp

<div class="inline-flex flex-col gap-0.5 {{ $alignClass }}">
    <span class="inline-flex items-center gap-1.5 {{ $iconRowClass }}">
        @if ($icon)
            <span class="serv-rx-col-icon serv-rx-col-icon--{{ $tone }}" aria-hidden="true">
                <x-ui.icon :name="$icon" class="h-3 w-3" />
            </span>
        @endif
        <span>{{ $title }}</span>
    </span>
    @if ($subtitle)
        <span class="text-[10px] font-normal normal-case tracking-normal opacity-75">{{ $subtitle }}</span>
    @endif
</div>
