@props(['inclusionData', 'chartExportContext' => []])

@php
    $methodology = $inclusionData['methodology'] ?? [];
    $totalMat = $inclusionData['total_matriculas'] ?? null;
    $eqFonte = $inclusionData['equidade_fonte'] ?? null;
    $neeChartsCount = (int) ($inclusionData['nee_charts_count'] ?? 0);
    $aeeCross = $inclusionData['aee_cross'] ?? null;
    $neeDetalheCatalogo = $inclusionData['nee_detalhe_catalogo'] ?? null;
    $hasNeeDetalheCatalogo = is_array($neeDetalheCatalogo)
        && (
            ! empty($neeDetalheCatalogo['deficiencias'])
            || ! empty($neeDetalheCatalogo['sindromes_tea'])
            || ! empty($neeDetalheCatalogo['ne_altas_habilidades'])
        );
    $neeGrupoResumo = is_array($inclusionData['nee_grupo_resumo'] ?? null) ? $inclusionData['nee_grupo_resumo'] : null;
    $neeGrupoResumoTotal = is_array($neeGrupoResumo)
        ? (int) (($neeGrupoResumo['deficiencias'] ?? 0) + ($neeGrupoResumo['sindromes_tea'] ?? 0) + ($neeGrupoResumo['ne_altas_habilidades'] ?? 0))
        : 0;
    $recursoProva = is_array($inclusionData['recurso_prova'] ?? null) ? $inclusionData['recurso_prova'] : null;
    $recursoSchema = is_array($recursoProva['schema'] ?? null) ? $recursoProva['schema'] : null;
    $recursoDisponivel = (bool) ($recursoSchema['available'] ?? false);
    $chartRacaPorEscolaStacked = is_array($inclusionData['chart_raca_por_escola_stacked'] ?? null) ? $inclusionData['chart_raca_por_escola_stacked'] : null;
    $chartNeePorRacaStacked = is_array($inclusionData['chart_nee_por_raca_stacked'] ?? null) ? $inclusionData['chart_nee_por_raca_stacked'] : null;
    $inclusionFiltersActive = is_array($inclusionData['inclusion_filters_active'] ?? null) ? $inclusionData['inclusion_filters_active'] : [];
    $neeMatriculasPorEscola = is_array($inclusionData['nee_matriculas_por_escola'] ?? null)
        ? $inclusionData['nee_matriculas_por_escola']
        : [];
    $eqLabel = match ($eqFonte) {
        'serie' => __('Série'),
        default => null,
    };
@endphp

