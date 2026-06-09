@props(['discrepanciesData', 'yearFilterReady' => false, 'chartExportContext' => [], 'municipalityContext' => null])

@php
    use App\Support\Dashboard\ConsultoriaFlow;

    $d = is_array($discrepanciesData) ? $discrepanciesData : [];
    $summary = is_array($d['summary'] ?? null) ? $d['summary'] : [];
    $checks = is_array($d['checks'] ?? null) ? $d['checks'] : [];
    $dimensions = is_array($d['dimensions'] ?? null) ? $d['dimensions'] : [];
    $modules = is_array($d['modules'] ?? null) ? $d['modules'] : [];
    $errosCriticos = array_values(array_filter($checks, static fn (array $c): bool => ! empty($c['is_erro'])));
    $demaisChecks = array_values(array_filter($checks, static fn (array $c): bool => empty($c['is_erro'])));
    $chartResumo = is_array($d['chart_resumo'] ?? null) ? $d['chart_resumo'] : null;
    $chartFinanceiro = is_array($d['chart_financeiro'] ?? null) ? $d['chart_financeiro'] : null;
    $fundingRef = is_array($d['funding_reference'] ?? null) ? $d['funding_reference'] : null;
    $vaafComparacao = is_array($fundingRef['vaaf_comparacao'] ?? null) ? $fundingRef['vaaf_comparacao'] : null;
    $divergenciaVaaf = is_array($fundingRef['divergencia_vaaf'] ?? null) ? $fundingRef['divergencia_vaaf'] : (is_array($fundingRef['divergencia'] ?? null) ? $fundingRef['divergencia'] : null);
    $pillars = is_array($d['funding_pillars'] ?? null) ? $d['funding_pillars'] : [];
    $activeCheckIds = is_array($d['active_check_ids'] ?? null) ? $d['active_check_ids'] : [];
    $fmtBrl = static fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
    $pendenciaDims = array_values(array_filter($dimensions, static fn (array $d): bool => (bool) ($d['has_issue'] ?? false)));
    usort($pendenciaDims, static fn (array $a, array $b): int => ((int) ($b['total'] ?? 0)) <=> ((int) ($a['total'] ?? 0)));
    $priorityDims = array_values(array_filter($pendenciaDims, static fn (array $d): bool => ($d['status'] ?? '') === 'danger'));
    $atencaoDims = array_values(array_filter($pendenciaDims, static fn (array $d): bool => ($d['status'] ?? '') === 'warning'));
    $showKpis = count($dimensions) > 0 || count($checks) > 0;
    $fundingMet = is_array($d['funding_metodologia'] ?? null) ? $d['funding_metodologia'] : null;
    $fundingResumo = is_array($d['funding_resumo_explicacao'] ?? null) ? $d['funding_resumo_explicacao'] : null;
    $perdaAgreg = (float) ($summary['perda_estimada_anual'] ?? 0);
    $ganhoAgreg = (float) ($summary['ganho_potencial_anual'] ?? 0);
    $discKpis = [
        ['label' => __('Ocorrências (soma)'), 'value' => number_format((int) ($summary['com_problema'] ?? 0)), 'tone' => 'rose'],
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
        ['label' => __('Corrigíveis no i-Educar'), 'value' => number_format((int) ($summary['corrigiveis'] ?? 0)), 'tone' => 'emerald'],
        ['label' => __('Escolas afetadas'), 'value' => number_format((int) ($summary['escolas_afetadas'] ?? 0)), 'tone' => 'teal'],
    ];
    $publicSources = is_array($d['public_data_sources'] ?? null) ? $d['public_data_sources'] : [];
    $hasPublicSources = count($publicSources['categories'] ?? []) > 0;
    $exportParams = is_array($d['export_params'] ?? null) ? $d['export_params'] : request()->only(['city_id', 'ano_letivo', 'escola_id', 'curso_id', 'turno_id']);
    $checksByModule = [];
    foreach ($modules as $mod) {
        foreach ($mod['routines'] ?? [] as $routine) {
            $rid = (string) ($routine['id'] ?? '');
            if ($rid === '') {
                continue;
            }
            foreach ($checks as $check) {
                if ((string) ($check['id'] ?? '') === $rid) {
                    $checksByModule[(string) ($mod['id'] ?? 'outros')][] = $check;
                    break;
                }
            }
        }
    }
    foreach ($checks as $check) {
        $cid = (string) ($check['id'] ?? '');
        $placed = false;
        foreach ($checksByModule as $list) {
            foreach ($list as $c) {
                if ((string) ($c['id'] ?? '') === $cid) {
                    $placed = true;
                    break 2;
                }
            }
        }
        if (! $placed) {
            $checksByModule['outros'][] = $check;
        }
    }

     $flowSteps = ConsultoriaFlow::numberedSteps([
        ['label' => __('Painel por módulo'), 'anchor' => 'disc-modulos', 'visible' => count($modules) > 0],
        ['label' => __('Detalhe e correção'), 'anchor' => 'disc-detalhe', 'visible' => count($checks) > 0],
        ['label' => __('VAAF de referência'), 'anchor' => 'disc-vaaf', 'visible' => $vaafComparacao !== null],
    ]);
    $discStep = ConsultoriaFlow::stepMap($flowSteps);
