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
                    <span class="pdf-footer__legal">{{ __('Uso interno — contém nome e CPF das Relações.') }}</span>
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
            <td>{{ __('Escolas em atividade') }}</td>
            <td>{{ $counters['schools_active'] ?? $counters['schools_total'] ?? 0 }}</td>
            <td>{{ __('Escopo operacional da Matrícula inicial') }}</td>
        </tr>
        <tr>
            <td>{{ __('Demais situações') }}</td>
            <td>{{ $counters['schools_other'] ?? $counters['schools_inactive'] ?? 0 }}</td>
            <td>{{ __('Extinta / paralisada / reforma / fora de atividade') }}</td>
        </tr>
        <tr>
            <td>{{ __('Escolas com tríade completa') }}</td>
            <td>{{ $counters['schools_triade'] ?? 0 }} / {{ $counters['schools_active'] ?? $counters['schools_total'] ?? 0 }}</td>
            <td>{{ __('Alunos + turmas + profissionais (só ativas)') }}</td>
        </tr>
        <tr>
            <td>{{ __('Escolas em boa forma') }}</td>
            <td>{{ $counters['schools_ok'] ?? 0 }}</td>
            <td>{{ __('Ativas com tríade ok e sem erro associado') }}</td>
        </tr>
        <tr>
            <td>{{ __('Escolas com erros') }}</td>
            <td>{{ $counters['schools_with_errors'] ?? 0 }}</td>
            <td>{{ __('Priorize estas unidades (ativas)') }}</td>
        </tr>
        <tr>
            <td>{{ __('Escolas incompletas') }}</td>
            <td>{{ $counters['schools_incomplete'] ?? 0 }}</td>
            <td>{{ __('Falta arquivo da tríade (ativas)') }}</td>
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

