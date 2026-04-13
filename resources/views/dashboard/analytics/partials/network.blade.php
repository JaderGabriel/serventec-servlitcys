@props(['networkData'])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Oferta por turno, distribuição de turmas, matrículas por série e por escola — útil para planear expansão de turnos, reorganização de turmas e priorização de unidades.') }}
    </p>

    @if (! empty($networkData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $networkData['error'] }}
        </div>
    @endif

    @if (! empty($networkData['notes']))
        <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-xs text-amber-900 dark:text-amber-100 space-y-1">
            @foreach ($networkData['notes'] as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif

    @if (! empty($networkData['charts']))
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @foreach ($networkData['charts'] as $idx => $chart)
                <x-dashboard.chart-panel :chart="$chart" :exportFilename="'rede-oferta-'.$idx" />
            @endforeach
        </div>
    @elseif (empty($networkData['error']))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem gráficos de rede para esta base ou filtros.') }}</p>
    @endif
</div>
