@props(['performanceData'])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Indicadores da rede (taxas sobre matrículas activas) e distribuição pelo campo de situação (por defeito «aprovado» na tabela matricula). Os filtros aplicam-se através da turma quando o ano ou escola/tipo/turno estão definidos.') }}
    </p>
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

    @if (! empty($performanceData['kpis']))
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach ($performanceData['kpis'] as $kpi)
                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 leading-tight">{{ $kpi['label'] ?? '—' }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">
                        @if (($kpi['percent'] ?? null) !== null)
                            {{ number_format((float) $kpi['percent'], 1, ',', '.') }}%
                        @else
                            —
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ isset($kpi['quantidade']) ? number_format((int) $kpi['quantidade']) : '—' }} {{ __('matrículas') }}
                    </p>
                </div>
            @endforeach
        </div>
        @if (($performanceData['distorcao_pct'] ?? null) !== null)
            <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/80 dark:bg-indigo-950/30 px-3 py-2 text-sm text-indigo-900 dark:text-indigo-100">
                {{ __('Distorção idade/série (rede)') }}:
                <span class="font-semibold">{{ number_format((float) $performanceData['distorcao_pct'], 1, ',', '.') }}%</span>
                <span class="text-xs text-indigo-700 dark:text-indigo-300">({{ __('fonte: IEDUCAR_SQL_DISTORCAO_REDE') }})</span>
            </div>
        @else
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Distorção idade/série: opcional via IEDUCAR_SQL_DISTORCAO_REDE (consulta personalizada por município).') }}</p>
        @endif
    @endif

    @php
        $perfCharts = $performanceData['charts'] ?? [];
        if ($perfCharts === [] && ! empty($performanceData['chart'])) {
            $perfCharts = [$performanceData['chart']];
        }
    @endphp
    @if ($perfCharts !== [])
        <div class="grid grid-cols-1 gap-6">
            @foreach ($perfCharts as $idx => $chart)
                <x-dashboard.chart-panel :chart="$chart" :exportFilename="'desempenho-'.$idx" />
            @endforeach
        </div>
    @elseif (empty($performanceData['error']) && empty($performanceData['message']))
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center text-sm text-gray-400 dark:text-gray-500">
            {{ __('Sem dados para desempenho com os filtros actuais.') }}
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
