@php
    $brand = is_array($brand ?? null) ? $brand : config('analytics.pdf_report.brand', []);
    $colors = is_array($colors ?? null) ? $colors : config('analytics.pdf_report.colors', []);
    $primary = $colors['primary'] ?? '#0f766e';
    $secondary = $colors['secondary'] ?? '#4338ca';
    $serventecName = $brand['serventec_name'] ?? 'Serventec Assessoria';
    $serventecUrl = $brand['serventec_url'] ?? '';
    $devName = $brand['developer_name'] ?? '';
    $devGithub = $brand['developer_github'] ?? '';
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
        @page { margin: 72px 42px 58px 42px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #1e293b; line-height: 1.45; }
        h1 { color: {{ $primary }}; font-size: 20pt; margin: 0 0 8px; }
        h2 { color: {{ $secondary }}; font-size: 13pt; margin: 22px 0 8px; border-bottom: 2px solid {{ $primary }}; padding-bottom: 4px; page-break-after: avoid; }
        h3 { font-size: 11pt; color: {{ $primary }}; margin: 14px 0 6px; }
        p { margin: 0 0 8px; }
        .cover { page-break-after: always; min-height: 90vh; }
        .cover-header { background: linear-gradient(135deg, {{ $primary }} 0%, {{ $secondary }} 100%); color: #fff; padding: 28px 32px; border-radius: 8px; }
        .cover-header h1 { color: #fff; font-size: 26pt; }
        .cover-meta { margin-top: 16px; font-size: 11pt; color: #ecfdf5; }
        .cover-grid { margin-top: 20px; }
        .cover-grid td { vertical-align: top; padding: 6px; }
        .cover-img { width: 100%; max-height: 200px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; }
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
        .footer {
            position: fixed;
            bottom: -42px;
            left: 0;
            right: 0;
            height: 36px;
            font-size: 7.5pt;
            color: #64748b;
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
        }
        .footer table { width: 100%; }
        .muted { color: #64748b; font-size: 9pt; }
        ul.compact { margin: 4px 0; padding-left: 18px; }
        ul.compact li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="footer">
        <table>
            <tr>
                <td style="width: 55%;">
                    <strong>{{ $serventecName }}</strong>
                    @if (filled($serventecUrl))
                        — <a href="{{ $serventecUrl }}" style="color: {{ $primary }};">{{ $serventecUrl }}</a>
                    @endif
                </td>
                <td style="width: 45%; text-align: right;">
                    @if (filled($devName) && filled($devGithub))
                        {{ $devName }} — <a href="{{ $devGithub }}" style="color: {{ $secondary }};">GitHub</a>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Capa --}}
    <div class="cover">
        <div class="cover-header">
            <p style="font-size: 10pt; opacity: 0.9; margin: 0;">{{ $serventecName }}</p>
            <h1>{{ $cover['municipality'] ?? ($city['name'] ?? '') }}</h1>
            <div class="cover-meta">
                <strong>{{ __('UF') }}:</strong> {{ $cover['uf'] ?? '' }}
                @if (filled($cover['ibge'] ?? null))
                    · <strong>IBGE:</strong> {{ $cover['ibge'] }}
                @endif
                <br>
                <strong>{{ $cover['year_label'] ?? $year_label ?? '' }}</strong>
                · {{ __('Gerado em') }} {{ $generated_at ?? now()->format('d/m/Y H:i') }}
                @if (filled($cover['region_label'] ?? null))
                    <br>{{ $cover['region_label'] }}
                @endif
            </div>
        </div>
        <table class="cover-grid" style="width:100%; margin-top: 20px;">
            <tr>
                <td style="width: 55%;">
                    @if (filled($cover['map_image_url'] ?? null))
                        <img src="{{ $cover['map_image_url'] }}" alt="Mapa" class="cover-img">
                        <p class="muted">{{ __('Mapa OpenStreetMap (centro das escolas georreferenciadas)') }}</p>
                    @else
                        <div class="box">{{ __('Mapa indisponível — sincronize coordenadas das unidades escolares.') }}</div>
                    @endif
                </td>
                <td style="width: 45%;">
                    @if (filled($cover['regional_image_data_uri'] ?? null))
                        <img src="{{ $cover['regional_image_data_uri'] }}" alt="Regional" class="cover-img" style="max-height: 180px;">
                    @endif
                    <p class="muted">{{ __('Imagem regional / identidade visual do relatório') }}</p>
                </td>
            </tr>
        </table>
        <div class="box" style="margin-top: 24px;">
            <p style="margin:0;"><strong>{{ __('Resumo executivo') }}</strong></p>
            <p style="margin:6px 0 0;">{{ $health['intro'] ?? '' }}</p>
            @if (isset($health['compliance_score']))
                <p style="margin-top:8px;"><strong>{{ __('Índice de conformidade') }}:</strong> {{ $health['compliance_score'] }}/100 — {{ $health['compliance_label'] ?? '' }}</p>
            @endif
        </div>
    </div>

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
            $text = "{{ __('Página') }} {PAGE_NUM} / {PAGE_COUNT}";
            $pdf->page_text(480, 820, $text, null, 8, array(100, 116, 139));
        }
    </script>
</body>
</html>
