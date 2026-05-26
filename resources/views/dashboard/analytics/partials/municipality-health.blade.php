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
        ['label' => __('Decisão'), 'anchor' => 'diag-decisao'],
        ['label' => __('Qualidade'), 'anchor' => 'diag-qualidade-sistema'],
        ['label' => __('Explorar'), 'anchor' => 'diag-explorar'],
        ['label' => __('Prioridades'), 'anchor' => 'diag-prioridades', 'visible' => count($topProblems) > 0],
        ['label' => __('VAAF e previsão'), 'anchor' => 'diag-vaaf', 'visible' => ! $strategicMode && $vaafComparacao !== null],
        ['label' => __('Programas complementares'), 'anchor' => 'diag-programas', 'visible' => ! $strategicMode && count($complementaryPrograms) > 0],
        ['label' => __('Leitura temática'), 'anchor' => 'diag-tematico', 'visible' => ! $strategicMode && count($thematicBlocks) > 0],
        ['label' => __('Fontes públicas'), 'anchor' => 'diag-fontes-publicas', 'visible' => $hasPublicSources],
        ['label' => __('Mapa de rotinas'), 'anchor' => 'diag-mapa', 'visible' => count($cadastro) > 0],
        ['label' => __('Roteiro FUNDEB'), 'anchor' => 'diag-roteiro', 'visible' => count($fundebMods) > 0],
    ]);
    $diagStep = ConsultoriaFlow::stepMap($flowSteps);
    $progressive = ! empty($h['sections_pending'] ?? []);
    $sectionsPending = is_array($h['sections_pending'] ?? null) ? $h['sections_pending'] : [];
    $strategicMode = ! empty($h['strategic_mode'] ?? false);
    $showDetailSections = $progressive && ! $strategicMode;
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

        @if (! empty($h['error']))
            <div class="serv-callout serv-callout--danger text-sm">
                {{ $h['error'] }}
            </div>
        @endif

        <div class="sticky top-0 z-10 -mx-1 px-1 py-1 rounded-lg bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm border border-slate-200/60 dark:border-slate-700/60">
            <x-dashboard.consultoria-flow-nav :steps="$flowSteps" tone="teal" />
        </div>

        @include('dashboard.analytics.partials.municipality-health-executive', [
            'h' => $h,
            'summary' => $summary,
            'topProblems' => $topProblems,
            'healthKpisPrioridades' => $healthKpisPrioridades,
            'complementaryPrograms' => $complementaryPrograms,
            'programasAlerta' => $programasAlerta,
            'vaafComparacao' => $vaafComparacao,
            'fmtBrl' => $fmtBrl,
        ])

        @include('dashboard.analytics.partials.municipality-health-system-quality', ['h' => $h])

        @include('dashboard.analytics.partials.municipality-health-explore', ['h' => $h])

        @if ($fundingMet !== null && ! $strategicMode)
            <x-dashboard.consultoria-funding-explanation
                :metodologia="$fundingMet"
                :resumo="$fundingResumo"
            />
        @endif

        @if ($showDetailSections && in_array('fundeb', $sectionsPending, true))
            <div data-municipality-health-section="fundeb" class="space-y-6">
                @include('dashboard.analytics.partials.municipality-health-section-skeleton', [
                    'message' => __('A carregar VAAF, previsão e roteiro FUNDEB…'),
                ])
            </div>
        @elseif (! $strategicMode && ($vaafComparacao !== null || count($fundebMods) > 0))
            @include('dashboard.analytics.partials.municipality-health-section-fundeb', [
                'healthData' => $h,
                'diagStep' => $diagStep,
            ])
        @endif

        @if ($showDetailSections && in_array('programas', $sectionsPending, true))
            <div data-municipality-health-section="programas" class="space-y-6">
                @include('dashboard.analytics.partials.municipality-health-section-skeleton', [
                    'message' => __('A carregar programas complementares…'),
                ])
            </div>
        @elseif (! $strategicMode && count($complementaryPrograms) > 0)
            @include('dashboard.analytics.partials.municipality-health-section-programas', [
                'healthData' => $h,
                'diagStep' => $diagStep,
            ])
        @endif

        @if ($showDetailSections && in_array('tematico', $sectionsPending, true))
            <div data-municipality-health-section="tematico" class="space-y-6">
                @include('dashboard.analytics.partials.municipality-health-section-skeleton', [
                    'message' => __('A carregar leitura temática e indicadores pedagógicos…'),
                ])
            </div>
        @elseif (! $strategicMode && count($thematicBlocks) > 0)
            @include('dashboard.analytics.partials.municipality-health-section-tematico', [
                'healthData' => $h,
                'diagStep' => $diagStep,
            ])
        @endif

        @if ($hasPublicSources || count($cadastro) > 0)
            <section class="serv-panel overflow-hidden border border-slate-200/90 dark:border-slate-700">
                <details class="group">
                    <summary class="cursor-pointer list-none px-4 py-3 bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200/80 dark:border-slate-700 flex items-center justify-between gap-2 select-none">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ __('Consolidado operacional') }}
                            </p>
                            <p class="text-sm font-semibold text-serv-navy dark:text-slate-100">
                                {{ __('Fontes públicas e mapa de rotinas') }}
                            </p>
                        </div>
                        <span class="text-slate-400 group-open:rotate-180 transition-transform" aria-hidden="true">▾</span>
                    </summary>
                    <div class="p-4 sm:p-5 space-y-6">
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
                    </div>
                </details>
            </section>
        @endif

    @endif
</div>
