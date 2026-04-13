@props(['inclusionData', 'chartExportContext' => []])

<div class="space-y-6">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Inclusão & Diversidade: medidores (NEE), distribuição por sexo, segundo gráfico de equidade (série, nível de ensino ou curso conforme a base), raça/cor e SQL opcional. Os filtros aplicam-se pela turma quando existir.') }}
    </p>

    @if (! empty($inclusionData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $inclusionData['error'] }}
        </div>
    @endif

    @if (! empty($inclusionData['notes']))
        <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-xs text-amber-900 dark:text-amber-100 space-y-1">
            @foreach ($inclusionData['notes'] as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif

    @if (! empty($inclusionData['gauges']))
        <div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ __('Medidores (sobre matrículas activas no filtro)') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($inclusionData['gauges'] as $idx => $gauge)
                    <div class="space-y-2">
                        <x-dashboard.chart-panel
                            :chart="$gauge['chart']"
                            :exportFilename="'inclusao-medidor-'.$idx"
                            :exportMeta="$chartExportContext"
                            :compact="true"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-snug">{{ $gauge['caption'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (! empty($inclusionData['charts']))
        <div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ __('Equidade, raça/cor e complementares') }}</h3>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                @foreach ($inclusionData['charts'] as $idx => $chart)
                    <x-dashboard.chart-panel
                        :chart="$chart"
                        :exportFilename="'inclusao-'.$idx"
                        :exportMeta="$chartExportContext"
                    />
                @endforeach
            </div>
        </div>
    @elseif (empty($inclusionData['error']) && empty($inclusionData['charts']) && empty($inclusionData['gauges'] ?? []))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem indicadores disponíveis para esta base ou filtros.') }}</p>
    @endif
</div>
