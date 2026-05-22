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
@endphp
<div class="cover-page">
    <table class="cover-hero" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="cover-hero__main" style="background: linear-gradient(135deg, {{ $primary }} 0%, {{ $secondary }} 100%);">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="vertical-align: top; width: 68%;">
                            <p class="cover-eyebrow">{{ $serventecName }}</p>
                            <p class="cover-report-type">{{ $cover['report_title'] ?? __('Relatório analítico municipal') }}</p>
                            <h1 class="cover-city">{{ $cover['municipality_line'] ?? ($cover['municipality'] ?? '') }}</h1>
                            @if (filled($cover['region_label'] ?? null))
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
            </td>
        </tr>
    </table>

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
                        <strong>{{ __('Município') }}</strong> —
                        {{ $cover['map_caption'] ?? __('Recorte municipal') }}
                        @if (filled($cover['map_source'] ?? null))
                            <span class="cover-map-caption__muted">({{ $cover['map_source'] }})</span>
                        @endif
                    </p>
                @else
                    <div class="cover-map-placeholder">
                        {{ __('Mapa indisponível nesta geração. Verifique ligação à internet do servidor ou sincronize coordenadas das escolas.') }}
                    </div>
                @endif
            </td>
            @if (filled($regionalMapUri))
                <td class="cover-map-side" style="width: 38%;">
                    <img src="{{ $regionalMapUri }}" alt="" class="cover-map-img cover-map-img--regional">
                    <p class="cover-map-caption">
                        <strong>{{ __('Regional') }}</strong> — {{ $cover['regional_map_caption'] ?? __('Recorte ampliado') }}
                    </p>
                </td>
            @elseif (filled($brandUri))
                <td class="cover-map-side" style="width: 38%;">
                    <img src="{{ $brandUri }}" alt="" class="cover-map-img cover-map-img--brand">
                    <p class="cover-map-caption">{{ __('Identidade visual · educação pública municipal') }}</p>
                </td>
            @endif
        </tr>
    </table>

    @if (isset($health) && is_array($health))
        <div class="cover-summary">
            <p class="cover-summary__title">{{ __('Resumo executivo') }}</p>
            <p class="cover-summary__text">{{ $health['intro'] ?? '' }}</p>
            @if (isset($health['compliance_score']))
                <p class="cover-summary__kpi">
                    <strong>{{ __('Índice de conformidade') }}:</strong>
                    {{ $health['compliance_score'] }}/100 — {{ $health['compliance_label'] ?? '' }}
                </p>
            @endif
        </div>
    @endif

    <p class="cover-footer-note">{{ $cover['report_subtitle'] ?? '' }}</p>
</div>
