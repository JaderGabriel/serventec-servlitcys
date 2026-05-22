@php
    $brand = is_array($brand ?? null) ? $brand : config('analytics.pdf_report.brand', []);
    $colors = is_array($colors ?? null) ? $colors : config('analytics.pdf_report.colors', []);
    $primary = $colors['primary'] ?? '#0f766e';
    $secondary = $colors['secondary'] ?? '#4338ca';
    $primaryLight = $colors['primary_light'] ?? '#ccfbf1';
    $cover = is_array($cover ?? null) ? $cover : [];
    $health = is_array($health ?? null) ? $health : [];
    $disc = is_array($discrepancies ?? null) ? $discrepancies : [];
    $fundeb = is_array($fundeb ?? null) ? $fundeb : [];
    $other = is_array($other_funding ?? null) ? $other_funding : [];
    $work = is_array($work_done ?? null) ? $work_done : [];
    $yearCmp = is_array($year_comparison ?? null) ? $year_comparison : [];
    $munState = is_array($municipal_vs_state ?? null) ? $municipal_vs_state : [];
    $chartBlocks = is_array($charts ?? null) ? $charts : [];
    $schoolMap = is_array($school_units_map ?? null) ? $school_units_map : [];
    $overview = is_array($overview ?? null) ? $overview : [];
    $enrollment = is_array($enrollment ?? null) ? $enrollment : [];
    $network = is_array($network ?? null) ? $network : [];
    $inclusion = is_array($inclusion ?? null) ? $inclusion : [];
    $performance = is_array($performance ?? null) ? $performance : [];
    $attendance = is_array($attendance ?? null) ? $attendance : [];
    $overviewKpis = is_array($overview['kpis'] ?? null) ? $overview['kpis'] : [];
    $enrollmentKpis = is_array($enrollment['kpis'] ?? null) ? $enrollment['kpis'] : [];
    $networkKpis = is_array($network['kpis'] ?? null) ? $network['kpis'] : [];
    $discDimensions = is_array($disc['dimensions'] ?? null) ? $disc['dimensions'] : [];
    $discSummary = is_array($disc['summary'] ?? null) ? $disc['summary'] : [];
    $perfKpis = is_array($performance['kpis'] ?? null) ? $performance['kpis'] : [];
    $saebSummary = is_array(data_get($performance, 'saeb_series.summary') ?? null) ? data_get($performance, 'saeb_series.summary') : [];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório {{ $cover['municipality'] ?? '' }}</title>
    <style>
        @page { margin: 72px 42px 68px 42px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #0f172a; line-height: 1.5; }
        h1 { color: {{ $primary }}; font-size: 20pt; margin: 0 0 8px; }
        h2 { color: {{ $secondary }}; font-size: 13pt; margin: 22px 0 8px; border-bottom: 2px solid {{ $primary }}; padding-bottom: 4px; page-break-after: avoid; }
        h3 { font-size: 11pt; color: {{ $primary }}; margin: 14px 0 6px; }
        p { margin: 0 0 8px; }
        .cover-page { page-break-after: always; padding: 0 0 12px; }
        .cover-hero { margin-bottom: 14px; border-radius: 10px; overflow: hidden; }
        .cover-hero__main { padding: 26px 28px 22px; color: #fff; }
        .cover-eyebrow { margin: 0 0 4px; font-size: 8.5pt; letter-spacing: 0.12em; text-transform: uppercase; opacity: 0.92; }
        .cover-audience { margin: 0 0 8px; font-size: 9pt; color: #ecfdf5; font-weight: bold; letter-spacing: 0.04em; }
        .cover-report-type { margin: 0 0 4px; font-size: 11pt; font-weight: bold; color: {{ $primaryLight }}; }
        .cover-city { margin: 0; font-size: 26pt; line-height: 1.15; font-weight: bold; color: #fff; }
        .cover-region { margin: 8px 0 0; font-size: 10pt; color: #ecfdf5; opacity: 0.95; }
        .cover-lead { margin: 14px 0 0; font-size: 9.5pt; line-height: 1.45; color: #f0fdfa; max-width: 95%; }
        .cover-kpi-strip { margin: 0 0 12px; border-collapse: separate; border-spacing: 6px 0; }
        .cover-kpi-cell { border: 1px solid; border-radius: 8px; padding: 10px 10px; vertical-align: top; width: 25%; }
        .cover-kpi-label { display: block; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; font-weight: bold; }
        .cover-kpi-value { display: block; font-size: 12pt; font-weight: bold; margin-top: 4px; line-height: 1.2; }
        .cover-framework { margin: 0 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .cover-framework__col { padding: 12px 14px; vertical-align: top; }
        .cover-framework__col--culture { border-left: 1px solid #e2e8f0; background: #fafafa; }
        .cover-framework__heading { margin: 0 0 4px; font-size: 10pt; font-weight: bold; color: {{ $primary }}; text-transform: uppercase; letter-spacing: 0.05em; }
        .cover-framework__intro { margin: 0 0 10px; font-size: 8.5pt; color: #475569; line-height: 1.4; }
        .cover-dimension { margin: 0 0 8px; padding-bottom: 6px; border-bottom: 1px dashed #e2e8f0; }
        .cover-dimension__title { margin: 0; font-size: 9.5pt; font-weight: bold; color: {{ $secondary }}; }
        .cover-dimension__step { color: {{ $primary }}; margin-right: 4px; }
        .cover-dimension__hint { margin: 2px 0 0; font-size: 8.5pt; color: #64748b; }
        .cover-dimension__topics { margin: 4px 0 0; padding-left: 14px; font-size: 8pt; color: #334155; }
        .cover-dimension__topics li { margin-bottom: 2px; }
        .cover-pillar { margin: 0 0 8px; }
        .cover-pillar__title { margin: 0; font-size: 9pt; font-weight: bold; color: #0f172a; }
        .cover-pillar__text { margin: 3px 0 0; font-size: 8.5pt; color: #475569; line-height: 1.4; }
        .cover-year-badge { display: inline-block; background: rgba(255,255,255,0.18); border: 2px solid rgba(255,255,255,0.55); border-radius: 10px; padding: 12px 16px; text-align: center; min-width: 120px; }
        .cover-year-badge__label { display: block; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.9; }
        .cover-year-badge__value { display: block; font-size: 22pt; font-weight: bold; line-height: 1.2; margin-top: 4px; }
        .cover-facts { margin: 0 0 12px; border-collapse: separate; border-spacing: 6px 0; }
        .cover-fact-cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; vertical-align: top; }
        .cover-fact-label { display: block; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; font-weight: bold; }
        .cover-fact-value { display: block; font-size: 11pt; font-weight: bold; color: {{ $primary }}; margin-top: 3px; }
        .cover-fact-value--small { font-size: 8.5pt; font-weight: normal; color: #334155; }
        .cover-filters { margin: 0 0 14px; background: #ecfdf5; border: 1px solid #5eead4; border-radius: 8px; }
        .cover-filters__title { padding: 8px 12px 4px; font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: {{ $primary }}; }
        .cover-filter-chip { padding: 6px 12px 10px; vertical-align: top; border-right: 1px solid #ccfbf1; }
        .cover-filter-chip:last-child { border-right: none; }
        .cover-filter-chip__label { display: block; font-size: 7.5pt; color: #475569; text-transform: uppercase; }
        .cover-filter-chip__value { display: block; font-size: 11pt; font-weight: bold; color: #0f172a; margin-top: 2px; }
        .cover-filters-empty { margin: 0 0 12px; font-size: 10pt; color: #475569; }
        .cover-maps { margin: 0 0 14px; }
        .cover-map-main, .cover-map-side { vertical-align: top; padding: 0 4px 0 0; }
        .cover-map-stack { position: relative; line-height: 0; }
        .cover-map-img { width: 100%; border-radius: 8px; border: 1px solid #94a3b8; display: block; }
        .cover-map-img--backdrop { max-height: 240px; opacity: 1; }
        .cover-map-img--main { max-height: 240px; }
        .cover-map-img--overlay { position: absolute; left: 0; top: 0; width: 100%; height: 100%; max-height: none; border: none; background: transparent; }
        .cover-map-img--regional, .cover-map-img--brand { max-height: 200px; }
        .cover-map-caption { margin: 6px 0 0; font-size: 8.5pt; color: #334155; line-height: 1.4; }
        .cover-map-caption__muted { font-size: 7.5pt; color: #475569; }
        .cover-map-placeholder { background: #f1f5f9; border: 1px dashed #64748b; border-radius: 8px; padding: 28px 16px; text-align: center; font-size: 9.5pt; color: #475569; min-height: 120px; }
        .cover-summary { background: #ecfdf5; border: 1px solid #5eead4; border-left: 5px solid {{ $primary }}; padding: 14px 16px; border-radius: 0 8px 8px 0; margin-bottom: 8px; }
        .cover-summary__title { margin: 0 0 6px; font-size: 11pt; font-weight: bold; color: #115e59; }
        .cover-summary__text { margin: 0; font-size: 10pt; line-height: 1.5; color: #1e293b; }
        .cover-summary__list { margin: 0; padding-left: 16px; font-size: 9.5pt; line-height: 1.45; color: #1e293b; }
        .cover-summary__list li { margin-bottom: 5px; }
        .cover-summary__kpi { margin: 10px 0 0; font-size: 10.5pt; font-weight: bold; color: {{ $secondary }}; }
        .cover-footer-note { margin: 0; font-size: 8pt; color: #64748b; text-align: center; line-height: 1.4; }
        .cover-footer-note__legal { font-size: 7.5pt; color: #94a3b8; }
        .kpi-row { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .kpi-row td { border: 1px solid #cbd5e1; padding: 8px 10px; background: #f8fafc; }
        .kpi-label { font-size: 8pt; color: #475569; text-transform: uppercase; font-weight: bold; }
        .kpi-value { font-size: 13pt; font-weight: bold; color: #115e59; }
        table.data { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin: 8px 0; }
        table.data th { background: #115e59; color: #fff; padding: 7px 8px; text-align: left; font-weight: bold; }
        table.data td { border: 1px solid #cbd5e1; padding: 6px 8px; color: #0f172a; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .box { background: #ecfdf5; border: 1px solid #99f6e4; border-left: 4px solid {{ $primary }}; padding: 10px 12px; margin: 10px 0; color: #1e293b; }
        .chart-block { text-align: center; margin: 14px 0; page-break-inside: avoid; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 8px 12px; background: #fff; }
        .chart-block__head { text-align: left; margin: 0 0 8px; padding: 0 6px; }
        .chart-block__section { display: block; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: {{ $secondary }}; font-weight: bold; }
        .chart-block__title { display: block; font-size: 10pt; font-weight: bold; color: #0f172a; margin-top: 2px; }
        .chart-block__hint { display: block; font-size: 8.5pt; color: #475569; margin-top: 4px; line-height: 1.35; }
        .map-section { page-break-inside: avoid; margin: 12px 0 16px; }
        .map-section__img { width: 100%; max-width: 680px; border: 1px solid #94a3b8; border-radius: 8px; display: block; margin: 0 auto; }
        .map-section__legend { margin: 8px 0 0; font-size: 9pt; color: #334155; line-height: 1.45; }
        .map-stats { width: 100%; border-collapse: collapse; margin: 10px 0 0; font-size: 9pt; }
        .map-stats td { border: 1px solid #cbd5e1; padding: 6px 10px; background: #f8fafc; }
        .action-lead { font-size: 10pt; color: #1e293b; margin: 0 0 10px; padding: 8px 10px; background: #f1f5f9; border-left: 3px solid {{ $secondary }}; }
        .decision-box { background: #fffbeb; border: 1px solid #fcd34d; border-left: 4px solid #d97706; padding: 8px 12px; margin: 0 0 12px; font-size: 9.5pt; }
        .decision-box__title { margin: 0 0 4px; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: #92400e; }
        .section-purpose { font-size: 9.5pt; color: #475569; margin: 0 0 8px; }
        .section { page-break-inside: avoid; }
        .pdf-footer {
            position: fixed;
            bottom: -52px;
            left: 0;
            right: 0;
            height: 48px;
            background: #f8fafc;
            border-top: 2px solid {{ $primary }};
            padding: 7px 0 0;
        }
        .pdf-footer__table { width: 100%; border-collapse: collapse; }
        .pdf-footer__icon { display: block; border-radius: 6px; }
        .pdf-footer__icon-fallback { width: 24px; height: 24px; border-radius: 6px; }
        .pdf-footer__system { display: block; font-size: 9.5pt; font-weight: bold; color: {{ $primary }}; line-height: 1.2; }
        .pdf-footer__tagline { display: block; font-size: 6.5pt; color: #475569; margin-top: 1px; line-height: 1.25; }
        .pdf-footer__context-city { display: block; font-size: 8pt; font-weight: bold; color: #0f172a; line-height: 1.25; }
        .pdf-footer__context-sub { display: block; font-size: 7pt; color: #64748b; margin-top: 1px; }
        .pdf-footer__context-year { display: block; font-size: 7pt; color: #475569; margin-top: 2px; }
        .pdf-footer__serventec-name { display: block; font-size: 7.5pt; font-weight: bold; color: {{ $secondary }}; line-height: 1.2; }
        .pdf-footer__serventec-link { display: block; font-size: 7pt; color: {{ $primary }}; text-decoration: none; margin-top: 1px; }
        .pdf-footer__dev { display: block; font-size: 6pt; color: #94a3b8; margin-top: 2px; }
        .pdf-footer__dev-link { color: #64748b; text-decoration: none; }
        .pdf-footer__page-slot { display: block; font-size: 7pt; color: #475569; margin-top: 3px; min-height: 9px; }
        .muted { color: #475569; font-size: 9pt; }
        .legal-notice { background: #fffbeb; border-left-color: #d97706; }
        .official-tag { display: inline-block; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: {{ $secondary }}; font-weight: bold; margin-bottom: 4px; }
        ul.compact { margin: 4px 0; padding-left: 18px; }
        ul.compact li { margin-bottom: 4px; }
    </style>
</head>
<body>
    @include('pdf.analytics-report.footer', [
        'brand' => $brand,
        'colors' => $colors,
        'cover' => $cover,
    ])

    @include('pdf.analytics-report.cover', [
        'cover' => $cover,
        'brand' => $brand,
        'colors' => $colors,
        'health' => $health,
        'generated_at' => $generated_at ?? null,
    ])

    @include('pdf.analytics-report.partials.preamble', ['health' => $health])

    <h2>{{ __('1. Serventec — diagnóstico consolidado') }}</h2>
    <div class="section">
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'health'])
        @if (filled($health['footnote'] ?? null))
            <p class="section-purpose">{{ $health['footnote'] }}</p>
        @endif
        <table class="kpi-row">
            <tr>
                <td>
                    <div class="kpi-label">{{ __('Índice de conformidade') }}</div>
                    <div class="kpi-value">{{ isset($health['compliance_score']) ? (int) $health['compliance_score'].'/100' : '—' }}</div>
                    <span class="muted">{{ $health['compliance_label'] ?? '' }}</span>
                </td>
                <td><div class="kpi-label">{{ __('Pendências cadastro') }}</div><div class="kpi-value">{{ number_format((int) data_get($health, 'summary.pendencias_cadastro', 0), 0, ',', '.') }}</div></td>
                <td><div class="kpi-label">{{ __('Perda estimada/ano') }}</div><div class="kpi-value">R$ {{ number_format((float) data_get($health, 'summary.perda_estimada_anual', 0), 2, ',', '.') }}</div></td>
                <td><div class="kpi-label">{{ __('Ganho potencial/ano') }}</div><div class="kpi-value">R$ {{ number_format((float) data_get($health, 'summary.ganho_potencial_anual', 0), 2, ',', '.') }}</div></td>
            </tr>
        </table>
        @if (is_array($health['vaaf_comparacao'] ?? null))
            <h3>{{ __('VAAF municipal × prévia federal') }}</h3>
            <p class="muted">{{ __('Divergência relevante exige revisão de matrículas ou sincronização com referência FNDE antes de planeamento orçamentário.') }}</p>
            <table class="data">
                <tr><th>{{ __('Indicador') }}</th><th>{{ __('Valor') }}</th><th>{{ __('Leitura') }}</th></tr>
                @foreach (['real', 'previa'] as $key)
                    @php $row = $health['vaaf_comparacao'][$key] ?? null; @endphp
                    @if (is_array($row))
                        <tr>
                            <td>{{ $row['label'] ?? $key }}</td>
                            <td>{{ $row['value'] ?? '—' }}</td>
                            <td class="muted">{{ $row['hint'] ?? '—' }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif
        @if (count(is_array($health['top_problems'] ?? null) ? $health['top_problems'] : []) > 0)
            <h3>{{ __('Principais alertas detectados') }}</h3>
            <ul class="compact">
                @foreach ($health['top_problems'] as $problem)
                    @if (is_array($problem))
                        <li>
                            <strong>{{ $problem['title'] ?? $problem['label'] ?? '' }}</strong>
                            @if (isset($problem['total']))
                                — {{ number_format((int) $problem['total'], 0, ',', '.') }} {{ __('ocorrências') }}
                            @endif
                            @if (filled($problem['funding_explicacao']['resumo'] ?? null))
                                <span class="muted"> — {{ $problem['funding_explicacao']['resumo'] }}</span>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </div>

    @include('pdf.analytics-report.comparatives', [
        'comparatives' => $comparatives ?? [],
        'year_comparison' => $yearCmp,
        'municipal_vs_state' => $munState,
    ])

    <h2>{{ __('3. Cadastro, matrículas e rede escolar') }}</h2>
    <div class="section">
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'cadastro'])
        @if (filled($overview['filter_note'] ?? null))
            <p class="muted">{{ $overview['filter_note'] }}</p>
        @endif
        @if (filled($overview['error'] ?? null) || filled($enrollment['error'] ?? null))
            <p class="muted">{{ $overview['error'] ?? $enrollment['error'] }}</p>
        @endif
        <table class="kpi-row">
            <tr>
                <td><div class="kpi-label">{{ __('Escolas (recorte)') }}</div><div class="kpi-value">{{ isset($overviewKpis['escolas']) ? number_format((int) $overviewKpis['escolas'], 0, ',', '.') : '—' }}</div></td>
                <td><div class="kpi-label">{{ __('Turmas') }}</div><div class="kpi-value">{{ isset($overviewKpis['turmas']) ? number_format((int) $overviewKpis['turmas'], 0, ',', '.') : '—' }}</div></td>
                <td><div class="kpi-label">{{ __('Matrículas activas') }}</div><div class="kpi-value">{{ isset($overviewKpis['matriculas']) ? number_format((int) $overviewKpis['matriculas'], 0, ',', '.') : '—' }}</div></td>
                <td><div class="kpi-label">{{ __('Turmas distintas (mat.)') }}</div><div class="kpi-value">{{ isset($enrollmentKpis['turmas_distintas']) ? number_format((int) $enrollmentKpis['turmas_distintas'], 0, ',', '.') : '—' }}</div></td>
            </tr>
        </table>
        @if ($networkKpis !== [])
            <h3>{{ __('Oferta e ocupação da rede') }}</h3>
            <table class="kpi-row">
                <tr>
                    <td><div class="kpi-label">{{ __('Capacidade (turmas)') }}</div><div class="kpi-value">{{ number_format((int) ($networkKpis['capacidade_total'] ?? 0), 0, ',', '.') }}</div></td>
                    <td><div class="kpi-label">{{ __('Vagas ociosas') }}</div><div class="kpi-value">{{ number_format((int) ($networkKpis['vagas_ociosas'] ?? 0), 0, ',', '.') }}</div></td>
                    <td><div class="kpi-label">{{ __('Taxa ociosidade') }}</div><div class="kpi-value">{{ isset($networkKpis['taxa_ociosidade_pct']) ? $networkKpis['taxa_ociosidade_pct'].'%' : '—' }}</div></td>
                    <td><div class="kpi-label">{{ __('Ocupação matrículas') }}</div><div class="kpi-value">{{ isset($enrollmentKpis['ocupacao_pct']) ? $enrollmentKpis['ocupacao_pct'].'%' : '—' }}</div></td>
                </tr>
            </table>
            @foreach (is_array($network['notes'] ?? null) ? $network['notes'] : [] as $note)
                <p class="muted">{{ $note }}</p>
            @endforeach
        @endif
        @php $distorcao = is_array($enrollment['distorcao'] ?? null) ? $enrollment['distorcao'] : null; @endphp
        @if ($distorcao !== null)
            <p><strong>{{ __('Distorção idade-série') }}:</strong>
                {{ number_format((int) ($distorcao['com'] ?? 0), 0, ',', '.') }} {{ __('com distorção') }}
                ({{ $distorcao['pct'] ?? '—' }}% {{ __('do total') }})
                <span class="muted"> — {{ $distorcao['fonte'] ?? '' }}</span>
            </p>
        @elseif (filled($enrollment['distorcao_cartao_motivo'] ?? null))
            <p class="muted">{{ $enrollment['distorcao_cartao_motivo'] }}</p>
        @endif
        @php $fluxo = is_array($enrollment['fluxo_taxas'] ?? null) ? $enrollment['fluxo_taxas'] : null; @endphp
        @if ($fluxo !== null && (int) ($fluxo['total'] ?? 0) > 0)
            <p><strong>{{ __('Fluxo escolar (situação INEP)') }}:</strong>
                {{ __('Abandono') }} {{ number_format((int) ($fluxo['abandono_q'] ?? 0), 0, ',', '.') }} ·
                {{ __('Remanejamento') }} {{ number_format((int) ($fluxo['remanejamento_q'] ?? 0), 0, ',', '.') }}
                ({{ __('base') }} {{ number_format((int) ($fluxo['total'] ?? 0), 0, ',', '.') }} {{ __('matrículas') }})
            </p>
        @endif
    </div>

    <h2>{{ __('4. Pedagógico, inclusão e permanência') }}</h2>
    <div class="section">
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'pedagogical'])
        @if (filled($performance['message'] ?? null))
            <p class="muted">{{ $performance['message'] }}</p>
        @endif
        @if ($perfKpis !== [])
            <h3>{{ __('Situação das matrículas (i-Educar)') }}</h3>
            <table class="data">
                <tr><th>{{ __('Indicador') }}</th><th>{{ __('Valor') }}</th><th>{{ __('% rede') }}</th></tr>
                @foreach (array_slice($perfKpis, 0, 8) as $kpi)
                    @if (is_array($kpi))
                        <tr>
                            <td>{{ $kpi['label'] ?? '' }}</td>
                            <td>{{ $kpi['value'] ?? '—' }}</td>
                            <td>{{ isset($kpi['pct']) ? $kpi['pct'].'%' : '—' }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif
        @if ($saebSummary !== [])
            <h3>{{ __('SAEB no recorte') }}</h3>
            <p class="muted">{{ data_get($performance, 'saeb_series.source_hint') }}</p>
            @foreach ($saebSummary as $key => $val)
                @if (is_scalar($val) && filled($val))
                    <p style="margin:2px 0;font-size:9pt;"><strong>{{ ucfirst(str_replace('_', ' ', (string) $key)) }}:</strong> {{ $val }}</p>
                @endif
            @endforeach
        @endif
        @php $recurso = is_array($inclusion['recurso_prova'] ?? null) ? $inclusion['recurso_prova'] : []; @endphp
        @if ($recurso !== [])
            <h3>{{ __('Inclusão — NEE e recursos em prova') }}</h3>
            <table class="kpi-row">
                <tr>
                    <td><div class="kpi-label">{{ __('Matrículas NEE (filtro)') }}</div><div class="kpi-value">{{ number_format((int) ($recurso['total_nee'] ?? data_get($inclusion, 'total_matriculas', 0)), 0, ',', '.') }}</div></td>
                    <td><div class="kpi-label">{{ __('Sem recurso cadastrado') }}</div><div class="kpi-value">{{ number_format((int) ($recurso['sem_nee'] ?? data_get($health, 'summary.recurso_prova_sem_nee', 0)), 0, ',', '.') }}</div></td>
                </tr>
            </table>
            @foreach (is_array($inclusion['notes'] ?? null) ? array_slice($inclusion['notes'], 0, 3) : [] as $note)
                <p class="muted">{{ $note }}</p>
            @endforeach
        @endif
        @if (filled($attendance['message'] ?? null))
            <h3>{{ __('Frequência e faltas') }}</h3>
            <p class="muted">{{ $attendance['message'] }}</p>
        @elseif (count(is_array($attendance['rows'] ?? null) ? $attendance['rows'] : []) > 0)
            <h3>{{ __('Frequência — amostra mensal') }}</h3>
            <table class="data">
                <tr><th>{{ __('Período') }}</th><th>{{ __('Registos') }}</th></tr>
                @foreach (array_slice($attendance['rows'], 0, 6) as $row)
                    @if (is_array($row))
                        <tr>
                            <td>{{ $row['mes'] ?? $row['periodo'] ?? '—' }}</td>
                            <td>{{ isset($row['faltas']) ? number_format((int) $row['faltas'], 0, ',', '.') : (isset($row['total']) ? number_format((int) $row['total'], 0, ',', '.') : ($row['valor'] ?? '—')) }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif
    </div>

    <h2>{{ __('5. Discrepâncias e impacto financeiro') }}</h2>
    <div class="section">
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'discrepancies'])
        <p>{{ $disc['intro'] ?? '' }}</p>
        @if (is_array($disc['funding_resumo_explicacao'] ?? null))
            @php $fundingResumo = $disc['funding_resumo_explicacao']; @endphp
            <div class="box">
                <p style="margin:0;"><strong>{{ $fundingResumo['titulo'] ?? __('Resumo financeiro') }}</strong></p>
                <p style="margin:4px 0 0;font-size:9pt;">{{ $fundingResumo['detalhe'] ?? '' }}</p>
                @if (is_array($fundingResumo['passos'] ?? null))
                    <ul class="compact" style="margin:6px 0 0;">
                        @foreach ($fundingResumo['passos'] as $passo)
                            <li>{{ $passo }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        <table class="kpi-row">
            <tr>
                <td><div class="kpi-label">{{ __('Com problema') }}</div><div class="kpi-value">{{ number_format((int) ($discSummary['com_problema'] ?? 0), 0, ',', '.') }}</div></td>
                <td><div class="kpi-label">{{ __('Escolas afetadas') }}</div><div class="kpi-value">{{ number_format((int) ($discSummary['escolas_afetadas'] ?? 0), 0, ',', '.') }}</div></td>
                <td><div class="kpi-label">{{ __('Perda estimada') }}</div><div class="kpi-value">R$ {{ number_format((float) ($discSummary['perda_estimada_anual'] ?? 0), 2, ',', '.') }}</div></td>
            </tr>
        </table>
        @php
            $issueDims = array_values(array_filter($discDimensions, static fn ($d): bool => is_array($d) && ($d['has_issue'] ?? false)));
            usort($issueDims, static fn ($a, $b): int => (int) ($b['occurrences_total'] ?? 0) <=> (int) ($a['occurrences_total'] ?? 0));
        @endphp
        @if ($issueDims !== [])
            <h3>{{ __('Rotinas prioritárias (por volume)') }}</h3>
            <table class="data">
                <tr>
                    <th>{{ __('Rotina') }}</th>
                    <th>{{ __('Ocorrências') }}</th>
                    <th>{{ __('Escolas') }}</th>
                    <th>{{ __('Perda est.') }}</th>
                    <th>{{ __('Estado') }}</th>
                </tr>
                @foreach (array_slice($issueDims, 0, 12) as $dim)
                    <tr>
                        <td>{{ $dim['title'] ?? '' }}</td>
                        <td>{{ number_format((int) ($dim['occurrences_total'] ?? 0), 0, ',', '.') }}</td>
                        <td>{{ number_format((int) ($dim['schools_count'] ?? 0), 0, ',', '.') }}</td>
                        <td>R$ {{ number_format((float) ($dim['perda_estimada_anual'] ?? 0), 2, ',', '.') }}</td>
                        <td>{{ $dim['status_label'] ?? '' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
        @if (filled($disc['footnote'] ?? null))
            <p class="muted">{{ $disc['footnote'] }}</p>
        @endif
        <p class="muted">{{ $disc['funding_aviso'] ?? '' }}</p>
    </div>

    <h2>{{ __('6. FUNDEB — previsão e complementação (VAAF / VAAT / VAAR)') }}</h2>
    <div class="section">
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'fundeb'])
    @php
        $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : [];
        $informe = is_array($fundeb['complementacao_informe'] ?? null) ? $fundeb['complementacao_informe'] : [];
    @endphp
    <span class="official-tag">{{ __('Referência Lei 14.113/2020 · portarias FNDE') }}</span>
    <p>{{ $fundeb['intro'] ?? $proj['aviso'] ?? '' }}</p>
    @if (filled($proj['previsao_referencia_label'] ?? null))
        <p><strong>{{ __('Previsão base (matrículas × VAAF)') }}:</strong> {{ $proj['previsao_referencia_label'] }}</p>
    @endif
    @if (is_array($proj['totais'] ?? null))
        <table class="kpi-row">
            <tr>
                <td><div class="kpi-label">{{ __('Base Fundeb anual') }}</div><div class="kpi-value">@if (isset($proj['totais']['fundeb_base_anual'])) R$ {{ number_format((float) $proj['totais']['fundeb_base_anual'], 2, ',', '.') }} @else — @endif</div></td>
                <td><div class="kpi-label">{{ __('Matrículas (filtro)') }}</div><div class="kpi-value">{{ isset($proj['matriculas']) ? number_format((int) $proj['matriculas'], 0, ',', '.') : '—' }}</div></td>
            </tr>
        </table>
    @endif
    @if (($informe['available'] ?? false) && count($informe['blocos'] ?? []) > 0)
        <h3>{{ __('Informes por eixo (modelo consultoria municipal)') }}</h3>
        @foreach ($informe['blocos'] as $bloco)
            @if (is_array($bloco))
                <div class="box">
                    <p style="margin:0 0 4px;"><strong>{{ $bloco['titulo'] ?? '' }}</strong> — <span class="muted">{{ $bloco['status_label'] ?? '' }}</span></p>
                    @foreach (is_array($bloco['indicadores'] ?? null) ? $bloco['indicadores'] : [] as $ind)
                        <p style="margin:2px 0;font-size:9pt;"><strong>{{ $ind['label'] ?? '' }}:</strong> {{ $ind['value'] ?? '' }}</p>
                    @endforeach
                </div>
            @endif
        @endforeach
        <p class="muted">{{ $informe['aviso'] ?? '' }}</p>
    @endif
    </div>

    <h2>{{ __('7. Financiamentos (programas complementares)') }}</h2>
    <div class="section">
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'other_funding'])
    <p>{{ $other['intro'] ?? '' }}</p>
    @foreach (is_array($other['programs'] ?? null) ? $other['programs'] : [] as $prog)
        <h3>{{ $prog['titulo'] ?? '' }}</h3>
        <p>{{ $prog['descricao'] ?? '' }}</p>
        @if (is_array($prog['kpis'] ?? null) && $prog['kpis'] !== [])
            <table class="data">
                <tr><th>{{ __('Indicador') }}</th><th>{{ __('Situação') }}</th></tr>
                @foreach ($prog['kpis'] as $kpi)
                    @if (is_array($kpi))
                        <tr>
                            <td>{{ $kpi['label'] ?? '' }}</td>
                            <td>{{ $kpi['value'] ?? $kpi['status_label'] ?? '—' }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif
    @endforeach
    </div>

    <h2>{{ __('8. Censo e cadastro') }}</h2>
    <div class="section">
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'work_done'])
    <p>{{ $work['intro'] ?? '' }}</p>
    @php $censo = is_array($work['censo'] ?? null) ? $work['censo'] : []; @endphp
    @if ($censo['available'] ?? false)
        <table class="kpi-row">
            <tr>
                <td>{{ __('Exportadas') }}: {{ number_format((int) data_get($censo, 'summary.exportadas', 0), 0, ',', '.') }}</td>
                <td>{{ __('Fechadas') }}: {{ number_format((int) data_get($censo, 'summary.fechadas', 0), 0, ',', '.') }}</td>
                <td>{{ __('Pendentes') }}: {{ number_format((int) data_get($censo, 'summary.pendentes', 0), 0, ',', '.') }}</td>
            </tr>
        </table>
    @endif
    @if (filled($work['estimativa']['dias_para_concluir'] ?? null))
        <p><strong>{{ __('Estimativa operacional') }}:</strong>
            {{ __('Ritmo') }} {{ number_format((float) ($work['estimativa']['ritmo_por_dia'] ?? 0), 1, ',', '.') }} {{ __('cadastros/dia') }}
            · {{ __('Prazo indicativo') }} {{ $work['estimativa']['dias_para_concluir'] }} {{ __('dias') }}
        </p>
    @endif
    </div>

    @if (count($chartBlocks) > 0)
        <h2>{{ __('9. Gráficos para decisão') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'charts'])
        @foreach ($chartBlocks as $block)
            <div class="chart-block section">
                <div class="chart-block__head">
                    <span class="chart-block__section">{{ $block['section'] ?? '' }}</span>
                    <span class="chart-block__title">{{ $block['title'] ?? '' }}</span>
                    <span class="chart-block__hint">{{ $block['hint'] ?? __('Compare magnitudes com as tabelas das seções 1–8 antes de definir acções.') }}</span>
                </div>
                {!! $block['svg'] ?? '' !!}
            </div>
        @endforeach
    @endif

    @if (count(is_array($health['thematic_blocks'] ?? null) ? $health['thematic_blocks'] : []) > 0)
        <h2>{{ __('10. Leitura temática e prioridades') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'thematic'])
        @foreach ($health['thematic_blocks'] as $block)
            @if (is_array($block))
                <h3>{{ $block['titulo'] ?? '' }}</h3>
                <ul class="compact">
                    @foreach (is_array($block['items'] ?? null) ? $block['items'] : [] as $item)
                        <li>{{ is_string($item) ? $item : '' }}</li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    @endif

    @if ($schoolMap['available'] ?? false)
        <h2>{{ __('11. Território — unidades escolares e abrangência das matrículas') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'map'])
        <div class="map-section section">
            @if (filled($schoolMap['data_uri'] ?? null))
                <img src="{{ $schoolMap['data_uri'] }}" alt="" class="map-section__img">
            @elseif (filled($schoolMap['svg'] ?? null))
                {!! $schoolMap['svg'] !!}
            @endif
            @if (filled($schoolMap['caption'] ?? null))
                <p class="map-section__legend"><strong>{{ __('Leitura') }}:</strong> {{ $schoolMap['caption'] }}</p>
            @endif
            @php $mapStats = is_array($schoolMap['stats'] ?? null) ? $schoolMap['stats'] : []; @endphp
            @if ($mapStats !== [])
                <table class="map-stats">
                    <tr>
                        <td><strong>{{ __('Escolas no mapa') }}</strong><br>{{ number_format((int) ($mapStats['schools'] ?? 0), 0, ',', '.') }}</td>
                        <td><strong>{{ __('Matrículas (filtro)') }}</strong><br>{{ number_format((int) ($mapStats['matriculas_total'] ?? 0), 0, ',', '.') }}</td>
                        <td><strong>{{ __('Âmbito') }}</strong><br>{{ ($schoolMap['map_scope'] ?? '') === 'matricula' ? __('Matrículas no recorte') : __('Unidades no recorte') }}</td>
                    </tr>
                </table>
            @endif
            @if (filled($schoolMap['geo_note'] ?? null))
                <p class="muted">{{ $schoolMap['geo_note'] }}</p>
            @endif
        </div>
    @endif

    <script type="text/php">
        if (isset($pdf)) {
            $primaryRgb = [15, 118, 110];
            $pageLabel = "{{ __('Página') }} {PAGE_NUM} {{ __('de') }} {PAGE_COUNT}";
            $pdf->page_text(468, 808, $pageLabel, null, 7.5, $primaryRgb);
        }
    </script>
</body>
</html>
