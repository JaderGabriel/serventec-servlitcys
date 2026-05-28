<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('CadÚnico — previsão fora da rede') }}</title>
    @include('pdf.analytics-report.partials.pdf-styles', ['colors' => $colors ?? []])
</head>
<body>
@php
    $d = is_array($data ?? null) ? $data : [];
    $gap = is_array($d['gap'] ?? null) ? $d['gap'] : [];
    $porEtapa = is_array($gap['por_etapa'] ?? null) ? $gap['por_etapa'] : [];
    $porFaixa = is_array($gap['por_faixa'] ?? null) ? $gap['por_faixa'] : [];
    $informe = is_array($d['informe'] ?? null) ? $d['informe'] : [];
    $blocos = is_array($informe['blocos'] ?? null) ? $informe['blocos'] : [];
    $impacto = is_array($gap['impacto_financeiro'] ?? null) ? $gap['impacto_financeiro'] : [];
@endphp

<div class="pdf-footer">
    <div class="pdf-footer__accent"></div>
    <div class="pdf-footer__body">
        <table class="pdf-footer__table">
            <tr>
                <td style="width: 33%;">
                    <span class="pdf-footer__brand-name">SERVLITCYS</span>
                    <span class="pdf-footer__brand-tag">{{ __('CadÚnico — consultoria') }}</span>
                </td>
                <td style="width: 34%; text-align: center;">
                    <span class="pdf-footer__doc-title">{{ $d['city_name'] ?? '' }}</span>
                    <span class="pdf-footer__doc-meta">{{ __('Gerado em :data', ['data' => $generated_at ?? '']) }}</span>
                </td>
                <td style="width: 33%;">
                    <span class="pdf-footer__legal">{{ $d['footnote'] ?? '' }}</span>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="cover-pro__band">
    <div class="cover-pro__band-inner">
        <p class="cover-pro__eyebrow">{{ __('Cadastro · CadÚnico') }}</p>
        <p class="cover-pro__type">{{ __('Previsão fora da rede municipal') }}</p>
        <h1 class="cover-pro__city">{{ $d['city_name'] ?? '' }}</h1>
        <p class="cover-pro__sub">{{ $d['intro'] ?? '' }}</p>
    </div>
</div>

<div class="cover-pro__body">
    <p class="cover-pro__lead">
        {{ __('Ano de referência: :ano · Cobertura: :cov · Lacuna estimada: :gap', [
            'ano' => (string) ($d['year_label'] ?? ''),
            'cov' => (string) ($gap['cobertura_label'] ?? '—'),
            'gap' => (string) ($gap['gap_total_fmt'] ?? '—'),
        ]) }}
    </p>
    @if (filled($impacto['formula'] ?? null))
        <p class="cover-pro__lead">{{ $impacto['formula'] }}</p>
    @endif
</div>

@if (count($porEtapa) > 0)
    <h2>{{ __('Lacuna por nível de ensino') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Nível') }}</th>
                <th>{{ __('CadÚnico') }}</th>
                <th>{{ __('i-Educar') }}</th>
                <th>{{ __('Fora da rede') }}</th>
                <th>{{ __('FUNDEB indic.') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($porEtapa as $row)
                <tr>
                    <td>{{ $row['etapa'] ?? '' }}</td>
                    <td>{{ isset($row['cadunico_estimado']) ? number_format((int) $row['cadunico_estimado'], 0, ',', '.') : '—' }}</td>
                    <td>{{ number_format((int) ($row['ieducar_matriculas'] ?? 0), 0, ',', '.') }}</td>
                    <td>{{ $row['gap_fmt'] ?? '0' }}</td>
                    <td>{{ $row['fundeb_gap_label'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (count($porFaixa) > 0)
    <h2>{{ __('Faixas etárias CadÚnico') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Faixa') }}</th>
                <th>{{ __('População') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($porFaixa as $faixa)
                <tr>
                    <td>{{ $faixa['faixa'] ?? '' }}</td>
                    <td>{{ number_format((int) ($faixa['cadunico'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (count($blocos) > 0)
    <h2>{{ __('Informes') }}</h2>
    @foreach ($blocos as $bloco)
        <h3>{{ $bloco['titulo'] ?? '' }}</h3>
        @foreach (is_array($bloco['paragrafos'] ?? null) ? $bloco['paragrafos'] : [] as $p)
            <p>{{ $p }}</p>
        @endforeach
    @endforeach
@endif

@if (filled($gap['nota'] ?? null))
    <p class="note">{{ $gap['nota'] }}</p>
@endif

</body>
</html>
