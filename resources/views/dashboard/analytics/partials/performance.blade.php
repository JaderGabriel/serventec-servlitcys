@props(['performanceData', 'chartExportContext' => [], 'municipalityContext' => null, 'yearFilterReady' => true])

@php
    use App\Support\Dashboard\ConsultoriaFlow;

    $perfCharts = $performanceData['charts'] ?? [];
    if ($perfCharts === [] && ! empty($performanceData['chart'])) {
        $perfCharts = [$performanceData['chart']];
    }
    $inepPanel = is_array($performanceData['inep_panel'] ?? null) ? $performanceData['inep_panel'] : null;
    $saebSeries = is_array($performanceData['saeb_series'] ?? null) ? $performanceData['saeb_series'] : null;
    $saebCharts = is_array($saebSeries['charts'] ?? null) ? $saebSeries['charts'] : [];
    $idebCharts = is_array($saebSeries['ideb_charts'] ?? null) ? $saebSeries['ideb_charts'] : [];
    $saebSummary = is_array($saebSeries['summary'] ?? null) ? $saebSeries['summary'] : null;
    $inepSections = is_array($inepPanel['sections'] ?? null) ? $inepPanel['sections'] : [];
    $inepSaebSec = is_array($inepSections['saeb'] ?? null) ? $inepSections['saeb'] : null;
    $inepIdebSec = is_array($inepSections['ideb'] ?? null) ? $inepSections['ideb'] : null;
    $inepPneSec = is_array($inepSections['pne'] ?? null) ? $inepSections['pne'] : null;
    $hasSaeb = $saebSeries !== null && (
        ($saebSeries['error'] ?? null)
        || $saebCharts !== []
        || ($saebSeries['notes'] ?? []) !== []
        || ($saebSeries['source_hint'] ?? null)
        || ! empty($saebSeries['explicacao_modal'] ?? null)
        || ($saebSummary !== null && (($saebSummary['rede_lp_ultimo'] ?? null) !== null || ($saebSummary['rede_mat_ultimo'] ?? null) !== null || ! empty($saebSummary['pontos_municipais']) || ! empty($saebSummary['pontos_escola'])))
        || ! empty($saebSeries['school_table'])
        || ! empty($saebSeries['extra_charts'])
        || $inepSaebSec !== null
    );
    $hasIdeb = (
        $idebCharts !== []
        || ($saebSummary !== null && ($saebSummary['rede_ideb_ultimo'] ?? null) !== null)
        || $inepIdebSec !== null
        || $inepPneSec !== null
    );
    $hasPrioridades = ! empty($performanceData['kpis']) || ($performanceData['distorcao_pct'] ?? null) !== null;
    $hasIeducar = $perfCharts !== [] || ! empty($performanceData['rows']);

    $publicSources = is_array($performanceData['public_data_sources'] ?? null) ? $performanceData['public_data_sources'] : [];
    $hasPublicSources = count($publicSources['categories'] ?? []) > 0;
    $flowSteps = ConsultoriaFlow::numberedSteps([
        ['label' => __('Prioridades (rede)'), 'anchor' => 'perf-prioridades', 'visible' => $hasPrioridades],
        ['label' => __('SAEB'), 'anchor' => 'perf-saeb', 'visible' => $hasSaeb],
        ['label' => __('IDEB'), 'anchor' => 'perf-ideb', 'visible' => $hasIdeb],
        ['label' => __('Extração oficial'), 'anchor' => 'perf-fontes-publicas', 'visible' => $hasPublicSources],
        ['label' => __('Situação no i-Educar'), 'anchor' => 'perf-ieducar', 'visible' => $hasIeducar],
    ]);
    $perfStep = ConsultoriaFlow::stepMap($flowSteps);
    $saebExplicacao = is_array($saebSeries) ? ($saebSeries['explicacao_modal'] ?? null) : null;
@endphp

