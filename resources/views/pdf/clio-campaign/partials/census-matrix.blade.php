{{-- Matriz de exposição do ano atual (escolas ativas) — modelo tipo Resultados finais, sem Δ ano anterior --}}
@php
    $matrix = $tables['census_matrix'] ?? [];
@endphp
@if (! empty($matrix['available']))
    <div style="page-break-before: always;"></div>
    <h2>{{ __('Exposição das matrículas — escolas ativas (:ano)', ['ano' => $matrix['year'] ?? '']) }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 8px;">
        {{ __('Cód. :ibge · :uf · :mun · :n escola(s) em atividade.', [
            'ibge' => $matrix['ibge'] ?: '—',
            'uf' => $matrix['uf'] ?? '',
            'mun' => $matrix['municipality'] ?? '',
            'n' => $matrix['schools_active'] ?? 0,
        ]) }}
        <br>{{ $matrix['note'] ?? '' }}
    </p>

    @foreach (['infantil', 'fundamental', 'eja'] as $blockKey)
        @php $block = $matrix[$blockKey] ?? null; @endphp
        @if (is_array($block))
            <h3 style="font-size: 12px; margin-top: 14px; margin-bottom: 4px;">{{ $block['title'] ?? '' }}</h3>
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ __('Matrícula') }}</th>
                        @foreach ($block['columns'] ?? [] as $col)
                            <th colspan="2" style="text-align: center;">{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th></th>
                        @foreach ($block['columns'] ?? [] as $col)
                            <th style="text-align: center; font-size: 9px;">{{ __('Urbana') }}</th>
                            <th style="text-align: center; font-size: 9px;">{{ __('Rural') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (($block['rows'] ?? []) as $modKey => $modLabel)
                        <tr>
                            <td><strong>{{ $modLabel }}</strong></td>
                            @foreach ($block['columns'] ?? [] as $col)
                                @php
                                    $vals = $block['values'][$col['key']] ?? [];
                                    $u = (int) ($vals['Urbana'][$modKey] ?? 0);
                                    $r = (int) ($vals['Rural'][$modKey] ?? 0);
                                @endphp
                                <td style="text-align: right; {{ $u > 0 ? 'font-weight: 600;' : 'color: #94a3b8;' }}">{{ $u }}</td>
                                <td style="text-align: right; {{ $r > 0 ? 'font-weight: 600;' : 'color: #94a3b8;' }}">{{ $r }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @php $geral = $matrix['geral'] ?? []; @endphp
    @if (! empty($geral))
        <h3 style="font-size: 12px; margin-top: 16px; margin-bottom: 4px;">{{ $geral['title'] ?? __('Análise geral') }}</h3>
        <table class="data">
            <thead>
                <tr>
                    @foreach ($geral['columns'] ?? [] as $col)
                        <th style="text-align: center; font-size: 9px;">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach ($geral['columns'] ?? [] as $col)
                        @php $v = (int) (($geral['values'][$col['key']] ?? 0)); @endphp
                        <td style="text-align: center; font-size: 13px; font-weight: 700; {{ ($col['key'] ?? '') === 'geral' ? 'background: #eff6ff;' : '' }}">
                            {{ $v }}
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
        <p style="font-size: 9px; color: #64748b; margin-top: 4px;">
            {{ __('GERAL = soma das colunas de Regular por etapa/jornada (Educação Especial é informativa e não entra no GERAL).') }}
        </p>
    @endif
@endif
