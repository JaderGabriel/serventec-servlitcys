@php
    $brand = is_array($brand ?? null) ? $brand : config('analytics.pdf_report.brand', []);
    $colors = is_array($colors ?? null) ? $colors : config('analytics.pdf_report.colors', []);
    $primary = $colors['primary'] ?? '#0f766e';
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
    @include('pdf.analytics-report.partials.pdf-styles', ['colors' => $colors])
</head>
<body>
    @include('pdf.analytics-report.footer', [
        'brand' => $brand,
        'colors' => $colors,
        'cover' => $cover,
        'bibliography' => $bibliography ?? [],
        'generated_at' => $generated_at ?? null,
    ])

    @include('pdf.analytics-report.cover', [
        'cover' => $cover,
        'brand' => $brand,
        'colors' => $colors,
        'health' => $health,
        'generated_at' => $generated_at ?? null,
    ])

    @include('pdf.analytics-report.partials.preface')

    @include('pdf.analytics-report.partials.toc', [
        'table_of_contents' => data_get($atm_report, 'table_of_contents', []),
    ])

    @include('pdf.analytics-report.partials.atm-sections', [
        'atm_report' => $atm_report ?? [],
        'publication' => $publication ?? [],
        'bibliography' => $bibliography ?? [],
        'school_units_map' => $schoolMap,
    ])

    @include('pdf.analytics-report.partials.preamble', ['health' => $health])

    <div class="appendix-section">
        <h2>{{ __('Apêndice A — Diagnóstico Serventec (detalhe)') }}</h2>
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
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </div>

    <div class="appendix-section">
        @include('pdf.analytics-report.comparatives', [
            'comparatives' => $comparatives ?? [],
            'year_comparison' => $yearCmp,
            'municipal_vs_state' => $munState,
        ])
    </div>

    <div class="appendix-section">
        <h2>{{ __('3. Cadastro, matrículas e rede escolar') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'cadastro'])
        @if (filled($overview['filter_note'] ?? null))
            <p class="muted">{{ $overview['filter_note'] }}</p>
        @endif
        <table class="kpi-row">
            <tr>
                <td><div class="kpi-label">{{ __('Escolas (recorte)') }}</div><div class="kpi-value">{{ isset($overviewKpis['escolas']) ? number_format((int) $overviewKpis['escolas'], 0, ',', '.') : '—' }}</div></td>
                <td><div class="kpi-label">{{ __('Turmas') }}</div><div class="kpi-value">{{ isset($overviewKpis['turmas']) ? number_format((int) $overviewKpis['turmas'], 0, ',', '.') : '—' }}</div></td>
                <td><div class="kpi-label">{{ __('Matrículas activas') }}</div><div class="kpi-value">{{ isset($overviewKpis['matriculas']) ? number_format((int) $overviewKpis['matriculas'], 0, ',', '.') : '—' }}</div></td>
                <td><div class="kpi-label">{{ __('Turmas distintas (mat.)') }}</div><div class="kpi-value">{{ isset($enrollmentKpis['turmas_distintas']) ? number_format((int) $enrollmentKpis['turmas_distintas'], 0, ',', '.') : '—' }}</div></td>
            </tr>
        </table>
        @php $distorcao = is_array($enrollment['distorcao'] ?? null) ? $enrollment['distorcao'] : null; @endphp
        @if ($distorcao !== null)
            <p><strong>{{ __('Distorção idade-série') }}:</strong>
                {{ number_format((int) ($distorcao['com'] ?? 0), 0, ',', '.') }} {{ __('com distorção') }}
                ({{ $distorcao['pct'] ?? '—' }}%)
            </p>
        @endif
    </div>

    <div class="appendix-section">
        <h2>{{ __('4. Pedagógico, inclusão e permanência') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'pedagogical'])
        @if (filled($performance['message'] ?? null))
            <p class="muted">{{ $performance['message'] }}</p>
        @endif
        @if ($perfKpis !== [])
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
    </div>

    <div class="appendix-section">
        <h2>{{ __('5. Discrepâncias e impacto financeiro') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'discrepancies'])
        <p>{{ $disc['intro'] ?? '' }}</p>
        <table class="kpi-row">
            <tr>
                <td><div class="kpi-label">{{ __('Com problema') }}</div><div class="kpi-value">{{ number_format((int) ($discSummary['com_problema'] ?? 0), 0, ',', '.') }}</div></td>
                <td><div class="kpi-label">{{ __('Escolas afetadas') }}</div><div class="kpi-value">{{ number_format((int) ($discSummary['escolas_afetadas'] ?? 0), 0, ',', '.') }}</div></td>
                <td><div class="kpi-label">{{ __('Perda estimada') }}</div><div class="kpi-value">R$ {{ number_format((float) ($discSummary['perda_estimada_anual'] ?? 0), 2, ',', '.') }}</div></td>
            </tr>
        </table>
    </div>

    <div class="appendix-section">
        <h2>{{ __('6. FUNDEB — previsão e complementação') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'fundeb'])
        <span class="official-tag">{{ __('Lei 14.113/2020 · FNDE') }}</span>
        <p>{{ $fundeb['intro'] ?? '' }}</p>
        @php
            $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : [];
            $fundebRefPdf = is_array($comparatives['fundeb_reference_tables'] ?? null)
                ? $comparatives['fundeb_reference_tables']
                : [];
        @endphp
        @if (is_array($proj['totais'] ?? null))
            <table class="kpi-row">
                <tr>
                    <td><div class="kpi-label">{{ __('Base Fundeb anual') }}</div><div class="kpi-value">@if (isset($proj['totais']['fundeb_base_anual'])) R$ {{ number_format((float) $proj['totais']['fundeb_base_anual'], 2, ',', '.') }} @else — @endif</div></td>
                    <td><div class="kpi-label">{{ __('Matrículas') }}</div><div class="kpi-value">{{ isset($proj['matriculas_base']) ? number_format((int) $proj['matriculas_base'], 0, ',', '.') : (isset($proj['matriculas']) ? number_format((int) $proj['matriculas'], 0, ',', '.') : '—') }}</div></td>
                </tr>
            </table>
        @endif

        @if ($fundebRefPdf !== [])
            <h3>{{ __('Quadros de referência — receita, complementação e planejamento') }}</h3>
            <p class="muted">{{ __('Valores objetivos para o exercício corrente e exercícios de planejamento (Portaria FNDE, eixos VAAF/VAAT/VAAR e cenários indicativos). Detalhe ampliado na secção 2 — Comparativos.') }}</p>
            @include('pdf.analytics-report.partials.fundeb-reference-tables', ['tables' => $fundebRefPdf])
        @endif

        @php $informe = is_array($fundeb['complementacao_informe'] ?? null) ? $fundeb['complementacao_informe'] : []; @endphp
        @if (count(is_array($informe['blocos'] ?? null) ? $informe['blocos'] : []) > 0)
            <h3>{{ __('Indicadores de complementação (síntese)') }}</h3>
            @foreach (array_slice($informe['blocos'], 0, 3) as $bloco)
                @if (is_array($bloco))
                    <p><strong>{{ $bloco['titulo'] ?? '' }}</strong> — {{ $bloco['subtitulo'] ?? '' }}</p>
                    @if (count(is_array($bloco['indicadores'] ?? null) ? $bloco['indicadores'] : []) > 0)
                        <table class="data">
                            <tr><th>{{ __('Indicador') }}</th><th>{{ __('Valor') }}</th></tr>
                            @foreach ($bloco['indicadores'] as $ind)
                                @if (is_array($ind))
                                    <tr><td>{{ $ind['label'] ?? '' }}</td><td>{{ $ind['value'] ?? '—' }}</td></tr>
                                @endif
                            @endforeach
                        </table>
                    @endif
                @endif
            @endforeach
        @endif
    </div>

    <div class="appendix-section">
        <h2>{{ __('7. Financiamentos complementares') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'other_funding'])
        <p>{{ $other['intro'] ?? '' }}</p>
        @foreach (is_array($other['programs'] ?? null) ? array_slice($other['programs'], 0, 4) : [] as $prog)
            <h3>{{ $prog['titulo'] ?? '' }}</h3>
            <p class="muted">{{ $prog['descricao'] ?? '' }}</p>
        @endforeach
    </div>

    <div class="appendix-section">
        <h2>{{ __('8. Censo e cadastro') }}</h2>
        @include('pdf.analytics-report.partials.section-lead', ['section' => 'work_done'])
        <p>{{ $work['intro'] ?? '' }}</p>
    </div>

    @if (count($chartBlocks) > 0)
        <div class="appendix-section">
            <h2>{{ __('9. Gráficos para decisão') }}</h2>
            @include('pdf.analytics-report.partials.section-lead', ['section' => 'charts'])
            @foreach ($chartBlocks as $block)
                <div class="chart-block">
                    <div class="chart-block__head">
                        <span class="chart-block__section">{{ $block['section'] ?? '' }}</span>
                        <span class="chart-block__title">{{ $block['title'] ?? '' }}</span>
                    </div>
                    {!! $block['svg'] ?? '' !!}
                </div>
            @endforeach
        </div>
    @endif

    @if (count(is_array($health['thematic_blocks'] ?? null) ? $health['thematic_blocks'] : []) > 0)
        <div class="appendix-section">
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
        </div>
    @endif

    @include('pdf.analytics-report.partials.data-gaps', ['data_gaps' => $data_gaps ?? []])

    <script type="text/php">
        if (isset($pdf)) {
            $mutedRgb = [100, 116, 139];
            $pdf->page_text(32, 24, "{{ __('Página') }} {PAGE_NUM} / {PAGE_COUNT}", null, 7, $mutedRgb);
        }
    </script>
</body>
</html>
