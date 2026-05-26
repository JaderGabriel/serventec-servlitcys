@props([
    'healthData',
    'yearFilterReady' => false,
    'chartExportContext' => [],
    'municipalityContext' => null,
    'selectedCity' => null,
    'filters' => null,
    'pdfExportsRecent' => [],
])

@php
    use App\Support\Dashboard\ConsultoriaFlow;

    $h = is_array($healthData) ? $healthData : [];
    $summary = is_array($h['summary'] ?? null) ? $h['summary'] : [];
    $cadastro = is_array($h['cadastro_dimensions'] ?? null) ? $h['cadastro_dimensions'] : [];
    $thematicBlocks = is_array($h['thematic_blocks'] ?? null) ? $h['thematic_blocks'] : [];
    $fundebMods = is_array($h['fundeb_modules'] ?? null) ? $h['fundeb_modules'] : [];
    $topProblems = is_array($h['top_problems'] ?? null) ? $h['top_problems'] : [];
    $score = $h['compliance_score'] ?? null;
    $activeCheckIds = is_array($h['active_check_ids'] ?? null) ? $h['active_check_ids'] : [];
    $activeProgramIds = is_array($h['active_program_ids'] ?? null) ? $h['active_program_ids'] : [];
    $complementaryPrograms = is_array($h['complementary_programs'] ?? null) ? $h['complementary_programs'] : [];
    if ($activeCheckIds === []) {
        foreach ($cadastro as $dim) {
            if (($dim['has_issue'] ?? $dim['detected'] ?? false) && filled($dim['id'] ?? null)) {
                $activeCheckIds[] = (string) $dim['id'];
            }
        }
    }
    $fmtBrl = static fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
    $fundingMet = is_array($h['funding_metodologia'] ?? null) ? $h['funding_metodologia'] : null;
    $fundingResumo = is_array($h['funding_resumo_explicacao'] ?? null) ? $h['funding_resumo_explicacao'] : null;
    $vaafComparacao = is_array($h['vaaf_comparacao'] ?? null) ? $h['vaaf_comparacao'] : null;
    $previsaoComparacao = is_array($h['previsao_comparacao'] ?? null) ? $h['previsao_comparacao'] : null;
    $divergenciaVaaf = is_array($h['divergencia_vaaf'] ?? null) ? $h['divergencia_vaaf'] : null;
    $fundingRef = is_array($h['funding_display'] ?? null)
        ? $h['funding_display']
        : (is_array($h['funding_reference'] ?? null) ? $h['funding_reference'] : null);
    $pendenciasCadastro = array_values(array_filter(
        $cadastro,
        static fn (array $d): bool => ($d['has_issue'] ?? false) === true
    ));
    $perdaAgreg = (float) ($summary['perda_estimada_anual'] ?? 0);
    $ganhoAgreg = (float) ($summary['ganho_potencial_anual'] ?? 0);
    $recursoSemNee = (int) ($summary['recurso_prova_sem_nee'] ?? 0);
    $programasAlerta = (int) ($h['programas_alerta'] ?? 0);
    $healthKpis = [
        ['label' => __('Pendências de cadastro'), 'value' => number_format((int) ($summary['pendencias_cadastro'] ?? 0)), 'tone' => 'rose'],
        ['label' => __('Módulos FUNDEB em alerta'), 'value' => number_format((int) ($summary['modulos_fundeb_alerta'] ?? 0)), 'tone' => 'amber'],
    ];
    if ($programasAlerta > 0) {
        $healthKpis[] = [
            'label' => __('Programas (PNAE/PNATE/…) em alerta'),
            'value' => number_format($programasAlerta),
            'tone' => 'teal',
            'explicacao_resumo' => __('Cobertura baixa de campos no i-Educar — ver Financiamentos e modal de condições.'),
        ];
    }
    if ((int) ($h['public_queries_success'] ?? 0) > 0) {
        $healthKpis[] = [
            'label' => __('Consultas públicas OK'),
            'value' => number_format((int) $h['public_queries_success']),
            'tone' => 'sky',
            'explicacao_resumo' => __('Fontes FNDE/Tesouro/Transparência com dados na última consulta (cache).'),
        ];
    }
    if ($recursoSemNee > 0) {
        $healthKpis[] = [
            'label' => __('Recurso de prova sem NEE'),
            'value' => number_format($recursoSemNee),
            'tone' => 'violet',
            'explicacao_resumo' => __('Matrículas com apoio INEP declarado sem cadastro de deficiência/NEE — ver Discrepâncias.'),
        ];
    }
    if ((int) ($summary['cadastros_quinzena'] ?? 0) > 0) {
        $healthKpis[] = [
            'label' => __('Cadastros (quinzena)'),
            'value' => number_format((int) $summary['cadastros_quinzena']),
            'tone' => 'sky',
            'explicacao_resumo' => __('Matrículas com data de cadastro recente, por usuários municipais (exc. admin).'),
        ];
    }
    $healthKpisFinanceiro = [
        [
            'label' => __('Perda estimada / ano'),
            'value' => $fmtBrl($perdaAgreg),
            'tone' => 'orange',
            'size' => 'xl',
            'explicacao_resumo' => filled($fundingResumo['detalhe'] ?? null) ? $fundingResumo['detalhe'] : null,
            'funding_explicacao' => $fundingResumo !== null ? [
                'formula_curta' => (string) ($fundingResumo['titulo'] ?? __('Soma das rotinas com pendência')),
                'formula_expandida' => (string) ($fundingResumo['detalhe'] ?? ''),
                'passos' => is_array($fundingResumo['passos'] ?? null) ? $fundingResumo['passos'] : [],
            ] : null,
        ],
        [
            'label' => __('Ganho potencial / ano'),
            'value' => $fmtBrl($ganhoAgreg),
            'tone' => 'emerald',
            'size' => 'xl',
            'explicacao_resumo' => $ganhoAgreg > 0
                ? __('Igual à perda neste modelo: valor indicativo recuperável após corrigir cadastro no i-Educar.')
                : null,
            'funding_explicacao' => $ganhoAgreg > 0 && $fundingResumo !== null ? [
                'formula_curta' => __('Ganho potencial = perda estimada (modelo indicativo)'),
                'ganho_texto' => __('Se todas as pendências forem resolvidas antes do Censo, a soma das estimativas por rotina indica :ganho/ano.', ['ganho' => $fmtBrl($ganhoAgreg)]),
                'passos' => is_array($fundingResumo['passos'] ?? null) ? $fundingResumo['passos'] : [],
            ] : null,
        ],
    ];
    $healthKpisPrioridades = array_merge($healthKpis, $healthKpisFinanceiro);
    $publicSources = is_array($h['public_data_sources'] ?? null) ? $h['public_data_sources'] : [];
    $hasPublicSources = count($publicSources['categories'] ?? []) > 0;
    $flowSteps = ConsultoriaFlow::numberedSteps([
        ['label' => __('Prioridades'), 'anchor' => 'diag-prioridades'],
        ['label' => __('VAAF e previsão'), 'anchor' => 'diag-vaaf', 'visible' => $vaafComparacao !== null],
        ['label' => __('Programas complementares'), 'anchor' => 'diag-programas', 'visible' => count($complementaryPrograms) > 0],
        ['label' => __('Leitura temática'), 'anchor' => 'diag-tematico', 'visible' => count($thematicBlocks) > 0],
        ['label' => __('Fontes públicas'), 'anchor' => 'diag-fontes-publicas', 'visible' => $hasPublicSources],
        ['label' => __('Mapa de rotinas'), 'anchor' => 'diag-mapa', 'visible' => count($cadastro) > 0],
        ['label' => __('Roteiro FUNDEB'), 'anchor' => 'diag-roteiro', 'visible' => count($fundebMods) > 0],
    ]);
    $diagStep = ConsultoriaFlow::stepMap($flowSteps);
    $progressive = ! empty($h['sections_pending'] ?? []);
    $sectionsPending = is_array($h['sections_pending'] ?? null) ? $h['sections_pending'] : [];
    $scoreRing = match ($h['compliance_status'] ?? 'neutral') {
        'success' => 'serv-panel border-emerald-300/80 dark:border-emerald-700',
        'warning' => 'serv-panel border-amber-300/80 dark:border-amber-700',
        'danger' => 'serv-panel border-rose-300/80 dark:border-rose-700',
        default => 'serv-panel',
    };
