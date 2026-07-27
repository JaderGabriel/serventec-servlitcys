<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('Clio') }} — {{ __('MAPA de Coleta') }} — {{ $campaign->municipality_name }}</title>
    @include('pdf.analytics-report.partials.pdf-styles', ['colors' => $colors ?? []])
    <style>
        .mapa-section { margin: 0 0 14px; page-break-inside: avoid; }
        .mapa-section h2 {
            font-size: 12px;
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
        .mapa-section h3 {
            font-size: 10px;
            margin: 8px 0 3px;
            color: #334155;
            font-weight: 700;
        }
        .mapa-meta { font-size: 9px; color: #64748b; margin: 0 0 10px; }
        table.data tr.row-hl td { background: #fff7ed; font-weight: 600; color: #9a3412; }
        table.data td { font-size: 9px; }
        table.data th { font-size: 8px; }
        .tone-emerald { color: #047857; font-weight: 700; }
        .tone-amber { color: #b45309; font-weight: 700; }
        .tone-rose { color: #be123c; font-weight: 700; }
    </style>
</head>
<body>
@php
    $sections = is_array($sections ?? null) ? $sections : [];
    $coverage = is_array($coverage ?? null) ? $coverage : [];
@endphp

@include('pdf.partials.fixed-header')
<div class="pdf-footer">
    <div class="pdf-footer__accent"></div>
    <div class="pdf-footer__body">
        <table class="pdf-footer__table">
            <tr>
                <td style="width: 33%;">
                    <span class="pdf-footer__brand-name">SERVLITCYS</span>
                    <span class="pdf-footer__brand-tag">{{ __('Clio — MAPA de Coleta') }}</span>
                </td>
                <td style="width: 34%; text-align: center;">
                    <span class="pdf-footer__doc-title">{{ $campaign->municipality_name }}</span>
                    <span class="pdf-footer__doc-meta">{{ __('Gerado em :data', ['data' => $generated_at ?? '']) }}</span>
                </td>
                <td style="width: 33%;">
                    <span class="pdf-footer__legal">{{ __('Uso interno — só tabelas quantitativas.') }}</span>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="cover-pro__band">
    <div class="cover-pro__band-inner">
        <p class="cover-pro__eyebrow">{{ __('Clio') }}</p>
        <p class="cover-pro__type">{{ __('MAPA de Coleta') }}</p>
        <h1 class="cover-pro__city">{{ $campaign->municipality_name }}</h1>
        <p class="cover-pro__sub">{{ $campaign->uf }} · {{ $campaign->year }} · {{ $campaign->profileLabel() }}</p>
    </div>
</div>

<p class="mapa-meta" style="margin-top: 10px;">
    {{ __('Ref. :d · tríade :p% · :n blocos', [
        'd' => (string) ($coverage['reference_date'] ?? '—'),
        'p' => (string) ($coverage['triade_coverage_pct'] ?? 0),
        'n' => (string) count($sections),
    ]) }}
</p>

@forelse ($sections as $section)
    <div class="mapa-section">
        <h2>{{ $section['title'] ?? '' }}</h2>
        @foreach ($section['tables'] ?? [] as $table)
            @if (! empty($table['rows']))
                @if (filled($table['title'] ?? null))
                    <h3>{{ $table['title'] }}</h3>
                @endif
                <table class="data" style="margin-bottom: 6px;">
                    <thead>
                        <tr>
                            @foreach ($table['headers'] ?? [] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table['rows'] as $row)
                            @php
                                $cells = is_array($row['cells'] ?? null) ? $row['cells'] : (is_array($row) ? $row : []);
                                $hl = (bool) ($row['highlight'] ?? false);
                            @endphp
                            <tr @class(['row-hl' => $hl])>
                                @foreach ($cells as $cell)
                                    @php
                                        $isRich = is_array($cell);
                                        $text = $isRich ? (string) ($cell['text'] ?? '') : (string) $cell;
                                        $tone = $isRich ? (string) ($cell['tone'] ?? '') : '';
                                    @endphp
                                    <td @class([
                                        'tone-emerald' => $tone === 'emerald',
                                        'tone-amber' => $tone === 'amber',
                                        'tone-rose' => $tone === 'rose',
                                    ])>{{ $text }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    </div>
@empty
    <p class="mapa-meta">{{ __('Sem dados quantitativos disponíveis nesta coleta.') }}</p>
@endforelse
</body>
</html>