<div class="space-y-6">
    <div class="rounded-lg border border-indigo-100 dark:border-indigo-900/40 bg-indigo-50/50 dark:bg-indigo-950/20 px-4 py-3">
        <h2 class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">{{ __('Inclusão & Diversidade') }}</h2>
        <p class="text-sm text-gray-700 dark:text-gray-300 mt-1 leading-relaxed">
            {{ __('Indicadores para acompanhar educação especial, equidade por etapa e cor ou raça, com o mesmo denominador de matrículas ativas sujeitas aos filtros (turma). Os dados refletem o registo na base escolar; para critérios oficiais do Censo ou do VAAR utilize os relatórios do INEP/MEC.') }}
        </p>
        <p class="mt-2 text-xs text-teal-800/90 dark:text-teal-200/90">
            {{ __('Consultoria municipal:') }}
            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'municipality_health')">{{ __('Diagnóstico Geral') }}</button>
            ·
            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Discrepâncias') }}</button>
            {{ __('(cadastro Censo/VAAR alinhado a esta aba).') }}
        </p>
        @if ($totalMat !== null)
            <p class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ __('Matrículas ativas no filtro (denominador comum):') }}
                <span class="tabular-nums text-indigo-700 dark:text-indigo-300">{{ number_format($totalMat) }}</span>
            </p>
        @endif
        @if ($eqLabel)
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                {{ __('Gráfico de equidade por etapa:') }} <span class="font-medium text-gray-800 dark:text-gray-200">{{ $eqLabel }}</span>
            </p>
        @endif
        @if ($inclusionFiltersActive !== [])
            <p class="mt-2 text-xs text-violet-800 dark:text-violet-200 bg-violet-50/80 dark:bg-violet-950/30 border border-violet-200/60 dark:border-violet-800/40 rounded-md px-3 py-2">
                <span class="font-semibold">{{ __('Filtros activos:') }}</span>
                {{ implode(' · ', $inclusionFiltersActive) }}
            </p>
        @else
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Filtros opcionais «Só NEE» e «Só inconsistências» na barra de filtros (com esta aba seleccionada).') }}
            </p>
        @endif
    </div>

    @if (! empty($methodology))
        <div class="rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800/50 px-4 py-3">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Referência metodológica') }}</h3>
            <ul class="mt-2 list-disc list-inside text-xs text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
                @foreach ($methodology as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($inclusionData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $inclusionData['error'] }}
        </div>
    @endif

    @if (! empty($inclusionData['notes']))
        <div class="rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 space-y-1.5 leading-relaxed">
            @foreach ($inclusionData['notes'] as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif

    @if ($recursoProva !== null)
        <div class="rounded-lg border border-teal-200/80 dark:border-teal-800/50 bg-white dark:bg-gray-900/40 px-4 py-4 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Recursos de prova INEP (Censo)') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                    {{ __('Distintos do cadastro de deficiência/NEE. O painel sinaliza cruzamentos para revisão pedagógica — ex.: óculos na prova sem baixa visão cadastrada pode ser legítimo.') }}
                </p>
                @if (! $recursoDisponivel)
                    <p class="mt-2 text-xs text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
                        {{ $recursoProva['schema_note'] ?? __('Tabela de recursos de prova não detectada nesta base. Configure IEDUCAR_TABLE_ALUNO_RECURSO_PROVA ou SQL personalizado.') }}
                    </p>
                @endif
            </div>
            @if ($recursoDisponivel)
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div class="rounded-md bg-teal-50/80 dark:bg-teal-950/30 border border-teal-200/60 dark:border-teal-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Com recurso de prova') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($recursoProva['com_recurso'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Recurso sem NEE') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($recursoProva['sem_nee'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-violet-50/80 dark:bg-violet-950/30 border border-violet-200/60 dark:border-violet-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('NEE sem recurso') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($recursoProva['nee_sem_recurso'] ?? 0)) }}</dd>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">
                            {{ __('Ver rotinas em Discrepâncias') }}
                        </button>
                    </div>
                </dl>
                @if (! empty($recursoProva['chart_catalogo']['labels']))
                    <div class="w-full min-w-0">
                        <x-dashboard.chart-panel
                            :chart="$recursoProva['chart_catalogo']"
                            :exportFilename="'inclusao-recursos-prova-catalogo'"
                            :exportMeta="$chartExportContext"
                            :compact="true"
                        />
                    </div>
                @endif
                @if (! empty($recursoProva['catalogo']))
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400 mb-2">{{ __('Catálogo de recursos (amostra)') }}</h4>
                        <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-600 max-h-48 overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800/95 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">{{ __('Recurso') }}</th>
                                        <th class="px-3 py-2 font-medium tabular-nums text-right">{{ __('Registos') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach (array_slice($recursoProva['catalogo'], 0, 15) as $row)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $row['nome'] ?? '—' }}</td>
                                            <td class="px-3 py-2 tabular-nums text-right text-gray-900 dark:text-gray-100">{{ number_format((int) ($row['total'] ?? 0)) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if (! empty($inclusionData['gauges']))
        <div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('Educação especial e multidiversidade') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Percentagem sobre matrículas ativas no filtro; valores calculados a partir do cadastro quando disponível.') }}</p>
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

    @if (! empty($neeMatriculasPorEscola))
        <div class="rounded-lg border border-violet-200/80 dark:border-violet-800/50 bg-white dark:bg-gray-900/40 px-4 py-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('Matrículas NEE por escola') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 leading-relaxed">
                {{ __('Matrículas ativas com registo em aluno_deficiência, por unidade escolar (ligação turma → escola ou, quando a turma não tem escola, pela FK de escola na matrícula). Ordenado por total decrescente.') }}
            </p>
            <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-600 max-h-[min(28rem,60vh)] overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800/95 text-left text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ __('Escola') }}</th>
                            <th class="px-3 py-2 font-medium tabular-nums text-right">{{ __('Matrículas NEE') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($neeMatriculasPorEscola as $row)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/50">
                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $row['nome'] ?? '—' }}</td>
                                <td class="px-3 py-2 tabular-nums text-right text-gray-900 dark:text-gray-100">{{ number_format((int) ($row['matriculas'] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! empty($inclusionData['charts']) || is_array($aeeCross) || $hasNeeDetalheCatalogo || is_array($chartRacaPorEscolaStacked) || is_array($chartNeePorRacaStacked) || ! empty($neeMatriculasPorEscola))
        @if ($neeChartsCount > 0 || $hasNeeDetalheCatalogo)
            <div class="mb-8">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('NEE — cadastro (deficiências, síndromes e altas habilidades)') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Gráficos derivados de aluno_deficiência (ou fisica_deficiência) e do catálogo de deficiências. Após o resumo por grupo, mostra-se o total de matrículas NEE por escola e, quando a base permite, segmentos empilhados por designação no catálogo. O detalhe em lista segue as designações registadas na base.') }}</p>
                @if ($neeGrupoResumo !== null && $neeGrupoResumoTotal > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4 items-stretch">
                        <div class="rounded-lg border border-violet-200/90 dark:border-violet-800/60 bg-white/90 dark:bg-gray-900/50 px-4 py-3 shadow-sm min-h-[11rem] flex flex-col">
                            <p class="text-[11px] font-medium uppercase text-violet-700 dark:text-violet-300">{{ __('Deficiências (grupo)') }}</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((int) ($neeGrupoResumo['deficiencias'] ?? 0)) }}</p>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 flex-1">{{ __('Matrículas distintas no critério do gráfico «Matrículas por grupo».') }}</p>
                        </div>
                        <div class="rounded-lg border border-violet-200/90 dark:border-violet-800/60 bg-white/90 dark:bg-gray-900/50 px-4 py-3 shadow-sm min-h-[11rem] flex flex-col">
                            <p class="text-[11px] font-medium uppercase text-violet-700 dark:text-violet-300">{{ __('Síndromes e TEA') }}</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((int) ($neeGrupoResumo['sindromes_tea'] ?? 0)) }}</p>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 flex-1">{{ __('Palavras-chave no nome da deficiência no catálogo.') }}</p>
                        </div>
                        <div class="rounded-lg border border-violet-200/90 dark:border-violet-800/60 bg-white/90 dark:bg-gray-900/50 px-4 py-3 shadow-sm min-h-[11rem] flex flex-col">
                            <p class="text-[11px] font-medium uppercase text-violet-700 dark:text-violet-300">{{ __('NE — altas habilidades') }}</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((int) ($neeGrupoResumo['ne_altas_habilidades'] ?? 0)) }}</p>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 flex-1">{{ __('Inclui superdotação quando configurado nas palavras-chave.') }}</p>
                        </div>
                    </div>
                @endif
                @if ($neeChartsCount > 0)
                    <div class="min-w-0 w-full [&_.chart-panel-host]:min-h-[min(32rem,70vh)]">
                        <x-dashboard.chart-panel
                            :chart="$inclusionData['charts'][0]"
                            :exportFilename="'inclusao-nee-0'"
                            :exportMeta="$chartExportContext"
                            :compact="false"
                        />
                    </div>
                @endif

                @if ($hasNeeDetalheCatalogo)
                    @php
                        $tot = $neeDetalheCatalogo['totais_por_secao'] ?? [];
                    @endphp

                    <div class="mt-6 rounded-lg border border-violet-100 dark:border-violet-900/40 bg-violet-50/40 dark:bg-violet-950/20 px-4 py-4 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Contagem por designação no catálogo (deficiências, síndromes/TEA e NE)') }}</h4>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                                {{ __('Cada linha corresponde a uma designação em cadastro; sem agrupamento em «Outros». A classificação em três blocos segue as mesmas palavras-chave do gráfico «Matrículas por grupo».') }}
                            </p>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 min-w-0 items-stretch">
                            <div class="rounded-md border border-white/80 dark:border-gray-700 bg-white/90 dark:bg-gray-800/70 min-h-[min(28rem,58vh)] flex flex-col shadow-sm">
                                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ __('Deficiências') }}</span>
                                    <span class="tabular-nums text-xs font-medium text-violet-700 dark:text-violet-300">{{ number_format((int) ($tot['deficiencias'] ?? 0)) }}</span>
                                </div>
                                <ul class="flex-1 max-h-[min(32rem,58vh)] min-h-[16rem] overflow-y-auto overscroll-y-contain pb-3 text-sm divide-y divide-gray-100 dark:divide-gray-700/80 [scrollbar-gutter:stable]">
                                    @foreach ($neeDetalheCatalogo['deficiencias'] as $row)
                                        <li class="px-3 py-1.5 flex justify-between gap-2">
                                            <span class="text-gray-800 dark:text-gray-200 break-words">{{ $row['nome'] ?? '—' }}</span>
                                            <span class="tabular-nums shrink-0 text-gray-900 dark:text-gray-100">{{ number_format((int) ($row['total'] ?? 0)) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="rounded-md border border-white/80 dark:border-gray-700 bg-white/90 dark:bg-gray-800/70 min-h-[min(20rem,46vh)] flex flex-col shadow-sm">
                                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ __('Síndromes / TEA') }}</span>
                                    <span class="tabular-nums text-xs font-medium text-violet-700 dark:text-violet-300">{{ number_format((int) ($tot['sindromes_tea'] ?? 0)) }}</span>
                                </div>
                                <ul class="flex-1 max-h-[min(24rem,46vh)] min-h-[11rem] overflow-y-auto overscroll-y-contain pb-3 text-sm divide-y divide-gray-100 dark:divide-gray-700/80 [scrollbar-gutter:stable]">
                                    @foreach ($neeDetalheCatalogo['sindromes_tea'] as $row)
                                        <li class="px-3 py-1.5 flex justify-between gap-2">
                                            <span class="text-gray-800 dark:text-gray-200 break-words">{{ $row['nome'] ?? '—' }}</span>
                                            <span class="tabular-nums shrink-0 text-gray-900 dark:text-gray-100">{{ number_format((int) ($row['total'] ?? 0)) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="rounded-md border border-white/80 dark:border-gray-700 bg-white/90 dark:bg-gray-800/70 min-h-[min(20rem,46vh)] flex flex-col shadow-sm">
                                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ __('NE (altas habilidades)') }}</span>
                                    <span class="tabular-nums text-xs font-medium text-violet-700 dark:text-violet-300">{{ number_format((int) ($tot['ne_altas_habilidades'] ?? 0)) }}</span>
                                </div>
                                <ul class="flex-1 max-h-[min(24rem,46vh)] min-h-[11rem] overflow-y-auto overscroll-y-contain pb-3 text-sm divide-y divide-gray-100 dark:divide-gray-700/80 [scrollbar-gutter:stable]">
                                    @foreach ($neeDetalheCatalogo['ne_altas_habilidades'] as $row)
                                        <li class="px-3 py-1.5 flex justify-between gap-2">
                                            <span class="text-gray-800 dark:text-gray-200 break-words">{{ $row['nome'] ?? '—' }}</span>
                                            <span class="tabular-nums shrink-0 text-gray-900 dark:text-gray-100">{{ number_format((int) ($row['total'] ?? 0)) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @if (! empty($neeDetalheCatalogo['footnote']))
                            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ $neeDetalheCatalogo['footnote'] }}</p>
                        @endif
                    </div>
                @endif

                @if ($neeChartsCount > 1)
                    @php
                        $neeTailCharts = array_slice($inclusionData['charts'], 1, $neeChartsCount - 1);
                        $neeTailCount = count($neeTailCharts);
                    @endphp
                    <div
                        class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0 mt-6 items-start [&>.chart-panel-host]:flex [&>.chart-panel-host]:flex-col [&>.chart-panel-host]:min-h-0"
                        @class([
                            '[&>.chart-panel-host:nth-last-child(-n+2)]:max-h-[min(30rem,72vh)] [&>.chart-panel-host:nth-last-child(-n+2)]:overflow-hidden' => $neeTailCount >= 2,
                        ])
                    >
                        @foreach ($neeTailCharts as $idx => $chart)
                            <x-dashboard.chart-panel
                                :chart="$chart"
                                :exportFilename="'inclusao-nee-'.($idx + 1)"
                                :exportMeta="$chartExportContext"
                                :compact="false"
                            />
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if (is_array($aeeCross))
            <div class="rounded-lg border border-amber-100 dark:border-amber-900/40 bg-amber-50/40 dark:bg-amber-950/15 px-4 py-4 space-y-4 mb-8">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('AEE e outras matrículas (alunos NEE)') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                        {{ __('Apenas alunos com registo em aluno_deficiência. Turmas AEE são identificadas por termos no nome da turma ou do curso. As outras matrículas do mesmo aluno são agrupadas por segmento de forma heurística.') }}
                    </p>
                </div>
                @if (! empty($aeeCross['note']))
                    <p class="text-xs text-amber-900 dark:text-amber-200/90 leading-relaxed">{{ $aeeCross['note'] }}</p>
                @endif
                <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-sm">
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Matrículas NEE (total)') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($aeeCross['nee_matriculas_total'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Matrículas em turmas AEE') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($aeeCross['matriculas_em_turmas_aee'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Alunos com pelo menos uma turma AEE') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($aeeCross['alunos_com_aee'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Alunos AEE também noutro segmento') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($aeeCross['alunos_nee_com_aee_e_outro_segmento'] ?? 0)) }}</dd>
                    </div>
                </dl>
                @if (! empty($aeeCross['matriculas_fora_aee_por_segmento']))
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400 mb-2">{{ __('Matrículas fora de AEE (por segmento), só para alunos que também têm AEE') }}</h4>
                        <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-600">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/80 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">{{ __('Segmento') }}</th>
                                        <th class="px-3 py-2 font-medium tabular-nums">{{ __('Matrículas') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($aeeCross['matriculas_fora_aee_por_segmento'] as $row)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $row['segmento'] ?? '—' }}</td>
                                            <td class="px-3 py-2 tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((int) ($row['matriculas'] ?? 0)) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @php
            $tailAfterNee = array_slice($inclusionData['charts'] ?? [], $neeChartsCount);
        @endphp
        @if (count($tailAfterNee) > 0 || is_array($chartRacaPorEscolaStacked) || is_array($chartNeePorRacaStacked))
            <div class="space-y-6">
                @if (is_array($chartNeePorRacaStacked) && ! empty($chartNeePorRacaStacked['labels']))
                    <div class="w-full min-w-0">
                        <x-dashboard.chart-panel
                            :chart="$chartNeePorRacaStacked"
                            :exportFilename="'inclusao-nee-por-raca-empilhado'"
                            :exportMeta="$chartExportContext"
                            :compact="false"
                        />
                    </div>
                @endif
                @if (count($tailAfterNee) >= 1)
                    {{-- Sexo e cor/raça: mesma altura de cartão em ecrã largo --}}
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0 items-stretch [&>div]:flex [&>div]:flex-col [&>div]:min-h-0 xl:[&>div]:min-h-[min(32rem,75vh)] [&_.chart-panel-host]:flex-1 [&_.chart-panel-host]:flex [&_.chart-panel-host]:flex-col [&_.chart-panel-host]:min-h-0">
                        @foreach (array_slice($tailAfterNee, 0, 2) as $idx => $chart)
                            <div class="{{ ! empty($chart['panel_layout']) && $chart['panel_layout'] === 'full' ? 'xl:col-span-2' : '' }} min-w-0 flex flex-col h-full min-h-0">
                                <x-dashboard.chart-panel
                                    :chart="$chart"
                                    :exportFilename="'inclusao-'.($neeChartsCount + $idx)"
                                    :exportMeta="$chartExportContext"
                                    :compact="! empty($chart['compact_panel'])"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif
                @if (is_array($chartRacaPorEscolaStacked) && ! empty($chartRacaPorEscolaStacked['labels']))
                    <div class="w-full min-w-0">
                        <x-dashboard.chart-panel
                            :chart="$chartRacaPorEscolaStacked"
                            :exportFilename="'inclusao-raca-por-escola-empilhado'"
                            :exportMeta="$chartExportContext"
                            :compact="false"
                        />
                    </div>
                @endif
                @if (count($tailAfterNee) > 2)
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0">
                        @foreach (array_slice($tailAfterNee, 2) as $idx => $chart)
                            <div class="{{ ! empty($chart['panel_layout']) && $chart['panel_layout'] === 'full' ? 'xl:col-span-2' : '' }} min-w-0">
                                <x-dashboard.chart-panel
                                    :chart="$chart"
                                    :exportFilename="'inclusao-'.($neeChartsCount + 2 + $idx)"
                                    :exportMeta="$chartExportContext"
                                    :compact="! empty($chart['compact_panel'])"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @elseif (empty($inclusionData['error']) && empty($inclusionData['charts']) && empty($inclusionData['gauges'] ?? []) && ! is_array($aeeCross) && ! $hasNeeDetalheCatalogo && ! is_array($chartRacaPorEscolaStacked) && empty($neeMatriculasPorEscola))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem indicadores disponíveis para esta base ou filtros.') }}</p>
    @endif
</div>
