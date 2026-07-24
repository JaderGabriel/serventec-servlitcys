{{-- Quadro Diagnóstico Geral (PDF detalhado e gerencial) --}}
@php
    $diag = $diagnosticoGeral ?? ['available' => false, 'rows' => [], 'totals' => []];
    $totals = is_array($diag['totals'] ?? null) ? $diag['totals'] : [];
@endphp
@if (! empty($diag['available']))
    <h2>{{ __('Diagnóstico Geral') }}</h2>
    <p style="font-size: 10px; color: #64748b; margin-bottom: 8px;">
        {{ __('Escolas em atividade: código INEP, localidade e alertas (erros e avisos), incluindo alunos sem declaração de Cor/Raça.') }}
    </p>

    <table class="data" style="margin-bottom: 8px;">
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
                <tr>
                    <td style="font-family: DejaVu Sans Mono, monospace; font-size: 9px;">{{ $row['inep'] }}</td>
                    <td style="font-size: 9.5px;">
                        <strong>{{ $row['name'] }}</strong>
                        @if (($row['error_count'] ?? 0) > 0 || ($row['warning_count'] ?? 0) > 0)
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
                        @php
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
                        <span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8.5px; font-weight: 700; background: {{ $locBg }}; color: {{ $locFg }};">
                            {{ $row['location'] }}
                        </span>
                    </td>
                    <td style="font-size: 9px; line-height: 1.35;">
                        @foreach ($row['alerts'] as $alert)
                            @php
                                $sev = $alert['severity'] ?? 'ok';
                                $chipBg = match ($sev) {
                                    'error' => '#fff1f2',
                                    'warning' => '#fff7ed',
                                    'ok' => '#ecfdf5',
                                    default => '#f8fafc',
                                };
                                $chipFg = match ($sev) {
                                    'error' => '#9f1239',
                                    'warning' => '#9a3412',
                                    'ok' => '#047857',
                                    default => '#475569',
                                };
                                $icon = match ($sev) {
                                    'error' => '●',
                                    'warning' => '▲',
                                    'ok' => '✓',
                                    default => '•',
                                };
                            @endphp
                            <div style="margin: 0 0 4px; padding: 3px 5px; border-radius: 3px; background: {{ $chipBg }}; color: {{ $chipFg }}; border-left: 2.5px solid {{ $chipFg }};">
                                <span style="font-weight: 700;">{{ $icon }}</span>
                                {{ $alert['message'] }}
                            </div>
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="data">
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
        </tbody>
    </table>
@endif
