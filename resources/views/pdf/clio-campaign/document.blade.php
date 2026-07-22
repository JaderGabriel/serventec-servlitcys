<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('Clio') }} — {{ $campaign->municipality_name }}</title>
    @include('pdf.analytics-report.partials.pdf-styles', ['colors' => $colors ?? []])
</head>
<body>
@php
    $counters = $counters ?? ($dashboard['counters'] ?? []);
@endphp
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
        <p class="cover-pro__type">{{ __('Relatório de coleta — Matrícula inicial') }}</p>
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

<h2>{{ __('Contadores da análise') }}</h2>
<p style="font-size: 11px; color: #475569; margin-bottom: 8px;">
    {{ __('Use estes números para priorizar: erros pedem correção; atenções pedem revisão; informações só contextualizam.') }}
</p>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('Indicador') }}</th>
            <th>{{ __('Quantidade') }}</th>
            <th>{{ __('Significado') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ __('Erros a corrigir') }}</td>
            <td>{{ $counters['errors'] ?? 0 }}</td>
            <td>{{ __('Ação obrigatória na coleta ou no sistema') }}</td>
        </tr>
        <tr>
            <td>{{ __('Pontos de atenção') }}</td>
            <td>{{ $counters['warnings'] ?? 0 }}</td>
            <td>{{ __('Revisar antes de concluir') }}</td>
        </tr>
        <tr>
            <td>{{ __('Informações') }}</td>
            <td>{{ $counters['infos'] ?? 0 }}</td>
            <td>{{ __('Contexto — sem correção imediata') }}</td>
        </tr>
        <tr>
            <td>{{ __('Escolas com tríade completa') }}</td>
            <td>{{ $counters['schools_triade'] ?? 0 }} / {{ $counters['schools_total'] ?? 0 }}</td>
            <td>{{ __('Alunos + turmas + profissionais') }}</td>
        </tr>
        <tr>
            <td>{{ __('Escolas em boa forma') }}</td>
            <td>{{ $counters['schools_ok'] ?? 0 }}</td>
            <td>{{ __('Tríade ok e sem erro associado') }}</td>
        </tr>
        <tr>
            <td>{{ __('Escolas com erros') }}</td>
            <td>{{ $counters['schools_with_errors'] ?? 0 }}</td>
            <td>{{ __('Priorize estas unidades') }}</td>
        </tr>
        <tr>
            <td>{{ __('Escolas incompletas') }}</td>
            <td>{{ $counters['schools_incomplete'] ?? 0 }}</td>
            <td>{{ __('Falta arquivo da tríade') }}</td>
        </tr>
    </tbody>
</table>

<h2>{{ __('O que os dados mostram') }}</h2>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('Tema') }}</th>
            <th>{{ __('Resumo') }}</th>
        </tr>
    </thead>
    <tbody>
        @php $highlightMap = collect($dashboard['highlights'] ?? [])->keyBy('code'); @endphp
        @forelse ($inferences as $inf)
            <tr>
                <td>{{ $highlightMap->get($inf->code)['title'] ?? $inf->code }}</td>
                <td>{{ $inf->summary }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2">{{ __('Sem resumos — execute a análise na aplicação.') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if (($toCorrect ?? $criticalFindings ?? collect())->isNotEmpty())
    <h2>{{ __('O que corrigir (erros)') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Escola') }}</th>
                <th>{{ __('Problema') }}</th>
                <th>{{ __('O que fazer') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($toCorrect ?? $criticalFindings as $f)
                <tr>
                    <td>
                        {{ $f->school?->name ?: __('Rede') }}
                        @if ($f->school?->inep_code)
                            <br><span style="font-size: 9px; color: #64748b;">INEP {{ $f->school->inep_code }}</span>
                        @endif
                    </td>
                    <td>{{ $f->message }}</td>
                    <td>{{ $f->actionHint() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (($toReview ?? collect())->isNotEmpty())
    <h2>{{ __('Pontos de atenção (revisar)') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Escola') }}</th>
                <th>{{ __('Aviso') }}</th>
                <th>{{ __('O que fazer') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($toReview as $f)
                <tr>
                    <td>
                        {{ $f->school?->name ?: __('Rede') }}
                        @if ($f->school?->inep_code)
                            <br><span style="font-size: 9px; color: #64748b;">INEP {{ $f->school->inep_code }}</span>
                        @endif
                    </td>
                    <td>{{ $f->message }}</td>
                    <td>{{ $f->actionHint() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>{{ __('Escolas — cobertura da tríade') }}</h2>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('INEP') }}</th>
            <th>{{ __('Escola') }}</th>
            <th>{{ __('Situação') }}</th>
            <th>{{ __('Tríade') }}</th>
            <th>{{ __('Erros') }}</th>
            <th>{{ __('Avisos') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($dashboard['schools'] ?? $coverage['schools'] ?? []) as $school)
            <tr>
                <td>{{ $school['inep'] ?? '' }}</td>
                <td>{{ $school['name'] ?? '' }}</td>
                <td>{{ $school['status'] ?? ((! empty($school['triade'])) ? __('Completa') : __('Incompleta')) }}</td>
                <td>{{ ! empty($school['triade']) ? __('Sim') : __('Não') }}</td>
                <td>{{ $school['errors'] ?? '—' }}</td>
                <td>{{ $school['warnings'] ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
