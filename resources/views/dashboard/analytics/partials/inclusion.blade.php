@props(['inclusionData'])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Indicadores de inclusão e diversidade: raça/cor declarada no cadastro (quando as tabelas aluno, pessoa e raca estiverem acessíveis) e espaço para SQL personalizado (Necessidades educacionais, público do transporte, etc.). Os filtros do painel aplicam-se quando a consulta passa pela turma.') }}
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

    @if (! empty($inclusionData['charts']))
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @foreach ($inclusionData['charts'] as $idx => $chart)
                <x-dashboard.chart-panel :chart="$chart" :exportFilename="'inclusao-'.$idx" />
            @endforeach
        </div>
    @elseif (empty($inclusionData['error']))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem gráficos disponíveis para esta base.') }}</p>
    @endif
</div>
