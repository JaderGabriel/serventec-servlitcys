@props(['networkData', 'chartExportContext' => []])

<div class="space-y-6 network-offer-tab">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Vagas ociosas por turno, vagas por segmento e por escola, matrículas por série e escola — útil para planear expansão e uso da rede.') }}
    </p>

    @if (! empty($networkData['kpis']) && is_array($networkData['kpis']))
        @php
            $vk = $networkData['kpis'];
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-stretch">
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4 min-h-[6.25rem] flex flex-col justify-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Capacidade (turmas)') }}</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($vk['capacidade_total'] ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4 min-h-[6.25rem] flex flex-col justify-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Matrículas') }}</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($vk['matriculas'] ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4 min-h-[6.25rem] flex flex-col justify-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Vagas ociosas') }}</p>
                <p class="mt-1 text-xl font-semibold text-amber-700 dark:text-amber-300">{{ number_format((int) ($vk['vagas_ociosas'] ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4 min-h-[6.25rem] flex flex-col justify-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Taxa de ociosidade') }}</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">
                    @if (($vk['taxa_ociosidade_pct'] ?? null) !== null)
                        {{ number_format((float) $vk['taxa_ociosidade_pct'], 1, ',', '.') }}%
                    @else
                        —
                    @endif
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4 min-h-[6.25rem] flex flex-col justify-center">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Turmas c/ capacidade') }}</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($vk['turmas_com_capacidade'] ?? 0)) }}</p>
            </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Vagas ociosas = soma de max(alunos) − matrículas por turma (quando a coluna de capacidade existe na turma).') }}</p>
    @endif

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

    <div class="rounded-xl border border-amber-200/90 dark:border-amber-800/80 bg-gradient-to-b from-amber-50/90 to-white dark:from-amber-950/35 dark:to-gray-900/80 shadow-sm overflow-hidden">
        <div class="border-b border-amber-200/80 dark:border-amber-800/60 px-4 py-3 bg-amber-100/50 dark:bg-amber-950/40">
            <h3 class="text-base font-semibold text-amber-950 dark:text-amber-100">{{ __('Vagas ociosas por escola') }}</h3>
            <p class="mt-1 text-xs text-amber-900/85 dark:text-amber-200/90 leading-relaxed">
                {{ __('Barras horizontais por unidade: soma das vagas ociosas nas turmas (capacidade declarada − matrículas ativas), conforme os filtros. Só entram escolas com vagas > 0 no agregado.') }}
            </p>
        </div>
        <div class="p-3 sm:p-4 min-w-0">
            @if (! empty($networkData['vagas_por_unidade_chart']) && is_array($networkData['vagas_por_unidade_chart']))
                <x-dashboard.chart-panel
                    :chart="$networkData['vagas_por_unidade_chart']"
                    exportFilename="rede-oferta-vagas-unidade"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                    chartPanelId="chart-vagas-por-unidade-escola"
                />
            @else
                <div class="rounded-lg border border-dashed border-amber-300/80 dark:border-amber-700/60 bg-amber-50/50 dark:bg-amber-950/20 px-4 py-8 text-center text-sm text-amber-900 dark:text-amber-200/90 leading-relaxed">
                    {{ __('Não foi possível gerar o gráfico por escola neste filtro. É necessário coluna de capacidade na turma (ex.: max. alunos), matrículas ligadas às turmas e, no agregado, pelo menos uma escola com vagas ociosas > 0. Confirme também ano letivo e filtros de escola/curso/turno.') }}
                </div>
            @endif
        </div>
    </div>

    @if (! empty($networkData['charts']))
        <div class="grid grid-cols-1 gap-8">
            @foreach ($networkData['charts'] as $idx => $chart)
                <x-dashboard.chart-panel
                    :chart="$chart"
                    :exportFilename="'rede-oferta-'.$idx"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                />
            @endforeach
        </div>
    @elseif (empty($networkData['error']) && empty($networkData['vagas_por_unidade_chart'] ?? null))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem gráficos de rede para esta base ou filtros.') }}</p>
    @endif
</div>
