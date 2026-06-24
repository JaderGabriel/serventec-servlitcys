@props(['inclusionData', 'chartExportContext' => [], 'municipalityContext' => null, 'yearFilterReady' => true, 'selectedCity' => null, 'filters' => null])

@php
    $chartsById = [];
    foreach ($inclusionData['charts'] ?? [] as $chartItem) {
        if (is_array($chartItem) && filled($chartItem['chart_id'] ?? null)) {
            $chartsById[(string) $chartItem['chart_id']] = $chartItem;
        }
    }
    $neeIndicators = is_array($inclusionData['nee_indicators'] ?? null) ? $inclusionData['nee_indicators'] : null;
    $totalMat = $inclusionData['total_matriculas'] ?? null;
    $calcNotes = is_array($inclusionData['calc_notes'] ?? null) ? $inclusionData['calc_notes'] : [];
    $tabMeta = is_array($inclusionData['tab_meta'] ?? null) ? $inclusionData['tab_meta'] : [];
    $calcNote = static fn (string $key): ?array => isset($calcNotes[$key]) && is_array($calcNotes[$key]) ? $calcNotes[$key] : null;
    $eqFonte = $inclusionData['equidade_fonte'] ?? null;
    $neeChartsCount = (int) ($inclusionData['nee_charts_count'] ?? 0);
    $matriculasNee = isset($inclusionData['matriculas_nee']) ? (int) $inclusionData['matriculas_nee'] : null;
    $neeExtraCharts = [];
    foreach (array_slice($inclusionData['charts'] ?? [], 0, $neeChartsCount) as $chartItem) {
        if (! is_array($chartItem)) {
            continue;
        }
        $chartId = (string) ($chartItem['chart_id'] ?? '');
        if (in_array($chartId, ['nee_grupo', 'nee_catalogo', 'nee_catalogo_mec'], true)) {
            continue;
        }
        $neeExtraCharts[] = $chartItem;
    }
    $aeeCross = $inclusionData['aee_cross'] ?? null;
    $neeDetalheCatalogo = $inclusionData['nee_detalhe_catalogo'] ?? null;
    $recursoProva = is_array($inclusionData['recurso_prova'] ?? null) ? $inclusionData['recurso_prova'] : null;
    $recursoSchema = is_array($recursoProva['schema'] ?? null) ? $recursoProva['schema'] : null;
    $recursoDisponivel = (bool) ($recursoSchema['available'] ?? false);
    $recursoInconsistencias = is_array($recursoProva['inconsistencias'] ?? null) ? $recursoProva['inconsistencias'] : null;
    $recursoLinhasInconsistencia = is_array($recursoInconsistencias['linhas'] ?? null) ? $recursoInconsistencias['linhas'] : [];
    $recursoLimiteInconsistencia = (int) ($recursoInconsistencias['limite'] ?? 150);
    $chartRacaPorEscolaStacked = is_array($inclusionData['chart_raca_por_escola_stacked'] ?? null) ? $inclusionData['chart_raca_por_escola_stacked'] : null;
    $chartNeePorRacaStacked = is_array($inclusionData['chart_nee_por_raca_stacked'] ?? null) ? $inclusionData['chart_nee_por_raca_stacked'] : null;
    $neeMatriculasPorEscola = is_array($inclusionData['nee_matriculas_por_escola'] ?? null)
        ? $inclusionData['nee_matriculas_por_escola']
        : [];
    $fundebNee = is_array($inclusionData['fundeb_nee'] ?? null) ? $inclusionData['fundeb_nee'] : [];
    $riscoAeeSemCadastro = is_array($fundebNee['risco_aee_sem_cadastro'] ?? null)
        ? $fundebNee['risco_aee_sem_cadastro']
        : [];
@endphp

