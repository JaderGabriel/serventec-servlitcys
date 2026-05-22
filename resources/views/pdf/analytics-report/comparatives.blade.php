@php
    $cmp = is_array($comparatives ?? null) ? $comparatives : [];
    $fundebYears = is_array($cmp['fundeb_years'] ?? null) ? $cmp['fundeb_years'] : [];
    $statePart = is_array($cmp['state_participation'] ?? null) ? $cmp['state_participation'] : [];
    $yearCmp = is_array($year_comparison ?? null) ? $year_comparison : [];
    $munState = is_array($municipal_vs_state ?? null) ? $municipal_vs_state : [];
    $fundebRef = is_array($cmp['fundeb_reference_tables'] ?? null) ? $cmp['fundeb_reference_tables'] : [];
@endphp

<h2>{{ __('2. Comparativos e contexto territorial') }}</h2>
@include('pdf.analytics-report.partials.section-lead', ['section' => 'comparatives'])

@if (filled($cmp['legal_notice'] ?? null))
    <div class="box legal-notice">
        <p class="muted" style="margin:0;"><strong>{{ __('Nota metodológica') }}</strong> — {{ $cmp['legal_notice'] }}</p>
    </div>
@endif

@if (count($yearCmp) > 0)
    <h3>{{ __('2.1 Evolução entre anos letivos (cadastro e Fundeb)') }}</h3>
    <p class="action-lead">{{ __('Use para avaliar crescimento ou queda de matrículas e estabilidade do VAAF municipal. Variações abruptas sem explicação cadastral merecem auditoria antes de metas de expansão da rede.') }}</p>
    <table class="data">
        <tr>
            <th>{{ __('Ano letivo') }}</th>
            <th>{{ __('Matrículas activas') }}</th>
            <th>{{ __('Variação matr.') }}</th>
            <th>{{ __('VAAF municipal') }}</th>
            <th>{{ __('Referência') }}</th>
        </tr>
        @foreach ($yearCmp as $row)
            <tr>
                <td><strong>{{ $row['ano'] ?? '' }}</strong></td>
                <td>{{ $row['matriculas_fmt'] ?? (isset($row['matriculas']) ? number_format((int) $row['matriculas'], 0, ',', '.') : '—') }}</td>
                <td>{{ $row['variacao_mat_pct'] ?? '—' }}</td>
                <td>{{ $row['vaaf'] ?? '—' }}</td>
                <td class="muted">{{ $row['label'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if (is_array($fundebRef['portaria_exercicios'] ?? null) && ($fundebRef['portaria_exercicios']['available'] ?? false))
    <h3>{{ __('2.2 Receita e complementações — Portaria FNDE (por exercício)') }}</h3>
    @include('pdf.analytics-report.partials.fundeb-reference-tables', [
        'tables' => [
            'portaria_exercicios' => $fundebRef['portaria_exercicios'],
            'complementacao_eixos' => $fundebRef['complementacao_eixos'] ?? ['available' => false],
        ],
        'prefix' => '',
    ])
@endif

@if (is_array($fundebRef['cenarios_previsao'] ?? null) && ($fundebRef['cenarios_previsao']['available'] ?? false))
    <h3>{{ __('2.3 Cenários de previsão e distribuição legal') }}</h3>
    @include('pdf.analytics-report.partials.fundeb-reference-tables', [
        'tables' => [
            'cenarios_previsao' => $fundebRef['cenarios_previsao'],
            'distribuicao_legal' => $fundebRef['distribuicao_legal'] ?? ['available' => false],
        ],
    ])
@endif

@if ($fundebYears['available'] ?? false)
    <h3>{{ __('2.4 Série VAAF/VAAT gravada (referência municipal)') }}</h3>
    <p class="action-lead"><strong>{{ $fundebYears['title'] ?? '' }}</strong> — {{ $fundebYears['subtitle'] ?? __('Série histórica para validar premissas da previsão base e da complementação VAAR no exercício corrente.') }}</p>
    @if (filled($fundebYears['previsao_label'] ?? null))
        <p>{{ __('Previsão no painel') }}: {{ $fundebYears['previsao_label'] }}</p>
    @endif
    @if (filled($fundebYears['previa_federal'] ?? null))
        <p>{{ __('Prévia federal de referência') }}: {{ $fundebYears['previa_federal'] }}</p>
    @endif
    <table class="data">
        <tr>
            <th>{{ __('Exercício') }}</th>
            <th>{{ __('VAAF') }}</th>
            <th>{{ __('VAAT') }}</th>
            <th>{{ __('Compl. VAAF') }}</th>
            <th>{{ __('Compl. VAAR') }}</th>
            <th>{{ __('Δ VAAF') }}</th>
            <th>{{ __('Fonte') }}</th>
        </tr>
        @foreach ($fundebYears['rows'] ?? [] as $row)
            <tr>
                <td>
                    {{ $row['ano'] ?? '' }}
                    @if ($row['is_anchor'] ?? false)
                        <span class="muted">({{ __('ref.') }})</span>
                    @endif
                </td>
                <td>{{ $row['vaaf'] ?? '—' }}</td>
                <td>{{ $row['vaat'] ?? '—' }}</td>
                <td>{{ $row['complementacao_vaaf'] ?? '—' }}</td>
                <td>{{ $row['complementacao_vaar'] ?? '—' }}</td>
                <td>{{ $row['variacao_vaaf_pct'] ?? '—' }}</td>
                <td class="muted" style="font-size:8pt;">{{ $row['fonte'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
    <p class="muted">{{ $fundebYears['note'] ?? '' }}</p>
@endif

@if (is_array($fundebRef['alertas_fnde'] ?? null) && ($fundebRef['alertas_fnde']['available'] ?? false))
    <h3>{{ __('2.5 Alertas FNDE / qualidade dos dados') }}</h3>
    @include('pdf.analytics-report.partials.fundeb-reference-tables', ['tables' => ['alertas_fnde' => $fundebRef['alertas_fnde']]])
@endif

@if ($statePart['available'] ?? false)
    <h3>{{ __('2.6 Participação do município no contexto da UF') }}</h3>
    <p class="action-lead"><strong>{{ $statePart['title'] ?? '' }}</strong> — {{ $statePart['subtitle'] ?? __('Indica peso relativo do município na UF para matrículas e repasses de referência — útil em negociação política e planeamento regional.') }}</p>
    <p class="muted">
        {{ __('Exercício') }}: {{ $statePart['exercicio'] ?? '—' }}
        @if (filled($statePart['publicacao_fnde'] ?? null))
            · {{ __('Publicação FNDE') }}: {{ $statePart['publicacao_fnde'] }}
        @endif
    </p>
    <table class="data">
        <tr>
            <th>{{ __('Indicador') }}</th>
            <th>{{ __('Município') }}</th>
            <th>{{ __('Referência UF') }}</th>
            <th>{{ __('Participação') }}</th>
            <th>{{ __('Fonte') }}</th>
        </tr>
        @foreach ($statePart['rows'] ?? [] as $row)
            <tr>
                <td>{{ $row['indicador'] ?? '' }}</td>
                <td>{{ $row['municipio'] ?? '—' }}</td>
                <td>{{ $row['referencia_uf'] ?? '—' }}</td>
                <td><strong>{{ $row['participacao'] ?? '—' }}</strong></td>
                <td class="muted" style="font-size:8pt;">{{ $row['fonte'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
    <p class="muted">{{ $statePart['note'] ?? '' }}</p>
@endif

@if ($munState['available'] ?? false)
    <h3>{{ __('2.7 Desempenho SAEB — município × UF') }}</h3>
    <p class="action-lead"><strong>{{ $munState['title'] ?? '' }}</strong> — {{ $munState['subtitle'] ?? __('Diferenças negativas persistentes orientam planos de formação e metas pedagógicas; confirme anos de referência antes de comparar com metas nacionais.') }}</p>
    <table class="data">
        <tr>
            <th>{{ __('Disciplina') }}</th>
            <th>{{ __('Ano (munic.)') }}</th>
            <th>{{ __('Município') }}</th>
            <th>{{ __('Ano (UF)') }}</th>
            <th>{{ __('UF') }}</th>
            <th>{{ __('Diferença') }}</th>
            <th>{{ __('Leitura') }}</th>
        </tr>
        @foreach ($munState['rows'] ?? [] as $row)
            <tr>
                <td>{{ $row['disciplina'] ?? '' }}</td>
                <td>{{ $row['ano_municipio'] ?? '—' }}</td>
                <td>{{ $row['valor_municipio'] ?? '—' }}</td>
                <td>{{ $row['ano_estado'] ?? '—' }}</td>
                <td>{{ $row['valor_estado'] ?? '—' }}</td>
                <td>{{ $row['diferenca'] ?? '—' }}</td>
                <td>{{ $row['leitura'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
    <p class="muted">{{ $munState['note'] ?? '' }}</p>
@endif
