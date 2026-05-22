@php
    $primary = $colors['primary'] ?? '#0f766e';
    $secondary = $colors['secondary'] ?? '#4338ca';
    $serventecName = $brand['serventec_name'] ?? 'Serventec Assessoria';
    $cover = is_array($cover ?? null) ? $cover : [];
    $filterDetails = is_array($cover['filter_details'] ?? null) ? $cover['filter_details'] : [];
    $yearValue = $cover['year_value'] ?? ($cover['year_label'] ?? '—');
    $mapUri = $cover['map_image_data_uri'] ?? null;
    $headlineKpis = is_array($cover['headline_kpis'] ?? null) ? $cover['headline_kpis'] : [];
    $executiveSummary = is_array($cover['executive_summary'] ?? null) ? $cover['executive_summary'] : [];
    $cityUpper = $cover['report_title_municipality_upper'] ?? mb_strtoupper((string) ($cover['municipality'] ?? ''), 'UTF-8');
@endphp
<div class="cover-page">
    <div class="cover-pro__band">
        <table width="100%" cellpadding="0" cellspacing="0" class="cover-pro__band-inner">
            <tr>
                <td style="vertical-align: top; width: 70%;">
                    <p class="cover-pro__eyebrow">{{ $serventecName }} · {{ $cover['report_subtitle'] ?? __('Consultoria educacional municipal') }}</p>
                    <p class="cover-pro__type">{{ $cover['report_title'] ?? __('Relatório analítico — educação básica') }}</p>
                    <h1 class="cover-pro__city">{{ $cityUpper }}</h1>
                    @if (filled($cover['municipality_subtitle'] ?? null))
                        <p class="cover-pro__sub">{{ $cover['municipality_subtitle'] }}</p>
                    @elseif (filled($cover['region_label'] ?? null))
                        <p class="cover-pro__sub">{{ $cover['region_label'] }}</p>
                    @endif
                    @if (filled($cover['version_line'] ?? null))
                        <p class="cover-pro__sub" style="font-size:8.5pt;opacity:0.85;">{{ $cover['version_line'] }}</p>
                    @endif
                </td>
                <td style="vertical-align: top; text-align: right; width: 30%;">
                    <div class="cover-pro__year-pill">
                        <span class="cover-pro__year-label">{{ __('Ano letivo') }}</span>
                        <span class="cover-pro__year-value">{{ $yearValue }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="cover-pro__body">
        @if (filled($cover['gestao_lead'] ?? null))
            <p class="cover-pro__lead">{{ $cover['gestao_lead'] }}</p>
        @endif

        @if ($headlineKpis !== [])
            <table class="cover-pro__metrics" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    @foreach (array_slice($headlineKpis, 0, 4) as $kpi)
                        <td class="cover-pro__metric">
                            <span class="cover-pro__metric-label">{{ $kpi['label'] ?? '' }}</span>
                            <span class="cover-pro__metric-value">{{ $kpi['value'] ?? '—' }}</span>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif

        @if (filled($mapUri))
            <div class="cover-pro__map-frame">
                <img src="{{ $mapUri }}" alt="" class="cover-pro__map-img">
                <p class="cover-pro__map-caption">
                    {{ $cover['map_caption'] ?? __('Visão territorial da rede municipal no recorte do relatório.') }}
                    @if (filled($cover['map_source'] ?? null))
                        <span style="color:#94a3b8;"> — {{ $cover['map_source'] }}</span>
                    @endif
                </p>
            </div>
        @endif

        @if ($filterDetails !== [])
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;font-size:8.5pt;border:1px solid #e2e8f0;border-radius:8px;">
                <tr>
                    <td colspan="{{ count($filterDetails) }}" style="padding:8px 12px 4px;font-weight:bold;color:{{ $primary }};text-transform:uppercase;font-size:7pt;letter-spacing:0.06em;">
                        {{ __('Recorte aplicado') }}
                    </td>
                </tr>
                <tr>
                    @foreach ($filterDetails as $detail)
                        <td style="padding:6px 12px 10px;border-right:1px solid #f1f5f9;vertical-align:top;">
                            <span style="display:block;color:#64748b;font-size:7pt;text-transform:uppercase;">{{ $detail['label'] ?? '' }}</span>
                            <span style="display:block;font-weight:bold;color:#0f172a;margin-top:2px;">{{ $detail['value'] ?? '' }}</span>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif

        @if ($executiveSummary !== [])
            <div class="cover-pro__summary">
                <p class="cover-pro__summary-title">{{ __('Síntese para a gestão') }}</p>
                <ul class="cover-pro__summary-list">
                    @foreach (array_slice($executiveSummary, 0, 5) as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
                @if (isset($health['compliance_score']) && is_numeric($health['compliance_score']))
                    <p style="margin:10px 0 0;font-size:9.5pt;font-weight:bold;color:{{ $secondary }};">
                        {{ __('Índice de conformidade') }}: {{ (int) $health['compliance_score'] }}/100 — {{ $health['compliance_label'] ?? '' }}
                    </p>
                @endif
            </div>
        @elseif (isset($health) && is_array($health) && filled($health['intro'] ?? null))
            <div class="cover-pro__summary">
                <p class="cover-pro__summary-title">{{ __('Resumo executivo') }}</p>
                <p style="margin:0;font-size:9.5pt;line-height:1.5;">{{ $health['intro'] }}</p>
            </div>
        @endif

        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:8pt;color:#64748b;margin-top:8px;">
            <tr>
                @if (filled($cover['ibge'] ?? null))
                    <td style="padding-right:12px;"><strong>IBGE</strong> {{ $cover['ibge'] }}</td>
                @endif
                <td style="padding-right:12px;"><strong>{{ __('Emissão') }}</strong> {{ $generated_at ?? now()->format('d/m/Y H:i') }}</td>
                @if (filled($cover['coords_source'] ?? null))
                    <td><strong>{{ __('Geo') }}</strong> {{ $cover['coords_source'] }}</td>
                @endif
            </tr>
        </table>

        <p class="cover-pro__legal">
            {{ $cover['confidentiality_note'] ?? __('Documento gerado automaticamente pela plataforma SERVLITCYS. Valores indicativos — confirmar em portarias FNDE, Censo e cadastro i-Educar.') }}
        </p>
    </div>
</div>
