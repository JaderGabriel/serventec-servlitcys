@props([
    'bars' => [],
    'tone' => 'sky',
])
@php
    $fill = 'clio-dist__fill--'.(in_array($tone, ['emerald', 'amber', 'rose', 'sky', 'slate'], true) ? $tone : 'sky');
@endphp
@foreach ($bars as $bar)
    <div class="clio-dist__row">
        <div class="clio-dist__head">
            <span class="clio-dist__label">{{ $bar['label'] }}</span>
            <span class="clio-dist__count">{{ number_format((int) ($bar['count'] ?? 0)) }} · {{ number_format((float) ($bar['pct'] ?? 0), 0) }}%</span>
        </div>
        <div class="clio-dist__track">
            <div class="clio-dist__fill {{ $fill }}" style="width: {{ min(100, max(0, (float) ($bar['pct'] ?? 0))) }}%"></div>
        </div>
    </div>
@endforeach
