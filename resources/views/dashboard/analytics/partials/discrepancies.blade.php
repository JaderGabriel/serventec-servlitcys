@props(['discrepanciesData', 'yearFilterReady' => false, 'chartExportContext' => []])

@php
    use App\Support\Dashboard\ConsultoriaFlow;

    $d = is_array($discrepanciesData) ? $discrepanciesData : [];
    $summary = is_array($d['summary'] ?? null) ? $d['summary'] : [];
    $checks = is_array($d['checks'] ?? null) ? $d['checks'] : [];
    $dimensions = is_array($d['dimensions'] ?? null) ? $d['dimensions'] : [];
    $errosCriticos = array_values(array_filter($checks, static fn (array $c): bool => ! empty($c['is_erro'])));
    $demaisChecks = array_values(array_filter($checks, static fn (array $c): bool => empty($c['is_erro'])));
    $chartResumo = is_array($d['chart_resumo'] ?? null) ? $d['chart_resumo'] : null;
    $chartFinanceiro = is_array($d['chart_financeiro'] ?? null) ? $d['chart_financeiro'] : null;
    $fundingRef = is_array($d['funding_reference'] ?? null) ? $d['funding_reference'] : null;
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
        ['label' => __('Escolas afetadas'), 'value' => number_format((int) ($summary['escolas_afetadas'] ?? 0)), 'tone' => 'indigo'],
    ];
    $publicSources = is_array($d['public_data_sources'] ?? null) ? $d['public_data_sources'] : [];
    $hasPublicSources = count($publicSources['categories'] ?? []) > 0;
    $flowSteps = ConsultoriaFlow::numberedSteps([
        ['label' => __('Prioridades'), 'anchor' => 'disc-prioridades'],
        ['label' => __('Referências'), 'anchor' => 'disc-referencias', 'visible' => count($pillars) > 0],
        ['label' => __('Fontes públicas'), 'anchor' => 'disc-fontes-publicas', 'visible' => $hasPublicSources],
        ['label' => __('Mapa de rotinas'), 'anchor' => 'disc-mapa', 'visible' => count($dimensions) > 0],
        ['label' => __('Detalhe por escola'), 'anchor' => 'disc-detalhe', 'visible' => count($checks) > 0],
    ]);
    $discStep = ConsultoriaFlow::stepMap($flowSteps);
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo e aplique os filtros para executar as rotinas de discrepâncias.') }}
        </p>
    @else
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="rounded-lg border border-rose-200 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/20 px-4 py-3 text-sm space-y-2 flex-1">
                <h2 class="font-semibold text-rose-950 dark:text-rose-100">{{ __('Discrepâncias e Erros de cadastro') }}</h2>
                <p class="leading-relaxed text-rose-900/95 dark:text-rose-200/95">{{ $d['intro'] ?? '' }}</p>
                <p class="text-xs text-rose-800/90 dark:text-rose-300/90">
                    <span class="font-medium">{{ __('Contexto') }}:</span>
                    {{ $d['city_name'] ?? '' }}
                    @if (filled($d['year_label'] ?? null))
                        — {{ $d['year_label'] }}
                    @endif
                    @if (($d['total_matriculas'] ?? null) !== null)
                        · {{ __('Matrículas ativas no filtro:') }}
                        <span class="tabular-nums font-medium">{{ number_format((int) $d['total_matriculas']) }}</span>
                    @endif
                    @if ($fundingRef !== null && isset($fundingRef['vaa_label']))
                        · {{ __('VAAF referência:') }} <span class="font-medium">{{ $fundingRef['vaa_label'] }}</span>
                    @endif
                </p>
            </div>
            <div class="shrink-0">
                <x-dashboard.funding-loss-conditions-button :activeCheckIds="$activeCheckIds" />
            </div>
        </div>

        @if (filled($d['funding_aviso'] ?? null))
            <p class="text-xs text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700 bg-amber-50/70 dark:bg-amber-950/30 rounded-md px-3 py-2 leading-relaxed">
                {{ $d['funding_aviso'] }}
            </p>
        @endif

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $d['footnote'] ?? '' }}
        </p>

        <x-dashboard.consultoria-flow-nav :steps="$flowSteps" tone="rose" />

        @if (! empty($d['error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ $d['error'] }}
            </div>
        @endif

        @if (! empty($d['notes']))
            <div class="rounded-md bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-600 px-4 py-3 text-xs text-slate-700 dark:text-slate-300 space-y-1.5">
                @foreach ($d['notes'] as $note)
                    <p>{{ $note }}</p>
                @endforeach
            </div>
        @endif

        <x-dashboard.consultoria-section
            :step="$discStep['disc-prioridades'] ?? null"
            anchor="disc-prioridades"
            :title="__('Prioridades e impacto')"
            :subtitle="__('Resumo financeiro indicativo e rotinas com maior gravidade.')"
        >
            @if ($showKpis)
                <x-dashboard.consultoria-kpi-grid :items="$discKpis" />
                @if ($fundingMet !== null)
                    <x-dashboard.consultoria-funding-explanation
                        :metodologia="$fundingMet"
                        :resumo="$fundingResumo"
                        class="mt-2"
                    />
                @endif
            @endif

            @if (count($errosCriticos) > 0 || count($priorityDims) > 0)
                <div class="rounded-lg border-2 border-red-500/80 dark:border-red-600 bg-red-50/50 dark:bg-red-950/30 px-4 py-3 space-y-2">
                    <h4 class="text-sm font-bold text-red-900 dark:text-red-100 uppercase tracking-wide">{{ __('Erros críticos') }}</h4>
                    @if (count($errosCriticos) > 0)
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
                    @endif
                    @if (count($priorityDims) > 0 && count($errosCriticos) === 0)
                        <ul class="text-xs text-red-900/95 dark:text-red-100 space-y-1.5">
                            @foreach (array_slice($priorityDims, 0, 6) as $dim)
                                <li class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-0.5">
                                    <span>{{ $dim['title'] ?? '' }}</span>
                                    <span class="tabular-nums font-semibold shrink-0 text-right">
                                        {{ number_format((int) ($dim['total'] ?? 0)) }} {{ __('ocorr.') }}
                                        · {{ __('perda') }} {{ $fmtBrl((float) ($dim['perda_estimada_anual'] ?? 0)) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="text-[11px] text-red-800/90 dark:text-red-200/90">{{ __('Impacto elevado em Censo, FUNDEB ou integridade da matrícula.') }}</p>
                </div>
            @endif

            @if (count($atencaoDims) > 0)
                <div class="rounded-lg border border-amber-400/80 dark:border-amber-600 bg-amber-50/40 dark:bg-amber-950/25 px-4 py-3 space-y-2">
                    <h4 class="text-sm font-bold text-amber-900 dark:text-amber-100 uppercase tracking-wide">{{ __('Pontos de atenção') }}</h4>
                    <ul class="text-xs text-amber-950/95 dark:text-amber-100 space-y-1.5">
                        @foreach (array_slice($atencaoDims, 0, 8) as $dim)
                            <li class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-0.5">
                                <span>{{ $dim['title'] ?? '' }}</span>
                                <span class="tabular-nums font-semibold shrink-0 text-right">
                                    {{ number_format((int) ($dim['total'] ?? 0)) }} {{ __('ocorr.') }}
                                    @if ((float) ($dim['perda_estimada_anual'] ?? 0) > 0)
                                        · {{ __('perda est.') }} {{ $fmtBrl((float) $dim['perda_estimada_anual']) }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="text-xs">
                <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'municipality_health')">{{ __('Ver consolidação no Diagnóstico Geral') }}</button>
            </p>
        </x-dashboard.consultoria-section>

        @if (count($pillars) > 0)
            <x-dashboard.consultoria-section
                :step="$discStep['disc-referencias'] ?? null"
                anchor="disc-referencias"
                :title="__('Referências FUNDEB / VAAR / Censo')"
                :subtitle="__('Contexto normativo e resumo municipal por pilar.')"
            >
                <ul class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-indigo-900/95 dark:text-indigo-200/90">
                    @foreach ($pillars as $pillar)
                        @php
                            $resumo = is_array($pillar['municipio_resumo'] ?? null) ? $pillar['municipio_resumo'] : [];
                            $resumoStatus = (string) ($resumo['status'] ?? 'ok');
                            $resumoBox = match ($resumoStatus) {
                                'danger' => 'border-rose-300/80 bg-rose-50/90 text-rose-950 dark:border-rose-700 dark:bg-rose-950/35 dark:text-rose-100',
                                'warning' => 'border-amber-300/80 bg-amber-50/90 text-amber-950 dark:border-amber-700 dark:bg-amber-950/35 dark:text-amber-100',
                                'neutral' => 'border-slate-300/80 bg-slate-50/90 text-slate-800 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-200',
                                default => 'border-emerald-300/80 bg-emerald-50/90 text-emerald-950 dark:border-emerald-700 dark:bg-emerald-950/35 dark:text-emerald-100',
                            };
                        @endphp
                        <li class="rounded-md border border-indigo-200/60 dark:border-indigo-700/50 bg-indigo-50/40 dark:bg-indigo-950/25 px-3 py-2 space-y-2">
                            <p class="font-semibold text-indigo-950 dark:text-indigo-100">{{ $pillar['titulo'] ?? '' }}</p>
                            <p class="leading-relaxed">{{ $pillar['descricao'] ?? '' }}</p>
                            <p class="text-[11px] leading-relaxed rounded-md border px-2 py-1.5 {{ $resumoBox }}">
                                <span class="font-semibold uppercase tracking-wide">{{ __('Resumo do município') }}:</span>
                                {{ $resumo['texto'] ?? '' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-dashboard.consultoria-section>
        @endif

        @if ($hasPublicSources)
            <x-dashboard.consultoria-section
                :step="$discStep['disc-fontes-publicas'] ?? null"
                anchor="disc-fontes-publicas"
                :title="__('Extração e relatórios oficiais')"
                :subtitle="__('FNDE, Tesouro, Simec e INEP — para cruzar com as estimativas desta aba.')"
            >
                <x-dashboard.consultoria-public-sources :catalog="$publicSources" :anchor="null" />
            </x-dashboard.consultoria-section>
        @endif

        @if (count($dimensions) > 0)
            <x-dashboard.consultoria-section
                :step="$discStep['disc-mapa'] ?? null"
                anchor="disc-mapa"
                :title="__('Mapa de rotinas')"
                :subtitle="__('Verde = sem pendência; cinza = indisponível; amarelo/vermelho = pendência (mesma base do Diagnóstico Geral).')"
            >
                <x-dashboard.consultoria-dimensions-grid :dimensions="$dimensions" :fmt-brl="$fmtBrl" />
            </x-dashboard.consultoria-section>
        @endif

        @if (count($checks) > 0)
            <x-dashboard.consultoria-section
                :step="$discStep['disc-detalhe'] ?? null"
                anchor="disc-detalhe"
                :title="__('Detalhe por escola')"
                :subtitle="__('Explicação, impacto, gráficos e localização por unidade.')"
            >
                @if ($chartFinanceiro !== null)
                    <x-dashboard.chart-panel
                        :chart="$chartFinanceiro"
                        exportFilename="discrepancias-financeiro"
                        :exportMeta="$chartExportContext"
                        :compact="false"
                        chartPanelId="chart-discrepancias-financeiro"
                        panelTone="amber"
                    />
                @endif

                @if ($chartResumo !== null)
                    <x-dashboard.chart-panel
                        :chart="$chartResumo"
                        exportFilename="discrepancias-resumo"
                        :exportMeta="$chartExportContext"
                        :compact="false"
                        chartPanelId="chart-discrepancias-resumo"
                        panelTone="indigo"
                    />
                @endif

                <div class="space-y-6">
                    @foreach (array_merge($errosCriticos, $demaisChecks) as $idx => $check)
                        @php
                            $isErro = ! empty($check['is_erro']);
                            $ring = match (true) {
                                $isErro => 'border-l-red-600 bg-red-50/70 dark:bg-red-950/40 ring-2 ring-red-400/40',
                                ($check['status'] ?? '') === 'warning' => 'border-l-amber-500 bg-amber-50/35 dark:bg-amber-950/20',
                                default => 'border-l-slate-400 bg-slate-50/40 dark:bg-slate-900/30',
                            };
                            $badge = match ($check['status'] ?? 'neutral') {
                                'danger' => 'bg-red-100 text-red-900 dark:bg-red-900/50 dark:text-red-100',
                                'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                                default => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100',
                            };
                            $vaarRefs = is_array($check['vaar_refs'] ?? null) ? $check['vaar_refs'] : [];
                        @endphp
                        <article class="rounded-lg border border-gray-200 dark:border-gray-700 border-l-4 {{ $ring }} shadow-sm overflow-hidden">
                            <header class="px-4 py-3 border-b border-gray-200/80 dark:border-gray-600/80 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $check['title'] ?? '' }}</h3>
                                    <p class="mt-1 text-sm tabular-nums text-gray-700 dark:text-gray-300">
                                        {{ __('Total: :n', ['n' => number_format((int) ($check['total'] ?? 0))]) }}
                                        @if (($check['pct_rede'] ?? null) !== null)
                                            <span class="text-gray-500 dark:text-gray-400">({{ number_format((float) $check['pct_rede'], 1, ',', '.') }}% {{ __('da rede') }})</span>
                                        @endif
                                    </p>
                                    <p class="mt-1 text-sm font-medium text-orange-700 dark:text-orange-300 tabular-nums">
                                        {{ __('Perda estimada:') }} {{ $fmtBrl((float) ($check['perda_estimada_anual'] ?? 0)) }}
                                        <span class="text-gray-500 dark:text-gray-400 font-normal">·</span>
                                        {{ __('Ganho potencial:') }} {{ $fmtBrl((float) ($check['ganho_potencial_anual'] ?? 0)) }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-1.5 shrink-0">
                                    @if ($isErro)
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-red-600 text-white">{{ __('Erro') }}</span>
                                    @endif
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium {{ $badge }}">
                                        {{ $check['consultoria_prioridade'] ?? match ($check['severity'] ?? '') {
                                            'danger' => __('Alta prioridade'),
                                            'warning' => __('Média prioridade'),
                                            default => __('Verificar'),
                                        } }}
                                    </span>
                                </span>
                            </header>
                            <div class="px-4 py-3 space-y-4 text-sm text-gray-700 dark:text-gray-300">
                                @if (count($vaarRefs) > 0)
                                    <p class="text-xs text-indigo-800 dark:text-indigo-200">
                                        <span class="font-semibold">{{ __('Eixos:') }}</span>
                                        {{ implode(' · ', $vaarRefs) }}
                                    </p>
                                @endif
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('O que é') }}</p>
                                    <p class="leading-relaxed">{{ $check['explanation'] ?? '' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300 mb-1">{{ __('Impacto financeiro / Censo') }}</p>
                                    <p class="leading-relaxed">{{ $check['impact'] ?? '' }}</p>
                                    @if (is_array($check['funding_explicacao'] ?? null))
                                        <div class="mt-2">
                                            <x-dashboard.consultoria-funding-explanation :explicacao="$check['funding_explicacao']" />
                                        </div>
                                    @elseif (filled($check['funding_formula'] ?? null))
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 italic">{{ $check['funding_formula'] }}</p>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300 mb-1">{{ __('Correção possível') }}</p>
                                    <p class="leading-relaxed">{{ $check['correction'] ?? '' }}</p>
                                </div>
                                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                                    @if (! empty($check['chart_financeiro']))
                                        <x-dashboard.chart-panel :chart="$check['chart_financeiro']" :exportFilename="'discrepancia-fin-'.($check['id'] ?? $idx)" :exportMeta="$chartExportContext" :compact="true" :chartPanelId="'chart-discrep-fin-'.$idx" panelTone="amber" />
                                    @endif
                                    @if (! empty($check['chart_rede']))
                                        <x-dashboard.chart-panel :chart="$check['chart_rede']" :exportFilename="'discrepancia-rede-'.($check['id'] ?? $idx)" :exportMeta="$chartExportContext" :compact="true" :chartPanelId="'chart-discrep-rede-'.$idx" panelTone="indigo" />
                                    @endif
                                    @if (! empty($check['chart_escolas']))
                                        <x-dashboard.chart-panel :chart="$check['chart_escolas']" :exportFilename="'discrepancia-escolas-'.($check['id'] ?? $idx)" :exportMeta="$chartExportContext" :compact="true" :chartPanelId="'chart-discrep-esc-'.$idx" panelTone="indigo" />
                                    @endif
                                </div>
                                @if (! empty($check['school_rows']) && is_array($check['school_rows']))
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Onde ocorre (escola)') }}</p>
                                        <div class="overflow-x-auto max-h-72 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                            <table class="min-w-full text-xs text-left">
                                                <thead class="bg-gray-50 dark:bg-gray-900/60 sticky top-0">
                                                    <tr>
                                                        <th class="px-3 py-2 font-medium">{{ __('Unidade escolar') }}</th>
                                                        <th class="px-3 py-2 font-medium text-right">{{ __('Ocorrências') }}</th>
                                                        <th class="px-3 py-2 font-medium text-right">{{ __('Perda est.') }}</th>
                                                        <th class="px-3 py-2 font-medium text-right">{{ __('Ganho pot.') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                    @foreach ($check['school_rows'] as $row)
                                                        <tr>
                                                            <td class="px-3 py-1.5 break-words max-w-[18rem]">{{ $row['escola'] ?? '—' }}</td>
                                                            <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ number_format((int) ($row['total'] ?? 0)) }}</td>
                                                            @php
                                                                $unitPerda = (int) ($row['total'] ?? 0) > 0
                                                                    ? ((float) ($check['perda_estimada_anual'] ?? 0)) / (int) ($check['total'] ?? 1)
                                                                    : 0.0;
                                                            @endphp
                                                            <td class="px-3 py-1.5 text-right tabular-nums text-orange-700 dark:text-orange-300">{{ $fmtBrl($unitPerda * (int) ($row['total'] ?? 0)) }}</td>
                                                            <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $fmtBrl((float) ($row['ganho_potencial_anual'] ?? 0)) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-dashboard.consultoria-section>
        @elseif ($showKpis && count($pendenciaDims) > 0)
            <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ __('Sem detalhe por escola nesta base — consulte o mapa de rotinas ou o Diagnóstico Geral.') }}</p>
        @endif
    @endif
</div>
