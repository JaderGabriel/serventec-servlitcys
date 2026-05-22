@php
    $cmp = is_array($comparatives ?? null) ? $comparatives : [];
    $fundebYears = is_array($cmp['fundeb_years'] ?? null) ? $cmp['fundeb_years'] : [];
    $statePart = is_array($cmp['state_participation'] ?? null) ? $cmp['state_participation'] : [];
    $yearCmp = is_array($year_comparison ?? null) ? $year_comparison : [];
    $munState = is_array($municipal_vs_state ?? null) ? $municipal_vs_state : [];
@endphp

@if (filled($cmp['legal_notice'] ?? null))
    <div class="box legal-notice">
        <p class="muted" style="margin:0;"><strong>{{ __('Nota metodológica') }}</strong> — {{ $cmp['legal_notice'] }}</p>
    </div>
@endif

@if (count($yearCmp) > 0)
    <h2>{{ __('2. Evolução entre anos letivos (cadastro e Fundeb)') }}</h2>
    <p class="muted">{{ __('Quadro no estilo de séries históricas dos cadernos FNDE/MEC — matrículas activas do i-Educar e VAAF municipal gravado por exercício.') }}</p>
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

@if ($fundebYears['available'] ?? false)
    <h2>{{ __('3. Série VAAF/VAAT por exercício (referência municipal)') }}</h2>
    <p class="muted"><strong>{{ $fundebYears['title'] ?? '' }}</strong> — {{ $fundebYears['subtitle'] ?? '' }}</p>
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
                <td>{{ $row['complementacao_vaar'] ?? '—' }}</td>
                <td>{{ $row['variacao_vaaf_pct'] ?? '—' }}</td>
                <td class="muted" style="font-size:8pt;">{{ $row['fonte'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
    <p class="muted">{{ $fundebYears['note'] ?? '' }}</p>
@endif

@if ($statePart['available'] ?? false)
    <h2>{{ __('4. Participação do município no contexto da UF') }}</h2>
    <p class="muted"><strong>{{ $statePart['title'] ?? '' }}</strong> — {{ $statePart['subtitle'] ?? '' }}</p>
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
    <h2>{{ __('5. Desempenho SAEB — município × UF') }}</h2>
    <p class="muted"><strong>{{ $munState['title'] ?? '' }}</strong> — {{ $munState['subtitle'] ?? '' }}</p>
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
