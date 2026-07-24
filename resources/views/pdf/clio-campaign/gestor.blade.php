<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('Clio') }} — {{ __('Painel gerencial') }} — {{ $campaign->municipality_name }}</title>
    @include('pdf.analytics-report.partials.pdf-styles', ['colors' => $colors ?? []])
    <style>
        .kpi-grid td { width: 25%; text-align: center; vertical-align: top; padding: 8px 6px; }
        .kpi-grid .kpi-value { font-size: 16px; font-weight: 700; color: #0f172a; display: block; }
        .kpi-grid .kpi-label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .chart-block { margin: 10px 0 14px; page-break-inside: avoid; }
        .chart-block svg { max-width: 100%; height: auto; }
        .note { font-size: 9px; color: #64748b; margin: 0 0 8px; }
    </style>
</head>
<body>
@php
    $bi = $bi ?? null;
    $coverage = $coverage ?? [];
    $seriesOk = ($series['ok'] ?? false) === true;
    $schoolTime = $schoolTime ?? ['available' => false, 'segments' => [], 'network' => [], 'note' => ''];
@endphp

<div class="pdf-footer">
    <div class="pdf-footer__accent"></div>
    <div class="pdf-footer__body">
        <table class="pdf-footer__table">
            <tr>
                <td style="width: 33%;">
                    <span class="pdf-footer__brand-name">SERVLITCYS</span>
                    <span class="pdf-footer__brand-tag">{{ __('Clio — Painel gerencial') }}</span>
                </td>
                <td style="width: 34%; text-align: center;">
                    <span class="pdf-footer__doc-title">{{ $campaign->municipality_name }}</span>
                    <span class="pdf-footer__doc-meta">{{ __('Gerado em :data', ['data' => $generated_at ?? '']) }}</span>
                </td>
                <td style="width: 33%;">
                    <span class="pdf-footer__legal">{{ __('Uso interno — indicadores agregados, sem dados pessoais.') }}</span>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="cover-pro__band">
    <div class="cover-pro__band-inner">
        <p class="cover-pro__eyebrow">{{ __('Clio') }}</p>
        <p class="cover-pro__type">{{ __('Painel gerencial — Matrícula inicial') }}</p>
        <h1 class="cover-pro__city">{{ $campaign->municipality_name }}</h1>
        <p class="cover-pro__sub">{{ $campaign->uf }} · {{ $campaign->year }} · {{ $campaign->profileLabel() }}</p>
    </div>
</div>

<div class="cover-pro__body">
    <p class="cover-pro__lead">
        {{ __('Leitura para o gestor municipal: totais da rede, série histórica do Censo, tempo escolar e diagnóstico geral por escola (erros e avisos).') }}
    </p>
    <p class="note" style="margin-top: 6px;">
        {{ __('Estado :s · tríade :p% (:c/:t escolas) · ref. :d', [
            's' => $campaign->statusLabel(),
            'p' => (string) ($bi?->triade_pct ?? $coverage['triade_coverage_pct'] ?? 0),
            'c' => (string) ($coverage['schools_triade_complete'] ?? 0),
            't' => (string) ($bi?->schools_active ?? $coverage['schools_total'] ?? 0),
            'd' => (string) ($coverage['reference_date'] ?? ($bi?->reference_date?->format('d/m/Y') ?? '—')),
        ]) }}
    </p>
</div>

<h2>{{ __('Indicadores da rede') }}</h2>
<table class="data kpi-grid" style="margin-bottom: 12px;">
    <tr>
        <td>
            <span class="kpi-value">{{ number_format((int) ($bi?->schools_active ?? $coverage['schools_active'] ?? 0), 0, ',', '.') }}</span>
            <span class="kpi-label">{{ __('Escolas ativas') }}</span>
        </td>
        <td>
            <span class="kpi-value">
                @if ($bi?->triade_pct !== null)
                    {{ number_format((float) $bi->triade_pct, 1, ',', '.') }}%
                @else
                    —
                @endif
            </span>
            <span class="kpi-label">{{ __('Cobertura da tríade') }}</span>
        </td>
        <td>
            <span class="kpi-value">{{ number_format((int) ($bi?->mat_curricular ?? 0), 0, ',', '.') }}</span>
            <span class="kpi-label">{{ __('Matrículas curriculares') }}</span>
        </td>
        <td>
            <span class="kpi-value">{{ number_format((int) (($bi?->mat_aee ?? 0) + ($bi?->mat_ac ?? 0)), 0, ',', '.') }}</span>
            <span class="kpi-label">{{ __('AEE + complementar') }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="kpi-value">
                @if ($bi?->distortion_pct !== null)
                    {{ number_format((float) $bi->distortion_pct, 1, ',', '.') }}%
                @else
                    —
                @endif
            </span>
            <span class="kpi-label">{{ __('Distorção idade-série') }}</span>
        </td>
        <td>
            <span class="kpi-value">
                @if ($bi?->density_avg !== null)
                    {{ number_format((float) $bi->density_avg, 1, ',', '.') }}
                @else
                    —
                @endif
            </span>
            <span class="kpi-label">{{ __('Densidade média (turma)') }}</span>
        </td>
        <td>
            <span class="kpi-value">{{ number_format((int) ($bi?->nee_people ?? 0), 0, ',', '.') }}</span>
            <span class="kpi-label">{{ __('Pessoas com NEE/TEA/AH') }}</span>
        </td>
        <td>
            <span class="kpi-value">
                @if (($schoolTime['network']['horas_aluno_semana'] ?? null) !== null)
                    {{ number_format((float) $schoolTime['network']['horas_aluno_semana'], 1, ',', '.') }}h
                @else
                    —
                @endif
            </span>
            <span class="kpi-label">{{ __('Horas/semana (média aluno)') }}</span>
        </td>
    </tr>
</table>

@if (($insights ?? collect())->isNotEmpty())
    <h2>{{ __('Leituras gerenciais') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 28%;">{{ __('Tema') }}</th>
                <th>{{ __('Resumo') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($insights as $insight)
                <tr>
                    <td>{{ $insight->title }}</td>
                    <td>
                        {{ $insight->body }}
                        @if ($insight->metric_value)
                            <span style="color: #0f766e; font-weight: 600;"> · {{ $insight->metric_value }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (($etapas ?? collect())->isNotEmpty())
    <h2>{{ __('Matrículas por etapa') }}</h2>
    <p class="note">{{ __('Totais agregados da coleta (dataset BI), sem identificação de alunos.') }}</p>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Etapa') }}</th>
                <th>{{ __('Alunos') }}</th>
                <th>{{ __('Turmas') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($etapas as $row)
                <tr>
                    <td>{{ $row->etapa }}</td>
                    <td>{{ number_format((int) $row->qt_alunos, 0, ',', '.') }}</td>
                    <td>{{ number_format((int) $row->qt_turmas, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (! empty($chartSvgs))
    <h2>{{ __('Visualizações') }}</h2>
    @foreach ($chartSvgs as $block)
        <div class="chart-block">
            {!! $block['svg'] !!}
        </div>
    @endforeach
@endif

@if ($seriesOk)
    <h2>{{ __('Série histórica — rede municipal (Censo INEP)') }}</h2>
    <p class="note">{{ $series['footnote'] ?? __('Últimos anos indexados, recorte municipal.') }}</p>
    @php
        $chart = $series['chart'] ?? [];
        $labels = is_array($chart['labels'] ?? null) ? $chart['labels'] : [];
        $datasets = is_array($chart['datasets'] ?? null) ? $chart['datasets'] : [];
    @endphp
    @if ($labels !== [] && $datasets !== [])
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Indicador') }}</th>
                    @foreach ($labels as $year)
                        <th>{{ $year }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($datasets as $ds)
                    <tr>
                        <td>{{ $ds['label'] ?? '—' }}</td>
                        @foreach (($ds['data'] ?? []) as $val)
                            <td>
                                @if ($val === null)
                                    —
                                @else
                                    {{ number_format((int) $val, 0, ',', '.') }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @if (! empty($series['stage_counters']['items']))
        <p class="note" style="margin-top: 8px;">
            {{ __('Último ano com dados (:y)', ['y' => $series['stage_counters']['ano'] ?? '—']) }}:
            @foreach ($series['stage_counters']['items'] as $item)
                <strong>{{ $item['label'] ?? '' }}</strong>
                {{ $item['value'] !== null ? number_format((int) $item['value'], 0, ',', '.') : '—' }}@if (! $loop->last) · @endif
            @endforeach
        </p>
    @endif
@endif

@if (! empty($schoolTime['available']))
    <h2>{{ __('Tempo escolar semanal dos alunos') }}</h2>
    <p class="note">{{ $schoolTime['note'] ?? '' }}</p>
    @if (($schoolTime['network']['horas_aluno_semana'] ?? null) !== null)
        <p style="font-size: 11px; margin-bottom: 8px;">
            {{ __('Média ponderada da rede: :h h/semana (:n alunos com carga horária identificada).', [
                'h' => number_format((float) $schoolTime['network']['horas_aluno_semana'], 1, ',', '.'),
                'n' => number_format((int) ($schoolTime['network']['alunos_com_ch'] ?? 0), 0, ',', '.'),
            ]) }}
        </p>
    @endif
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Segmento') }}</th>
                <th>{{ __('Turmas') }}</th>
                <th>{{ __('Alunos') }}</th>
                <th>{{ __('h/sem. (aluno)') }}</th>
                <th>{{ __('Curricular') }}</th>
                <th>{{ __('AEE') }}</th>
                <th>{{ __('Complementar') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schoolTime['segments'] as $seg)
                <tr>
                    <td>{{ $seg['label'] }}</td>
                    <td>{{ number_format((int) $seg['turmas'], 0, ',', '.') }}</td>
                    <td>{{ number_format((int) $seg['alunos'], 0, ',', '.') }}</td>
                    <td>
                        @if ($seg['horas_aluno_semana'] !== null)
                            {{ number_format((float) $seg['horas_aluno_semana'], 1, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if (($seg['curricular']['ch_media_aluno'] ?? null) !== null)
                            {{ number_format((float) $seg['curricular']['ch_media_aluno'], 1, ',', '.') }}h
                            <span style="color:#94a3b8;">({{ number_format((int) $seg['curricular']['alunos'], 0, ',', '.') }})</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if (($seg['aee']['ch_media_aluno'] ?? null) !== null)
                            {{ number_format((float) $seg['aee']['ch_media_aluno'], 1, ',', '.') }}h
                            <span style="color:#94a3b8;">({{ number_format((int) $seg['aee']['alunos'], 0, ',', '.') }})</span>
                        @elseif ((int) ($seg['aee']['turmas'] ?? 0) > 0)
                            {{ number_format((int) $seg['aee']['turmas'], 0, ',', '.') }} {{ __('turma(s)') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if (($seg['ac']['ch_media_aluno'] ?? null) !== null)
                            {{ number_format((float) $seg['ac']['ch_media_aluno'], 1, ',', '.') }}h
                            <span style="color:#94a3b8;">({{ number_format((int) $seg['ac']['alunos'], 0, ',', '.') }})</span>
                        @elseif ((int) ($seg['ac']['turmas'] ?? 0) > 0)
                            {{ number_format((int) $seg['ac']['turmas'], 0, ',', '.') }} {{ __('turma(s)') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="note" style="margin-top: 6px;">
        {{ __('A óptica é do aluno: a carga horária da turma é ponderada pelo número de alunos vinculados. Curricular, AEE e atividade complementar aparecem em colunas distintas porque representam experiências escolares diferentes na mesma semana.') }}
    </p>
@endif

@include('pdf.clio-campaign.partials.diagnostico-geral', ['diagnosticoGeral' => $diagnosticoGeral ?? []])

</body>
</html>
