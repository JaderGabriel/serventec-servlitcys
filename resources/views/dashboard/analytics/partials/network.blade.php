@props(['networkData', 'chartExportContext' => []])

<div class="space-y-6 network-offer-tab">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Oferta por turno e segmento, distribuição de vagas por unidade e matrículas por série e escola — útil para planear expansão e uso da rede.') }}
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
                <p class="mt-1 text-xl font-semibold text-violet-700 dark:text-violet-300">{{ number_format((int) ($vk['vagas_ociosas'] ?? 0)) }}</p>
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
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Vagas ociosas = soma, por turma, de max(alunos) − matrículas ativas (quando a coluna de capacidade existe na turma).') }}</p>
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

    {{-- Cartão principal: distribuição de vagas (mesma lógica de dados que o gráfico PHP) --}}
    <div class="rounded-xl border border-violet-200/90 dark:border-violet-800/55 bg-gradient-to-br from-violet-50/95 via-white to-white dark:from-violet-950/35 dark:via-gray-900/90 dark:to-gray-900/95 shadow-sm overflow-hidden">
        <div class="border-b border-violet-200/75 dark:border-violet-800/45 bg-violet-100/45 dark:bg-violet-950/45 px-4 py-4 sm:px-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold uppercase tracking-[0.14em] text-violet-950 dark:text-violet-100">
                        {{ __('Distribuição de vagas na cidade') }}
                    </h3>
                    <p class="mt-2 text-xs text-violet-900/90 dark:text-violet-200/85 leading-relaxed">
                        {{ __('Compara, por unidade escolar, a capacidade declarada nas turmas com as matrículas ativas — no ano letivo e nos filtros de curso e turno. O território completo da rede é mostrado mesmo com filtro de escola noutros blocos.') }}
                    </p>
                </div>
                <div class="w-full shrink-0 rounded-lg border border-violet-200/85 dark:border-violet-700/45 bg-white/90 dark:bg-gray-900/55 px-3 py-3 sm:max-w-md lg:max-w-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-600 dark:text-violet-300">{{ __('Definição de dados') }}</p>
                    <ul class="mt-2 list-disc pl-4 text-[11px] leading-snug text-violet-900 dark:text-violet-100/95 space-y-1.5">
                        <li>{{ __('Por turma: vagas livres = capacidade máxima da turma menos matrículas ativas (limitadas à capacidade).') }}</li>
                        <li>{{ __('Somatório por escola; séries por curso quando a base o permite (barras agrupadas ou empilhadas).') }}</li>
                        <li>{{ __('Exige coluna de capacidade na turma (ex.: max. alunos) e matrículas ligadas às turmas.') }}</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="p-3 sm:p-4 min-w-0 bg-white/60 dark:bg-gray-900/20">
            @if (! empty($networkData['vagas_por_unidade_chart']) && is_array($networkData['vagas_por_unidade_chart']))
                <x-dashboard.chart-panel
                    :chart="$networkData['vagas_por_unidade_chart']"
                    exportFilename="rede-oferta-distribuicao-vagas-cidade"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                    chartPanelId="chart-distribuicao-vagas-cidade"
                    :suppressTitle="true"
                />
            @else
                <div class="rounded-lg border border-dashed border-violet-300/90 dark:border-violet-700/55 bg-violet-50/50 dark:bg-violet-950/25 px-4 py-8 text-center text-sm text-violet-950 dark:text-violet-100/90 leading-relaxed">
                    {{ __('Não foi possível gerar a distribuição por escola neste filtro. Confirme coluna de capacidade na turma, matrículas ligadas às turmas e, no agregado, pelo menos uma escola com vagas livres > 0. Verifique ano letivo e filtros de curso/turno.') }}
                </div>
            @endif
        </div>
    </div>

    @if (! empty($networkData['charts']))
        @php
            $pairKeyRede = 'rede-oferta-turno-segmento';
            $redeFragments = [];
            $ri = 0;
            $nRede = count($networkData['charts']);
            while ($ri < $nRede) {
                $ch = $networkData['charts'][$ri];
                $pid = $ch['pair_in_row'] ?? null;
                if ($pid === $pairKeyRede) {
                    $group = [$ch];
                    $ri++;
                    if ($ri < $nRede && (($networkData['charts'][$ri]['pair_in_row'] ?? null) === $pairKeyRede)) {
                        $group[] = $networkData['charts'][$ri];
                        $ri++;
                    }
                    $redeFragments[] = ['type' => 'pair', 'charts' => $group];
                } else {
                    $redeFragments[] = ['type' => 'single', 'charts' => [$ch]];
                    $ri++;
                }
            }
            $redeChartIdx = 0;
        @endphp
        <div class="grid grid-cols-1 gap-6">
            @foreach ($redeFragments as $frag)
                @if ($frag['type'] === 'pair' && count($frag['charts']) === 2)
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-stretch min-w-0">
                        @foreach ($frag['charts'] as $chart)
                            @php
                                $panelPayload = $chart;
                                unset($panelPayload['pair_in_row']);
                            @endphp
                            <x-dashboard.chart-panel
                                :chart="$panelPayload"
                                :exportFilename="'rede-oferta-'.$redeChartIdx"
                                :exportMeta="$chartExportContext"
                                :compact="true"
                                :chartPanelId="'chart-rede-'.$redeChartIdx"
                            />
                            @php $redeChartIdx++; @endphp
                        @endforeach
                    </div>
                @else
                    @foreach ($frag['charts'] as $chart)
                        @php
                            $panelPayload = $chart;
                            unset($panelPayload['pair_in_row']);
                        @endphp
                        <x-dashboard.chart-panel
                            :chart="$panelPayload"
                            :exportFilename="'rede-oferta-'.$redeChartIdx"
                            :exportMeta="$chartExportContext"
                            :compact="false"
                            :chartPanelId="'chart-rede-'.$redeChartIdx"
                        />
                        @php $redeChartIdx++; @endphp
                    @endforeach
                @endif
            @endforeach
        </div>
    @elseif (empty($networkData['error']) && empty($networkData['vagas_por_unidade_chart'] ?? null))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem gráficos de rede para esta base ou filtros.') }}</p>
    @endif
</div>
