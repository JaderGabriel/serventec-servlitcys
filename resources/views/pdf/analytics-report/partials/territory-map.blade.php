@php
    $map = is_array($school_units_map ?? null) ? $school_units_map : [];
    if (! ($map['available'] ?? false)) {
        return;
    }
    $imgUri = $map['image_data_uri'] ?? $map['data_uri'] ?? null;
    $stats = is_array($map['stats'] ?? null) ? $map['stats'] : [];
    $schoolRows = is_array($map['schools_table'] ?? null) ? $map['schools_table'] : [];
@endphp
<div class="territory-block">
    @if (filled($imgUri))
        <img src="{{ $imgUri }}" alt="" class="territory-map" width="720">
    @elseif (filled($map['svg'] ?? null))
        <div style="text-align:center;border:1px solid #cbd5e1;border-radius:10px;padding:8px;background:#f8fafc;">
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

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;font-size:8pt;">
        <tr>
            <td style="width:50%;vertical-align:top;">
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0f766e;margin-right:6px;"></span>
                {{ __('Círculo verde: escola (tamanho ∝ matrículas)') }}
            </td>
            <td style="width:50%;vertical-align:top;">
                <span style="display:inline-block;width:14px;height:14px;border-radius:50%;border:2px solid #4338ca;margin-right:6px;"></span>
                {{ __('Anel índigo: centro de abrangência das matrículas') }}
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top:4px;color:#64748b;">
                {{ __('Fundo: OpenStreetMap (quando disponível na geração). Consulte o painel interactivo para zoom e camadas adicionais.') }}
            </td>
        </tr>
    </table>

    @if (filled($map['geo_note'] ?? null))
        <p class="muted">{{ $map['geo_note'] }}</p>
    @endif
</div>
