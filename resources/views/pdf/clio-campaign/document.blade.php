<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('Clio') }} — {{ $campaign->municipality_name }}</title>
    @include('pdf.analytics-report.partials.pdf-styles', ['colors' => $colors ?? []])
</head>
<body>
<div class="pdf-footer">
    <div class="pdf-footer__accent"></div>
    <div class="pdf-footer__body">
        <table class="pdf-footer__table">
            <tr>
                <td style="width: 33%;">
                    <span class="pdf-footer__brand-name">SERVLITCYS</span>
                    <span class="pdf-footer__brand-tag">{{ __('Clio — Educacenso 1ª etapa') }}</span>
                </td>
                <td style="width: 34%; text-align: center;">
                    <span class="pdf-footer__doc-title">{{ $campaign->municipality_name }}</span>
                    <span class="pdf-footer__doc-meta">{{ __('Gerado em :data', ['data' => $generated_at ?? '']) }}</span>
                </td>
                <td style="width: 33%;">
                    <span class="pdf-footer__legal">{{ __('Sem dados pessoais — agregados e códigos INEP.') }}</span>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="cover-pro__band">
    <div class="cover-pro__band-inner">
        <p class="cover-pro__eyebrow">{{ __('Clio') }}</p>
        <p class="cover-pro__type">{{ __('Relatório de campanha — Matrícula inicial') }}</p>
        <h1 class="cover-pro__city">{{ $campaign->municipality_name }}</h1>
        <p class="cover-pro__sub">{{ $campaign->uf }} · {{ $campaign->year }} · {{ $campaign->profileLabel() }}</p>
    </div>
</div>

<div class="cover-pro__body">
    <p class="cover-pro__lead">
        {{ __('Estado :s · tríade :p% (:c/:t escolas) · ref. :d', [
            's' => $campaign->statusLabel(),
            'p' => (string) ($coverage['triade_coverage_pct'] ?? 0),
            'c' => (string) ($coverage['schools_triade_complete'] ?? 0),
            't' => (string) ($coverage['schools_total'] ?? 0),
            'd' => (string) ($coverage['reference_date'] ?? '—'),
        ]) }}
    </p>
</div>

<h2>{{ __('Inferências') }}</h2>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('Código') }}</th>
            <th>{{ __('Resumo') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($inferences as $inf)
            <tr>
                <td>{{ $inf->code }}</td>
                <td>{{ $inf->summary }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2">{{ __('Sem inferências — corra a análise na aplicação.') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if ($criticalFindings->isNotEmpty())
    <h2>{{ __('Achados críticos') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Código') }}</th>
                <th>{{ __('Mensagem') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($criticalFindings as $f)
                <tr>
                    <td>{{ $f->code }}</td>
                    <td>{{ $f->message }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>{{ __('Escolas (cobertura tríade)') }}</h2>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('INEP') }}</th>
            <th>{{ __('Escola') }}</th>
            <th>{{ __('Tríade') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($coverage['schools'] ?? []) as $school)
            <tr>
                <td>{{ $school['inep'] ?? '' }}</td>
                <td>{{ $school['name'] ?? '' }}</td>
                <td>{{ ! empty($school['triade']) ? __('Sim') : __('Não') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
