@props(['enrollmentData', 'chartExportContext' => []])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Inclui distorção idade/série (rede), em seguida matrículas por escola (principais + «outras» ou top 10), níveis/séries/cursos, turno, oferta e vagas. KPIs no topo: matrículas activas, turmas e ocupação média quando existir capacidade na turma.') }}
    </p>

    @if (! empty($enrollmentData['kpis']))
        @php $k = $enrollmentData['kpis']; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Matrículas activas') }}</p>
                <p class="mt-1 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($k['matriculas'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Turmas com matrícula') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($k['turmas_distintas'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ocupação média (turmas com vaga)') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    @if (isset($k['ocupacao_pct']) && $k['ocupacao_pct'] !== null)
                        {{ number_format($k['ocupacao_pct'], 1) }}%
                    @else
                        —
                    @endif
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Requer coluna de capacidade na turma (ex.: max_aluno).') }}</p>
            </div>
        </div>
    @endif

    @php
        $enrollmentCharts = $enrollmentData['charts'] ?? [];
        if ($enrollmentCharts === [] && ! empty($enrollmentData['chart'])) {
            $enrollmentCharts = [$enrollmentData['chart']];
        }
    @endphp
    @if ($enrollmentCharts !== [])
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @foreach ($enrollmentCharts as $idx => $chart)
                <x-dashboard.chart-panel
                    :chart="$chart"
                    :exportFilename="'matriculas-'.$idx"
                    :exportMeta="$chartExportContext"
                />
            @endforeach
        </div>
    @endif

    @if (! empty($enrollmentData['error']))
        <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
            {{ $enrollmentData['error'] }}
        </div>
    @endif

    @if (empty($enrollmentData['error']) && ($enrollmentCharts ?? []) === [] && empty($enrollmentData['chart'] ?? null))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem gráficos de matrícula para estes filtros ou cidade não selecionada.') }}</p>
    @endif
</div>
