@props([
    'indicator' => [],
    'filters' => null,
    'selectedCity' => null,
    'yearFilterReady' => false,
])

@php
    use App\Support\Dashboard\AnalyticsDockQualityIndicator;

    $q = is_array($indicator) ? $indicator : [];
    $available = (bool) ($q['available'] ?? false);
    $partial = (bool) ($q['partial'] ?? false);
    $estimated = (bool) ($q['estimated'] ?? false);
    $title = (string) ($q['title'] ?? __('Qualidade'));
    $hint = (string) ($q['hint'] ?? '');
    $status = (string) ($q['status'] ?? 'neutral');
    $statusLabel = (string) ($q['status_label'] ?? __('Sem índice'));
    $score = is_numeric($q['score'] ?? null) ? max(0, min(100, (int) $q['score'])) : null;

    $diagnosisUrl = ($selectedCity && $filters)
        ? AnalyticsDockQualityIndicator::diagnosisUrl($selectedCity, $filters)
        : null;

    $statusBadge = match ($status) {
        'success' => 'diag-explore-badge--success',
        'warning' => 'diag-explore-badge--warning',
        'danger' => 'diag-explore-badge--danger',
        default => 'diag-explore-badge--neutral',
    };

    $ringColor = match ($status) {
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'danger' => '#ef4444',
        default => '#64748b',
    };
@endphp

<div
    class="serv-analytics-filter-dock__quality-meter serv-quality-dock-kpi"
    aria-label="{{ __('Índice geral de qualidade') }}"
>
    <div class="serv-quality-dock-kpi__head">
        <div class="serv-quality-dock-kpi__title-wrap min-w-0">
            @if ($diagnosisUrl)
                <a href="{{ $diagnosisUrl }}" class="serv-quality-dock-kpi__title">
                    {{ $title }}
                </a>
            @else
                <span class="serv-quality-dock-kpi__title serv-quality-dock-kpi__title--static">{{ $title }}</span>
            @endif
            @if ($hint !== '')
                <span class="serv-quality-dock-kpi__hint" title="{{ $hint }}" aria-label="{{ $hint }}">i</span>
            @endif
        </div>
        @if ($available)
            <span class="diag-explore-badge {{ $statusBadge }} serv-quality-dock-kpi__badge">{{ $statusLabel }}</span>
        @endif
    </div>

    @if ($yearFilterReady && $available && $score !== null)
        <div class="serv-quality-dock-kpi__body">
            <div
                class="serv-quality-dock-kpi__ring"
                style="--score-pct: {{ $score }}; --ring-color: {{ $ringColor }};"
                role="img"
                aria-label="{{ __('Índice de conformidade :n de 100', ['n' => $score]) }}"
            >
                <span class="serv-quality-dock-kpi__score tabular-nums">{{ $score }}</span>
            </div>
            <div class="serv-quality-dock-kpi__meta min-w-0">
                <p class="serv-quality-dock-kpi__index-label">{{ __('Índice geral') }}</p>
                <p class="serv-quality-dock-kpi__index-scale tabular-nums">{{ $score }}/100</p>
                @if ($estimated)
                    <p class="serv-quality-dock-kpi__estimated">{{ __('Estimativa') }}</p>
                @endif
            </div>
        </div>
    @elseif ($yearFilterReady && $partial)
        <p class="serv-quality-dock-kpi__empty">
            {{ __('Índice indisponível — abra Diagnóstico ou verifique filtros e conexão.') }}
        </p>
    @else
        <div class="serv-quality-dock-kpi__placeholder" aria-hidden="true">
            <span class="serv-quality-dock-kpi__ring serv-quality-dock-kpi__ring--ghost">
                <span class="serv-quality-dock-kpi__score">—</span>
            </span>
        </div>
        <p class="serv-quality-dock-kpi__empty">
            {{ __('Aplique o ano letivo para ver o índice de qualidade.') }}
        </p>
    @endif
</div>
