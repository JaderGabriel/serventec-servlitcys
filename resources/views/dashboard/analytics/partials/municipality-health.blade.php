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
    $fundingRef = is_array($h['funding_reference'] ?? null) ? $h['funding_reference'] : null;
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
            'explicacao_resumo' => __('Matrículas com data de cadastro recente, por utilizadores municipais (exc. admin).'),
        ];
    }
    $healthKpis = array_merge($healthKpis, [
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
    ]);
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
    $scoreRing = match ($h['compliance_status'] ?? 'neutral') {
        'success' => 'serv-panel border-emerald-300/80 dark:border-emerald-700',
        'warning' => 'serv-panel border-amber-300/80 dark:border-amber-700',
        'danger' => 'serv-panel border-rose-300/80 dark:border-rose-700',
        default => 'serv-panel',
    };
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('Seleccione o ano letivo e aplique os filtros para ver o diagnóstico geral de conformidade do município.') }}
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
                        · {{ __('VAAF municipal:') }} <span class="font-medium">{{ $fundingRef['vaa_label'] }}</span>
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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-1 {{ $scoreRing }} p-6 flex flex-col items-center justify-center text-center">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-1">{{ __('Índice de conformidade') }}</p>
                        <x-dashboard.compliance-speedometer
                            :score="(int) $score"
                            :status="(string) ($h['compliance_status'] ?? 'neutral')"
                            :label="(string) ($h['compliance_label'] ?? '')"
                            class="w-full"
                        />
                        <div class="mt-3 flex flex-wrap gap-2 justify-center text-xs">
                            <x-consultoria-tab-link tab="discrepancies" />
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <x-consultoria-tab-link tab="fundeb" />
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <x-consultoria-tab-link tab="work_done" :label="__('Censo')" />
                        </div>
                    </div>
                    <div class="lg:col-span-2 space-y-2">
                        <x-dashboard.consultoria-kpi-grid :items="$healthKpis" class="lg:grid-cols-2" />
                        @if ($fundingMet !== null)
                            <x-dashboard.consultoria-funding-explanation
                                :metodologia="$fundingMet"
                                :resumo="$fundingResumo"
                            />
                        @endif
                    </div>
                </div>
                @if (! empty($h['chart_pendencias']))
                    <div class="max-w-3xl">
                        <x-dashboard.chart-panel
                            :chart="$h['chart_pendencias']"
                            exportFilename="saude-municipio-pendencias"
                            :exportMeta="$chartExportContext"
                            :compact="true"
                            chartPanelId="chart-saude-pendencias"
                            panelTone="rose"
                        />
                    </div>
                @endif
            @endif

            @if (count($topProblems) > 0)
                <div class="serv-panel overflow-hidden">
                    <header class="px-4 py-3 border-b border-slate-200/80 dark:border-slate-700/80 bg-rose-50/50 dark:bg-rose-950/20">
                        <h4 class="text-sm font-semibold font-display text-rose-950 dark:text-rose-100">{{ __('Principais problemas (impacto financeiro indicativo)') }}</h4>
                    </header>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($topProblems as $problem)
                            <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm">
                                <div>
                                    <p class="font-medium text-serv-navy dark:text-slate-100">{{ $problem['title'] ?? '' }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ __(':n ocorrências', ['n' => number_format((int) ($problem['total'] ?? 0))]) }}
                                        @if (($problem['pct_rede'] ?? null) !== null)
                                            · {{ number_format((float) $problem['pct_rede'], 1, ',', '.') }}% {{ __('da rede') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="shrink-0 text-right space-y-1">
                                    <p class="text-sm font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">
                                        {{ $fmtBrl((float) ($problem['ganho_potencial_anual'] ?? 0)) }}
                                    </p>
                                    @if (is_array($problem['funding_explicacao'] ?? null))
                                        <x-dashboard.consultoria-funding-explanation :explicacao="$problem['funding_explicacao']" compact class="max-w-xs ml-auto" />
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-dashboard.consultoria-section>

        @if ($vaafComparacao !== null)
            <x-dashboard.consultoria-section
                :step="$diagStep['diag-vaaf'] ?? null"
                anchor="diag-vaaf"
                :title="__('Medidores financeiros (VAAF e previsão)')"
                :subtitle="__('Valor municipal usado nos cálculos × prévia federal para comparação com painéis do governo.')"
            >
                <x-dashboard.consultoria-vaaf-comparacao
                    :comparacao="$vaafComparacao"
                    :divergencia="$divergenciaVaaf"
                    :previsaoComparacao="$previsaoComparacao"
                />
            </x-dashboard.consultoria-section>
        @endif

        @if (count($complementaryPrograms) > 0)
            <x-dashboard.consultoria-section
                :step="$diagStep['diag-programas'] ?? null"
                anchor="diag-programas"
                :title="__('Financiamentos complementares (análise municipal)')"
                :subtitle="__('PNAE, PNATE, PDDE e correlatos — cobertura de cadastro no i-Educar (não é valor de repasse FNDE).')"
            >
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($complementaryPrograms as $prog)
                        @php
                            $pst = (string) ($prog['status'] ?? 'neutral');
                            $pborder = match ($pst) {
                                'success' => 'border-emerald-300 dark:border-emerald-800',
                                'warning' => 'border-amber-300 dark:border-amber-800',
                                'danger' => 'border-rose-300 dark:border-rose-800',
                                default => '',
                            };
                        @endphp
                        <article class="serv-panel {{ $pborder }} px-3 py-3 text-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h4 class="font-semibold text-serv-navy dark:text-slate-100 text-xs leading-snug">{{ $prog['titulo'] ?? '' }}</h4>
                                <span class="serv-status-pill
                                    @if ($pst === 'success') serv-status-pill--success
                                    @elseif ($pst === 'danger') serv-status-pill--danger
                                    @elseif ($pst === 'warning') serv-status-pill--warning
                                    @else serv-status-pill--neutral @endif">
                                    {{ $prog['status_label'] ?? '' }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $prog['resumo'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
                <p class="serv-callout flex flex-wrap gap-x-2 gap-y-1">
                    <x-consultoria-tab-link tab="other_funding" :label="__('Detalhe na aba Financiamentos')" class="text-xs" />
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <button type="button" class="serv-inline-tab-link text-xs" x-on:click="$dispatch('funding-loss-set-active', { ids: @js($activeCheckIds), programIds: @js($activeProgramIds) }); $dispatch('open-modal', 'funding-loss-conditions')">{{ __('Condições de perda (todos os programas)') }}</button>
                </p>
            </x-dashboard.consultoria-section>
        @endif

        @if (count($thematicBlocks) > 0)
            <x-dashboard.consultoria-section
                :step="$diagStep['diag-tematico'] ?? null"
                anchor="diag-tematico"
                :title="__('Leitura temática')"
                :subtitle="__('Consolida i-Educar com indicadores públicos quando disponíveis.')"
            >
                <x-dashboard.consultoria-thematic-blocks :blocks="$thematicBlocks" />
            </x-dashboard.consultoria-section>
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

        @if (count($fundebMods) > 0)
            <x-dashboard.consultoria-section
                :step="$diagStep['diag-roteiro'] ?? null"
                anchor="diag-roteiro"
                :title="__('Roteiro FUNDEB / VAAR')"
                :subtitle="__('Eixos de condicionalidade e situação municipal.')"
            >
                <div class="space-y-2">
                    @foreach ($fundebMods as $mod)
                        @php
                            $mst = (string) ($mod['status'] ?? 'neutral');
                            $mchip = match ($mst) {
                                'success' => 'border-l-emerald-500',
                                'warning' => 'border-l-amber-500',
                                'danger' => 'border-l-red-500',
                                default => 'border-l-slate-400',
                            };
                        @endphp
                        <article class="serv-panel border-l-4 {{ $mchip }} px-3 py-2 text-xs">
                            <p class="font-medium text-serv-navy dark:text-slate-100">{{ $mod['title'] ?? '' }}</p>
                            <p class="text-slate-500 dark:text-slate-400 mt-0.5">{{ $mod['reference'] ?? '' }}</p>
                            <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">{{ $mod['situacao'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
                <p class="serv-callout">
                    <x-consultoria-tab-link tab="fundeb" :label="__('Abrir aba FUNDEB completa')" class="text-xs" />
                </p>
            </x-dashboard.consultoria-section>
        @endif
    @endif
</div>
