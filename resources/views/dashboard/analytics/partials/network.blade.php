@props(['networkData', 'chartExportContext' => []])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Vagas ociosas por turno, vagas por segmento e por escola, matrículas por série e escola — útil para planear expansão e uso da rede.') }}
    </p>

    @if (! empty($networkData['kpis']) && is_array($networkData['kpis']))
        @php
            $vk = $networkData['kpis'];
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Capacidade (turmas)') }}</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($vk['capacidade_total'] ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Matrículas') }}</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($vk['matriculas'] ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Vagas ociosas') }}</p>
                <p class="mt-1 text-xl font-semibold text-amber-700 dark:text-amber-300">{{ number_format((int) ($vk['vagas_ociosas'] ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Taxa de ociosidade') }}</p>
                <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">
                    @if (($vk['taxa_ociosidade_pct'] ?? null) !== null)
                        {{ number_format((float) $vk['taxa_ociosidade_pct'], 1, ',', '.') }}%
                    @else
                        —
                    @endif
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3">
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

    @if (! empty($networkData['charts']))
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @foreach ($networkData['charts'] as $idx => $chart)
                <x-dashboard.chart-panel
                    :chart="$chart"
                    :exportFilename="'rede-oferta-'.$idx"
                    :exportMeta="$chartExportContext"
                />
            @endforeach
        </div>
    @elseif (empty($networkData['error']))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem gráficos de rede para esta base ou filtros.') }}</p>
    @endif
</div>
