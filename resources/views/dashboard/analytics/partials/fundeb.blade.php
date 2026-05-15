@props(['fundebData', 'yearFilterReady' => false, 'chartExportContext' => []])

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo (ou «Todos os anos») e aplique os filtros para gerar o relatório FUNDEB com base nos dados do i-Educar.') }}
        </p>
    @else
        <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-950/25 px-4 py-3 text-sm text-indigo-950 dark:text-indigo-100 space-y-2">
            <p class="font-semibold">{{ __('FUNDEB — condicionalidades e situação municipal') }}</p>
            <p class="leading-relaxed text-indigo-900/95 dark:text-indigo-200/95">{{ $fundebData['intro'] ?? '' }}</p>
            <p class="text-xs text-indigo-800/90 dark:text-indigo-300/90">
                <span class="font-medium">{{ __('Contexto') }}:</span>
                {{ $fundebData['city_name'] ?? '' }}
                @if (filled($fundebData['year_label'] ?? null))
                    — {{ $fundebData['year_label'] }}
                @endif
            </p>
        </div>

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $fundebData['footnote'] ?? '' }}
        </p>

        <p class="text-xs text-teal-800/90 dark:text-teal-200/90 border border-teal-200/80 dark:border-teal-800/60 rounded-md px-3 py-2">
            {{ __('Consultoria municipal:') }}
            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'municipality_health')">{{ __('Diagnóstico Geral') }}</button>
            ·
            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Discrepâncias e erros de cadastro') }}</button>
            {{ __('(impacto financeiro indicativo e rotinas Censo).') }}
        </p>

        @php
            $publicSources = is_array($fundebData['public_data_sources'] ?? null) ? $fundebData['public_data_sources'] : [];
            $proj = is_array($fundebData['resource_projection'] ?? null) ? $fundebData['resource_projection'] : [];
            $projAvailable = (bool) ($proj['available'] ?? false);
            $distLegal = is_array($proj['distribuicao_legal'] ?? null) ? $proj['distribuicao_legal'] : [];
            $distItens = is_array($distLegal['itens'] ?? null) ? $distLegal['itens'] : [];
            $porEtapa = is_array($proj['por_etapa'] ?? null) ? $proj['por_etapa'] : [];
        @endphp
        @if (count($publicSources['categories'] ?? []) > 0)
            <x-dashboard.consultoria-public-sources
                :catalog="$publicSources"
                anchor="fundeb-fontes-publicas"
            />
        @endif

        <section id="fundeb-previsao-recursos" class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20 shadow-sm overflow-hidden">
            <header class="px-4 py-3 border-b border-emerald-200/80 dark:border-emerald-800/80">
                <h3 class="text-base font-semibold text-emerald-950 dark:text-emerald-100">{{ __('Previsão de recursos e distribuição legal') }}</h3>
                <p class="text-xs text-emerald-900/90 dark:text-emerald-200/90 mt-1 leading-relaxed">
                    {{ __('Estimativa anual com base nas matrículas ativas do filtro e nos pisos mínimos de aplicação do FUNDEB (Lei nº 14.113/2020). Valores indicativos para planejamento — não substituem repasse do FNDE nem prestação de contas.') }}
                </p>
            </header>
            <div class="px-4 py-4 space-y-4">
                @if (! $projAvailable)
                    <p class="text-sm text-amber-800 dark:text-amber-200">{{ $proj['formula_base'] ?? __('Sem matrículas no filtro para calcular a previsão.') }}</p>
                @else
                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ $proj['formula_base'] ?? '' }}</p>
                    @if (filled($proj['vaa_fonte_label'] ?? null))
                        <p class="text-xs text-indigo-800 dark:text-indigo-200 bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200/60 dark:border-indigo-800/50 rounded-md px-3 py-2">
                            {{ __('Fonte do VAAF:') }} {{ $proj['vaa_fonte_label'] }}
                            @if (filled($proj['vaa_ano'] ?? null))
                                · {{ __('ano :y', ['y' => $proj['vaa_ano']]) }}
                            @endif
                        </p>
                    @endif

                    @if (filled($proj['aviso'] ?? null))
                        <p class="text-xs text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-600 rounded-md px-3 py-2">{{ $proj['aviso'] }}</p>
                    @endif

                    @if (count($proj['kpis'] ?? []) > 0)
                        <x-dashboard.consultoria-kpi-grid :items="$proj['kpis']" />
                        <x-dashboard.consultoria-funding-explanation
                            :metodologia="\App\Support\Ieducar\DiscrepanciesFundingImpact::metodologiaResumo()"
                            class="mt-2"
                        />
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @if (! empty($proj['chart_previsao']))
                            <x-dashboard.chart-panel
                                :chart="$proj['chart_previsao']"
                                exportFilename="fundeb-previsao-cenarios"
                                :exportMeta="$chartExportContext"
                                :compact="true"
                                chartPanelId="chart-fundeb-previsao"
                                panelTone="emerald"
                            />
                        @endif
                        @if (! empty($proj['chart_distribuicao']))
                            <x-dashboard.chart-panel
                                :chart="$proj['chart_distribuicao']"
                                exportFilename="fundeb-distribuicao-legal"
                                :exportMeta="$chartExportContext"
                                :compact="true"
                                chartPanelId="chart-fundeb-distribuicao"
                                panelTone="teal"
                            />
                        @endif
                    </div>

                    @if (count($distItens) > 0)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Pisos legais de aplicação (sobre previsão base :total)', ['total' => $distLegal['total_base_label'] ?? '']) }}</p>
                            <p class="text-[11px] text-gray-600 dark:text-gray-400 mb-3">{{ $distLegal['referencia_legal'] ?? '' }}</p>
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Finalidade') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Piso') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Valor mínimo (ano)') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900/30">
                                        @foreach ($distItens as $item)
                                            <tr>
                                                <td class="px-3 py-2.5">
                                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item['titulo'] ?? '' }}</p>
                                                    @if (filled($item['descricao'] ?? null))
                                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ $item['descricao'] }}</p>
                                                    @endif
                                                    @if (filled($item['nota'] ?? null))
                                                        <p class="text-[11px] text-indigo-700 dark:text-indigo-300 mt-0.5">{{ $item['nota'] }}</p>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $item['percentual_label'] ?? '' }}</td>
                                                <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $item['valor_label'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if (filled($distLegal['nota'] ?? null))
                                <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-2">{{ $distLegal['nota'] }}</p>
                            @endif
                        </div>
                    @endif

                    @if (count($porEtapa) > 0)
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Participação por nível de ensino (previsão base)') }}</p>
                            @if (! empty($proj['chart_etapa']))
                                <x-dashboard.chart-panel
                                    :chart="$proj['chart_etapa']"
                                    exportFilename="fundeb-previsao-etapa"
                                    :exportMeta="$chartExportContext"
                                    :compact="true"
                                    chartPanelId="chart-fundeb-etapa"
                                    panelTone="indigo"
                                />
                            @endif
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Nível') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Matrículas') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('% rede') }}</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('FUNDEB base') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($porEtapa as $row)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $row['etapa'] ?? '' }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) ($row['matriculas'] ?? 0), 0, ',', '.') }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($row['participacao_pct'] ?? 0), 1, ',', '.') }}%</td>
                                                <td class="px-3 py-2 text-right tabular-nums font-medium">{{ $row['fundeb_label'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <p class="text-xs">
                        <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Ver impacto de cadastro em Discrepâncias e erros') }}</button>
                    </p>
                @endif
            </div>
        </section>

        <div class="space-y-5">
            @foreach ($fundebData['modules'] ?? [] as $mod)
                @php
                    $ring = match ($mod['status'] ?? 'neutral') {
                        'success' => 'border-l-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20',
                        'warning' => 'border-l-amber-500 bg-amber-50/40 dark:bg-amber-950/20',
                        'danger' => 'border-l-red-500 bg-red-50/40 dark:bg-red-950/20',
                        default => 'border-l-slate-400 bg-slate-50/50 dark:bg-slate-900/30',
                    };
                    $badge = match ($mod['status'] ?? 'neutral') {
                        'success' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-100',
                        'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                        'danger' => 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
                        default => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100',
                    };
                    $badgeLabel = match ($mod['status'] ?? 'neutral') {
                        'success' => __('Dados locais favoráveis'),
                        'warning' => __('Atenção / parcial'),
                        'danger' => __('Lacuna na base ou erro'),
                        default => __('Comprovar fora do i-Educar'),
                    };
                @endphp
                <article class="rounded-lg border border-gray-200 dark:border-gray-700 border-l-4 {{ $ring }} shadow-sm overflow-hidden">
                    <header class="px-4 py-3 border-b border-gray-200/80 dark:border-gray-600/80 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $mod['title'] ?? '' }}</h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ $mod['reference'] ?? '' }}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium {{ $badge }}">{{ $badgeLabel }}</span>
                    </header>
                    <div class="px-4 py-3 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('O que este módulo cobre') }}</p>
                            <p class="leading-relaxed">{{ $mod['explanation'] ?? '' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('Situação com base no filtro atual (i-Educar)') }}</p>
                            <p class="leading-relaxed">{{ $mod['situacao'] ?? '' }}</p>
                        </div>
                        @if (! empty($mod['evidencias']))
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('Evidências no painel') }}</p>
                                <ul class="list-disc list-inside space-y-1 text-sm">
                                    @foreach ($mod['evidencias'] as $ev)
                                        <li>{{ $ev }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (! empty($mod['gaps']))
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300 mb-1">{{ __('Pontos a verificar ou lacunas') }}</p>
                                <ul class="list-disc list-inside space-y-1 text-sm text-gray-800 dark:text-gray-200">
                                    @foreach ($mod['gaps'] as $gap)
                                        <li>{{ $gap }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
