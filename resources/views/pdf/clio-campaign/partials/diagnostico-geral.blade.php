{{-- Quadro Diagnóstico Geral (PDF detalhado, gerencial e final) --}}
@php
    $diag = $diagnosticoGeral ?? ['available' => false, 'rows' => [], 'totals' => []];
    $totals = is_array($diag['totals'] ?? null) ? $diag['totals'] : [];
    $networkNotices = is_array($diag['network_notices'] ?? null) ? $diag['network_notices'] : [];
    $corUndeclared = is_array($diag['cor_raca_undeclared'] ?? null) ? $diag['cor_raca_undeclared'] : ['total' => 0, 'schools' => []];
    /** DomPDF: linhas altas invadem o rodapé — fragmentar alertas e repetir a escola. */
    $alertChunkSize = max(1, min(6, (int) ($diagAlertChunkSize ?? 3)));
@endphp
@if (! empty($diag['available']))
    <h2 @if (! empty($diagTitleUpper)) style="text-transform: uppercase;" @endif>{{ __('Diagnóstico Geral') }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 8px;">
        {{ __('Visão por escola em atividade: consolida avisos dos temas anteriores (matrículas, inclusão, distorção, demografia, transporte, tempos escolares, densidade/profissionais) e da tríade. Só neste quadro a leitura é escola a escola.') }}
    </p>

    @foreach ($networkNotices as $notice)
        @php
            $sev = $notice['severity'] ?? 'warning';
            $bg = $sev === 'error' ? '#fff1f2' : '#fff7ed';
            $fg = $sev === 'error' ? '#9f1239' : '#9a3412';
            $icon = $sev === 'error' ? '●' : '▲';
        @endphp
        <p class="diag-geral-notice" style="font-size: 9.5px; margin: 0 0 6px; padding: 6px 8px; border-radius: 3px; background: {{ $bg }}; color: {{ $fg }}; border-left: 3px solid {{ $fg }};">
            <strong>{{ $icon }}</strong> {{ $notice['message'] ?? '' }}
        </p>
    @endforeach

    @if ((int) ($corUndeclared['total'] ?? 0) > 0 && $networkNotices === [])
        <p class="diag-geral-notice" style="font-size: 9.5px; margin: 0 0 6px; padding: 6px 8px; border-radius: 3px; background: #fff7ed; color: #9a3412; border-left: 3px solid #9a3412;">
            <strong>▲</strong>
            {{ __('Cor/Raça não declarada: :n aluno(s) na rede. Complete no Educacenso para o indicador demográfico ficar confiável.', [
                'n' => number_format((int) $corUndeclared['total'], 0, ',', '.'),
            ]) }}
        </p>
    @endif

    <table class="data diag-geral-table" style="margin-bottom: 8px;">
        <thead>
            <tr>
                <th style="width: 12%;">{{ __('INEP') }}</th>
                <th style="width: 28%;">{{ __('Escola') }}</th>
                <th style="width: 12%;">{{ __('Localidade') }}</th>
                <th>{{ __('Alertas / pendências') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($diag['rows'] as $row)
                @php
                    $alerts = array_values(array_filter(
                        is_array($row['alerts'] ?? null) ? $row['alerts'] : [],
                        static fn ($a): bool => is_array($a),
                    ));
                    $chunks = $alerts === [] ? [[]] : array_chunk($alerts, $alertChunkSize);
                    $locTone = $row['location_tone'] ?? 'slate';
                    $locBg = match ($locTone) {
                        'amber' => '#fff7ed',
                        'sky' => '#f0f9ff',
                        default => '#f8fafc',
                    };
                    $locFg = match ($locTone) {
                        'amber' => '#9a3412',
                        'sky' => '#075985',
                        default => '#475569',
                    };
                @endphp
                @foreach ($chunks as $chunkIndex => $chunk)
                    @php $isContinuation = $chunkIndex > 0; @endphp
                    <tr class="diag-geral-row">
                        <td style="font-family: DejaVu Sans Mono, monospace; font-size: 9px;">{{ $row['inep'] }}</td>
                        <td style="font-size: 9.5px;">
                            <strong>{{ $row['name'] }}</strong>
                            @if ($isContinuation)
                                <div style="margin-top: 2px; font-size: 8px; color: #64748b; font-weight: 700;">
                                    {{ __('(continuação)') }}
                                </div>
                            @elseif (($row['error_count'] ?? 0) > 0 || ($row['warning_count'] ?? 0) > 0)
                                <div style="margin-top: 2px; font-size: 8px;">
                                    @if (($row['error_count'] ?? 0) > 0)
                                        <span style="color: #be123c; font-weight: 700;">● {{ $row['error_count'] }} {{ __('erro(s)') }}</span>
                                    @endif
                                    @if (($row['warning_count'] ?? 0) > 0)
                                        <span style="color: #c2410c; font-weight: 700; margin-left: 4px;">▲ {{ $row['warning_count'] }} {{ __('aviso(s)') }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8.5px; font-weight: 700; background: {{ $locBg }}; color: {{ $locFg }};">
                                {{ $row['location'] }}
                            </span>
                        </td>
                        <td style="font-size: 9px; line-height: 1.35;">
                            @if ($chunk === [])
                                <div style="margin: 0; padding: 3px 5px; border-radius: 3px; background: #ecfdf5; color: #047857; border-left: 2.5px solid #047857;">
                                    <span style="font-weight: 700;">✓</span>
                                    {{ __('Sem pendências gerenciais nesta coleta.') }}
                                </div>
                            @else
                                @foreach ($chunk as $alert)
                                    @php
                                        $sev = $alert['severity'] ?? 'ok';
                                        $chipBg = match ($sev) {
                                            'error' => '#fff1f2',
                                            'warning' => '#fff7ed',
                                            'info' => '#ecfeff',
                                            'ok' => '#ecfdf5',
                                            default => '#f8fafc',
                                        };
                                        $chipFg = match ($sev) {
                                            'error' => '#9f1239',
                                            'warning' => '#9a3412',
                                            'info' => '#0e7490',
                                            'ok' => '#047857',
                                            default => '#475569',
                                        };
                                        $icon = match ($sev) {
                                            'error' => '●',
                                            'warning' => '▲',
                                            'info' => 'ℹ',
                                            'ok' => '✓',
                                            default => '•',
                                        };
                                    @endphp
                                    <div style="margin: 0 0 4px; padding: 3px 5px; border-radius: 3px; background: {{ $chipBg }}; color: {{ $chipFg }}; border-left: 2.5px solid {{ $chipFg }};">
                                        <span style="font-weight: 700;">{{ $icon }}</span>
                                        @if (! empty($alert['theme']))
                                            @php
                                                $themeLabel = match ($alert['theme']) {
                                                    'inclusao' => __('Inclusão'),
                                                    'distorcao' => __('Distorção'),
                                                    'demografia' => __('Demografia'),
                                                    'transporte' => __('Transporte'),
                                                    'tempos' => __('Tempos'),
                                                    'densidade' => __('Densidade'),
                                                    default => __('Matrículas'),
                                                };
                                            @endphp
                                            <span style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; font-size: 7.5px;">[{{ $themeLabel }}]</span>
                                        @endif
                                        {{ $alert['message'] }}
                                    </div>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="data diag-geral-totals">
        <thead>
            <tr>
                <th>{{ __('Totalizador') }}</th>
                <th>{{ __('Quantidade') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('Escolas em atividade') }}</td>
                <td><strong>{{ (int) ($totals['schools'] ?? 0) }}</strong></td>
            </tr>
            <tr>
                <td><span style="color: #be123c;">●</span> {{ __('Total de erros') }}</td>
                <td style="color: #be123c; font-weight: 700;">{{ (int) ($totals['errors'] ?? 0) }}</td>
            </tr>
            <tr>
                <td><span style="color: #c2410c;">▲</span> {{ __('Total de avisos') }}</td>
                <td style="color: #c2410c; font-weight: 700;">{{ (int) ($totals['warnings'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>{{ __('Escolas com alertas') }}</td>
                <td>{{ (int) ($totals['with_alerts'] ?? 0) }}</td>
            </tr>
            <tr>
                <td><span style="color: #047857;">✓</span> {{ __('Escolas sem pendências') }}</td>
                <td style="color: #047857; font-weight: 700;">{{ (int) ($totals['ok'] ?? 0) }}</td>
            </tr>
            @if (($totals['without_data'] ?? 0) > 0)
                <tr>
                    <td>{{ __('Escolas sem lançamento') }}</td>
                    <td>{{ (int) $totals['without_data'] }}</td>
                </tr>
            @endif
            @if ((int) ($corUndeclared['total'] ?? 0) > 0)
                <tr>
                    <td>{{ __('Alunos com Cor/Raça não declarada') }}</td>
                    <td style="color: #c2410c; font-weight: 700;">{{ number_format((int) $corUndeclared['total'], 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endif
