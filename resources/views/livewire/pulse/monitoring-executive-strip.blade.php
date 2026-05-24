@php
    $kpis = [
        [
            'label' => __('Municípios ativos'),
            'value' => number_format((int) ($ops['ready'] ?? 0)).' / '.number_format((int) ($ops['active'] ?? 0)),
            'hint' => __('Com base i-Educar configurada'),
            'tone' => ($ops['ready'] ?? 0) >= ($ops['active'] ?? 1) ? 'emerald' : 'amber',
            'icon' => 'building',
        ],
        [
            'label' => __('Pedidos (período)'),
            'value' => number_format((int) ($pulse['global_requests'] ?? 0)),
            'hint' => __('Tráfego global na aplicação'),
            'tone' => 'cyan',
            'icon' => 'activity',
        ],
        [
            'label' => __('Excepções'),
            'value' => number_format((int) ($pulse['exceptions'] ?? 0)),
            'hint' => __('Registadas pelo Pulse'),
            'tone' => ($pulse['exceptions'] ?? 0) > 0 ? 'rose' : 'emerald',
            'icon' => 'alert',
        ],
        [
            'label' => __('Pedidos lentos'),
            'value' => number_format((int) ($pulse['slow_requests'] ?? 0)),
            'hint' => ($pulse['max_slow_ms'] ?? null) !== null
                ? __('Pior: :ms ms', ['ms' => number_format((int) $pulse['max_slow_ms'])])
                : __('HTTP acima do limiar'),
            'tone' => ($pulse['slow_requests'] ?? 0) > 0 ? 'amber' : 'slate',
            'icon' => 'clock',
        ],
        [
            'label' => __('SQL lentas (sistema)'),
            'value' => number_format((int) ($pulse['system_slow_queries'] ?? 0)),
            'hint' => __('Base Laravel · limiar PULSE_DB_DIAGNOSTICS_SLOW_MS'),
            'tone' => ($pulse['system_slow_queries'] ?? 0) > 0 ? 'amber' : 'emerald',
            'icon' => 'database',
        ],
        [
            'label' => __('SQL lentas (municípios)'),
            'value' => number_format((int) ($pulse['municipal_slow_queries'] ?? 0)),
            'hint' => ($pulse['municipal_worst_ms'] ?? null) !== null
                ? __('Pior: :ms ms', ['ms' => number_format((int) $pulse['municipal_worst_ms'])])
                : __('Bases i-Educar'),
            'tone' => ($pulse['municipal_slow_queries'] ?? 0) > 0 ? 'rose' : 'emerald',
            'icon' => 'server',
        ],
        [
            'label' => __('Operações lentas'),
            'value' => number_format((int) ($pulse['slow_operations'] ?? 0)),
            'hint' => ($pulse['max_operation_ms'] ?? null) !== null
                ? __('Pior: :ms ms', ['ms' => number_format((int) $pulse['max_operation_ms'])])
                : __('Analytics, RX, sync, PDF'),
            'tone' => ($pulse['slow_operations'] ?? 0) > 0 ? 'violet' : 'slate',
            'icon' => 'queue',
        ],
        [
            'label' => __('Sync / PDF em fila'),
            'value' => number_format((int) ($ops['syncPending'] ?? 0) + (int) ($ops['pdfPending'] ?? 0)),
            'hint' => __(':sync sync · :pdf PDF', [
                'sync' => number_format((int) ($ops['syncPending'] ?? 0)),
                'pdf' => number_format((int) ($ops['pdfPending'] ?? 0)),
            ]),
            'tone' => (($ops['syncPending'] ?? 0) + ($ops['pdfPending'] ?? 0)) > 0 ? 'violet' : 'slate',
            'icon' => 'queue',
        ],
        [
            'label' => __('Sync falhou (24h)'),
            'value' => number_format((int) ($ops['syncFailed'] ?? 0)),
            'hint' => __('Rever fila administrativa'),
            'tone' => ($ops['syncFailed'] ?? 0) > 0 ? 'rose' : 'emerald',
            'icon' => 'cloud',
        ],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'pulse-exec-strip default:col-span-full']) }} wire:poll.15s>
    <div class="pulse-exec-strip__inner">
        <div class="pulse-exec-strip__head">
            <div>
                <p class="pulse-exec-strip__eyebrow">{{ __('Painel executivo') }}</p>
                <p class="pulse-exec-strip__period">{{ __('Período Pulse:') }} {{ $period }}</p>
            </div>
            <div class="pulse-exec-strip__links">
                <a href="{{ route('admin.sync-queue.index') }}" class="pulse-exec-strip__link">{{ __('Fila de sincronização') }}</a>
                <a href="{{ route('cities.index') }}" class="pulse-exec-strip__link">{{ __('Cidades') }}</a>
                <a href="{{ route('dashboard.analytics') }}" class="pulse-exec-strip__link">{{ __('Análise') }}</a>
            </div>
        </div>
        <div class="pulse-exec-kpi-grid">
            @foreach ($kpis as $kpi)
                @php
                    $tone = (string) ($kpi['tone'] ?? 'slate');
                @endphp
                <article class="pulse-exec-kpi pulse-exec-kpi--{{ $tone }}">
                    <p class="pulse-exec-kpi__label">{{ $kpi['label'] }}</p>
                    <p class="pulse-exec-kpi__value">{{ $kpi['value'] }}</p>
                    <p class="pulse-exec-kpi__hint">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </div>
        <p class="pulse-exec-strip__meta">
            {{ __('Bases:') }}
            <span class="font-mono">PG {{ number_format((int) ($ops['pgsql'] ?? 0)) }}</span>
            ·
            <span class="font-mono">MySQL {{ number_format((int) ($ops['mysql'] ?? 0)) }}</span>
            ·
            {{ __('Actualizado') }} {{ $runAt }}
        </p>
    </div>
</div>