@php $jornada = $dashboard['jornada'] ?? []; @endphp
@if (! empty($jornada['available']))
    <h2>{{ __('Tempo de escolarização') }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">{{ $jornada['summary'] ?? '' }}</p>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Indicador') }}</th>
                <th>{{ __('Quantidade') }}</th>
                <th>{{ __('Leitura') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('Fund. regular + AEE (contraturno)') }}</td>
                <td>{{ $jornada['fund_aee_contraturno'] ?? 0 }}</td>
                <td>{{ $jornada['note_fund_aee'] ?? '' }}</td>
            </tr>
            <tr>
                <td>{{ __('Regular + atividade complementar') }}</td>
                <td>{{ $jornada['curricular_ac'] ?? 0 }}</td>
                <td>{{ __('Não confundir com AEE') }}</td>
            </tr>
            <tr>
                <td>{{ __('Infantil em turma estendida') }}</td>
                <td>{{ $jornada['infantil_turma_estendida'] ?? 0 }}</td>
                <td>{{ $jornada['note_infantil'] ?? '' }}</td>
            </tr>
            <tr>
                <td>{{ __('Pessoas com ≥2 matrículas') }}</td>
                <td>{{ $jornada['multi_enrollment'] ?? 0 }}</td>
                <td>{{ __('Inclui AEE, AC e outros vínculos') }}</td>
            </tr>
        </tbody>
    </table>

    @if (! empty($jornada['schools_active']))
        <h3 style="font-size: 12px; margin-top: 12px;">{{ __('Jornada por escola — em atividade') }}</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Escola') }}</th>
                    <th>{{ __('Turmas') }}</th>
                    <th>{{ __('Fund.+AEE') }}</th>
                    <th>{{ __('Reg.+AC') }}</th>
                    <th>{{ __('Inf. estendido') }}</th>
                    <th>{{ __('≥2 matr.') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jornada['schools_active'] as $row)
                    <tr>
                        <td>
                            {{ $row['name'] }}
                            <br><span style="font-size: 9px; color: #64748b;">INEP {{ $row['inep'] }}</span>
                        </td>
                        <td>{{ $row['turmas'] }}</td>
                        <td>{{ $row['fund_aee_contraturno'] }}</td>
                        <td>{{ $row['curricular_ac'] }}</td>
                        <td>{{ $row['infantil_turma_estendida'] }}</td>
                        <td>{{ $row['multi_enrollment'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($jornada['schools_other']))
        <h3 style="font-size: 12px; margin-top: 12px;">{{ __('Jornada por escola — demais situações') }}</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Escola') }}</th>
                    <th>{{ __('Turmas') }}</th>
                    <th>{{ __('Fund.+AEE') }}</th>
                    <th>{{ __('Reg.+AC') }}</th>
                    <th>{{ __('Inf. estendido') }}</th>
                    <th>{{ __('≥2 matr.') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jornada['schools_other'] as $row)
                    <tr>
                        <td>
                            {{ $row['name'] }}
                            <br><span style="font-size: 9px; color: #64748b;">INEP {{ $row['inep'] }} · {{ $row['functioning'] }}</span>
                        </td>
                        <td>{{ $row['turmas'] }}</td>
                        <td>{{ $row['fund_aee_contraturno'] }}</td>
                        <td>{{ $row['curricular_ac'] }}</td>
                        <td>{{ $row['infantil_turma_estendida'] }}</td>
                        <td>{{ $row['multi_enrollment'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@php $transporte = $dashboard['transporte'] ?? []; @endphp
@if (! empty($transporte['available']))
    <h2>{{ __('Transporte escolar') }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">
        {{ $transporte['summary'] ?? '' }}
        @if (! empty($transporte['note_location']))
            · {{ $transporte['note_location'] }}
        @endif
    </p>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Indicador') }}</th>
                <th>{{ __('Quantidade') }}</th>
                <th>{{ __('Nota') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('Usam transporte (rede)') }}</td>
                <td>{{ $transporte['flagged'] ?? 0 }} ({{ $transporte['pct'] ?? 0 }}%)</td>
                <td>{{ __('De :s matrículas', ['s' => $transporte['scanned'] ?? 0]) }}</td>
            </tr>
            <tr>
                <td>{{ __('Usam · escolas ativas') }}</td>
                <td>{{ $transporte['active']['flagged'] ?? 0 }} ({{ $transporte['active']['pct'] ?? 0 }}%)</td>
                <td>{{ __('Escopo operacional') }}</td>
            </tr>
            <tr>
                <td>{{ __('Usam · demais situações') }}</td>
                <td>{{ $transporte['other']['flagged'] ?? 0 }}</td>
                <td>{{ __('Extinta / paralisada / reforma') }}</td>
            </tr>
        </tbody>
    </table>

    @if (! empty($transporte['by_location_users']) || ! empty($transporte['by_veiculo']))
        <table class="data" style="margin-top: 8px;">
            <thead>
                <tr>
                    <th>{{ __('Dimensão') }}</th>
                    <th>{{ __('Distribuição') }}</th>
                </tr>
            </thead>
            <tbody>
                @if (! empty($transporte['by_location_users']))
                    <tr>
                        <td>{{ __('Quem usa · rural / urbana') }}</td>
                        <td>
                            @foreach ($transporte['by_location_users'] as $bar)
                                {{ $bar['label'] }}: {{ $bar['count'] }}@if (! $loop->last) · @endif
                            @endforeach
                        </td>
                    </tr>
                @endif
                @if (! empty($transporte['by_veiculo']))
                    <tr>
                        <td>{{ __('Tipo de veículo') }}</td>
                        <td>
                            @foreach ($transporte['by_veiculo'] as $bar)
                                {{ $bar['label'] }}: {{ $bar['count'] }}@if (! $loop->last) · @endif
                            @endforeach
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    @if (! empty($transporte['schools_active']))
        <h3 style="font-size: 12px; margin-top: 12px;">{{ __('Transporte por escola — em atividade') }}</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Escola') }}</th>
                    <th>{{ __('Localização') }}</th>
                    <th>{{ __('Usam') }}</th>
                    <th>{{ __('%') }}</th>
                    <th>{{ __('Veículos') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transporte['schools_active'] as $row)
                    <tr @if (! empty($row['highlight_rural'])) style="background: #fffbeb;" @elseif (! empty($row['highlight'])) style="background: #f0f9ff;" @endif>
                        <td>
                            {{ $row['name'] }}
                            <br><span style="font-size: 9px; color: #64748b;">INEP {{ $row['inep'] }}</span>
                        </td>
                        <td><strong>{{ $row['location'] }}</strong></td>
                        <td>{{ $row['flagged'] }}</td>
                        <td>{{ number_format((float) ($row['pct'] ?? 0), 1, ',', '.') }}%</td>
                        <td>
                            @forelse ($row['by_veiculo'] ?? [] as $v)
                                {{ $v['label'] }} ({{ $v['count'] }})@if (! $loop->last); @endif
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($transporte['schools_other']))
        <h3 style="font-size: 12px; margin-top: 12px;">{{ __('Transporte por escola — demais situações') }}</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Escola') }}</th>
                    <th>{{ __('Localização') }}</th>
                    <th>{{ __('Usam') }}</th>
                    <th>{{ __('%') }}</th>
                    <th>{{ __('Veículos') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transporte['schools_other'] as $row)
                    <tr>
                        <td>
                            {{ $row['name'] }}
                            <br><span style="font-size: 9px; color: #64748b;">INEP {{ $row['inep'] }} · {{ $row['functioning'] }}</span>
                        </td>
                        <td>{{ $row['location'] }}</td>
                        <td>{{ $row['flagged'] }}</td>
                        <td>{{ number_format((float) ($row['pct'] ?? 0), 1, ',', '.') }}%</td>
                        <td>
                            @forelse ($row['by_veiculo'] ?? [] as $v)
                                {{ $v['label'] }} ({{ $v['count'] }})@if (! $loop->last); @endif
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@if (($toCorrect ?? $criticalFindings ?? collect())->isNotEmpty())
    <h2>{{ __('O que corrigir (erros)') }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">
        {{ __('Mesmas unidades do Tempo de escolarização (escolas em atividade). Itens da Rede ao final desta lista.') }}
    </p>
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
    <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">
        {{ __('Avisos das escolas em atividade — o mesmo conjunto do Tempo de escolarização. Itens da Rede ao final desta lista. Unidades extintas, paralisadas ou em reforma estão no final do relatório.') }}
    </p>
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

@php $tables = $pdfTables ?? []; @endphp

@if (! empty($tables['distortion_by_etapa']))
    <h2>{{ __('Distorção por etapa / ano') }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">
        {{ __('Estimativa INEP (atraso ≥2 anos no EF/EM). Contagens de alunos e escolas a partir das Relações.') }}
    </p>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Etapa / ano') }}</th>
                <th>{{ __('No escopo') }}</th>
                <th>{{ __('Distorção') }}</th>
                <th>{{ __('%') }}</th>
                <th>{{ __('Alunos') }}</th>
                <th>{{ __('Escolas') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tables['distortion_by_etapa'] as $row)
                <tr>
                    <td>{{ $row['etapa'] }}</td>
                    <td>{{ $row['eligible'] }}</td>
                    <td>{{ $row['distorcao'] }}</td>
                    <td>{{ $row['pct'] === null ? '—' : number_format((float) $row['pct'], 1, ',', '.').'%' }}</td>
                    <td>{{ $row['alunos'] }}</td>
                    <td>{{ $row['escolas'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (! empty($tables['distortion_students']))
    <h2>{{ __('Alunos em distorção (amostra)') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Nome') }}</th>
                <th>{{ __('CPF') }}</th>
                <th>{{ __('Escola') }}</th>
                <th>{{ __('Turma') }}</th>
                <th>{{ __('Matrícula') }}</th>
                <th>{{ __('Etapa') }}</th>
                <th>{{ __('Idade') }}</th>
                <th>{{ __('Esperada') }}</th>
                <th>{{ __('Atraso') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tables['distortion_students'] as $row)
                <tr>
                    <td>
                        {{ $row['name'] ?? '—' }}
                        @if (! empty($row['id']) && ($row['id'] ?? '') !== '—')
                            <br><span style="font-size: 8px; color: #64748b;">{{ $row['id'] }}</span>
                        @endif
                    </td>
                    <td>{{ $row['cpf'] ?? '—' }}</td>
                    <td>
                        {{ $row['school'] }}
                        @if ($row['inep'] !== '')
                            <br><span style="font-size: 9px; color: #64748b;">INEP {{ $row['inep'] }}</span>
                        @endif
                    </td>
                    <td>{{ $row['turma'] }}</td>
                    <td>{{ $row['matricula'] }}</td>
                    <td>{{ $row['etapa'] }}</td>
                    <td>{{ $row['age'] ?? '—' }}</td>
                    <td>{{ $row['expected'] ?? '—' }}</td>
                    <td>{{ $row['delay'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (! empty($tables['missing_demographics']) || (int) ($tables['missing_demographics_total'] ?? 0) > 0)
    <h2>{{ __('Sem Cor/Raça ou Sexo definidos') }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">
        {{ __('Total de pessoas: :n (amostra abaixo com nome e CPF).', ['n' => (int) ($tables['missing_demographics_total'] ?? 0)]) }}
    </p>
    @if (! empty($tables['missing_demographics']))
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Nome') }}</th>
                    <th>{{ __('CPF') }}</th>
                    <th>{{ __('Escola') }}</th>
                    <th>{{ __('Faltando') }}</th>
                    <th>{{ __('Matrículas') }}</th>
                    <th>{{ __('Turmas') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tables['missing_demographics'] as $row)
                    <tr>
                        <td>
                            {{ $row['name'] ?? '—' }}
                            @if (! empty($row['id']) && ($row['id'] ?? '') !== '—')
                                <br><span style="font-size: 8px; color: #64748b;">{{ $row['id'] }}</span>
                            @endif
                        </td>
                        <td>{{ $row['cpf'] ?? '—' }}</td>
                        <td>
                            {{ $row['school'] }}
                            @if ($row['inep'] !== '')
                                <br><span style="font-size: 9px; color: #64748b;">INEP {{ $row['inep'] }}</span>
                            @endif
                        </td>
                        <td>{{ $row['faltando'] }}</td>
                        <td>{{ $row['matriculas'] }}</td>
                        <td>{{ $row['turmas'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@if (! empty($tables['nee_students']) || (int) ($tables['nee_total'] ?? 0) > 0)
    <h2>{{ __('Pessoas com NEE — deficiências e transtornos') }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">
        {{ __('Total com marcador: :t · sem matrícula AEE: :a · com alerta de subnotificação: :u. Códigos DEF-* = deficiência; TRS-* = transtorno; AH = altas habilidades; SUB-* = possível subnotificação.', [
            't' => (int) ($tables['nee_total'] ?? 0),
            'a' => (int) ($tables['nee_without_aee'] ?? 0),
            'u' => (int) ($tables['nee_underreporting'] ?? 0),
        ]) }}
    </p>
    @if (! empty($tables['nee_students']))
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Nome') }}</th>
                    <th>{{ __('CPF') }}</th>
                    <th>{{ __('Escola') }}</th>
                    <th>{{ __('Deficiências') }}</th>
                    <th>{{ __('Transtornos') }}</th>
                    <th>{{ __('AH') }}</th>
                    <th>{{ __('Subnotificação') }}</th>
                    <th>{{ __('AEE') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tables['nee_students'] as $row)
                    <tr @if (empty($row['has_aee']) || ! empty($row['has_underreporting'])) style="background: {{ empty($row['has_aee']) ? '#fff7ed' : '#fef2f2' }};" @endif>
                        <td>
                            {{ $row['name'] ?? '—' }}
                            @if (! empty($row['id']) && ($row['id'] ?? '') !== '—')
                                <br><span style="font-size: 8px; color: #64748b;">{{ $row['id'] }}</span>
                            @endif
                        </td>
                        <td>{{ $row['cpf'] ?? '—' }}</td>
                        <td>
                            {{ $row['school'] }}
                            @if ($row['inep'] !== '')
                                <br><span style="font-size: 9px; color: #64748b;">INEP {{ $row['inep'] }}</span>
                            @endif
                        </td>
                        <td>
                            @if (($row['deficiencies'] ?? '—') !== '—')
                                <span style="color: #0369a1;">{{ $row['deficiencies'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if (($row['disorders'] ?? '—') !== '—')
                                <span style="color: #b45309;">{{ $row['disorders'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if (($row['ah'] ?? '—') !== '—')
                                <span style="color: #047857;">{{ $row['ah'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if (! empty($row['has_underreporting']))
                                <strong style="color: #b91c1c;">{{ $row['underreporting'] }}</strong>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if (empty($row['has_aee']))
                                <strong style="color: #c2410c;">{{ $row['aee_flag'] }}</strong>
                            @else
                                {{ $row['aee_flag'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

<h2>{{ __('Escolas em atividade — cobertura da tríade') }}</h2>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('INEP') }}</th>
            <th>{{ __('Escola') }}</th>
            <th>{{ __('Situação') }}</th>
            <th>{{ __('Funcionamento') }}</th>
            <th>{{ __('Tríade') }}</th>
            <th>{{ __('Erros') }}</th>
            <th>{{ __('Avisos') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse (($dashboard['schools_active'] ?? []) as $school)
            <tr>
                <td>{{ $school['inep'] ?? '' }}</td>
                <td>{{ $school['name'] ?? '' }}</td>
                <td>{{ $school['status'] ?? ((! empty($school['triade'])) ? __('Completa') : __('Incompleta')) }}</td>
                <td>{{ $school['functioning'] ?? '—' }}</td>
                <td>{{ ! empty($school['triade']) ? __('Sim') : __('Não') }}</td>
                <td>{{ $school['errors'] ?? '—' }}</td>
                <td>{{ $school['warnings'] ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7">{{ __('Nenhuma escola em atividade nesta coleta.') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

@include('pdf.clio-campaign.partials.census-matrix', ['tables' => $pdfTables ?? []])

@php
    $schoolsOther = collect($dashboard['schools_other'] ?? []);
    $findingsOther = collect($toCorrectOther ?? [])->concat(collect($toReviewOther ?? []));
@endphp
@if ($schoolsOther->isNotEmpty() || $findingsOther->isNotEmpty())
    <h2>{{ __('Demais situações de funcionamento') }}</h2>
    <p class="muted">
        {{ __('Estas unidades não entram no relatório operacional acima (Tempo de escolarização, o que corrigir e pontos de atenção) porque estão extintas, paralisadas, em reforma ou fora de atividade. Os totais e avisos abaixo são só informativos — a falta de tríade ou relações aqui não indica coleta em aberto.') }}
    </p>

    @if ($schoolsOther->isNotEmpty())
        <h3 style="font-size: 12px; margin-top: 10px;">{{ __('Quantitativo por unidade') }}</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('INEP') }}</th>
                    <th>{{ __('Escola') }}</th>
                    <th>{{ __('Situação') }}</th>
                    <th>{{ __('Funcionamento') }}</th>
                    <th>{{ __('Tríade') }}</th>
                    <th>{{ __('Erros') }}</th>
                    <th>{{ __('Avisos') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schoolsOther as $school)
                    <tr>
                        <td>{{ $school['inep'] ?? '' }}</td>
                        <td>{{ $school['name'] ?? '' }}</td>
                        <td>{{ $school['status'] ?? '—' }}</td>
                        <td>{{ $school['functioning'] ?? '—' }}</td>
                        <td>{{ ! empty($school['triade']) ? __('Sim') : __('Não') }}</td>
                        <td>{{ $school['errors'] ?? '—' }}</td>
                        <td>{{ $school['warnings'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($findingsOther->isNotEmpty())
        <h3 style="font-size: 12px; margin-top: 12px;">{{ __('Avisos e erros destas unidades') }}</h3>
        <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">
            {{ __('Detalhe do que o sistema registrou para as demais situações — use só como referência; o foco da Matrícula inicial continua nas escolas em atividade.') }}
        </p>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Escola') }}</th>
                    <th>{{ __('Tipo') }}</th>
                    <th>{{ __('Mensagem') }}</th>
                    <th>{{ __('O que fazer') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($findingsOther as $f)
                    <tr>
                        <td>
                            {{ $f->school?->name ?: __('—') }}
                            @if ($f->school?->inep_code)
                                <br><span style="font-size: 9px; color: #64748b;">INEP {{ $f->school->inep_code }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $f->severity === \App\Models\Clio\ClioCampaignFinding::SEVERITY_ERROR
                                ? __('Erro')
                                : __('Aviso') }}
                        </td>
                        <td>{{ $f->message }}</td>
                        <td>{{ $f->actionHint() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif
</body>
</html>
