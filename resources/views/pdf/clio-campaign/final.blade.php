<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('Clio') }} — {{ __('PDF Final') }} — {{ $campaign->municipality_name }}</title>
    @include('pdf.analytics-report.partials.pdf-styles', ['colors' => $colors ?? []])
    <style>
        .theme-page { page-break-after: always; }
        .theme-page:last-of-type { page-break-after: auto; }
        .theme-page--diagnostico { page-break-inside: auto; }
        .theme-page--diagnostico table.data.diag-geral-table tr.diag-geral-row {
            page-break-inside: avoid;
        }
        .theme-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .theme-status--rose { background: #fff1f2; color: #9f1239; }
        .theme-status--amber { background: #fff7ed; color: #9a3412; }
        .theme-status--emerald { background: #ecfdf5; color: #047857; }
        .theme-status--slate { background: #f8fafc; color: #475569; }
        .kpi-grid td { width: 25%; text-align: center; vertical-align: top; padding: 8px 6px; }
        .kpi-grid .kpi-value { font-size: 15px; font-weight: 700; color: #0f172a; display: block; }
        .kpi-grid .kpi-label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .diag-list { margin: 0 0 10px; padding-left: 14px; font-size: 10px; color: #334155; line-height: 1.4; }
        .finding {
            font-size: 9px;
            margin: 0 0 4px;
            padding: 5px 7px;
            border-radius: 3px;
            border-left: 3px solid #94a3b8;
            background: #f8fafc;
            color: #334155;
        }
        .finding--error { border-left-color: #be123c; background: #fff1f2; color: #9f1239; }
        .finding--warning { border-left-color: #c2410c; background: #fff7ed; color: #9a3412; }
        .triade-ok { color: #047857; font-weight: 700; }
        .triade-miss { color: #94a3b8; }
        .note { font-size: 9px; color: #64748b; margin: 0 0 8px; }
        .chart-block { margin: 8px 0 12px; text-align: center; page-break-inside: avoid; }
    </style>
</head>
<body>
@php
    $themes = is_array($themes ?? null) ? $themes : [];
    $schoolsTriade = is_array($schoolsTriade ?? null) ? $schoolsTriade : [];
    $triade = is_array(($dashboard['triade'] ?? null)) ? $dashboard['triade'] : [];
    $counters = is_array(($dashboard['counters'] ?? null)) ? $dashboard['counters'] : [];
@endphp

<div class="pdf-footer">
    <div class="pdf-footer__accent"></div>
    <div class="pdf-footer__body">
        <table class="pdf-footer__table">
            <tr>
                <td style="width: 33%;">
                    <span class="pdf-footer__brand-name">SERVLITCYS</span>
                    <span class="pdf-footer__brand-tag">{{ __('Clio — PDF Final') }}</span>
                </td>
                <td style="width: 34%; text-align: center;">
                    <span class="pdf-footer__doc-title">{{ $campaign->municipality_name }}</span>
                    <span class="pdf-footer__doc-meta">{{ __('Gerado em :data', ['data' => $generated_at ?? '']) }}</span>
                </td>
                <td style="width: 33%;">
                    <span class="pdf-footer__legal">{{ __('Uso interno — retrato temático sem dados pessoais.') }}</span>
                </td>
            </tr>
        </table>
    </div>
</div>

{{-- Capa --}}
<div class="theme-page">
    <div class="cover-pro__band">
        <div class="cover-pro__band-inner">
            <p class="cover-pro__eyebrow">{{ __('Clio') }}</p>
            <p class="cover-pro__type">{{ __('PDF Final — Retrato municipal por tema') }}</p>
            <h1 class="cover-pro__city">{{ $campaign->municipality_name }}</h1>
            <p class="cover-pro__sub">{{ $campaign->uf }} · {{ $campaign->year }} · {{ $campaign->profileLabel() }}</p>
        </div>
    </div>
    <div class="cover-pro__body">
        <p class="cover-pro__lead">
            {{ __('Cada página apresenta uma área temática da educação: status, números, diagnóstico curto e erros ou avisos. Abre com a série histórica do Censo; no fim, o Diagnóstico Geral e a rede/cobertura da tríade nas escolas em atividade.') }}
        </p>
        <p class="note" style="margin-top: 6px;">
            {{ __('Estado :s · tríade :p% (:c/:t escolas ativas) · :n temas · ref. :d', [
                's' => $campaign->statusLabel(),
                'p' => (string) ($triade['pct'] ?? $coverage['triade_coverage_pct'] ?? 0),
                'c' => (string) ($triade['complete'] ?? $coverage['schools_triade_complete'] ?? 0),
                't' => (string) ($triade['total'] ?? $counters['schools_active'] ?? 0),
                'n' => (string) count($themes),
                'd' => (string) ($coverage['reference_date'] ?? '—'),
            ]) }}
        </p>
        @if ($themes !== [])
            <h2 style="margin-top: 16px;">{{ __('Temas neste relatório') }}</h2>
            <ol style="font-size: 11px; color: #334155; line-height: 1.55; margin: 0; padding-left: 18px;">
                @foreach ($themes as $theme)
                    <li>
                        <strong>{{ $theme['title'] ?? '' }}</strong>
                        — <span class="theme-status theme-status--{{ $theme['status_tone'] ?? 'slate' }}">{{ $theme['status'] ?? '' }}</span>
                    </li>
                @endforeach
                <li><strong>{{ mb_strtoupper(__('Diagnóstico Geral'), 'UTF-8') }}</strong> — {{ __('alertas por escola') }}</li>
                <li><strong>{{ mb_strtoupper(__('Rede e cobertura da tríade'), 'UTF-8') }}</strong> — {{ __('KPIs e situação por escola em atividade') }}</li>
            </ol>
        @endif
    </div>
</div>

{{-- Temas (1 por página) --}}
@foreach ($themes as $theme)
    <div class="theme-page">
        <table style="width: 100%; margin-bottom: 8px;">
            <tr>
                <td style="vertical-align: top;">
                    <p class="note" style="margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">{{ __('Tema educativo') }}</p>
                    <h2 style="margin: 0; text-transform: uppercase;">{{ $theme['title'] ?? '' }}</h2>
                </td>
                <td style="text-align: right; vertical-align: top; white-space: nowrap;">
                    <span class="theme-status theme-status--{{ $theme['status_tone'] ?? 'slate' }}">{{ $theme['status'] ?? '' }}</span>
                    @if (($theme['error_count'] ?? 0) > 0 || ($theme['warning_count'] ?? 0) > 0)
                        <div style="margin-top: 4px; font-size: 8px;">
                            @if (($theme['error_count'] ?? 0) > 0)
                                <span style="color: #be123c; font-weight: 700;">● {{ $theme['error_count'] }}</span>
                            @endif
                            @if (($theme['warning_count'] ?? 0) > 0)
                                <span style="color: #c2410c; font-weight: 700; margin-left: 4px;">▲ {{ $theme['warning_count'] }}</span>
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
        </table>
        <p class="note">{{ $theme['lead'] ?? '' }}</p>

        @if (! empty($theme['kpis']))
            <table class="data kpi-grid" style="margin-bottom: 10px;">
                <tr>
                    @foreach (array_slice($theme['kpis'], 0, 4) as $kpi)
                        <td>
                            <span class="kpi-value">{{ $kpi['value'] ?? '—' }}</span>
                            <span class="kpi-label">{{ $kpi['label'] ?? '' }}</span>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif

        @if (! empty($theme['chart_img']))
            <div class="chart-block">
                <img src="{{ $theme['chart_img'] }}" alt="{{ $theme['chart_alt'] ?? ($theme['title'] ?? '') }}" width="520" height="248" style="max-width: 100%; height: auto;">
            </div>
        @endif

        @if (! empty($theme['diagnosis']))
            <h3 style="font-size: 11px; margin: 8px 0 4px;">{{ __('Diagnóstico') }}</h3>
            <ul class="diag-list">
                @foreach ($theme['diagnosis'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif

        @foreach ($theme['tables'] ?? [] as $table)
            @if (! empty($table['rows']))
                <h3 style="font-size: 11px; margin: 10px 0 4px;">{{ $table['title'] ?? __('Indicadores') }}</h3>
                <table class="data" style="margin-bottom: 8px;">
                    <thead>
                        <tr>
                            @foreach ($table['headers'] ?? [] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table['rows'] as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td style="font-size: 9.5px;">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach

        @if (! empty($theme['findings']))
            <h3 style="font-size: 11px; margin: 10px 0 4px;">{{ __('Erros e avisos') }}</h3>
            @foreach ($theme['findings'] as $finding)
                @php
                    $sev = $finding['severity'] ?? 'info';
                    $cls = $sev === 'error' ? 'finding--error' : ($sev === 'warning' ? 'finding--warning' : '');
                    $icon = $sev === 'error' ? '●' : ($sev === 'warning' ? '▲' : '•');
                @endphp
                <div class="finding {{ $cls }}">
                    <strong>{{ $icon }}</strong>
                    @if (! empty($finding['school']))
                        <strong>{{ $finding['school'] }}</strong> —
                    @endif
                    {{ $finding['message'] ?? '' }}
                </div>
            @endforeach
        @elseif (($theme['error_count'] ?? 0) === 0 && ($theme['warning_count'] ?? 0) === 0)
            <p class="note" style="margin-top: 8px;">{{ __('Sem erros ou avisos associados a este tema nesta coleta.') }}</p>
        @endif
    </div>
@endforeach

{{-- Diagnóstico Geral + tríade --}}
<div class="theme-page theme-page--diagnostico">
    @include('pdf.clio-campaign.partials.diagnostico-geral', [
        'diagnosticoGeral' => $diagnosticoGeral ?? [],
        'diagAlertChunkSize' => 3,
        'diagTitleUpper' => true,
    ])

    @php
        $triadeSummary = $triadeSummary ?? [];
        $triadeKpis = is_array($triadeSummary['kpis'] ?? null) ? $triadeSummary['kpis'] : [];
        $triadeDiagnosis = is_array($triadeSummary['diagnosis'] ?? null) ? $triadeSummary['diagnosis'] : [];
    @endphp

    <h2 style="margin-top: 18px; page-break-before: auto; text-transform: uppercase;">{{ __('Rede e cobertura da tríade') }}</h2>
    <p class="note">
        {{ __('Retrato da coleta: escolas em atividade, completude aluno+turma+profissional e situação por unidade (Completa, Incompleta, Com erros ou Sem arquivos).') }}
    </p>

    @if ($triadeKpis !== [])
        <table class="data kpi-grid" style="margin-bottom: 10px;">
            <tr>
                @foreach ($triadeKpis as $kpi)
                    <td>
                        <span class="kpi-value">{{ $kpi['value'] ?? '—' }}</span>
                        <span class="kpi-label">{{ $kpi['label'] ?? '' }}</span>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if ($triadeDiagnosis !== [])
        <h3 style="font-size: 11px; margin: 8px 0 4px;">{{ __('Leitura') }}</h3>
        <ul class="diag-list">
            @foreach ($triadeDiagnosis as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    @endif

    @if ($schoolsTriade === [])
        <p class="note">{{ __('Nenhuma escola em atividade nesta coleta.') }}</p>
    @else
        <table class="data diag-geral-table">
            <thead>
                <tr>
                    <th style="width: 12%;">{{ __('INEP') }}</th>
                    <th style="width: 34%;">{{ __('Escola') }}</th>
                    <th style="width: 16%;">{{ __('Situação') }}</th>
                    <th style="width: 22%;">{{ __('Arquivos da tríade') }}</th>
                    <th style="width: 16%;">{{ __('Problemas') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schoolsTriade as $row)
                    @php
                        $tone = $row['tone'] ?? 'slate';
                        $bg = match ($tone) {
                            'rose' => '#fff1f2',
                            'amber' => '#fff7ed',
                            'emerald' => '#ecfdf5',
                            default => '#f8fafc',
                        };
                        $fg = match ($tone) {
                            'rose' => '#9f1239',
                            'amber' => '#9a3412',
                            'emerald' => '#047857',
                            default => '#475569',
                        };
                    @endphp
                    <tr class="diag-geral-row">
                        <td style="font-family: DejaVu Sans Mono, monospace; font-size: 8.5px;">{{ $row['inep'] }}</td>
                        <td style="font-size: 9px;"><strong>{{ $row['name'] }}</strong></td>
                        <td>
                            <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: 700; background: {{ $bg }}; color: {{ $fg }};">
                                {{ $row['status'] }}
                            </span>
                        </td>
                        <td style="font-size: 8.5px;">
                            <span class="{{ ! empty($row['aluno']) ? 'triade-ok' : 'triade-miss' }}">{{ __('Alunos') }}</span>
                            ·
                            <span class="{{ ! empty($row['turma']) ? 'triade-ok' : 'triade-miss' }}">{{ __('Turmas') }}</span>
                            ·
                            <span class="{{ ! empty($row['profissional']) ? 'triade-ok' : 'triade-miss' }}">{{ __('Prof.') }}</span>
                        </td>
                        <td style="font-size: 8.5px;">
                            @if (($row['errors'] ?? 0) > 0)
                                <span style="color: #be123c; font-weight: 700;">● {{ $row['errors'] }}</span>
                            @endif
                            @if (($row['warnings'] ?? 0) > 0)
                                <span style="color: #c2410c; font-weight: 700; margin-left: 3px;">▲ {{ $row['warnings'] }}</span>
                            @endif
                            @if (($row['errors'] ?? 0) === 0 && ($row['warnings'] ?? 0) === 0)
                                <span style="color: #94a3b8;">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

</body>
</html>
