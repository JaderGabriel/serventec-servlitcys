@props(['performanceData', 'chartExportContext' => []])

@php
    $perfCharts = $performanceData['charts'] ?? [];
    if ($perfCharts === [] && ! empty($performanceData['chart'])) {
        $perfCharts = [$performanceData['chart']];
    }
@endphp

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Cada taxa = (matrículas na categoria) ÷ (total de matrículas ativas no filtro) × 100. O total usa matricula.ativo e o campo de situação (códigos INEP via matricula_situacao ou equivalente); os filtros de ano/escola/curso/turno aplicam-se pela turma. Entre os indicadores destacam-se a taxa de reclassificação (cód. 10), abandono (11), remanejamento (16) e taxas agregadas de aprovação e reprovação. O gráfico de distorção idade/série (rede), quando presente, usa idade à 31/03 e limite etário + 2 anos, ou SQL IEDUCAR_SQL_DISTORCAO_REDE_CHART.') }}
    </p>

    @php $saebSeries = $performanceData['saeb_series'] ?? null; @endphp
    @php $saebExplicacao = is_array($saebSeries) ? ($saebSeries['explicacao_modal'] ?? null) : null; @endphp
    @if ($saebSeries !== null && ($saebSeries['error'] ?? null))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $saebSeries['error'] }}
        </div>
    @endif

    @if ($saebSeries !== null && (($saebSeries['charts'] ?? []) !== [] || ($saebSeries['notes'] ?? []) !== [] || ($saebSeries['source_hint'] ?? null) || ! empty($saebExplicacao)))
        <div class="rounded-xl border border-emerald-200/90 dark:border-emerald-800/60 bg-gradient-to-br from-emerald-50/95 via-white to-white dark:from-emerald-950/35 dark:via-gray-900/90 dark:to-gray-900/95 shadow-sm overflow-hidden" x-data="{ saebHelpOpen: false }">
            <div class="border-b border-emerald-200/80 dark:border-emerald-800/50 bg-emerald-100/50 dark:bg-emerald-950/45 px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold uppercase tracking-[0.12em] text-emerald-950 dark:text-emerald-100">
                            {{ __('SAEB — resultados oficiais e séries históricas') }}
                        </h3>
                        <p class="mt-2 text-xs text-emerald-900/90 dark:text-emerald-200/85 leading-relaxed">
                            {{ __('Indicadores do Sistema de Avaliação da Educação Básica divulgados pelo INEP (escalas e percentuais de proficientes, conforme a publicação). Com o filtro de ano letivo X, mostram-se todos os anos disponíveis na fonte até X (inclusive).') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 shrink-0 items-center justify-end">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-white/90 dark:bg-emerald-950/40 dark:border-emerald-700 px-2.5 py-1 text-[11px] font-semibold text-emerald-900 dark:text-emerald-100">
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-700 ring-2 ring-white dark:ring-emerald-900" aria-hidden="true"></span>
                            {{ __('Final') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-white/90 dark:bg-amber-950/30 dark:border-amber-700 px-2.5 py-1 text-[11px] font-semibold text-amber-900 dark:text-amber-100">
                            <span class="inline-block h-0 w-0 border-l-[5px] border-r-[5px] border-b-[8px] border-l-transparent border-r-transparent border-b-amber-600" aria-hidden="true"></span>
                            {{ __('Preliminar') }}
                        </span>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-300/90 bg-white px-3 py-2.5 text-xs font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-100 dark:hover:bg-emerald-900/60 sm:py-2"
                            @click="saebHelpOpen = true"
                        >
                            <svg class="h-4 w-4 text-emerald-700 dark:text-emerald-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            {{ __('Informações (dados SAEB importados)') }}
                        </button>
                    </div>
                </div>
                @if (! empty($saebSeries['notes']) && is_array($saebSeries['notes']))
                    <ul class="mt-3 text-[11px] text-emerald-900/90 dark:text-emerald-200/90 list-disc pl-5 space-y-1">
                        @foreach ($saebSeries['notes'] as $sn)
                            <li>{{ $sn }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($saebSeries['source_hint']))
                    <p class="mt-2 text-[11px] text-emerald-800/80 dark:text-emerald-300/80">{{ $saebSeries['source_hint'] }}</p>
                @endif
            </div>
            @if (! empty($saebSeries['charts']) && is_array($saebSeries['charts']))
                <div class="p-3 sm:p-4 space-y-4 bg-white/50 dark:bg-gray-900/40">
                    @foreach ($saebSeries['charts'] as $sidx => $saebChart)
                        <x-dashboard.chart-panel
                            :chart="$saebChart"
                            :exportFilename="'desempenho-saeb-'.$sidx"
                            :exportMeta="$chartExportContext"
                            :compact="false"
                            :chartPanelId="'chart-saeb-' . $sidx"
                            :suppressTitle="false"
                        />
                    @endforeach
                </div>
            @elseif (empty($saebSeries['error']))
                <div class="px-4 py-6 text-sm text-emerald-900/90 dark:text-emerald-200/90">
                    {{ __('Ainda não há dados SAEB importados. Em Admin → Sincronizações → Pedagógicas, importe o JSON (INEP com fallbacks) ou copie o modelo; os gráficos leem apenas storage/app/public/saeb/historico.json.') }}
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
                        class="relative z-10 flex max-h-[95vh] w-full min-h-0 max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="saeb-import-help-title"
                    >
                        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                            <h3 id="saeb-import-help-title" class="pr-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ is_array($saebExplicacao) && ! empty($saebExplicacao['titulo']) ? $saebExplicacao['titulo'] : __('Informações sobre os dados SAEB importados') }}
                            </h3>
                            <button
                                type="button"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                @click="saebHelpOpen = false"
                                title="{{ __('Fechar') }}"
                                aria-label="{{ __('Fechar') }}"
                            >
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-4 py-4 text-sm text-gray-700 dark:text-gray-300 space-y-5 leading-relaxed [scrollbar-gutter:stable]">
                            @if (is_array($saebExplicacao) && ! empty($saebExplicacao['secoes']))
                                @if (! empty($saebExplicacao['gerado_em']))
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
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
                                        <h4 class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('Links oficiais e consultas') }}</h4>
                                        <ul class="mt-2 list-disc list-outside space-y-2 pl-5">
                                            @foreach ($saebExplicacao['links'] as $lnk)
                                                @if (is_array($lnk) && ! empty($lnk['url']))
                                                    <li>
                                                        <a href="{{ $lnk['url'] }}" class="font-medium text-emerald-800 dark:text-emerald-200 underline break-all" target="_blank" rel="noopener noreferrer">{{ $lnk['label'] ?? $lnk['url'] }}</a>
                                                        @if (! empty($lnk['nota']))
                                                            <span class="text-gray-600 dark:text-gray-400"> — {{ $lnk['nota'] }}</span>
                                                        @endif
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @else
                                <p class="text-gray-600 dark:text-gray-400">
                                    {{ __('O texto explicativo detalhado é gravado em meta.explicacao_modal no ficheiro JSON após cada sincronização pedagógica bem-sucedida. Execute «Importar» ou «Copiar modelo» em Admin → Sincronizações → Pedagógicas para gerar ou actualizar esse conteúdo.') }}
                                </p>
                                <p class="mt-3">
                                    <a href="https://www.gov.br/inep/pt-br/areas-de-atuacao/avaliacoes-e-exames-educacionais/saeb" class="font-medium text-emerald-800 dark:text-emerald-200 underline break-all" target="_blank" rel="noopener noreferrer">https://www.gov.br/inep/pt-br/areas-de-atuacao/avaliacoes-e-exames-educacionais/saeb</a>
                                </p>
                            @endif
                        </div>
                        <div class="shrink-0 border-t border-gray-100 px-4 py-3 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
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

    @php $inepPanel = $performanceData['inep_panel'] ?? null; @endphp
    @if ($inepPanel !== null)
        @if (! empty($inepPanel['sql_error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ $inepPanel['sql_error'] }}
            </div>
        @endif
        @if (! empty($inepPanel['sql_note']))
            <div class="rounded-md bg-sky-50 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800 px-4 py-3 text-xs text-sky-900 dark:text-sky-100 leading-relaxed">
                {{ $inepPanel['sql_note'] }}
            </div>
        @endif

        <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-950/25 p-4 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-indigo-950 dark:text-indigo-100">{{ __('Indicadores externos: IDEB, SAEB e PNE') }}</h3>
                <p class="text-xs text-indigo-900/90 dark:text-indigo-200/90 mt-1 leading-relaxed">
                    {{ __('O IDEB e o SAEB são produzidos pelo INEP; as metas do PNE são acompanhadas com indicadores nacionais. Abaixo consolidamos referências e, se configurado, valores lidos da sua base (tabela ou view própria). Consulte também o portal do INEP para séries históricas oficiais.') }}
                    <a href="https://www.gov.br/inep/pt-br" class="text-indigo-700 dark:text-indigo-300 underline" target="_blank" rel="noopener noreferrer">https://www.gov.br/inep/pt-br</a>
                </p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @foreach (['ideb', 'saeb', 'pne'] as $secKey)
                    @php $sec = $inepPanel['sections'][$secKey] ?? null; @endphp
                    @if ($sec !== null)
                        <div class="rounded-lg border border-white/80 dark:border-indigo-900/50 bg-white/90 dark:bg-gray-900/40 p-4 shadow-sm flex flex-col gap-2 min-h-[12rem]">
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $sec['title'] }}</p>
                            <p class="text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed flex-1">{{ $sec['intro'] }}</p>
                            @if (! empty($sec['items']))
                                <ul class="text-xs space-y-2 text-gray-800 dark:text-gray-200 border-t border-gray-100 dark:border-gray-700 pt-2">
                                    @foreach ($sec['items'] as $item)
                                        <li class="leading-snug">
                                            <span class="font-medium">{{ $item['label'] ?? '—' }}</span>
                                            @if (($item['valor'] ?? null) !== null && is_numeric($item['valor']))
                                                <span class="tabular-nums text-indigo-700 dark:text-indigo-300"> — {{ number_format((float) $item['valor'], 2, ',', '.') }}</span>
                                                @if (! empty($item['unidade']))
                                                    <span class="text-gray-500"> ({{ $item['unidade'] }})</span>
                                                @endif
                                            @else
                                                <span class="text-gray-500"> — {{ __('valor não numérico na base') }}</span>
                                            @endif
                                            @if (! empty($item['referencia']))
                                                <span class="text-gray-500"> · {{ __('ref.') }} {{ $item['referencia'] }}</span>
                                            @endif
                                            @if (! empty($item['detalhe']))
                                                <p class="text-[10px] text-gray-500 mt-0.5">{{ $item['detalhe'] }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-[11px] text-amber-800 dark:text-amber-200/90">{{ $sec['empty_hint'] }}</p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
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
        @if (($performanceData['distorcao_pct'] ?? null) !== null)
            <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/80 dark:bg-indigo-950/30 px-3 py-2 text-sm text-indigo-900 dark:text-indigo-100">
                {{ __('Distorção idade/série (rede)') }}:
                <span class="font-semibold">{{ number_format((float) $performanceData['distorcao_pct'], 1, ',', '.') }}%</span>
                <span class="text-xs text-indigo-700 dark:text-indigo-300">({{ __('fonte: IEDUCAR_SQL_DISTORCAO_REDE') }})</span>
            </div>
        @endif
    @endif

    @if ($perfCharts !== [])
        <div class="grid grid-cols-1 gap-6">
            @foreach ($perfCharts as $idx => $chart)
                <x-dashboard.chart-panel
                    :chart="$chart"
                    :exportFilename="'desempenho-'.$idx"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                />
            @endforeach
        </div>
    @elseif (empty($performanceData['error']) && empty($performanceData['message']) && $perfCharts === [] && empty($performanceData['kpis'] ?? []) && empty($performanceData['inep_panel'] ?? null) && empty(($performanceData['saeb_series']['charts'] ?? [])) && empty(($performanceData['saeb_series']['explicacao_modal'] ?? null)))
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
</div>
