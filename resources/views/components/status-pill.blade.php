@props(['status' => 'neutral', 'label' => ''])

@php
    $class = match ((string) $status) {
        'success' => 'serv-status-pill serv-status-pill--success',
        'warning' => 'serv-status-pill serv-status-pill--warning',
        'danger' => 'serv-status-pill serv-status-pill--danger',
        'info' => 'serv-status-pill serv-status-pill--info',
        default => 'serv-status-pill serv-status-pill--neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => $class]) }}>
    {{ $label !== '' ? $label : $slot }}
</span>
