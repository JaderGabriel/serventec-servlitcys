@php
    $brand = is_array($brand ?? null) ? $brand : config('analytics.pdf_report.brand', []);
    $colors = is_array($colors ?? null) ? $colors : config('analytics.pdf_report.colors', []);
    $primary = $colors['primary'] ?? '#0f766e';
    $secondary = $colors['secondary'] ?? '#4338ca';
    $cover = is_array($cover ?? null) ? $cover : [];
    $health = is_array($health ?? null) ? $health : [];
    $disc = is_array($discrepancies ?? null) ? $discrepancies : [];
    $fundeb = is_array($fundeb ?? null) ? $fundeb : [];
    $other = is_array($other_funding ?? null) ? $other_funding : [];
    $work = is_array($work_done ?? null) ? $work_done : [];
    $yearCmp = is_array($year_comparison ?? null) ? $year_comparison : [];
    $munState = is_array($municipal_vs_state ?? null) ? $municipal_vs_state : [];
    $chartBlocks = is_array($charts ?? null) ? $charts : [];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório {{ $cover['municipality'] ?? '' }}</title>
    <style>
        @page { margin: 72px 42px 68px 42px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #1e293b; line-height: 1.45; }
        h1 { color: {{ $primary }}; font-size: 20pt; margin: 0 0 8px; }
        h2 { color: {{ $secondary }}; font-size: 13pt; margin: 22px 0 8px; border-bottom: 2px solid {{ $primary }}; padding-bottom: 4px; page-break-after: avoid; }
        h3 { font-size: 11pt; color: {{ $primary }}; margin: 14px 0 6px; }
        p { margin: 0 0 8px; }
        .cover-page { page-break-after: always; padding: 0 0 12px; }
        .cover-hero { margin-bottom: 14px; border-radius: 10px; overflow: hidden; }
        .cover-hero__main { padding: 26px 28px 22px; color: #fff; }
        .cover-eyebrow { margin: 0 0 6px; font-size: 9pt; letter-spacing: 0.12em; text-transform: uppercase; opacity: 0.92; }
        .cover-report-type { margin: 0 0 4px; font-size: 11pt; font-weight: bold; color: {{ $primaryLight }}; }
        .cover-city { margin: 0; font-size: 28pt; line-height: 1.15; font-weight: bold; color: #fff; }
        .cover-region { margin: 10px 0 0; font-size: 10pt; color: #ecfdf5; opacity: 0.95; }
        .cover-year-badge { display: inline-block; background: rgba(255,255,255,0.18); border: 2px solid rgba(255,255,255,0.55); border-radius: 10px; padding: 12px 16px; text-align: center; min-width: 120px; }
        .cover-year-badge__label { display: block; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.9; }
        .cover-year-badge__value { display: block; font-size: 22pt; font-weight: bold; line-height: 1.2; margin-top: 4px; }
        .cover-facts { margin: 0 0 12px; border-collapse: separate; border-spacing: 6px 0; }
        .cover-fact-cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; vertical-align: top; }
        .cover-fact-label { display: block; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; font-weight: bold; }
        .cover-fact-value { display: block; font-size: 11pt; font-weight: bold; color: {{ $primary }}; margin-top: 3px; }
        .cover-fact-value--small { font-size: 8.5pt; font-weight: normal; color: #475569; }
        .cover-filters { margin: 0 0 14px; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; }
        .cover-filters__title { padding: 8px 12px 4px; font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: {{ $primary }}; }
        .cover-filter-chip { padding: 6px 12px 10px; vertical-align: top; border-right: 1px solid #ccfbf1; }
        .cover-filter-chip:last-child { border-right: none; }
        .cover-filter-chip__label { display: block; font-size: 7.5pt; color: #64748b; text-transform: uppercase; }
        .cover-filter-chip__value { display: block; font-size: 11pt; font-weight: bold; color: #0f172a; margin-top: 2px; }
        .cover-filters-empty { margin: 0 0 12px; font-size: 10pt; color: #64748b; }
        .cover-maps { margin: 0 0 14px; }
        .cover-map-main, .cover-map-side { vertical-align: top; padding: 0 4px 0 0; }
        .cover-map-img { width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; display: block; }
        .cover-map-img--main { max-height: 240px; }
        .cover-map-img--regional, .cover-map-img--brand { max-height: 200px; }
        .cover-map-caption { margin: 6px 0 0; font-size: 8pt; color: #64748b; line-height: 1.35; }
        .cover-map-caption__muted { font-size: 7.5pt; }
        .cover-map-placeholder { background: #f1f5f9; border: 1px dashed #94a3b8; border-radius: 8px; padding: 28px 16px; text-align: center; font-size: 9.5pt; color: #64748b; min-height: 120px; }
        .cover-summary { background: linear-gradient(90deg, {{ $primaryLight }} 0%, #fff 100%); border-left: 5px solid {{ $primary }}; padding: 14px 16px; border-radius: 0 8px 8px 0; margin-bottom: 8px; }
        .cover-summary__title { margin: 0 0 6px; font-size: 11pt; font-weight: bold; color: {{ $primary }}; }
        .cover-summary__text { margin: 0; font-size: 10pt; line-height: 1.45; color: #334155; }
        .cover-summary__kpi { margin: 10px 0 0; font-size: 10.5pt; font-weight: bold; color: {{ $secondary }}; }
        .cover-footer-note { margin: 0; font-size: 8pt; color: #94a3b8; text-align: center; }
        .kpi-row { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .kpi-row td { border: 1px solid #e2e8f0; padding: 8px 10px; background: #f8fafc; }
        .kpi-label { font-size: 8pt; color: #64748b; text-transform: uppercase; }
        .kpi-value { font-size: 13pt; font-weight: bold; color: {{ $primary }}; }
        table.data { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 8px 0; }
        table.data th { background: {{ $primary }}; color: #fff; padding: 6px 8px; text-align: left; }
        table.data td { border: 1px solid #e2e8f0; padding: 5px 8px; }
        .box { background: #f0fdfa; border-left: 4px solid {{ $primary }}; padding: 10px 12px; margin: 10px 0; }
        .chart-block { text-align: center; margin: 12px 0; page-break-inside: avoid; }
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
        .pdf-footer__tagline { display: block; font-size: 6.5pt; color: #64748b; margin-top: 1px; line-height: 1.25; }
        .pdf-footer__context-city { display: block; font-size: 8pt; font-weight: bold; color: #334155; line-height: 1.25; }
        .pdf-footer__context-year { display: block; font-size: 7pt; color: #64748b; margin-top: 2px; }
        .pdf-footer__serventec-name { display: block; font-size: 7.5pt; font-weight: bold; color: {{ $secondary }}; line-height: 1.2; }
        .pdf-footer__serventec-link { display: block; font-size: 7pt; color: {{ $primary }}; text-decoration: none; margin-top: 1px; }
        .pdf-footer__dev { display: block; font-size: 6pt; color: #94a3b8; margin-top: 2px; }
        .pdf-footer__dev-link { color: #64748b; text-decoration: none; }
        .pdf-footer__page-slot { display: block; font-size: 7pt; color: #64748b; margin-top: 3px; min-height: 9px; }
        .muted { color: #64748b; font-size: 9pt; }
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

    {{-- Serventec --}}
    <h2>{{ __('1. Serventec — diagnóstico consolidado') }}</h2>
    <div class="section">
        <p>{{ $health['footnote'] ?? '' }}</p>
        <table class="kpi-row">
            <tr>
                <td><div class="kpi-label">{{ __('Pendências cadastro') }}</div><div class="kpi-value">{{ number_format((int) data_get($health, 'summary.pendencias_cadastro', 0), 0, ',', '.') }}</div></td>
                <td><div class="kpi-label">{{ __('Perda estimada/ano') }}</div><div class="kpi-value">R$ {{ number_format((float) data_get($health, 'summary.perda_estimada_anual', 0), 2, ',', '.') }}</div></td>
                <td><div class="kpi-label">{{ __('Ganho potencial/ano') }}</div><div class="kpi-value">R$ {{ number_format((float) data_get($health, 'summary.ganho_potencial_anual', 0), 2, ',', '.') }}</div></td>
            </tr>
        </table>
        @if (is_array($health['vaaf_comparacao'] ?? null))
            <h3>{{ __('VAAF municipal × prévia federal') }}</h3>
            <table class="data">
                <tr><th>{{ __('Indicador') }}</th><th>{{ __('Valor') }}</th></tr>
                @foreach (['real', 'previa'] as $key)
                    @php $row = $health['vaaf_comparacao'][$key] ?? null; @endphp
                    @if (is_array($row))
                        <tr>
                            <td>{{ $row['label'] ?? $key }}</td>
                            <td>{{ $row['value'] ?? '—' }}@if (filled($row['hint'] ?? null)) <span class="muted"> — {{ $row['hint'] }}</span>@endif</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif
    </div>

    @if (count($yearCmp) > 0)
        <h2>{{ __('2. Comparativo entre anos letivos') }}</h2>
        <table class="data">
            <tr><th>{{ __('Ano') }}</th><th>{{ __('Matrículas activas (filtro)') }}</th><th></th></tr>
            @foreach ($yearCmp as $row)
                <tr>
                    <td>{{ $row['ano'] ?? '' }}</td>
                    <td>{{ isset($row['matriculas']) ? number_format((int) $row['matriculas'], 0, ',', '.') : '—' }}</td>
                    <td>{{ $row['label'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($munState['available'] ?? false)
        <h2>{{ __('3. Município × referência estadual (SAEB)') }}</h2>
        <p class="muted">{{ $munState['note'] ?? '' }}</p>
        <table class="data">
            <tr>
                <th>{{ __('Disciplina') }}</th>
                <th>{{ __('Ano (munic.)') }}</th>
                <th>{{ __('Município') }}</th>
                <th>{{ __('Ano (UF)') }}</th>
                <th>{{ __('Estado') }}</th>
            </tr>
            @foreach ($munState['rows'] ?? [] as $row)
                <tr>
                    <td>{{ $row['disciplina'] ?? '' }}</td>
                    <td>{{ $row['ano_municipio'] ?? '' }}</td>
                    <td>{{ $row['valor_municipio'] ?? '' }}</td>
                    <td>{{ $row['ano_estado'] ?? '' }}</td>
                    <td>{{ $row['valor_estado'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('4. Discrepâncias e impacto financeiro') }}</h2>
    <p>{{ $disc['intro'] ?? '' }}</p>
    <p class="muted">{{ $disc['funding_aviso'] ?? '' }}</p>

    <h2>{{ __('5. FUNDEB e complementação') }}</h2>
    @php $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : []; @endphp
    <p>{{ $proj['aviso'] ?? '' }}</p>
    @if (filled($proj['previsao_referencia_label'] ?? null))
        <p><strong>{{ __('Previsão base') }}:</strong> {{ $proj['previsao_referencia_label'] }}</p>
    @endif

    <h2>{{ __('6. Financiamentos (programas complementares)') }}</h2>
    <p>{{ $other['intro'] ?? '' }}</p>
    @foreach (is_array($other['programs'] ?? null) ? $other['programs'] : [] as $prog)
        <h3>{{ $prog['titulo'] ?? '' }}</h3>
        <p>{{ $prog['descricao'] ?? '' }}</p>
    @endforeach

    <h2>{{ __('7. Censo e cadastro') }}</h2>
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

    @if (count($chartBlocks) > 0)
        <h2>{{ __('8. Gráficos e indicadores visuais') }}</h2>
        @foreach ($chartBlocks as $block)
            <div class="chart-block section">
                <p class="muted"><strong>{{ $block['section'] ?? '' }}</strong> — {{ $block['title'] ?? '' }}</p>
                {!! $block['svg'] ?? '' !!}
            </div>
        @endforeach
    @endif

    @if (count(is_array($health['thematic_blocks'] ?? null) ? $health['thematic_blocks'] : []) > 0)
        <h2>{{ __('9. Leitura temática') }}</h2>
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

    <script type="text/php">
        if (isset($pdf)) {
            $primaryRgb = [15, 118, 110];
            $pageLabel = "{{ __('Página') }} {PAGE_NUM} {{ __('de') }} {PAGE_COUNT}";
            $pdf->page_text(468, 808, $pageLabel, null, 7.5, $primaryRgb);
        }
    </script>
</body>
</html>