<div class="space-y-6">
    @include('dashboard.analytics.partials.tab-impact-strip', [
        'tab' => 'inclusion',
        'yearFilterReady' => $yearFilterReady,
        'municipalityContext' => $municipalityContext,
        'tabData' => ['inclusionData' => $inclusionData],
    ])

    @if ($filters !== null)
        @include('dashboard.analytics.partials.inclusion-scope', ['filters' => $filters])
    @endif

    @if (! empty($inclusionData['inclusion_filters_active'] ?? []))
        <div class="rounded-md border border-violet-300/80 dark:border-violet-700/50 bg-violet-100/40 dark:bg-violet-950/40 px-4 py-2.5 text-xs text-violet-950 dark:text-violet-100">
            <span class="font-semibold">{{ __('Recorte NEE ativo nesta visualização:') }}</span>
            {{ implode(' · ', $inclusionData['inclusion_filters_active']) }}
        </div>
    @endif

    <x-dashboard.serv-tab-intro :title="__('Inclusão e educação especial (NEE)')" tone="blue">
        {{ $inclusionData['intro'] ?? __('Matrículas NEE, cadastro de deficiências, turmas AEE e recursos de prova INEP no recorte dos filtros.') }}
        @if (count($tabMeta) > 0)
            <x-slot name="meta">
                {{ implode(' · ', $tabMeta) }}
            </x-slot>
        @endif
    </x-dashboard.serv-tab-intro>

    <p class="serv-callout text-sm">
        {{ __('Aprofundar:') }}
        <button type="button" class="text-sky-600 dark:text-sky-400 hover:underline font-medium" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Discrepâncias') }}</button>
        {{ __('(rotinas Censo/VAAR)') }}
        ·
        <button type="button" class="text-sky-600 dark:text-sky-400 hover:underline font-medium" x-on:click="$dispatch('set-analytics-tab', 'municipality_health')">{{ __('Serventec') }}</button>
        {{ __('(diagnóstico municipal)') }}
    </p>

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
        <div class="rounded-lg border border-blue-200/80 dark:border-blue-800/50 bg-white dark:bg-gray-900/40 px-4 py-4 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Recursos de prova INEP (Censo)') }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ __('Conferência cadastral: apoios de prova e turmas AEE sem deficiência registada.') }}</p>
                @if (! $recursoDisponivel)
                    <p class="mt-2 text-xs text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
                        {{ $recursoProva['schema_note'] ?? __('Tabela de recursos de prova não detectada nesta base. Configure IEDUCAR_TABLE_ALUNO_RECURSO_PROVA ou SQL personalizado.') }}
                    </p>
                @endif
            </div>
            @if ($recursoDisponivel)
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div class="rounded-md bg-blue-50/80 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Com recurso de prova') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($recursoProva['com_recurso'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-rose-50/80 dark:bg-rose-950/30 border border-rose-200/60 dark:border-rose-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Turma AEE sem deficiência no cadastro') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($recursoProva['aee_sem_cadastro_nee'] ?? 0)) }}</dd>
                        @if (($riscoAeeSemCadastro['available'] ?? false) && (int) ($recursoProva['aee_sem_cadastro_nee'] ?? 0) > 0)
                            <dd class="mt-1 text-[11px] text-rose-800 dark:text-rose-200 leading-snug">
                                {{ __('Perda indicativa ≈ :v/ano', ['v' => (string) ($riscoAeeSemCadastro['perda_anual_fmt'] ?? '—')]) }}
                            </dd>
                        @endif
                    </div>
                    <div class="rounded-md bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Recurso SAEB/INEP sem deficiência no cadastro') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($recursoProva['sem_nee'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-violet-50/80 dark:bg-violet-950/30 border border-violet-200/60 dark:border-violet-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('NEE no cadastro sem recurso de prova') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($recursoProva['nee_sem_recurso'] ?? 0)) }}</dd>
                    </div>
                </dl>
                @if ($recursoLinhasInconsistencia !== [])
                    <div class="rounded-lg border border-amber-200/70 dark:border-amber-800/50 bg-amber-50/40 dark:bg-amber-950/20 px-4 py-3 space-y-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Inconsistências (nome do aluno)') }}</h4>
                            @if (($recursoInconsistencias['truncado']['aee_sem_cadastro_nee'] ?? false) || ($recursoInconsistencias['truncado']['recurso_prova_sem_nee'] ?? false))
                                <p class="mt-2 text-xs text-amber-900 dark:text-amber-100">
                                    {{ __('Listagem limitada a :n linhas por tipo — use Discrepâncias para o universo completo.', ['n' => number_format($recursoLimiteInconsistencia)]) }}
                                </p>
                            @endif
                        </div>
                        <div class="overflow-x-auto rounded-md border border-amber-200/80 dark:border-amber-800/60 max-h-[min(28rem,55vh)] overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-amber-50 dark:bg-amber-950/90 text-left text-xs uppercase text-gray-600 dark:text-gray-400 border-b border-amber-200 dark:border-amber-800">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">{{ __('Aluno') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Escola') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Tipo') }}</th>
                                        <th class="px-3 py-2 font-medium min-w-[14rem]">{{ __('Detalhe') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-amber-100/80 dark:divide-amber-900/50 bg-white/80 dark:bg-gray-900/40">
                                    @foreach ($recursoLinhasInconsistencia as $row)
                                        <tr class="hover:bg-amber-50/60 dark:hover:bg-amber-950/30">
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100 font-medium">{{ $row['nome'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['escola'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['tipo_label'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs leading-relaxed">{{ $row['detalhe'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ((int) ($recursoProva['aee_sem_cadastro_nee'] ?? 0) === 0 && (int) ($recursoProva['sem_nee'] ?? 0) === 0)
                    <p class="text-xs text-emerald-800 dark:text-emerald-200 bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-md px-3 py-2">
                        {{ __('Nenhuma inconsistência deste tipo no filtro actual — cadastro AEE e recursos de prova alinhados à verificação automática.') }}
                    </p>
                @endif
                @if (($riscoAeeSemCadastro['available'] ?? false) && (int) ($riscoAeeSemCadastro['matriculas'] ?? 0) > 0)
                    <div class="rounded-md border border-rose-300/80 dark:border-rose-800 bg-rose-50/70 dark:bg-rose-950/35 px-4 py-3 space-y-2 text-sm text-rose-950 dark:text-rose-100">
                        <p class="font-semibold">{{ __('Impacto financeiro indicativo (turma AEE sem deficiência no cadastro)') }}</p>
                        <p class="text-xs leading-relaxed">{{ $riscoAeeSemCadastro['observacao'] ?? '' }}</p>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div>
                                <dt class="text-rose-800/80 dark:text-rose-300/80">{{ __('Perda estimada / ano') }}</dt>
                                <dd class="tabular-nums font-semibold text-lg">{{ $riscoAeeSemCadastro['perda_anual_fmt'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-rose-800/80 dark:text-rose-300/80">{{ __('Ganho potencial ao corrigir cadastro') }}</dt>
                                <dd class="tabular-nums font-semibold text-lg text-emerald-800 dark:text-emerald-200">{{ $riscoAeeSemCadastro['ganho_potencial_anual_fmt'] ?? '—' }}</dd>
                            </div>
                        </dl>
                        @if (filled($riscoAeeSemCadastro['formula'] ?? null))
                            <p class="text-[11px] text-rose-900/90 dark:text-rose-200/90">{{ $riscoAeeSemCadastro['formula'] }}</p>
                        @endif
                        @php $cnRiscoAee = $calcNote('risco_aee_sem_cadastro'); @endphp
                        @if ($cnRiscoAee !== null)
                            <x-dashboard.section-calc-note
                                :formula="$cnRiscoAee['formula'] ?? null"
                                :note="$cnRiscoAee['note'] ?? null"
                            />
                        @endif
                    </div>
                @endif
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
                @php $cnRecurso = $calcNote('recurso_prova'); @endphp
                @if ($cnRecurso !== null)
                    <x-dashboard.section-calc-note
                        :formula="$cnRecurso['formula'] ?? null"
                        :note="$cnRecurso['note'] ?? null"
                    />
                @endif
            @endif
        </div>
    @endif

    @if ($neeIndicators !== null)
        @include('dashboard.analytics.partials.inclusion-nee-indicators', [
            'panel' => $neeIndicators,
            'chartExportContext' => $chartExportContext,
            'neeDetalheCatalogo' => $neeDetalheCatalogo,
            'neeExtraCharts' => $neeExtraCharts,
            'calcNote' => $neeIndicators['calc_note'] ?? $calcNote('nee_indicators') ?? $calcNote('gauges'),
        ])
    @endif

    @if (! empty($neeMatriculasPorEscola))
        <div class="rounded-lg border border-violet-200/80 dark:border-violet-800/50 bg-white dark:bg-gray-900/40 px-4 py-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ __('Matrículas NEE por escola') }}</h3>
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
            @php $cnEscola = $calcNote('nee_escola'); @endphp
            @if ($cnEscola !== null)
                <x-dashboard.section-calc-note
                    :formula="$cnEscola['formula'] ?? null"
                    :note="$cnEscola['note'] ?? null"
                />
            @endif
        </div>
    @endif

    @if (! empty($inclusionData['charts']) || is_array($aeeCross) || $neeIndicators !== null || is_array($chartRacaPorEscolaStacked) || is_array($chartNeePorRacaStacked) || ! empty($neeMatriculasPorEscola))
        @if (is_array($aeeCross))
            <div class="rounded-lg border border-amber-100 dark:border-amber-900/40 bg-amber-50/40 dark:bg-amber-950/15 px-4 py-4 space-y-4 mb-8">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Turmas AEE e matrículas NEE') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ __('Cruzamento do total NEE com oferta AEE e outras matrículas do mesmo aluno.') }}</p>
                </div>
                @if (! empty($aeeCross['note']))
                    <p class="text-xs text-amber-900 dark:text-amber-200/90 leading-relaxed">{{ $aeeCross['note'] }}</p>
                @endif
                <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-sm">
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Matrículas NEE (total)') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format($matriculasNee ?? (int) ($aeeCross['nee_matriculas_total'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-violet-200/60 dark:border-violet-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Com cadastro deficiência') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($aeeCross['matriculas_com_cadastro_nee'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Só turma AEE (est.)') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($aeeCross['matriculas_somente_turma_aee'] ?? 0)) }}</dd>
                        @if (($riscoAeeSemCadastro['available'] ?? false) && (int) ($riscoAeeSemCadastro['matriculas'] ?? $aeeCross['matriculas_aee_sem_cadastro'] ?? 0) > 0)
                            <dd class="mt-1 text-[11px] text-amber-900 dark:text-amber-200 leading-snug">
                                {{ __('Gap NEE vs cadastro (estimativa). Ganho/perda financeiro usa só matrículas em turma AEE sem cadastro — ver bloco abaixo.') }}
                            </dd>
                        @endif
                    </div>
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-amber-200/60 dark:border-amber-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Matrículas em turmas AEE') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($aeeCross['matriculas_em_turmas_aee'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-md bg-white/80 dark:bg-gray-800/60 border border-rose-200/60 dark:border-rose-800/40 px-3 py-2">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Matrículas AEE sem cadastro NEE') }}</dt>
                        <dd class="tabular-nums font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) ($aeeCross['matriculas_aee_sem_cadastro'] ?? $riscoAeeSemCadastro['matriculas'] ?? 0)) }}</dd>
                        <dd class="mt-0.5 text-[10px] text-gray-600 dark:text-gray-400 leading-snug">{{ __('Base do ganho/perda indicativo (só vínculo AEE, não matrículas regulares do aluno).') }}</dd>
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
                @php $cnAee = $calcNote('aee_cross'); @endphp
                @if ($cnAee !== null)
                    <x-dashboard.section-calc-note
                        :formula="$cnAee['formula'] ?? null"
                        :note="$cnAee['note'] ?? null"
                    />
                @endif
            </div>
        @endif

        @php
            $tailAfterNee = array_slice($inclusionData['charts'] ?? [], $neeChartsCount);
        @endphp
        @if (count($tailAfterNee) > 0 || is_array($chartRacaPorEscolaStacked) || is_array($chartNeePorRacaStacked))
            <div class="space-y-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-white/50 dark:bg-gray-900/20 px-4 py-4">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Perfil, equidade e distorção') }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400 -mt-2">{{ __('Rede completa do filtro (salvo indicação no gráfico).') }}</p>
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
                @php
                    $cnSexoRaca = $calcNote('sexo_raca');
                    $cnDistorcao = $calcNote('distorcao');
                @endphp
                @if ($cnSexoRaca !== null)
                    <x-dashboard.section-calc-note
                        :formula="$cnSexoRaca['formula'] ?? null"
                        :note="$cnSexoRaca['note'] ?? null"
                    />
                @endif
                @if ($cnDistorcao !== null && count($tailAfterNee) > 0)
                    <x-dashboard.section-calc-note
                        :formula="$cnDistorcao['formula'] ?? null"
                        :note="$cnDistorcao['note'] ?? null"
                        class="mt-0 pt-2 border-t-0"
                    />
                @endif
            </div>
        @endif
    @elseif (empty($inclusionData['error']) && empty($inclusionData['charts']) && $neeIndicators === null && ! is_array($aeeCross) && ! is_array($chartRacaPorEscolaStacked) && empty($neeMatriculasPorEscola))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem indicadores disponíveis para esta base ou filtros.') }}</p>
    @endif
</div>