@endphp

@php
    $discMeta = '<span class="font-medium">'.e(__('Contexto')).':</span> '.e($d['city_name'] ?? '');
    if (filled($d['year_label'] ?? null)) {
        $discMeta .= ' — '.e($d['year_label']);
    }
    if (($d['total_matriculas'] ?? null) !== null) {
        $discMeta .= ' · '.e(__('Matrículas:')).' <span class="tabular-nums font-medium">'.number_format((int) $d['total_matriculas']).'</span>';
        if (($d['total_alunos_distintos'] ?? null) !== null) {
            $discMeta .= ' · '.e(__('Alunos distintos:')).' <span class="tabular-nums font-medium">'.number_format((int) $d['total_alunos_distintos']).'</span>';
        }
    }
    if ($fundingRef !== null && isset($fundingRef['vaa_label'])) {
        $discMeta .= ' · '.e(__('VAAF:')).' <span class="font-medium">'.e($fundingRef['vaa_label']).'</span>';
    }
@endphp

<x-dashboard.consultoria-tab-frame
    tab="discrepancies"
    tone="rose"
    :title="__('Discrepâncias e erros de cadastro')"
    :intro="$d['intro'] ?? ''"
    :meta="$discMeta"
    :footnote="$d['footnote'] ?? null"
    :year-filter-ready="$yearFilterReady"
    :municipality-context="$municipalityContext"
    :tab-data="['discrepanciesData' => $discrepanciesData]"
    :flow-steps="$flowSteps"
    flow-tone="rose"
    :no-year-message="__('Selecione o ano letivo e aplique os filtros para executar as rotinas de discrepâncias.')"
