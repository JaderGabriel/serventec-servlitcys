@php
    $map = is_array($school_units_map ?? null) ? $school_units_map : [];
    if (! ($map['available'] ?? false)) {
        return;
    }
    $imgUri = $map['image_data_uri'] ?? $map['data_uri'] ?? null;
    $stats = is_array($map['stats'] ?? null) ? $map['stats'] : [];
    $schoolRows = is_array($map['schools_table'] ?? null) ? $map['schools_table'] : [];
    $mapW = (int) ($map['width'] ?? config('analytics.pdf_report.content_width_pt', 520));
    $mapH = (int) ($map['height'] ?? config('analytics.pdf_report.school_map_height_pt', 292));
@endphp
<div class="territory-block">
    @if (filled($imgUri))
        <div class="territory-map-wrap">
            <img src="{{ $imgUri }}" alt="" class="territory-map" width="{{ $mapW }}" height="{{ $mapH }}">
        </div>
    @elseif (filled($map['svg'] ?? null))
        <div class="territory-map-wrap territory-map-svg">
            {!! $map['svg'] !!}
        </div>
    @endif

    @if (filled($map['caption'] ?? null))
        <p class="territory-legend"><strong>{{ __('Leitura do mapa') }}:</strong> {{ $map['caption'] }}</p>
    @endif

    @if ($stats !== [])
        <table class="territory-stats" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <strong>{{ __('Escolas georreferenciadas') }}</strong><br>
                    {{ number_format((int) ($stats['schools'] ?? 0), 0, ',', '.') }}
                </td>
                <td>
                    <strong>{{ __('Matrículas no recorte') }}</strong><br>
                    {{ number_format((int) ($stats['matriculas_total'] ?? 0), 0, ',', '.') }}
                </td>
                <td>
                    <strong>{{ __('Âmbito do mapa') }}</strong><br>
                    {{ ($map['map_scope'] ?? '') === 'matricula' ? __('Ponderado por matrículas activas') : __('Unidades no recorte') }}
                </td>
            </tr>
        </table>
    @endif

    <table class="territory-legend-inline" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:50%;">
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0f766e;margin-right:4px;vertical-align:middle;"></span>
                {{ __('Escola (tamanho ∝ matrículas)') }}
            </td>
            <td style="width:50%;">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;border:2px solid #4338ca;margin-right:4px;vertical-align:middle;"></span>
                {{ __('Centro de abrangência das matrículas') }}
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top:3px;color:#64748b;font-size:7pt;">
                {{ __('Fundo: OpenStreetMap quando disponível na geração.') }}
            </td>
        </tr>
    </table>

    @if (count($schoolRows) > 0)
        <h4>{{ __('Unidades no recorte (principais por matrículas)') }}</h4>
        <table class="data data--schools">
            <tr>
                <th>{{ __('Unidade escolar') }}</th>
                <th>{{ __('Matr.') }}</th>
                <th>{{ __('Coordenadas') }}</th>
            </tr>
            @foreach ($schoolRows as $row)
                @if (is_array($row))
                    <tr>
                        <td>{{ $row['escola'] ?? '' }}</td>
                        <td>{{ number_format((int) ($row['matriculas'] ?? 0), 0, ',', '.') }}</td>
                        <td class="muted">
                            @if (isset($row['lat'], $row['lng']))
                                {{ $row['lat'] }}, {{ $row['lng'] }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    @if (filled($map['geo_note'] ?? null))
        <p class="muted">{{ $map['geo_note'] }}</p>
    @endif
</div>
