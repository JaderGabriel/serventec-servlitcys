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
    $eqLabel = match ($eqFonte) {
        'serie' => __('Série'),
        default => null,
    };
@endphp

<div class="space-y-6">
    <div class="rounded-lg border border-indigo-100 dark:border-indigo-900/40 bg-indigo-50/50 dark:bg-indigo-950/20 px-4 py-3">
        <h2 class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">{{ __('Inclusão & Diversidade') }}</h2>
        <p class="text-sm text-gray-700 dark:text-gray-300 mt-1 leading-relaxed">
            {{ __('Indicadores para acompanhar educação especial, equidade por etapa e cor ou raça, com o mesmo denominador de matrículas ativas sujeitas aos filtros (turma). Os dados seguem o registo na base i-Educar; para regras oficiais do Censo ou do VAAR use os relatórios do INEP/MEC ou SQL personalizado em config/ieducar.php.') }}
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

    @if (! empty($inclusionData['gauges']))
        <div>
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('Educação especial e multidiversidade') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Percentagem sobre matrículas ativas no filtro; prioridade a SQL em IEDUCAR_SQL_INCLUSION_GAUGE_*.') }}</p>
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

    @if (! empty($inclusionData['charts']) || is_array($aeeCross) || $hasNeeDetalheCatalogo)
        @if ($neeChartsCount > 0 || $hasNeeDetalheCatalogo)
            <div class="mb-8">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('NEE — cadastro (deficiências, síndromes e altas habilidades)') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Gráficos derivados de aluno_deficiência e do catálogo de deficiências; o detalhe por nome segue as designações registadas na base.') }}</p>
                @if ($neeChartsCount > 0)
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0">
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

                    {{-- Cards adicionais: matrículas por deficiência / síndromes / altas habilidades (quando houver lista detalhada) --}}
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 min-w-0 mt-6">
                        @php
                            $mkChart = function (string $title, array $rows): array {
                                $labels = [];
                                $values = [];
                                foreach (array_slice($rows, 0, 14) as $r) {
                                    $labels[] = (string) ($r['nome'] ?? '—');
                                    $values[] = (float) ((int) ($r['total'] ?? 0));
                                }
                                return \App\Support\Dashboard\ChartPayload::barHorizontal($title, __('Matrículas'), $labels, $values);
                            };
                        @endphp
                        @if (! empty($neeDetalheCatalogo['deficiencias']))
                            <x-dashboard.chart-panel
                                :chart="$mkChart(__('Matrículas — deficiências (top 14)'), $neeDetalheCatalogo['deficiencias'])"
                                :exportFilename="'inclusao-nee-deficiencias'"
                                :exportMeta="$chartExportContext"
                                :compact="false"
                            />
                        @endif
                        @if (! empty($neeDetalheCatalogo['sindromes_tea']))
                            <x-dashboard.chart-panel
                                :chart="$mkChart(__('Matrículas — síndromes/TEA (top 14)'), $neeDetalheCatalogo['sindromes_tea'])"
                                :exportFilename="'inclusao-nee-sindromes-tea'"
                                :exportMeta="$chartExportContext"
                                :compact="false"
                            />
                        @endif
                        @if (! empty($neeDetalheCatalogo['ne_altas_habilidades']))
                            <x-dashboard.chart-panel
                                :chart="$mkChart(__('Matrículas — altas habilidades (top 14)'), $neeDetalheCatalogo['ne_altas_habilidades'])"
                                :exportFilename="'inclusao-nee-altas-habilidades'"
                                :exportMeta="$chartExportContext"
                                :compact="false"
                            />
                        @endif
                    </div>

                    <div class="mt-6 rounded-lg border border-violet-100 dark:border-violet-900/40 bg-violet-50/40 dark:bg-violet-950/20 px-4 py-4 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Contagem por designação no catálogo (deficiências, síndromes/TEA e NE)') }}</h4>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                                {{ __('Cada linha corresponde a uma designação em cadastro; sem agrupamento em «Outros». A classificação em três blocos segue as mesmas palavras-chave do gráfico «Matrículas por grupo».') }}
                            </p>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 min-w-0">
                            <div class="rounded-md border border-white/80 dark:border-gray-700 bg-white/90 dark:bg-gray-800/70 min-h-0 flex flex-col">
                                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ __('Deficiências') }}</span>
                                    <span class="tabular-nums text-xs font-medium text-violet-700 dark:text-violet-300">{{ number_format((int) ($tot['deficiencias'] ?? 0)) }}</span>
                                </div>
                                <ul class="max-h-[min(32rem,58vh)] min-h-[12rem] overflow-y-auto overscroll-y-contain pb-3 text-sm divide-y divide-gray-100 dark:divide-gray-700/80 [scrollbar-gutter:stable]">
                                    @foreach ($neeDetalheCatalogo['deficiencias'] as $row)
                                        <li class="px-3 py-1.5 flex justify-between gap-2">
                                            <span class="text-gray-800 dark:text-gray-200 break-words">{{ $row['nome'] ?? '—' }}</span>
                                            <span class="tabular-nums shrink-0 text-gray-900 dark:text-gray-100">{{ number_format((int) ($row['total'] ?? 0)) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="rounded-md border border-white/80 dark:border-gray-700 bg-white/90 dark:bg-gray-800/70 min-h-0 flex flex-col">
                                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ __('Síndromes / TEA') }}</span>
                                    <span class="tabular-nums text-xs font-medium text-violet-700 dark:text-violet-300">{{ number_format((int) ($tot['sindromes_tea'] ?? 0)) }}</span>
                                </div>
                                <ul class="max-h-[min(32rem,58vh)] min-h-[12rem] overflow-y-auto overscroll-y-contain pb-3 text-sm divide-y divide-gray-100 dark:divide-gray-700/80 [scrollbar-gutter:stable]">
                                    @foreach ($neeDetalheCatalogo['sindromes_tea'] as $row)
                                        <li class="px-3 py-1.5 flex justify-between gap-2">
                                            <span class="text-gray-800 dark:text-gray-200 break-words">{{ $row['nome'] ?? '—' }}</span>
                                            <span class="tabular-nums shrink-0 text-gray-900 dark:text-gray-100">{{ number_format((int) ($row['total'] ?? 0)) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="rounded-md border border-white/80 dark:border-gray-700 bg-white/90 dark:bg-gray-800/70 min-h-0 flex flex-col">
                                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ __('NE (altas habilidades)') }}</span>
                                    <span class="tabular-nums text-xs font-medium text-violet-700 dark:text-violet-300">{{ number_format((int) ($tot['ne_altas_habilidades'] ?? 0)) }}</span>
                                </div>
                                <ul class="max-h-[min(32rem,58vh)] min-h-[12rem] overflow-y-auto overscroll-y-contain pb-3 text-sm divide-y divide-gray-100 dark:divide-gray-700/80 [scrollbar-gutter:stable]">
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
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0 mt-6">
                        @foreach (array_slice($inclusionData['charts'], 1, $neeChartsCount - 1) as $idx => $chart)
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
                        {{ __('Apenas alunos com registo em aluno_deficiência. Turmas AEE são detectadas por palavras-chave no nome da turma ou do curso (config/ieducar.php — inclusão). As outras matrículas do mesmo aluno são agrupadas por segmento de forma heurística.') }}
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

        @if (count($inclusionData['charts']) > $neeChartsCount)
            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ __('Por escola, género, raça e equidade') }}</h3>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0">
                    @foreach (array_slice($inclusionData['charts'], $neeChartsCount) as $idx => $chart)
                        <div class="{{ ! empty($chart['panel_layout']) && $chart['panel_layout'] === 'full' ? 'xl:col-span-2' : '' }} min-w-0">
                            <x-dashboard.chart-panel
                                :chart="$chart"
                                :exportFilename="'inclusao-'.($neeChartsCount + $idx)"
                                :exportMeta="$chartExportContext"
                                :compact="! empty($chart['compact_panel'])"
                            />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @elseif (empty($inclusionData['error']) && empty($inclusionData['charts']) && empty($inclusionData['gauges'] ?? []) && ! is_array($aeeCross) && ! $hasNeeDetalheCatalogo)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem indicadores disponíveis para esta base ou filtros.') }}</p>
    @endif
</div>
