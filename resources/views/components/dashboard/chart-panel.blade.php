@props([
    'chart' => null,
    'exportFilename' => 'grafico',
    'exportMeta' => [],
    /** Quando false, o gráfico usa mais altura (ex.: visão geral em coluna única). */
    'compact' => true,
    /** ID estável do painel (evita efeitos cruzados entre gráficos em CSS/JS). */
    'chartPanelId' => null,
])

@php
    $hasChart = is_array($chart)
        && ! empty($chart['labels'])
        && ! empty($chart['datasets']);
    $exportMeta = is_array($exportMeta) ? $exportMeta : [];
    $chartPanelDomId = $chartPanelId ?? 'chart-panel-'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid());
    $chartSubtitle = is_array($chart) && ! empty($chart['subtitle']) ? (string) $chart['subtitle'] : null;
    $chartFootnote = is_array($chart) && ! empty($chart['footnote']) ? (string) $chart['footnote'] : null;
@endphp

@if ($hasChart)
    <div
        id="{{ $chartPanelDomId }}"
        data-chart-panel-root="1"
        data-chart-panel-id="{{ $chartPanelDomId }}"
        class="chart-panel-host min-w-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
        x-data="chartPanel(@js($chart), @js($exportFilename), @js($exportMeta), @js($chartPanelDomId), @js($compact))"
    >
        <div class="flex flex-col gap-3 px-3 py-3 sm:py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
            <div class="min-w-0 w-full text-center sm:flex-1 sm:text-left">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 break-words">{{ $chart['title'] ?? '' }}</h4>
                @if ($chartSubtitle)
                    <p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400 leading-relaxed text-center sm:text-left">{{ $chartSubtitle }}</p>
                @endif
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-stretch sm:justify-end w-full sm:gap-2">
                <button
                    type="button"
                    @click="exportPng()"
                    class="inline-flex w-full sm:w-auto min-h-[44px] sm:min-h-0 shrink-0 items-center justify-center gap-1 whitespace-nowrap rounded-md bg-indigo-600 px-3 py-2.5 sm:py-1.5 text-xs font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
                    title="{{ __('Exportar imagem PNG com cabeçalho e filtros') }}"
                >
                    {{ __('PNG') }}
                </button>
                <button
                    type="button"
                    @click="legendModalOpen = true"
                    class="inline-flex w-full sm:w-auto min-h-[44px] sm:min-h-0 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2.5 sm:py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
                    title="{{ __('Ver todos os rótulos e valores numa lista') }}"
                >
                    <svg class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                    <span>{{ __('Ver lista completa (rótulos)') }}</span>
                </button>
                <button
                    type="button"
                    x-show="filterUi"
                    x-cloak
                    @click="filterModalOpen = true"
                    class="inline-flex w-full sm:w-auto min-h-[44px] sm:min-h-0 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2.5 sm:py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
                    title="{{ __('Mostrar ou ocultar categorias ou séries no gráfico') }}"
                >
                    <svg class="h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                    </svg>
                    <span>{{ __('Filtrar dados no gráfico') }}</span>
                </button>
            </div>
        </div>

        {{-- Zoom / pan: visível em gráficos cartesianos (telefone: botões + pinça; desktop: Ctrl+roda) --}}
        <div
            x-show="zoomUi"
            x-cloak
            class="border-b border-gray-100 dark:border-gray-700 bg-slate-50/90 dark:bg-slate-950/40 px-3 py-2.5 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
        >
            <p class="text-[11px] leading-snug text-center text-slate-600 dark:text-slate-400 sm:text-left sm:flex-1 sm:min-w-0">
                <span class="sm:hidden">{{ __('Pinça com dois dedos para ampliar ou reduzir. Arraste para mover. No computador: Ctrl + roda do rato para zoom.') }}</span>
                <span class="hidden sm:inline">{{ __('Pinça para zoom · arrastar para mover · Ctrl + roda para zoom (computador).') }}</span>
            </p>
            <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-end shrink-0">
                <button
                    type="button"
                    @click="zoomOut()"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-800 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    title="{{ __('Reduzir zoom') }}"
                    aria-label="{{ __('Reduzir zoom') }}"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                </button>
                <button
                    type="button"
                    @click="zoomIn()"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-800 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    title="{{ __('Aumentar zoom') }}"
                    aria-label="{{ __('Aumentar zoom') }}"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" /></svg>
                </button>
                <button
                    type="button"
                    @click="resetZoomView()"
                    class="inline-flex h-11 min-w-[2.75rem] shrink-0 items-center justify-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 text-indigo-900 shadow-sm hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/80 dark:text-indigo-100 dark:hover:bg-indigo-900/80 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    title="{{ __('Repor vista inicial (zoom e posição)') }}"
                    aria-label="{{ __('Repor vista inicial') }}"
                >
                    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <span class="text-[11px] font-medium">{{ __('Início') }}</span>
                </button>
            </div>
        </div>

        <div
            class="relative z-0 isolate p-2 sm:p-4 w-full overflow-x-auto transition-[min-height] duration-200 ease-out"
            :class="[panelBodyClass, zoomUi ? 'touch-none' : '']"
            :style="panelBodyStyle || null"
        >
            <canvas
                id="{{ $chartPanelDomId }}-canvas"
                x-ref="canvas"
                class="block w-full max-w-full chart-panel-canvas"
                :class="canvasExtraClass"
            ></canvas>
        </div>
        @if ($chartFootnote)
            <div class="px-3 py-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ $chartFootnote }}
            </div>
        @endif

        {{-- Modais no body para não serem recortados por overflow dos antepassados --}}
        <template x-teleport="body">
            <div
                x-show="legendModalOpen"
                x-transition.opacity.duration.150ms
                @keydown.escape.window="legendModalOpen = false"
                class="fixed inset-0 z-[240] flex items-center justify-center p-3 sm:p-4"
                style="display: none;"
                x-cloak
            >
                <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="legendModalOpen = false"></div>
                <div
                    class="relative z-10 flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="'{{ $chartPanelDomId }}-legend-title'"
                >
                    <div class="flex shrink-0 items-start justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <h3 id="{{ $chartPanelDomId }}-legend-title" class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Rótulos do gráfico') }}
                        </h3>
                        <button
                            type="button"
                            class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            @click="legendModalOpen = false"
                            title="{{ __('Fechar') }}"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <ul class="min-h-0 flex-1 overflow-y-auto px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                        <template x-for="(row, idx) in legendRows()" :key="idx">
                            <li class="border-b border-gray-100 py-2 last:border-0 dark:border-gray-700">
                                <span class="block break-words font-medium" x-text="row.label"></span>
                                <span class="tabular-nums text-xs text-gray-600 dark:text-gray-400" x-show="row.value !== null && row.value !== undefined" x-text="row.valueText"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </template>

        <template x-teleport="body">
            <div
                x-show="filterModalOpen"
                x-transition.opacity.duration.150ms
                @keydown.escape.window="filterModalOpen = false"
                class="fixed inset-0 z-[240] flex items-center justify-center p-3 sm:p-4"
                style="display: none;"
                x-cloak
            >
                <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="filterModalOpen = false"></div>
                <div
                    class="relative z-10 flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="'{{ $chartPanelDomId }}-filter-title'"
                >
                    <div class="flex shrink-0 items-start justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <h3 id="{{ $chartPanelDomId }}-filter-title" class="pr-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Filtrar dados no gráfico') }}
                        </h3>
                        <button
                            type="button"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            @click="filterModalOpen = false"
                            title="{{ __('Fechar') }}"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <p class="shrink-0 px-4 pb-1 pt-2 text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ __('Marque ou desmarque para incluir ou excluir categorias ou séries: o gráfico é atualizado com os dados filtrados (não é só esconder a cor). A legenda do gráfico faz o mesmo quando está visível.') }}
                    </p>
                    <ul class="min-h-0 flex-1 overflow-y-auto px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                        <template x-for="row in filterRows()" :key="row.key + '-' + _filterNonce">
                            <li class="flex items-start gap-3 border-b border-gray-100 py-2.5 last:border-0 dark:border-gray-700">
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                                    :checked="row.visible"
                                    @change="toggleFilterRow(row)"
                                />
                                <span class="min-w-0 flex-1 break-words leading-snug" x-text="row.label"></span>
                            </li>
                        </template>
                    </ul>
                    <div class="shrink-0 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        <button
                            type="button"
                            class="min-h-[44px] w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @click="filterModalOpen = false"
                        >
                            {{ __('Concluir') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
