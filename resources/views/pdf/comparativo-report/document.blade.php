<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('Comparativo anual FUNDEB') }}</title>
    @include('pdf.analytics-report.partials.pdf-styles', ['colors' => $colors ?? []])
</head>
<body>
@php
    $d = is_array($data ?? null) ? $data : [];
    $detail = is_array($d['base_year_detail'] ?? null) ? $d['base_year_detail'] : [];
    $variacoes = is_array($d['variacoes'] ?? null) ? $d['variacoes'] : [];
    $porEtapa = is_array($detail['por_etapa'] ?? null) ? $detail['por_etapa'] : [];
    $proj = is_array($d['next_year_projection'] ?? null) ? $d['next_year_projection'] : [];
    $informe = is_array($d['informe'] ?? null) ? $d['informe'] : [];
    $blocos = is_array($informe['blocos'] ?? null) ? $informe['blocos'] : [];
@endphp

<div class="pdf-footer">
    <div class="pdf-footer__accent"></div>
    <div class="pdf-footer__body">
        <table class="pdf-footer__table">
            <tr>
                <td style="width: 33%;">
                    <span class="pdf-footer__brand-name">SERVLITCYS</span>
                    <span class="pdf-footer__brand-tag">{{ __('Comparativo anual — consultoria') }}</span>
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
        <p class="cover-pro__eyebrow">{{ __('Finanças · Comparativo') }}</p>
        <p class="cover-pro__type">{{ __('Relatório para gestão municipal') }}</p>
        <h1 class="cover-pro__city">{{ $d['city_name'] ?? '' }}</h1>
        <p class="cover-pro__sub">{{ $d['intro'] ?? '' }}</p>
    </div>
</div>

<div class="cover-pro__body">
    <p class="cover-pro__lead">
        {{ __('Ano base :base · comparado com :anterior · projeção :proximo.', [
            'base' => (string) ($d['base_year'] ?? ''),
            'anterior' => (string) ($d['prev_year'] ?? ''),
            'proximo' => (string) ($d['next_year'] ?? ''),
        ]) }}
    </p>
</div>

<h2>{{ __('Variação ano a ano') }}</h2>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('Indicador') }}</th>
            <th>{{ __('Ano base') }}</th>
            <th>{{ __('Ano anterior') }}</th>
            <th>{{ __('Variação') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($variacoes as $row)
            <tr>
                <td>{{ $row['label'] ?? '' }}</td>
                <td>{{ $row['base_fmt'] ?? '—' }}</td>
                <td>{{ $row['prev_fmt'] ?? '—' }}</td>
                <td>{{ $row['delta_label'] ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if (count($porEtapa) > 0)
    <h2>{{ __('Matrículas por etapa (ano base)') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Nível') }}</th>
                <th>{{ __('Matrículas') }}</th>
                <th>{{ __('% rede') }}</th>
                <th>{{ __('FUNDEB indicativo') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($porEtapa as $row)
                <tr>
                    <td>{{ $row['etapa'] ?? '' }}</td>
                    <td>{{ number_format((int) ($row['matriculas'] ?? 0), 0, ',', '.') }}</td>
                    <td>{{ number_format((float) ($row['participacao_pct'] ?? 0), 1, ',', '.') }}%</td>
                    <td>{{ $row['fundeb_label'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (($proj['available'] ?? false))
    <h2>{{ __('Projeção :ano', ['ano' => (string) ($proj['year'] ?? '')]) }}</h2>
    <p>{{ __('Previsão: :v · Δ ano base: :d', ['v' => $proj['previsao_label'] ?? '—', 'd' => $proj['delta_label'] ?? '—']) }}</p>
    <p class="muted">{{ $proj['note'] ?? '' }}</p>
@endif

@if (count($blocos) > 0)
    <h2>{{ __('Informes para consultoria') }}</h2>
    @foreach ($blocos as $bloco)
        <div class="section" style="margin-bottom: 12px;">
            <h3 style="font-size: 11pt; margin: 0 0 6px;">{{ $bloco['titulo'] ?? '' }}</h3>
            @foreach ($bloco['paragrafos'] ?? [] as $par)
                <p style="margin: 0 0 6px;">{{ $par }}</p>
            @endforeach
            @if (count($bloco['acoes'] ?? []) > 0)
                <ul style="margin: 0; padding-left: 16px;">
                    @foreach ($bloco['acoes'] as $acao)
                        <li>{{ $acao }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
@endif

<p class="muted" style="margin-top: 16px;">{{ $informe['aviso'] ?? $d['footnote'] ?? '' }}</p>
</body>
</html>
