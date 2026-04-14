@props(['inclusionData', 'chartExportContext' => []])

@php
    $methodology = $inclusionData['methodology'] ?? [];
    $totalMat = $inclusionData['total_matriculas'] ?? null;
    $eqFonte = $inclusionData['equidade_fonte'] ?? null;
    $neeChartsCount = (int) ($inclusionData['nee_charts_count'] ?? 0);
    $aeeCross = $inclusionData['aee_cross'] ?? null;
    $eqLabel = match ($eqFonte) {
        'serie' => __('Série'),
        'curso' => __('Curso'),
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

    @if (! empty($inclusionData['charts']) || is_array($aeeCross))
        @if ($neeChartsCount > 0)
            <div class="mb-8">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">{{ __('NEE — cadastro (deficiências, síndromes e altas habilidades)') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Gráficos derivados de aluno_deficiência e do catálogo de deficiências; o detalhe por nome segue as designações registadas na base.') }}</p>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0">
                    @foreach (array_slice($inclusionData['charts'], 0, $neeChartsCount) as $idx => $chart)
                        <x-dashboard.chart-panel
                            :chart="$chart"
                            :exportFilename="'inclusao-nee-'.$idx"
                            :exportMeta="$chartExportContext"
                            :compact="false"
                        />
                    @endforeach
                </div>
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
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ __('Género, cor ou raça, distorção idade/série e série/curso') }}</h3>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 min-w-0">
                    @foreach (array_slice($inclusionData['charts'], $neeChartsCount) as $idx => $chart)
                        <x-dashboard.chart-panel
                            :chart="$chart"
                            :exportFilename="'inclusao-'.($neeChartsCount + $idx)"
                            :exportMeta="$chartExportContext"
                            :compact="false"
                        />
                    @endforeach
                </div>
            </div>
        @endif
    @elseif (empty($inclusionData['error']) && empty($inclusionData['charts']) && empty($inclusionData['gauges'] ?? []) && ! is_array($aeeCross))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem indicadores disponíveis para esta base ou filtros.') }}</p>
    @endif
</div>
