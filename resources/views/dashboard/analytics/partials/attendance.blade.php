@props(['attendanceData', 'chartExportContext' => []])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Total de registos de faltas agrupados por mês, com os filtros aplicados pela matrícula e turma.') }}
    </p>
    @if (! empty($attendanceData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $attendanceData['error'] }}
        </div>
    @endif
    @if (! empty($attendanceData['message']))
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">{{ $attendanceData['message'] }}</p>
    @endif

    @php
        $attCharts = $attendanceData['charts'] ?? [];
        if ($attCharts === [] && ! empty($attendanceData['chart'])) {
            $attCharts = [$attendanceData['chart']];
        }
    @endphp
    @if ($attCharts !== [])
        <div class="grid grid-cols-1 gap-6">
            @foreach ($attCharts as $idx => $chart)
                <x-dashboard.chart-panel
                    :chart="$chart"
                    :exportFilename="'frequencia-'.$idx"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                />
            @endforeach
        </div>
    @elseif (empty($attendanceData['error']) && empty($attendanceData['message']))
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center text-sm text-gray-400 dark:text-gray-500">
            {{ __('Sem dados de frequência para os filtros actuais.') }}
        </div>
    @endif

    @if (! empty($attendanceData['rows']))
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Mês') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Registos') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($attendanceData['rows'] as $row)
                        <tr>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $row['mes'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $row['faltas'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