>
    <x-slot name="links">
        <span class="text-slate-600 dark:text-slate-400">{{ __('Relacionado:') }}</span>
        <x-consultoria-tab-link tab="municipality_health" :label="__('Diagnóstico')" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="fundeb" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="work_done" :label="__('Censo')" class="text-xs" />
    </x-slot>

    <div class="flex flex-wrap gap-2 items-center justify-end -mt-2">
        <x-dashboard.funding-loss-conditions-button :activeCheckIds="$activeCheckIds" />
    </div>

    @if (filled($d['funding_aviso'] ?? null))
        <p class="serv-callout serv-callout--warning">{{ $d['funding_aviso'] }}</p>
    @endif

    @if ($showKpis)
        <x-dashboard.consultoria-kpi-grid :items="$discKpis" class="grid-cols-2 md:grid-cols-3 2xl:grid-cols-6 gap-2" />
    @endif

        @if ($vaafComparacao !== null)
            <x-dashboard.consultoria-section
                :step="$discStep['disc-vaaf'] ?? null"
                anchor="disc-vaaf"
                :title="__('Medidor VAAF — municipal × prévia federal')"
                :subtitle="__('Base dos cálculos de perda e ganho indicativos desta aba.')"
            >
                <x-dashboard.consultoria-vaaf-comparacao
                    :comparacao="$vaafComparacao"
                    :divergencia="$divergenciaVaaf"
                />
            </x-dashboard.consultoria-section>
        @endif

        @if (! empty($d['error']))
            <div class="serv-callout serv-callout--danger text-sm">
                {{ $d['error'] }}
            </div>
        @endif

        @if (! empty($d['notes']))
            <div class="serv-callout space-y-1.5">
                @foreach ($d['notes'] as $note)
                    <p>{{ $note }}</p>
                @endforeach
            </div>
        @endif

        @if (count($modules) > 0)
            <x-dashboard.consultoria-section
                :step="$discStep['disc-modulos'] ?? null"
                anchor="disc-modulos"
                :title="__('Painel por módulo de cadastro')"
                :subtitle="__('Cada bloco reúne rotinas relacionadas, impacto financeiro indicativo e onde corrigir no i-Educar ou no painel municipal.')"
            >
                @if ($fundingMet !== null)
                    <x-dashboard.consultoria-funding-explanation
                        :metodologia="$fundingMet"
                        :resumo="$fundingResumo"
                        class="mb-3"
                    />
                @endif

                @if (count($errosCriticos) > 0)
                    <div class="serv-alert-panel serv-alert-panel--critical mb-3">
                        <h4 class="text-sm font-bold font-display text-rose-950 dark:text-rose-100 uppercase tracking-wide">{{ __('Erros críticos') }}</h4>
                        <ul class="text-xs text-red-900/95 dark:text-red-100 space-y-1.5">
                            @foreach ($errosCriticos as $c)
                                <li class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-0.5">
                                    <span>{{ $c['title'] ?? '' }}</span>
                                    <span class="tabular-nums font-semibold shrink-0 text-right">
                                        {{ number_format((int) ($c['total'] ?? 0)) }} {{ __('ocorr.') }}
                                        · {{ __('perda') }} {{ $fmtBrl((float) ($c['perda_estimada_anual'] ?? 0)) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <x-dashboard.consultoria-discrepancies-hub :modules="$modules" :fmt-brl="$fmtBrl" />

                <p class="serv-callout text-xs">
                    {{ __('Legenda:') }}
                    <span class="inline-flex items-center gap-1 mx-1"><span class="w-2 h-2 rounded-full bg-rose-500"></span>{{ __('crítico') }}</span>
                    <span class="inline-flex items-center gap-1 mx-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span>{{ __('atenção') }}</span>
                    <span class="inline-flex items-center gap-1 mx-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>{{ __('ok') }}</span>
                    <span class="inline-flex items-center gap-1 mx-1"><span class="w-2 h-2 rounded-full bg-sky-400"></span>{{ __('sem dados') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-400"></span>{{ __('indisponível') }}</span>
                </p>
            </x-dashboard.consultoria-section>
        @elseif (count($dimensions) > 0)
            <x-dashboard.consultoria-section
                anchor="disc-modulos"
                :title="__('Mapa de rotinas')"
                :subtitle="__('Estado das rotinas de cadastro no filtro actual.')"
            >
                <x-dashboard.consultoria-dimensions-grid :dimensions="$dimensions" :fmt-brl="$fmtBrl" />
            </x-dashboard.consultoria-section>
        @endif

        @if (count($checks) > 0)
            <x-dashboard.consultoria-section
                :step="$discStep['disc-detalhe'] ?? null"
                anchor="disc-detalhe"
                :title="__('Detalhe, correção e unidades afetadas')"
                :subtitle="__('Agrupado por módulo — problema, impacto financeiro, onde corrigir e lista por escola na mesma secção.')"
            >
                @if ($chartFinanceiro !== null || $chartResumo !== null)
                    <div class="disc-charts-overview grid grid-cols-1 lg:grid-cols-2 gap-3 mb-5">
                        @if ($chartResumo !== null)
                            <x-dashboard.chart-panel
                                :chart="$chartResumo"
                                exportFilename="discrepancias-resumo"
                                :exportMeta="$chartExportContext"
                                :compact="true"
                                chartPanelId="chart-discrepancias-resumo"
                                panelTone="teal"
                            />
                        @endif
                        @if ($chartFinanceiro !== null)
                            <x-dashboard.chart-panel
                                :chart="$chartFinanceiro"
                                exportFilename="discrepancias-financeiro"
                                :exportMeta="$chartExportContext"
                                :compact="true"
                                chartPanelId="chart-discrepancias-financeiro"
                                panelTone="amber"
                            />
                        @endif
                    </div>
                @endif

                <div class="space-y-6">
                    @foreach ($modules as $mod)
                        @php
                            $modChecks = $checksByModule[(string) ($mod['id'] ?? '')] ?? [];
                        @endphp
                        @if (count($modChecks) === 0)
                            @continue
                        @endif
                        <div id="disc-mod-detail-{{ $mod['id'] ?? '' }}" class="scroll-mt-24 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 dark:border-slate-700/70 pb-2">
                                <h4 class="text-sm font-semibold font-display text-serv-navy dark:text-slate-100">{{ $mod['title'] ?? '' }}</h4>
                                @if (filled($mod['correction_tab'] ?? null))
                                    <x-consultoria-tab-link
                                        :tab="$mod['correction_tab']"
                                        :label="$mod['correction_label'] ?? __('Onde corrigir')"
                                        class="text-xs font-semibold"
                                    />
                                @endif
                            </div>
                            @foreach ($modChecks as $idx => $check)
                                @include('dashboard.analytics.partials.discrepancies-check-card', [
                                    'check' => $check,
                                    'idx' => $idx,
                                    'fmtBrl' => $fmtBrl,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            @endforeach
                        </div>
                    @endforeach

                    @if (count($checksByModule['outros'] ?? []) > 0)
                        <div class="space-y-3">
                            <h4 class="text-sm font-semibold font-display text-serv-navy dark:text-slate-100">{{ __('Outras rotinas') }}</h4>
                            @foreach ($checksByModule['outros'] as $idx => $check)
                                @include('dashboard.analytics.partials.discrepancies-check-card', [
                                    'check' => $check,
                                    'idx' => 'outros-'.$idx,
                                    'fmtBrl' => $fmtBrl,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-dashboard.consultoria-section>
        @elseif ($showKpis && count($pendenciaDims) > 0)
            <p class="text-xs text-slate-500 dark:text-slate-400 italic">{{ __('Sem detalhe por escola nesta base — consulte o painel de módulos ou Serventec.') }}</p>
        @endif

        @if ($hasPublicSources)
            <details class="serv-panel scroll-mt-6 group">
                <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-2 select-none text-sm font-medium text-slate-700 dark:text-slate-300">
                    <span>{{ __('Consultas públicas (FNDE, Tesouro, INEP)') }}</span>
                    <span class="text-slate-400 group-open:rotate-180 transition-transform" aria-hidden="true">▾</span>
                </summary>
                <div class="px-4 pb-4 border-t border-slate-200/80 dark:border-slate-700/70">
                    <p class="text-xs text-slate-500 dark:text-slate-400 py-2">{{ __('Links de apoio — não substituem a correção no i-Educar indicada em cada módulo.') }}</p>
                    <x-dashboard.consultoria-public-sources :catalog="$publicSources" :anchor="null" />
                    <p class="mt-2 text-xs">
                        <x-consultoria-tab-link tab="fundeb" :label="__('Repasse e FUNDEB')" class="text-xs" />
                    </p>
                </div>
            </details>
        @endif
</x-dashboard.consultoria-tab-frame>
