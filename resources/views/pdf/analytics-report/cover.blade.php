@php
    $primary = $colors['primary'] ?? '#0f766e';
    $secondary = $colors['secondary'] ?? '#4338ca';
    $primaryLight = $colors['primary_light'] ?? '#ccfbf1';
    $serventecName = $brand['serventec_name'] ?? 'Serventec Assessoria';
    $cover = is_array($cover ?? null) ? $cover : [];
    $filterDetails = is_array($cover['filter_details'] ?? null) ? $cover['filter_details'] : [];
    $yearValue = $cover['year_value'] ?? ($cover['year_label'] ?? '—');
    $mapUri = $cover['map_image_data_uri'] ?? null;
    $osmBackdropUri = $cover['map_osm_backdrop_uri'] ?? null;
    $regionalMapUri = $cover['regional_map_data_uri'] ?? null;
    $brandUri = $cover['regional_image_data_uri'] ?? null;
    $headlineKpis = is_array($cover['headline_kpis'] ?? null) ? $cover['headline_kpis'] : [];
    $systemicDimensions = is_array($cover['systemic_dimensions'] ?? null) ? $cover['systemic_dimensions'] : [];
    $culturalPillars = is_array($cover['cultural_pillars'] ?? null) ? $cover['cultural_pillars'] : [];
    $executiveSummary = is_array($cover['executive_summary'] ?? null) ? $cover['executive_summary'] : [];
    $kpiToneBg = static fn (string $tone): string => match ($tone) {
        'success' => '#dcfce7',
        'warning' => '#fef3c7',
        'danger' => '#ffe4e6',
        'primary' => $primaryLight,
        default => '#f8fafc',
    };
    $kpiToneColor = static fn (string $tone): string => match ($tone) {
        'success' => '#15803d',
        'warning' => '#b45309',
        'danger' => '#be123c',
        'primary' => $primary,
        default => '#334155',
    };
