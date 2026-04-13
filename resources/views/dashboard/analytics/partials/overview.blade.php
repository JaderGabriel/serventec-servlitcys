@props(['overviewData', 'yearFilterReady' => true])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Esta aba mostra totais na base do município (escola, turma, matrícula). O ano letivo é obrigatório; depois pode filtrar escola, tipo/segmento e turno.') }}
    </p>

    @if ($yearFilterReady && ! empty($overviewData['filter_note']))
        <p class="text-xs text-indigo-800 dark:text-indigo-200 bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900 rounded-md px-3 py-2">
            {{ $overviewData['filter_note'] }}
        </p>
    @endif

    @if ($yearFilterReady && ! empty($overviewData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $overviewData['error'] }}
        </div>
    @endif

    @if ($yearFilterReady && ! empty($overviewData['kpis']))
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Escolas') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ $overviewData['kpis']['escolas'] !== null ? number_format($overviewData['kpis']['escolas']) : '—' }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Turmas') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ $overviewData['kpis']['turmas'] !== null ? number_format($overviewData['kpis']['turmas']) : '—' }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Matrículas (tabela)') }}</p>
                <p class="mt-1 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">
                    {{ $overviewData['kpis']['matriculas'] !== null ? number_format($overviewData['kpis']['matriculas']) : '—' }}
                </p>
            </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Totais conforme filtros e config/ieducar.php (colunas da turma para recortes).') }}</p>
    @elseif ($yearFilterReady && empty($overviewData['error']))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem totais para estes filtros.') }}</p>
    @endif

    @if ($yearFilterReady && ! empty($overviewData['charts']))
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-4">
            @foreach ($overviewData['charts'] as $idx => $chart)
                <x-dashboard.chart-panel :chart="$chart" :exportFilename="'visao-geral-'.$idx" />
            @endforeach
        </div>
    @endif
</div>
