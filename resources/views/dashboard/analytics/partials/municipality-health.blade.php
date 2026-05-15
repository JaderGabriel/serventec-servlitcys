@props(['healthData', 'yearFilterReady' => false, 'chartExportContext' => []])

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
    $perdaAgreg = (float) ($summary['perda_estimada_anual'] ?? 0);
    $ganhoAgreg = (float) ($summary['ganho_potencial_anual'] ?? 0);
    $healthKpis = [
        ['label' => __('Pendências de cadastro'), 'value' => number_format((int) ($summary['pendencias_cadastro'] ?? 0)), 'tone' => 'rose'],
        ['label' => __('Módulos FUNDEB em alerta'), 'value' => number_format((int) ($summary['modulos_fundeb_alerta'] ?? 0)), 'tone' => 'amber'],
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
    $publicSources = is_array($h['public_data_sources'] ?? null) ? $h['public_data_sources'] : [];
    $hasPublicSources = count($publicSources['categories'] ?? []) > 0;
    $flowSteps = ConsultoriaFlow::numberedSteps([
        ['label' => __('Prioridades'), 'anchor' => 'diag-prioridades'],
        ['label' => __('Leitura temática'), 'anchor' => 'diag-tematico', 'visible' => count($thematicBlocks) > 0],
        ['label' => __('Fontes públicas'), 'anchor' => 'diag-fontes-publicas', 'visible' => $hasPublicSources],
        ['label' => __('Mapa de rotinas'), 'anchor' => 'diag-mapa', 'visible' => count($cadastro) > 0],
        ['label' => __('Roteiro FUNDEB'), 'anchor' => 'diag-roteiro', 'visible' => count($fundebMods) > 0],
    ]);
    $diagStep = ConsultoriaFlow::stepMap($flowSteps);
    $scoreRing = match ($h['compliance_status'] ?? 'neutral') {
        'success' => 'border-emerald-400 bg-emerald-50/60 dark:bg-emerald-950/25',
        'warning' => 'border-amber-400 bg-amber-50/60 dark:bg-amber-950/25',
        'danger' => 'border-red-400 bg-red-50/60 dark:bg-red-950/25',
        default => 'border-slate-300 bg-slate-50/60',
    };
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo e aplique os filtros para ver o diagnóstico geral de conformidade do município.') }}
        </p>
    @else
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="rounded-lg border border-teal-200 dark:border-teal-900/50 bg-teal-50/60 dark:bg-teal-950/20 px-4 py-3 text-sm space-y-2 flex-1">
                <h2 class="font-semibold text-teal-950 dark:text-teal-100">{{ __('Diagnóstico Geral') }}</h2>
                <p class="leading-relaxed text-teal-900/95 dark:text-teal-200/95">{{ $h['intro'] ?? '' }}</p>
                <p class="text-xs text-teal-800/90 dark:text-teal-300/90">
                    <span class="font-medium">{{ __('Contexto') }}:</span>
                    {{ $h['city_name'] ?? '' }}
                    @if (filled($h['year_label'] ?? null))
                        — {{ $h['year_label'] }}
                    @endif
                </p>
            </div>
            <div class="shrink-0">
                <x-dashboard.funding-loss-conditions-button :activeCheckIds="$activeCheckIds" />
            </div>
        </div>

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $h['footnote'] ?? '' }}
        </p>

        <x-dashboard.consultoria-flow-nav :steps="$flowSteps" tone="teal" />

        @if (! empty($h['error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
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
                    <div class="lg:col-span-1 rounded-xl border-2 {{ $scoreRing }} p-6 flex flex-col items-center justify-center text-center shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400 mb-1">{{ __('Índice de conformidade') }}</p>
                        <x-dashboard.compliance-speedometer
                            :score="(int) $score"
                            :status="(string) ($h['compliance_status'] ?? 'neutral')"
                            :label="(string) ($h['compliance_label'] ?? '')"
                            class="w-full"
                        />
                        <div class="mt-3 flex flex-wrap gap-2 justify-center">
                            <button type="button" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Ver discrepâncias') }}</button>
                            <span class="text-gray-300 dark:text-gray-600">·</span>
                            <button type="button" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'fundeb')">{{ __('Ver FUNDEB') }}</button>
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
                <div class="rounded-lg border border-rose-200 dark:border-rose-800/60 overflow-hidden">
                    <header class="px-4 py-3 bg-rose-50/80 dark:bg-rose-950/30 border-b border-rose-200/80 dark:border-rose-800/50">
                        <h4 class="text-sm font-semibold text-rose-950 dark:text-rose-100">{{ __('Principais problemas (impacto financeiro indicativo)') }}</h4>
                    </header>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($topProblems as $problem)
                            <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $problem['title'] ?? '' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
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
                <p class="text-xs">
                    <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Detalhar por escola em Discrepâncias') }}</button>
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
                        <article class="rounded-md border border-gray-200 dark:border-gray-700 border-l-4 {{ $mchip }} px-3 py-2 text-xs">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $mod['title'] ?? '' }}</p>
                            <p class="text-gray-500 dark:text-gray-400 mt-0.5">{{ $mod['reference'] ?? '' }}</p>
                            <p class="mt-1 text-gray-700 dark:text-gray-300 leading-relaxed">{{ $mod['situacao'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
                <p class="text-xs">
                    <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'fundeb')">{{ __('Abrir aba FUNDEB completa') }}</button>
                </p>
            </x-dashboard.consultoria-section>
        @endif
    @endif
</div>
