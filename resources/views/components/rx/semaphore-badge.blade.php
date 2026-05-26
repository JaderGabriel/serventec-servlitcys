@props([
    'status' => 'neutral',
    'label' => '',
    'title' => '',
])

@php
    $pillStatus = match ((string) $status) {
        'green' => 'success',
        'yellow' => 'warning',
        'red' => 'danger',
        'error' => 'neutral',
        default => 'neutral',
    };
    $displayLabel = $label !== '' ? $label : '—';
@endphp

<span
    class="inline-flex max-w-[11rem]"
    @if ($title !== '') title="{{ $title }}" @endif
>
    <x-status-pill :status="$pillStatus" :label="$displayLabel" class="whitespace-normal text-left leading-snug" />
</span>