<div class="space-y-6">
    @include('dashboard.analytics.partials.tab-impact-strip', [
        'tab' => 'performance',
        'yearFilterReady' => $yearFilterReady,
        'municipalityContext' => $municipalityContext,
        'tabData' => ['performanceData' => $performanceData],
    ])
    <div class="rounded-lg border border-slate-200 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-900/40 px-4 py-3 text-sm">
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ __('Desempenho combina a rede no i-Educar com indicadores oficiais do INEP (IDEB e SAEB).') }}
            <button type="button" class="text-sky-700 dark:text-sky-300 hover:underline font-medium" x-on:click="$dispatch('set-analytics-tab', 'municipality_health')">{{ __('Diagnóstico') }}</button>
            ·
            <button type="button" class="text-sky-700 dark:text-sky-300 hover:underline font-medium" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Discrepâncias') }}</button>
        </p>
    </div>

    @if (count($flowSteps) > 0)
        <x-dashboard.consultoria-flow-nav :steps="$flowSteps" tone="slate" />
    @endif

    @if ($hasPrioridades)
        <x-dashboard.consultoria-section
            :step="$perfStep['perf-prioridades'] ?? null"
            anchor="perf-prioridades"
            :title="__('Prioridades na rede (i-Educar)')"
            :subtitle="__('Taxas de situação de matrícula e distorção idade/série no filtro actual.')"
        >
    @if (! empty($performanceData['kpis']))
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 items-stretch">
            @foreach ($performanceData['kpis'] as $kpi)
                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4 flex flex-col gap-2 min-h-[13rem]">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-snug">
                        {{ $kpi['chart_label'] ?? $kpi['label'] ?? '—' }}
                    </p>
                    <div class="text-[11px] text-gray-600 dark:text-gray-400 space-y-2 text-justify flex-1">
                        @if (! empty($kpi['formula']))
                            <p class="font-mono text-gray-700 dark:text-gray-300 leading-relaxed">{{ $kpi['formula'] }}</p>
                        @endif
                        @if (! empty($kpi['description']))
                            <p class="leading-relaxed">{{ $kpi['description'] }}</p>
                        @endif
                    </div>
                    <div class="mt-auto pt-3 border-t border-gray-200 dark:border-gray-600 space-y-1">
                        <p class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                            @if (array_key_exists('percent', $kpi) && is_numeric($kpi['percent']))
                                {{ number_format((float) $kpi['percent'], 1, ',', '.') }}%
                            @else
                                —
                            @endif
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-300 tabular-nums">
                            {{ isset($kpi['quantidade']) ? number_format((int) $kpi['quantidade']) : '—' }} {{ __('matrículas') }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
            @endif
            @if (($performanceData['distorcao_pct'] ?? null) !== null)
            <div class="rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50/80 dark:bg-sky-950/30 px-3 py-2 text-sm text-sky-900 dark:text-sky-100">
                {{ __('Distorção idade/série (rede)') }}:
                <span class="font-semibold">{{ number_format((float) $performanceData['distorcao_pct'], 1, ',', '.') }}%</span>
                <span class="text-xs text-sky-700 dark:text-sky-300">({{ __('critério de rede ou definição personalizada') }})</span>
            </div>
        @endif
        </x-dashboard.consultoria-section>
    @endif

    @if ($hasSaeb)
        <x-dashboard.consultoria-section
            :step="$perfStep['perf-saeb'] ?? null"
            anchor="perf-saeb"
            :title="__('SAEB (INEP)')"
            :subtitle="__('Desempenho em Língua Portuguesa e Matemática — séries oficiais do município.')"
        >
            @if (! empty($inepPanel['sql_error']))
                <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ $inepPanel['sql_error'] }}
                </div>
            @endif

            @if ($inepSaebSec !== null)
                @php
                    $sec = $inepSaebSec;
                    $border = 'border-l-emerald-500 border-slate-200 dark:border-slate-700 dark:border-l-emerald-400';
                    $badge = 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-100';
                @endphp
                <div class="rounded-lg border border-l-4 {{ $border }} bg-white dark:bg-slate-900/50 p-4 shadow-sm flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badge }}">{{ __('SAEB') }}</span>
                        <p class="text-sm font-semibold text-serv-navy dark:text-slate-100 leading-snug">{{ $sec['title'] }}</p>
                    </div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">{{ $sec['intro'] }}</p>
                    @if (! empty($sec['items']))
                        <ul class="text-xs space-y-2 text-slate-800 dark:text-slate-200 border-t border-slate-100 dark:border-slate-700/80 pt-2">
                            @foreach ($sec['items'] as $item)
                                <li class="leading-snug">
                                    <span class="font-medium">{{ $item['label'] ?? '—' }}</span>
                                    @if (($item['valor'] ?? null) !== null && is_numeric($item['valor']))
                                        <span class="tabular-nums text-sky-800 dark:text-sky-300"> — {{ number_format((float) $item['valor'], 2, ',', '.') }}</span>
                                        @if (! empty($item['unidade']))
                                            <span class="text-slate-500"> ({{ $item['unidade'] }})</span>
                                        @endif
                                    @else
                                        <span class="text-slate-500"> — {{ __('sem valor numérico') }}</span>
                                    @endif
                                    @if (! empty($item['referencia']))
                                        <span class="text-slate-500"> · {{ __('ref.') }} {{ $item['referencia'] }}</span>
                                    @endif
                                    @if (! empty($item['detalhe']))
                                        <p class="text-[10px] text-slate-500 mt-0.5">{{ $item['detalhe'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $sec['empty_hint'] }}</p>
                    @endif
                </div>
            @endif

            @if ($saebSeries !== null && ($saebSeries['error'] ?? null))
                <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ $saebSeries['error'] }}
                </div>
            @endif

            @if ($saebSeries !== null && ($saebCharts !== [] || ($saebSeries['notes'] ?? []) !== [] || ($saebSeries['source_hint'] ?? null) || ! empty($saebExplicacao) || $saebSummary !== null || ! empty($saebSeries['school_table']) || ! empty($saebSeries['extra_charts'])))
                <div class="rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900/40 shadow-sm overflow-hidden" x-data="{ saebHelpOpen: false }">
                    <div class="border-b border-slate-200/90 dark:border-slate-700/80 bg-slate-50/90 dark:bg-slate-900/60 px-4 py-4 sm:px-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <div class="min-w-0 space-y-1.5">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-emerald-700 dark:text-emerald-300">{{ __('Importação oficial') }}</p>
                                <h3 class="text-base font-semibold font-display text-serv-navy dark:text-slate-100">
                                    {{ __('Séries históricas do SAEB') }}
                                </h3>
                                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl">
                                    {{ __('Proficiência em Língua Portuguesa e Matemática por etapa, conforme divulgação do INEP. Com ano letivo seleccionado, a série vai até esse ano (inclusive).') }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0 items-center justify-end">
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200/90 bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-100">
                                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-600" aria-hidden="true"></span>
                                    {{ __('Resultado final') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-amber-200/90 bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-950 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-100">
                                    <span class="inline-block h-0 w-0 border-l-[4px] border-r-[4px] border-b-[7px] border-l-transparent border-r-transparent border-b-amber-600" aria-hidden="true"></span>
                                    {{ __('Preliminar') }}
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                                    @click="saebHelpOpen = true"
                                >
                                    <svg class="h-4 w-4 text-slate-500 dark:text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    {{ __('Como ler estes dados') }}
                                </button>
                            </div>
                        </div>
                        @if (! empty($saebSeries['notes']) && is_array($saebSeries['notes']))
                            <ul class="mt-3 text-[11px] text-slate-600 dark:text-slate-400 list-disc pl-5 space-y-1">
                                @foreach ($saebSeries['notes'] as $sn)
                                    <li>{{ $sn }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($saebSeries['source_hint']))
                            <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-500">{{ $saebSeries['source_hint'] }}</p>
                        @endif
                        @if ($saebSummary !== null && $saebSummary !== [])
                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Município') }}</p>
                                    <p class="mt-1 text-xs text-slate-900 dark:text-slate-100">
                                        @if (! empty($saebSummary['municipio_nome']))
                                            {{ $saebSummary['municipio_nome'] }}
                                            @if (! empty($saebSummary['municipio_ibge']))
                                                <span class="text-slate-500 dark:text-slate-400">· IBGE {{ $saebSummary['municipio_ibge'] }}</span>
                                            @endif
                                        @else
                                            {{ __('Cadastro local #:id', ['id' => (string) ($saebSummary['city_id_local'] ?? '—')]) }}
                                        @endif
                                    </p>
                                </div>
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Cobertura importada') }}</p>
                                    <p class="mt-1 text-xs tabular-nums text-slate-900 dark:text-slate-100">
                                        {{ __('Rede: :m · Escolas: :e (:s unidades)', ['m' => (string) ($saebSummary['pontos_municipais'] ?? '0'), 'e' => (string) ($saebSummary['pontos_escola'] ?? '0'), 's' => (string) ($saebSummary['escolas_distintas'] ?? '0')]) }}
                                    </p>
                                </div>
                                <div class="rounded-lg border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/25 px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('Último SAEB (rede)') }}</p>
                                    <p class="mt-1 text-xs tabular-nums text-emerald-950 dark:text-emerald-100">
                                        @if (($saebSummary['rede_lp_ultimo'] ?? null) !== null && ($saebSummary['rede_mat_ultimo'] ?? null) !== null)
                                            {{ __('LP :lp · MAT :mat', [
                                                'lp' => number_format((float) $saebSummary['rede_lp_ultimo'], 1, ',', '.'),
                                                'mat' => number_format((float) $saebSummary['rede_mat_ultimo'], 1, ',', '.'),
                                            ]) }}
                                            @if (($saebSummary['rede_gap_lp_menos_mat'] ?? null) !== null)
                                                <span class="block mt-0.5 text-[11px] text-emerald-800/85 dark:text-emerald-200/85">{{ __('Diferença LP − MAT: :g', ['g' => number_format((float) $saebSummary['rede_gap_lp_menos_mat'], 1, ',', '.')]) }}</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </p>
                                </div>
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Leitura para a consultoria') }}</p>
                                    <p class="mt-1 text-[11px] text-slate-700 dark:text-slate-300 leading-snug">{{ $saebSummary['decisao_nota'] ?? '—' }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    @if (! empty($saebSeries['school_table']) && is_array($saebSeries['school_table']) && ($saebSeries['school_table'] ?? []) !== [])
                        <div class="px-3 sm:px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-white/80 dark:bg-slate-900/30">
                            <p class="text-xs font-semibold text-serv-navy dark:text-slate-100">{{ __('Escolas com resultado no último ano disponível') }}</p>
                            <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                <table class="min-w-full text-xs text-left">
                                    <thead class="bg-slate-50 dark:bg-slate-900/70 text-[10px] uppercase tracking-wide text-slate-600 dark:text-slate-300">
                                        <tr>
                                            <th class="px-3 py-2">{{ __('Escola') }}</th>
                                            <th class="px-3 py-2 tabular-nums">{{ __('Código INEP') }}</th>
                                            <th class="px-3 py-2 tabular-nums">{{ __('LP') }}</th>
                                            <th class="px-3 py-2 tabular-nums">{{ __('MAT') }}</th>
                                            <th class="px-3 py-2 tabular-nums">{{ __('LP − MAT') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-900 dark:text-slate-100">
                                        @foreach ($saebSeries['school_table'] as $row)
                                            <tr>
                                                <td class="px-3 py-2 max-w-[14rem]">{{ $row['nome'] ?? '—' }}</td>
                                                <td class="px-3 py-2 tabular-nums">{{ $row['escola_id'] ?? '—' }}</td>
                                                <td class="px-3 py-2 tabular-nums">
                                                    @if (($row['lp_pct'] ?? null) !== null)
                                                        {{ number_format((float) $row['lp_pct'], 1, ',', '.') }}@if (! empty($row['lp_ano'])) <span class="text-slate-500">({{ $row['lp_ano'] }})</span>@endif
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 tabular-nums">
                                                    @if (($row['mat_pct'] ?? null) !== null)
                                                        {{ number_format((float) $row['mat_pct'], 1, ',', '.') }}@if (! empty($row['mat_ano'])) <span class="text-slate-500">({{ $row['mat_ano'] }})</span>@endif
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 tabular-nums">{{ ($row['gap_lp_menos_mat'] ?? null) !== null ? number_format((float) $row['gap_lp_menos_mat'], 1, ',', '.') : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                    @if ($saebCharts !== [] || (! empty($saebSeries['extra_charts']) && is_array($saebSeries['extra_charts'])))
                        <div class="p-3 sm:p-4 bg-slate-50/40 dark:bg-slate-950/20">
                            <p class="mb-3 text-xs font-semibold text-serv-navy dark:text-slate-100">{{ __('Gráficos por disciplina e etapa') }}</p>
                            <div class="perf-saeb-charts grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 items-stretch min-w-0 [&>.chart-panel-host]:min-w-0 [&>.chart-panel-host]:h-full">
                                @foreach ($saebCharts as $sidx => $saebChart)
                                    <x-dashboard.chart-panel
                                        :chart="$saebChart"
                                        :exportFilename="'desempenho-saeb-'.$sidx"
                                        :exportMeta="$chartExportContext"
                                        :compact="true"
                                        :chartPanelId="'chart-saeb-' . $sidx"
                                        :suppressTitle="false"
                                    />
                                @endforeach
                                @if (! empty($saebSeries['extra_charts']) && is_array($saebSeries['extra_charts']))
                                    @foreach ($saebSeries['extra_charts'] as $eidx => $saebExtra)
                                        <x-dashboard.chart-panel
                                            :chart="$saebExtra"
                                            :exportFilename="'desempenho-saeb-extra-' . $eidx"
                                            :exportMeta="$chartExportContext"
                                            :compact="true"
                                            :chartPanelId="'chart-saeb-extra-' . $eidx"
                                            :suppressTitle="false"
                                        />
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @elseif (empty($saebSeries['error']) && empty($saebSummary) && empty($saebSeries['school_table']))
                        <div class="px-4 py-6 text-sm text-slate-600 dark:text-slate-400">
                            {{ __('Ainda não há séries SAEB importadas para este município. Em Administração → Sincronizações → Pedagógicas, importe a divulgação oficial.') }}
                        </div>
                    @endif

            <template x-teleport="body">
                <div
                    x-show="saebHelpOpen"
                    x-transition.opacity.duration.150ms
                    @keydown.escape.window="saebHelpOpen = false"
                    class="fixed inset-0 z-[250] flex items-center justify-center p-3 sm:p-4"
                    style="display: none;"
                    x-cloak
                >
                    <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="saebHelpOpen = false" aria-hidden="true"></div>
                    <div
                        class="relative z-10 flex max-h-[95vh] w-full min-h-0 max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-600 dark:bg-slate-800"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="saeb-import-help-title"
                    >
                        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                            <h3 id="saeb-import-help-title" class="pr-2 text-base font-semibold text-serv-navy dark:text-slate-100">
                                {{ is_array($saebExplicacao) && ! empty($saebExplicacao['titulo']) ? $saebExplicacao['titulo'] : __('Como ler SAEB e IDEB neste painel') }}
                            </h3>
                            <button
                                type="button"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-700 dark:hover:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                @click="saebHelpOpen = false"
                                title="{{ __('Fechar') }}"
                                aria-label="{{ __('Fechar') }}"
                            >
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-4 py-4 text-sm text-slate-700 dark:text-slate-300 space-y-5 leading-relaxed [scrollbar-gutter:stable]">
                            @if (is_array($saebExplicacao) && ! empty($saebExplicacao['secoes']))
                                @if (! empty($saebExplicacao['gerado_em']))
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Texto gerado na importação: :d', ['d' => (string) $saebExplicacao['gerado_em']]) }}
                                        @if (! empty($saebExplicacao['ultima_sincronizacao_em']) && (string) $saebExplicacao['ultima_sincronizacao_em'] !== (string) ($saebExplicacao['gerado_em'] ?? ''))
                                            · {{ __('Última sincronização sem alteração dos pontos: :d', ['d' => (string) $saebExplicacao['ultima_sincronizacao_em']]) }}
                                        @endif
                                    </p>
                                @endif
                                @foreach ($saebExplicacao['secoes'] as $sec)
                                    @if (is_array($sec))
                                        <div>
                                            @if (! empty($sec['titulo']))
                                                <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ $sec['titulo'] }}</h4>
                                            @endif
                                            @if (! empty($sec['paragrafos']) && is_array($sec['paragrafos']))
                                                @foreach ($sec['paragrafos'] as $par)
                                                    <p class="mt-2">{{ $par }}</p>
                                                @endforeach
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                                @if (! empty($saebExplicacao['links']) && is_array($saebExplicacao['links']))
                                    <div>
                                        <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('Links oficiais') }}</h4>
                                        <ul class="mt-2 list-disc list-outside space-y-2 pl-5">
                                            @foreach ($saebExplicacao['links'] as $lnk)
                                                @if (is_array($lnk) && ! empty($lnk['url']))
                                                    <li>
                                                        <a href="{{ $lnk['url'] }}" class="font-medium text-sky-700 dark:text-sky-300 underline break-all" target="_blank" rel="noopener noreferrer">{{ $lnk['label'] ?? $lnk['url'] }}</a>
                                                        @if (! empty($lnk['nota']))
                                                            <span class="text-slate-600 dark:text-slate-400"> — {{ $lnk['nota'] }}</span>
                                                        @endif
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @else
                                <p class="text-slate-600 dark:text-slate-400">
                                    {{ __('O SAEB avalia aprendizagem; o IDEB combina desempenho e fluxo escolar. Os gráficos desta aba usam a importação pedagógica (divulgação INEP). Para detalhes da fonte, abra Administração → Sincronizações → Pedagógicas.') }}
                                </p>
                                <p class="mt-3">
                                    <a href="https://www.gov.br/inep/pt-br/areas-de-atuacao/avaliacoes-e-exames-educacionais/saeb" class="font-medium text-sky-700 dark:text-sky-300 underline break-all" target="_blank" rel="noopener noreferrer">{{ __('SAEB no portal do INEP') }}</a>
                                </p>
                            @endif
                        </div>
                        <div class="shrink-0 border-t border-slate-100 px-4 py-3 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40">
                            <button
                                type="button"
                                class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                @click="saebHelpOpen = false"
                            >
                                {{ __('Fechar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
                </div>
            @endif
        </x-dashboard.consultoria-section>
    @endif

    @if ($hasIdeb)
        <x-dashboard.consultoria-section
            :step="$perfStep['perf-ideb'] ?? null"
            anchor="perf-ideb"
            :title="__('IDEB (INEP)')"
            :subtitle="__('Índice de Desenvolvimento da Educação Básica por etapa — séries oficiais do município.')"
        >
            @if ($inepIdebSec !== null || $inepPneSec !== null)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    @foreach ([
                        'ideb' => ['sec' => $inepIdebSec, 'tone' => 'sky', 'short' => __('IDEB')],
                        'pne' => ['sec' => $inepPneSec, 'tone' => 'amber', 'short' => __('PNE')],
                    ] as $secKey => $secMeta)
                        @php $sec = $secMeta['sec']; @endphp
                        @if ($sec !== null)
                            @php
                                $tone = $secMeta['tone'];
                                $border = $tone === 'amber'
                                    ? 'border-l-amber-500 border-slate-200 dark:border-slate-700 dark:border-l-amber-400'
                                    : 'border-l-sky-500 border-slate-200 dark:border-slate-700 dark:border-l-sky-400';
                                $badge = $tone === 'amber'
                                    ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-100'
                                    : 'bg-sky-100 text-sky-900 dark:bg-sky-950/60 dark:text-sky-100';
                            @endphp
                            <div class="rounded-lg border border-l-4 {{ $border }} bg-white dark:bg-slate-900/50 p-4 shadow-sm flex flex-col gap-2 min-h-[10rem]">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badge }}">{{ $secMeta['short'] }}</span>
                                    <p class="text-sm font-semibold text-serv-navy dark:text-slate-100 leading-snug">{{ $sec['title'] }}</p>
                                </div>
                                <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed flex-1">{{ $sec['intro'] }}</p>
                                @if (! empty($sec['items']))
                                    <ul class="text-xs space-y-2 text-slate-800 dark:text-slate-200 border-t border-slate-100 dark:border-slate-700/80 pt-2">
                                        @foreach ($sec['items'] as $item)
                                            <li class="leading-snug">
                                                <span class="font-medium">{{ $item['label'] ?? '—' }}</span>
                                                @if (($item['valor'] ?? null) !== null && is_numeric($item['valor']))
                                                    <span class="tabular-nums text-sky-800 dark:text-sky-300"> — {{ number_format((float) $item['valor'], 2, ',', '.') }}</span>
                                                    @if (! empty($item['unidade']))
                                                        <span class="text-slate-500"> ({{ $item['unidade'] }})</span>
                                                    @endif
                                                @else
                                                    <span class="text-slate-500"> — {{ __('sem valor numérico') }}</span>
                                                @endif
                                                @if (! empty($item['referencia']))
                                                    <span class="text-slate-500"> · {{ __('ref.') }} {{ $item['referencia'] }}</span>
                                                @endif
                                                @if (! empty($item['detalhe']))
                                                    <p class="text-[10px] text-slate-500 mt-0.5">{{ $item['detalhe'] }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $sec['empty_hint'] }}</p>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ($idebCharts !== [] || ($saebSummary !== null && ($saebSummary['rede_ideb_ultimo'] ?? null) !== null))
                <div class="rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900/40 shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200/90 dark:border-slate-700/80 bg-slate-50/90 dark:bg-slate-900/60 px-4 py-4 sm:px-5">
                        <div class="min-w-0 space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300">{{ __('Importação oficial') }}</p>
                            <h3 class="text-base font-semibold font-display text-serv-navy dark:text-slate-100">
                                {{ __('Séries históricas do IDEB') }}
                            </h3>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl">
                                {{ __('Índice por etapa (anos iniciais, finais e ensino médio), conforme divulgação do INEP. Com ano letivo seleccionado, a série vai até esse ano (inclusive).') }}
                            </p>
                        </div>
                        @if (($saebSummary['rede_ideb_ultimo'] ?? null) !== null)
                            <div class="mt-4 max-w-xs">
                                <div class="rounded-lg border border-sky-200/80 dark:border-sky-800/60 bg-sky-50/50 dark:bg-sky-950/25 px-3 py-2.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200">{{ __('Último IDEB (rede)') }}</p>
                                    <p class="mt-1 text-sm font-semibold tabular-nums text-sky-950 dark:text-sky-100">
                                        {{ number_format((float) $saebSummary['rede_ideb_ultimo'], 1, ',', '.') }}
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                    @if ($idebCharts !== [])
                        <div class="p-3 sm:p-4 bg-slate-50/40 dark:bg-slate-950/20">
                            <p class="mb-3 text-xs font-semibold text-serv-navy dark:text-slate-100">{{ __('Gráficos por etapa') }}</p>
                            <div class="perf-saeb-charts grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 items-stretch min-w-0 [&>.chart-panel-host]:min-w-0 [&>.chart-panel-host]:h-full">
                                @foreach ($idebCharts as $iidx => $idebChart)
                                    <x-dashboard.chart-panel
                                        :chart="$idebChart"
                                        :exportFilename="'desempenho-ideb-'.$iidx"
                                        :exportMeta="$chartExportContext"
                                        :compact="true"
                                        :chartPanelId="'chart-ideb-' . $iidx"
                                        :suppressTitle="false"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </x-dashboard.consultoria-section>
    @endif

    @if ($hasIeducar)
        <x-dashboard.consultoria-section
            :step="$perfStep['perf-ieducar'] ?? null"
            anchor="perf-ieducar"
            :title="__('Situação de matrícula (i-Educar)')"
            :subtitle="__('Taxas, gráficos e tabela de situações no filtro actual.')"
        >
            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                {{ __('Cada taxa = (matrículas na categoria) ÷ (total de matrículas ativas no filtro) × 100. Os filtros de ano, escola, curso e turno aplicam-se pela turma. Entre os indicadores destacam-se reclassificação (cód. 10), abandono (11), remanejamento (16) e taxas de aprovação e reprovação. O gráfico de distorção idade/série, quando presente, segue o critério INEP (idade à 31/03 e limite etário + 2 anos).') }}
            </p>

    @if (! empty($performanceData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $performanceData['error'] }}
        </div>
    @endif
    @if (! empty($performanceData['message']))
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">{{ $performanceData['message'] }}</p>
    @endif

    @if (! empty($performanceData['distorcao_note']))
        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2">{{ $performanceData['distorcao_note'] }}</p>
    @endif

    @if (filled(data_get($performanceData, 'kpi_meta.denominador_texto')))
        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ data_get($performanceData, 'kpi_meta.denominador_texto') }}
        </p>
    @endif
    @if (filled(data_get($performanceData, 'kpi_meta.alerta_ano_encerrado')))
        <div class="rounded-md bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 px-3 py-2 text-sm text-amber-950 dark:text-amber-100">
            {{ data_get($performanceData, 'kpi_meta.alerta_ano_encerrado') }}
        </div>
    @endif

    @if ($perfCharts !== [])
        @php
            $pairKeyPerf = 'desempenho-taxas-situacao';
            $perfFragments = [];
            $pi = 0;
            $nPerf = count($perfCharts);
            while ($pi < $nPerf) {
                $ch = $perfCharts[$pi];
                $pid = $ch['pair_in_row'] ?? null;
                if ($pid === $pairKeyPerf) {
                    $group = [$ch];
                    $pi++;
                    if ($pi < $nPerf && (($perfCharts[$pi]['pair_in_row'] ?? null) === $pairKeyPerf)) {
                        $group[] = $perfCharts[$pi];
                        $pi++;
                    }
                    $perfFragments[] = ['type' => 'pair', 'charts' => $group];
                } else {
                    $perfFragments[] = ['type' => 'single', 'charts' => [$ch]];
                    $pi++;
                }
            }
            $globalChartIdx = 0;
        @endphp
        <div class="grid grid-cols-1 gap-6">
            @foreach ($perfFragments as $frag)
                @if ($frag['type'] === 'pair' && count($frag['charts']) === 2)
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-stretch min-w-0">
                        @foreach ($frag['charts'] as $chart)
                            @php
                                $panelPayload = $chart;
                                unset($panelPayload['pair_in_row']);
                            @endphp
                            <x-dashboard.chart-panel
                                :chart="$panelPayload"
                                :exportFilename="'desempenho-'.$globalChartIdx"
                                :exportMeta="$chartExportContext"
                                :compact="true"
                                :chartPanelId="'chart-desp-'.$globalChartIdx"
                                :suppressTitle="false"
                            />
                            @php $globalChartIdx++; @endphp
                        @endforeach
                    </div>
                @else
                    @foreach ($frag['charts'] as $chart)
                        @php
                            $panelPayload = $chart;
                            unset($panelPayload['pair_in_row']);
                        @endphp
                        <x-dashboard.chart-panel
                            :chart="$panelPayload"
                            :exportFilename="'desempenho-'.$globalChartIdx"
                            :exportMeta="$chartExportContext"
                            :compact="false"
                            :chartPanelId="'chart-desp-'.$globalChartIdx"
                            :suppressTitle="false"
                        />
                        @php $globalChartIdx++; @endphp
                    @endforeach
                @endif
            @endforeach
        </div>
    @elseif (empty($performanceData['error']) && empty($performanceData['message']) && $perfCharts === [] && empty($performanceData['kpis'] ?? []) && empty($performanceData['inep_panel'] ?? null) && empty(($performanceData['saeb_series']['charts'] ?? [])) && empty(($performanceData['saeb_series']['extra_charts'] ?? [])) && empty(($performanceData['saeb_series']['summary'] ?? null)) && empty(($performanceData['saeb_series']['school_table'] ?? [])) && empty(($performanceData['saeb_series']['explicacao_modal'] ?? null)))
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center text-sm text-gray-400 dark:text-gray-500">
            {{ __('Sem dados para desempenho com os filtros atuais.') }}
        </div>
    @endif

    @if (! empty($performanceData['rows']))
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Situação') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Quantidade') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($performanceData['rows'] as $row)
                        <tr>
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $row['label'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ $row['quantidade'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
        </x-dashboard.consultoria-section>
    @endif

    @if ($hasPublicSources)
        <x-dashboard.consultoria-section
            :step="$perfStep['perf-fontes-publicas'] ?? null"
            anchor="perf-fontes-publicas"
            :title="__('Downloads e microdados (INEP)')"
            :subtitle="__('Portais oficiais para análises que complementam as séries já importadas no painel.')"
        >
            <x-dashboard.consultoria-public-sources :catalog="$publicSources" :anchor="null" />
        </x-dashboard.consultoria-section>
    @endif
</div>
