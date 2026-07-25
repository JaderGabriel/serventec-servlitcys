<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ __('Canteiro — alertas consultoria') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        .meta { color: #64748b; margin-bottom: 18px; }
        .note { background: #fff7ed; border: 1px solid #fdba74; padding: 8px 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        .city { margin-bottom: 18px; page-break-inside: avoid; }
        .city h2 { font-size: 13px; margin: 0 0 6px; }
        .warn { color: #b45309; font-weight: bold; }
        ul { margin: 4px 0 0 16px; padding: 0; }
    </style>
</head>
<body>
    <h1>{{ __('Canteiro — obras de educação (consultoria)') }}</h1>
    <p class="meta">
        {{ __('Gerado em') }}: {{ $generatedAt }}
        @if($simecUrl !== '')
            · {{ __('SIMEC') }}: {{ $simecUrl }}
        @endif
    </p>
    <div class="note">
        {{ $payload['note'] ?? __('Alertas apenas para municípios com consultoria activa. Valores de empenho/pago são indicativos (Obrasgov).') }}
    </div>

    @forelse($cities as $city)
        <div class="city">
            <h2>
                {{ $city['city_name'] ?? '—' }} / {{ $city['uf'] ?? '' }}
                <span class="warn">
                    · P {{ $city['paralisadas'] ?? 0 }}
                    · E {{ $city['em_execucao'] ?? 0 }}
                    · I {{ $city['inacabadas'] ?? 0 }}
                </span>
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Situação') }}</th>
                        <th>{{ __('Obra') }}</th>
                        <th>{{ __('% físico') }}</th>
                        <th>{{ __('Pago (ind.)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($city['works'] ?? []) as $work)
                        <tr>
                            <td>{{ $work['situacao'] ?? '—' }}</td>
                            <td>{{ $work['nome'] ?? '—' }}</td>
                            <td>
                                @if(isset($work['percentual']) && $work['percentual'] !== null)
                                    {{ number_format((float) $work['percentual'], 0, ',', '.') }}%
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if(!empty($work['valor_pago']))
                                    R$ {{ number_format((float) $work['valor_pago'], 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p>{{ __('Nenhum município de consultoria com obras paralisadas, em execução ou inacabadas neste ciclo.') }}</p>
    @endforelse
</body>
</html>
