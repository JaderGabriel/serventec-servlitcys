@props(['status' => 'unknown'])

@php
    $classes = match ($status) {
        'healthy' => 'module-monitor-badge module-monitor-badge--healthy',
        'warning' => 'module-monitor-badge module-monitor-badge--warning',
        'critical' => 'module-monitor-badge module-monitor-badge--critical',
        default => 'module-monitor-badge module-monitor-badge--unknown',
    };
    $label = match ($status) {
        'healthy' => __('Saudável'),
        'warning' => __('Atenção'),
        'critical' => __('Crítico'),
        default => __('Sem dados'),
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    <span class="module-monitor-badge__dot" aria-hidden="true"></span>
    {{ $label }}
</span>
