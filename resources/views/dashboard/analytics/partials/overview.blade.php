@props(['overviewData'])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Esta aba mostra totais agregados na base do município: número de registros nas tabelas de escola, turma e matrícula (conforme nomes em config/ieducar.php). São contagens diretas, sem filtros de ano ou escola, úteis para validar se a extração está coerente.') }}
    </p>

    @if (! empty($overviewData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $overviewData['error'] }}
        </div>
    @endif

    @if (! empty($overviewData['kpis']))
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
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Totais lidos diretamente da base da cidade (config/ieducar.php).') }}</p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Selecione uma cidade para carregar os indicadores.') }}</p>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-6 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('Gráfico: evolução (em desenvolvimento)') }}</div>
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-6 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('Gráfico: distribuição (em desenvolvimento)') }}</div>
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-6 text-center text-xs text-gray-400 dark:text-gray-500">{{ __('Mapa / tabela (em desenvolvimento)') }}</div>
    </div>
</div>