@endphp

<div
    class="space-y-6"
    @if ($progressive) data-municipality-health-progressive="1" data-analytics-panel-root @endif
>
    @if (! $yearFilterReady)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('Selecione o ano letivo e aplique os filtros para ver o diagnóstico geral de conformidade do município.') }}
        </p>
    @else
        @include('dashboard.analytics.partials.tab-impact-strip', [
            'tab' => 'municipality_health',
            'yearFilterReady' => $yearFilterReady,
            'municipalityContext' => $municipalityContext,
            'tabData' => ['healthData' => $healthData],
        ])

        @include('dashboard.analytics.partials.serventec-pdf-export', [
            'selectedCity' => $selectedCity,
            'filters' => $filters,
            'yearFilterReady' => $yearFilterReady,
            'pdfExportsRecent' => $pdfExportsRecent,
        ])

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <x-dashboard.serv-tab-intro :title="__('Diagnóstico municipal')" tone="teal">
                {{ $h['intro'] ?? '' }}
                <x-slot name="meta">
                    <span class="font-medium">{{ __('Contexto') }}:</span>
                    {{ $h['city_name'] ?? '' }}
                    @if (filled($h['year_label'] ?? null))
                        — {{ $h['year_label'] }}
                    @endif
                    @if ($fundingRef !== null && isset($fundingRef['vaa_label']))
                        ·
                        <span class="font-medium">{{ \App\Support\Ieducar\FundebReferenceDisplay::rotuloVaafCurto($fundingRef) }}:</span>
                        <span class="font-medium">{{ $fundingRef['vaa_label'] }}</span>
                        <span class="text-gray-500 dark:text-gray-400">/aluno/ano</span>
                        @if (filled($fundingRef['vaa_previa_label'] ?? null))
                            · {{ __('prévia:') }} {{ $fundingRef['vaa_previa_label'] }}
                        @endif
                    @endif
                </x-slot>
            </x-dashboard.serv-tab-intro>
            <div class="shrink-0">
                <x-dashboard.funding-loss-conditions-button :activeCheckIds="$activeCheckIds" :activeProgramIds="$activeProgramIds" />
            </div>
        </div>

        @if (filled($h['footnote'] ?? null))
            <p class="serv-callout">{{ $h['footnote'] }}</p>
        @endif

        <p class="serv-callout">
            {{ __('Aprofundar:') }}
            <x-consultoria-tab-link tab="discrepancies" class="text-xs" />
            ·
            <x-consultoria-tab-link tab="fundeb" class="text-xs" />
            ·
            <x-consultoria-tab-link tab="other_funding" :label="__('Financiamentos')" class="text-xs" />
            ·
            <x-consultoria-tab-link tab="work_done" :label="__('Censo')" class="text-xs" />
        </p>

        <x-dashboard.consultoria-flow-nav :steps="$flowSteps" tone="teal" />

        @if (! empty($h['error']))
            <div class="serv-callout serv-callout--danger text-sm">
                {{ $h['error'] }}
            </div>
        @endif

        <x-dashboard.consultoria-section
            :step="$diagStep['diag-prioridades'] ?? null"
            anchor="diag-prioridades"
            :title="__('Prioridades e índice')"
            :subtitle="__('Visão executiva: conformidade, impacto financeiro e principais problemas.')"
        >
            @if ($score !== null)
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-stretch">
                    <div class="xl:col-span-4 {{ $scoreRing }} p-4 sm:p-5 flex flex-col justify-between gap-3 min-h-[14rem]" data-health-compliance-root data-compliance-score="{{ (int) $score }}" data-compliance-status="{{ $h['compliance_status'] ?? 'neutral' }}" data-compliance-label="{{ $h['compliance_label'] ?? '' }}">
                        <div class="text-center">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-2">{{ __('Índice de conformidade') }}</p>
                            <x-dashboard.compliance-speedometer
                                :score="(int) $score"
                                :status="(string) ($h['compliance_status'] ?? 'neutral')"
                                :label="(string) ($h['compliance_label'] ?? '')"
                                class="w-full max-w-[280px] mx-auto"
                            />
                        </div>
                        <div class="flex flex-wrap gap-x-2 gap-y-1 justify-center text-xs border-t border-slate-200/70 dark:border-slate-700/70 pt-3">
                            <x-consultoria-tab-link tab="discrepancies" />
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <x-consultoria-tab-link tab="fundeb" />
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <x-consultoria-tab-link tab="work_done" :label="__('Censo')" />
                        </div>
                    </div>
                    <div class="xl:col-span-8 flex flex-col gap-3 min-h-0">
                        @if (count($healthKpisPrioridades) > 0)
                            <x-dashboard.consultoria-kpi-grid
                                :items="$healthKpisPrioridades"
                                class="grid-cols-2 md:grid-cols-3 2xl:grid-cols-4 flex-1 auto-rows-fr [&>.serv-panel]:h-full"
                            />
                        @endif
                        @if ($fundingMet !== null)
                            <x-dashboard.consultoria-funding-explanation
                                :metodologia="$fundingMet"
                                :resumo="$fundingResumo"
                                class="shrink-0"
                            />
                        @endif
                    </div>
                </div>
            @endif

            @if (count($topProblems) > 0)
                <div class="mt-4 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
                        <div>
                            <h4 class="text-sm font-semibold font-display text-rose-950 dark:text-rose-100">
                                {{ __('Principais pendências de cadastro') }}
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ __('Ordenadas por impacto financeiro indicativo (VAAF municipal × peso). Detalhe por escola na aba Discrepâncias.') }}
                            </p>
                        </div>
                        <x-consultoria-tab-link tab="discrepancies" :label="__('Ver todas em Discrepâncias')" class="text-xs shrink-0" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                        @foreach ($topProblems as $problem)
                            @php
                                $perdaProb = (float) ($problem['perda_estimada_anual'] ?? 0);
                                $ganhoProb = (float) ($problem['ganho_potencial_anual'] ?? 0);
                            @endphp
                            <article class="serv-panel border border-rose-200/70 dark:border-rose-900/50 px-3 py-2.5 text-sm h-full flex flex-col gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-serv-navy dark:text-slate-100 leading-snug">{{ $problem['title'] ?? '' }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 tabular-nums">
                                        {{ __(':n ocorrências', ['n' => number_format((int) ($problem['total'] ?? 0))]) }}
                                        @if (($problem['pct_rede'] ?? null) !== null)
                                            <span class="text-slate-400 dark:text-slate-500">·</span>
                                            {{ number_format((float) $problem['pct_rede'], 1, ',', '.') }}% {{ __('da rede') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-xs tabular-nums border-t border-slate-100 dark:border-slate-800 pt-2">
                                    <span class="text-orange-800 dark:text-orange-300">
                                        <span class="font-semibold uppercase tracking-wide text-[10px] text-orange-900/80 dark:text-orange-200/80">{{ __('Perda') }}</span>
                                        {{ $fmtBrl($perdaProb) }}
                                    </span>
                                    <span class="text-emerald-800 dark:text-emerald-300">
                                        <span class="font-semibold uppercase tracking-wide text-[10px] text-emerald-900/80 dark:text-emerald-200/80">{{ __('Ganho') }}</span>
                                        {{ $fmtBrl($ganhoProb) }}
                                    </span>
                                </div>
                                @if (is_array($problem['funding_explicacao'] ?? null))
                                    <x-dashboard.consultoria-funding-explanation :explicacao="$problem['funding_explicacao']" compact class="mt-auto" />
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @elseif (count($pendenciasCadastro) > 0)
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-400 serv-callout">
                    {{ __('Há :n tipo(s) de pendência no mapa de rotinas abaixo; nenhuma com impacto financeiro calculável neste filtro.', ['n' => count($pendenciasCadastro)]) }}
                </p>
            @endif
        </x-dashboard.consultoria-section>

        @if ($progressive && in_array('fundeb', $sectionsPending, true))
            <div data-municipality-health-section="fundeb" class="space-y-6">
                @include('dashboard.analytics.partials.municipality-health-section-skeleton', [
                    'message' => __('A carregar VAAF, previsão e roteiro FUNDEB…'),
                ])
            </div>
        @elseif ($vaafComparacao !== null || count($fundebMods) > 0)
            @include('dashboard.analytics.partials.municipality-health-section-fundeb', [
                'healthData' => $h,
                'diagStep' => $diagStep,
            ])
        @endif

        @if ($progressive && in_array('programas', $sectionsPending, true))
            <div data-municipality-health-section="programas" class="space-y-6">
                @include('dashboard.analytics.partials.municipality-health-section-skeleton', [
                    'message' => __('A carregar programas complementares…'),
                ])
            </div>
        @elseif (count($complementaryPrograms) > 0)
            @include('dashboard.analytics.partials.municipality-health-section-programas', [
                'healthData' => $h,
                'diagStep' => $diagStep,
            ])
        @endif

        @if ($progressive && in_array('tematico', $sectionsPending, true))
            <div data-municipality-health-section="tematico" class="space-y-6">
                @include('dashboard.analytics.partials.municipality-health-section-skeleton', [
                    'message' => __('A carregar leitura temática e indicadores pedagógicos…'),
                ])
            </div>
        @elseif (count($thematicBlocks) > 0)
            @include('dashboard.analytics.partials.municipality-health-section-tematico', [
                'healthData' => $h,
                'diagStep' => $diagStep,
            ])
        @endif

        @if ($hasPublicSources)
            <x-dashboard.consultoria-section
                :step="$diagStep['diag-fontes-publicas'] ?? null"
                anchor="diag-fontes-publicas"
                :title="__('Extração e relatórios oficiais')"
                :subtitle="__('Painéis, dados abertos e sistemas de comprovação (FNDE, Tesouro, Simec, INEP).')"
            >
                <x-dashboard.consultoria-public-sources :catalog="$publicSources" :anchor="null" />
            </x-dashboard.consultoria-section>
        @endif

        @if (count($cadastro) > 0)
            <x-dashboard.consultoria-section
                :step="$diagStep['diag-mapa'] ?? null"
                anchor="diag-mapa"
                :title="__('Mapa de rotinas de cadastro')"
                :subtitle="__('Alinhado à aba Discrepâncias — verde = sem pendência; cinza = indisponível.')"
            >
                <x-dashboard.consultoria-dimensions-grid :dimensions="$cadastro" :fmt-brl="$fmtBrl" columns="2" />
                <p class="serv-callout">
                    <x-consultoria-tab-link tab="discrepancies" :label="__('Detalhar por escola em Discrepâncias')" class="text-xs" />
                </p>
            </x-dashboard.consultoria-section>
        @endif

    @endif
</div>