@endphp
<div class="cover-page">
    <table class="cover-hero" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="cover-hero__main" style="background: linear-gradient(135deg, {{ $primary }} 0%, {{ $secondary }} 100%);">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="vertical-align: top; width: 68%;">
                            <p class="cover-eyebrow">{{ $serventecName }}</p>
                            <p class="cover-audience">{{ $cover['audience_line'] ?? __('Documento institucional para gestão municipal') }}</p>
                            <p class="cover-report-type">{{ $cover['report_title'] ?? __('Relatório de gestão educacional municipal') }}</p>
                            <h1 class="cover-city">{{ $cover['municipality_line'] ?? ($cover['municipality'] ?? '') }}</h1>
                            @if (filled($cover['municipality_subtitle'] ?? null))
                                <p class="cover-region">{{ $cover['municipality_subtitle'] }}</p>
                            @elseif (filled($cover['region_label'] ?? null))
                                <p class="cover-region">{{ $cover['region_label'] }}</p>
                            @endif
                        </td>
                        <td style="vertical-align: top; text-align: right; width: 32%;">
                            <div class="cover-year-badge">
                                <span class="cover-year-badge__label">{{ __('Ano letivo') }}</span>
                                <span class="cover-year-badge__value">{{ $yearValue }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
                @if (filled($cover['gestao_lead'] ?? null))
                    <p class="cover-lead">{{ $cover['gestao_lead'] }}</p>
                @endif
            </td>
        </tr>
    </table>

    @if ($headlineKpis !== [])
        <table class="cover-kpi-strip" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                @foreach ($headlineKpis as $kpi)
                    @php
                        $tone = (string) ($kpi['tone'] ?? 'neutral');
                    @endphp
                    <td class="cover-kpi-cell" style="background: {{ $kpiToneBg($tone) }}; border-color: {{ $kpiToneColor($tone) }};">
                        <span class="cover-kpi-label">{{ $kpi['label'] ?? '' }}</span>
                        <span class="cover-kpi-value" style="color: {{ $kpiToneColor($tone) }};">{{ $kpi['value'] ?? '—' }}</span>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="cover-facts" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            @if (filled($cover['ibge'] ?? null))
                <td class="cover-fact-cell">
                    <span class="cover-fact-label">IBGE</span>
                    <span class="cover-fact-value">{{ $cover['ibge'] }}</span>
                </td>
            @endif
            @if (filled($cover['uf'] ?? null))
                <td class="cover-fact-cell">
                    <span class="cover-fact-label">{{ __('Estado') }}</span>
                    <span class="cover-fact-value">{{ $cover['uf_name'] ?? $cover['uf'] }} ({{ $cover['uf'] }})</span>
                </td>
            @endif
            <td class="cover-fact-cell">
                <span class="cover-fact-label">{{ __('Gerado em') }}</span>
                <span class="cover-fact-value">{{ $generated_at ?? now()->format('d/m/Y H:i') }}</span>
            </td>
            @if (filled($cover['coords_source'] ?? null))
                <td class="cover-fact-cell">
                    <span class="cover-fact-label">{{ __('Georref.') }}</span>
                    <span class="cover-fact-value cover-fact-value--small">{{ $cover['coords_source'] }}</span>
                </td>
            @endif
        </tr>
    </table>

    @if ($filterDetails !== [])
        <table class="cover-filters" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td colspan="4" class="cover-filters__title">{{ __('Recorte do relatório (filtros aplicados)') }}</td>
            </tr>
            <tr>
                @foreach ($filterDetails as $detail)
                    <td class="cover-filter-chip">
                        <span class="cover-filter-chip__label">{{ $detail['label'] ?? '' }}</span>
                        <span class="cover-filter-chip__value">{{ $detail['value'] ?? '' }}</span>
                    </td>
                @endforeach
            </tr>
        </table>
    @else
        <p class="cover-filters-empty">{{ $cover['year_label'] ?? '' }} · {{ $cover['report_subtitle'] ?? '' }}</p>
    @endif

    @if ($systemicDimensions !== [] || $culturalPillars !== [])
        <table class="cover-framework" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                @if ($systemicDimensions !== [])
                    <td class="cover-framework__col" style="width: 55%; vertical-align: top;">
                        <p class="cover-framework__heading">{{ __('Dimensões sistêmicas do relatório') }}</p>
                        <p class="cover-framework__intro">{{ __('Estrutura analítica alinhada ao painel municipal — do cadastro da rede aos repasses e ao Censo.') }}</p>
                        @foreach ($systemicDimensions as $dim)
                            <div class="cover-dimension">
                                <p class="cover-dimension__title">
                                    @if (filled($dim['step'] ?? null))
                                        <span class="cover-dimension__step">{{ $dim['step'] }}.</span>
                                    @endif
                                    {{ $dim['title'] ?? '' }}
                                </p>
                                @if (filled($dim['hint'] ?? null))
                                    <p class="cover-dimension__hint">{{ $dim['hint'] }}</p>
                                @endif
                                @if (is_array($dim['topics'] ?? null) && $dim['topics'] !== [])
                                    <ul class="cover-dimension__topics">
                                        @foreach ($dim['topics'] as $topic)
                                            <li>{{ $topic }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </td>
                @endif
                @if ($culturalPillars !== [])
                    <td class="cover-framework__col cover-framework__col--culture" style="width: 45%; vertical-align: top;">
                        <p class="cover-framework__heading">{{ __('Compromissos culturais da educação pública') }}</p>
                        <p class="cover-framework__intro">{{ __('Eixos de leitura para gestão democrática, equidade e responsabilidade fiscal na educação municipal.') }}</p>
                        @foreach ($culturalPillars as $pillar)
                            <div class="cover-pillar">
                                <p class="cover-pillar__title">{{ $pillar['title'] ?? '' }}</p>
                                <p class="cover-pillar__text">{{ $pillar['text'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </td>
                @endif
            </tr>
        </table>
    @endif

    <table class="cover-maps" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="cover-map-main" style="width: {{ $regionalMapUri ? '62%' : '100%' }};">
                @if (filled($mapUri))
                    <div class="cover-map-stack">
                        @if (filled($osmBackdropUri))
                            <img src="{{ $osmBackdropUri }}" alt="" class="cover-map-img cover-map-img--backdrop">
                        @endif
                        <img src="{{ $mapUri }}" alt="" class="cover-map-img cover-map-img--main {{ filled($osmBackdropUri) ? 'cover-map-img--overlay' : '' }}">
                    </div>
                    <p class="cover-map-caption">
                        <strong>{{ __('Território municipal') }}</strong> —
                        {{ $cover['map_caption'] ?? __('Recorte ilustrativo da rede') }}
                        @if (filled($cover['map_source'] ?? null))
                            <span class="cover-map-caption__muted">({{ $cover['map_source'] }})</span>
                        @endif
                    </p>
                @else
                    <div class="cover-map-placeholder">
                        {{ __('Mapa indisponível nesta geração. Sincronize coordenadas das escolas ou código INEP para georreferenciação.') }}
                    </div>
                @endif
            </td>
            @if (filled($regionalMapUri))
                <td class="cover-map-side" style="width: 38%;">
                    <img src="{{ $regionalMapUri }}" alt="" class="cover-map-img cover-map-img--regional">
                    <p class="cover-map-caption">
                        <strong>{{ __('Contexto regional') }}</strong> — {{ $cover['regional_map_caption'] ?? __('Recorte ampliado') }}
                    </p>
                </td>
            @elseif (filled($brandUri))
                <td class="cover-map-side" style="width: 38%;">
                    <img src="{{ $brandUri }}" alt="" class="cover-map-img cover-map-img--brand">
                    <p class="cover-map-caption">{{ __('Identidade · educação pública municipal') }}</p>
                </td>
            @endif
        </tr>
    </table>

    @if ($executiveSummary !== [])
        <div class="cover-summary">
            <p class="cover-summary__title">{{ __('Mensagem executiva') }}</p>
            <ul class="cover-summary__list">
                @foreach ($executiveSummary as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            @if (isset($health['compliance_score']) && is_numeric($health['compliance_score']))
                <p class="cover-summary__kpi">
                    <strong>{{ __('Índice de conformidade (cadastro + FUNDEB)') }}:</strong>
                    {{ (int) $health['compliance_score'] }}/100 — {{ $health['compliance_label'] ?? '' }}
                </p>
            @endif
        </div>
    @elseif (isset($health) && is_array($health) && filled($health['intro'] ?? null))
        <div class="cover-summary">
            <p class="cover-summary__title">{{ __('Resumo executivo') }}</p>
            <p class="cover-summary__text">{{ $health['intro'] }}</p>
            @if (isset($health['compliance_score']))
                <p class="cover-summary__kpi">
                    <strong>{{ __('Índice de conformidade') }}:</strong>
                    {{ $health['compliance_score'] }}/100 — {{ $health['compliance_label'] ?? '' }}
                </p>
            @endif
        </div>
    @endif

    <p class="cover-footer-note">
        {{ $cover['report_subtitle'] ?? '' }}
        @if (filled($cover['confidentiality_note'] ?? null))
            <br><span class="cover-footer-note__legal">{{ $cover['confidentiality_note'] }}</span>
        @endif
    </p>
</div>
