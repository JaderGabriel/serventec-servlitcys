@props(['performanceData', 'chartExportContext' => []])

@php
    $perfCharts = $performanceData['charts'] ?? [];
    if ($perfCharts === [] && ! empty($performanceData['chart'])) {
        $perfCharts = [$performanceData['chart']];
    }
@endphp

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Cada taxa = (matrículas na categoria) ÷ (total de matrículas ativas no filtro) × 100. O total usa matricula.ativo e o campo de situação (por defeito «aprovado»); os filtros de ano/escola/curso/turno aplicam-se pela turma. As categorias seguem os códigos i-Educar no mesmo campo. O gráfico de distorção idade/série (rede) mostra barras horizontais com quantidades absolutas por ano/série (idade à data de corte 31/03 e limite etário + 2 anos), ou SQL personalizado IEDUCAR_SQL_DISTORCAO_REDE_CHART.') }}
    </p>

    @php $inepPanel = $performanceData['inep_panel'] ?? null; @endphp
    @if ($inepPanel !== null)
        @if (! empty($inepPanel['sql_error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ $inepPanel['sql_error'] }}
            </div>
        @endif
        @if (! empty($inepPanel['sql_note']))
            <div class="rounded-md bg-sky-50 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800 px-4 py-3 text-xs text-sky-900 dark:text-sky-100 leading-relaxed">
                {{ $inepPanel['sql_note'] }}
            </div>
        @endif

        <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-950/25 p-4 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-indigo-950 dark:text-indigo-100">{{ __('Indicadores externos: IDEB, SAEB e PNE') }}</h3>
                <p class="text-xs text-indigo-900/90 dark:text-indigo-200/90 mt-1 leading-relaxed">
                    {{ __('O IDEB e o SAEB são produzidos pelo INEP; as metas do PNE são acompanhadas com indicadores nacionais. Abaixo consolidamos referências e, se configurado, valores lidos da sua base (tabela ou view própria). Consulte também o portal do INEP para séries históricas oficiais.') }}
                    <a href="https://www.gov.br/inep/pt-br" class="text-indigo-700 dark:text-indigo-300 underline" target="_blank" rel="noopener noreferrer">https://www.gov.br/inep/pt-br</a>
                </p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @foreach (['ideb', 'saeb', 'pne'] as $secKey)
                    @php $sec = $inepPanel['sections'][$secKey] ?? null; @endphp
                    @if ($sec !== null)
                        <div class="rounded-lg border border-white/80 dark:border-indigo-900/50 bg-white/90 dark:bg-gray-900/40 p-3 shadow-sm flex flex-col gap-2 min-h-[11rem]">
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $sec['title'] }}</p>
                            <p class="text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed flex-1">{{ $sec['intro'] }}</p>
                            @if (! empty($sec['items']))
                                <ul class="text-xs space-y-2 text-gray-800 dark:text-gray-200 border-t border-gray-100 dark:border-gray-700 pt-2">
                                    @foreach ($sec['items'] as $item)
                                        <li class="leading-snug">
                                            <span class="font-medium">{{ $item['label'] ?? '—' }}</span>
                                            @if (($item['valor'] ?? null) !== null && is_numeric($item['valor']))
                                                <span class="tabular-nums text-indigo-700 dark:text-indigo-300"> — {{ number_format((float) $item['valor'], 2, ',', '.') }}</span>
                                                @if (! empty($item['unidade']))
                                                    <span class="text-gray-500"> ({{ $item['unidade'] }})</span>
                                                @endif
                                            @else
                                                <span class="text-gray-500"> — {{ __('valor não numérico na base') }}</span>
                                            @endif
                                            @if (! empty($item['referencia']))
                                                <span class="text-gray-500"> · {{ __('ref.') }} {{ $item['referencia'] }}</span>
                                            @endif
                                            @if (! empty($item['detalhe']))
                                                <p class="text-[10px] text-gray-500 mt-0.5">{{ $item['detalhe'] }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-[11px] text-amber-800 dark:text-amber-200/90">{{ $sec['empty_hint'] }}</p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
    @if (! empty($performanceData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $performanceData['error'] }}
        </div>
    @endif
    @if (! empty($performanceData['message']))
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">{{ $performanceData['message'] }}</p>
    @endif

    @if (! empty($performanceData['distorcao_note']))
        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2">{{ $performanceData['distorcao_note'] }}</p>
    @endif

    @if (filled(data_get($performanceData, 'kpi_meta.denominador_texto')))
        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ data_get($performanceData, 'kpi_meta.denominador_texto') }}
        </p>
    @endif
    @if (filled(data_get($performanceData, 'kpi_meta.alerta_ano_encerrado')))
        <div class="rounded-md bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 px-3 py-2 text-sm text-amber-950 dark:text-amber-100">
            {{ data_get($performanceData, 'kpi_meta.alerta_ano_encerrado') }}
        </div>
    @endif

    @if (! empty($performanceData['kpis']))
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach ($performanceData['kpis'] as $kpi)
                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3 flex flex-col gap-2 min-h-[12rem]">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-snug">
                        {{ $kpi['chart_label'] ?? $kpi['label'] ?? '—' }}
                    </p>
                    <div class="text-[11px] text-gray-600 dark:text-gray-400 space-y-2 text-justify flex-1">
                        @if (! empty($kpi['formula']))
                            <p class="font-mono text-gray-700 dark:text-gray-300 leading-relaxed">{{ $kpi['formula'] }}</p>
                        @endif
                        @if (! empty($kpi['description']))
                            <p class="leading-relaxed">{{ $kpi['description'] }}</p>
                        @endif
                    </div>
                    <div class="mt-auto pt-3 border-t border-gray-200 dark:border-gray-600 space-y-1">
                        <p class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                            @if (array_key_exists('percent', $kpi) && is_numeric($kpi['percent']))
                                {{ number_format((float) $kpi['percent'], 1, ',', '.') }}%
                            @else
                                —
                            @endif
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-300 tabular-nums">
                            {{ isset($kpi['quantidade']) ? number_format((int) $kpi['quantidade']) : '—' }} {{ __('matrículas') }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
        @if (($performanceData['distorcao_pct'] ?? null) !== null)
            <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/80 dark:bg-indigo-950/30 px-3 py-2 text-sm text-indigo-900 dark:text-indigo-100">
                {{ __('Distorção idade/série (rede)') }}:
                <span class="font-semibold">{{ number_format((float) $performanceData['distorcao_pct'], 1, ',', '.') }}%</span>
                <span class="text-xs text-indigo-700 dark:text-indigo-300">({{ __('fonte: IEDUCAR_SQL_DISTORCAO_REDE') }})</span>
            </div>
        @endif
    @endif

    @if ($perfCharts !== [])
        <div class="grid grid-cols-1 gap-6">
            @foreach ($perfCharts as $idx => $chart)
                <x-dashboard.chart-panel
                    :chart="$chart"
                    :exportFilename="'desempenho-'.$idx"
                    :exportMeta="$chartExportContext"
                />
            @endforeach
        </div>
    @elseif (empty($performanceData['error']) && empty($performanceData['message']) && $perfCharts === [] && empty($performanceData['kpis'] ?? []) && empty($performanceData['inep_panel'] ?? null))
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center text-sm text-gray-400 dark:text-gray-500">
            {{ __('Sem dados para desempenho com os filtros atuais.') }}
        </div>
    @endif

    @if (! empty($performanceData['rows']))
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Situação') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Quantidade') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($performanceData['rows'] as $row)
                        <tr>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $row['label'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $row['quantidade'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
