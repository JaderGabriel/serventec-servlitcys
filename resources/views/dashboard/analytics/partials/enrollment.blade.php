@props(['enrollmentData'])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Gráficos de matrículas por turma (ordenados por etapa/série), escola, série, turno, oferta e vagas em aberto (capacidade − alunos), conforme filtros e config/ieducar.php.') }}
    </p>

    @php
        $enrollmentCharts = $enrollmentData['charts'] ?? [];
        if ($enrollmentCharts === [] && ! empty($enrollmentData['chart'])) {
            $enrollmentCharts = [$enrollmentData['chart']];
        }
    @endphp
    @if ($enrollmentCharts !== [])
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @foreach ($enrollmentCharts as $idx => $chart)
                <x-dashboard.chart-panel :chart="$chart" :exportFilename="'matriculas-'.$idx" />
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
