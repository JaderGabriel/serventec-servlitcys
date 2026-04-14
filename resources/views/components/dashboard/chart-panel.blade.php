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
        class="chart-panel-host min-w-0 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm"
        :class="expanded || menuOpen ? 'overflow-visible' : 'overflow-hidden'"
        x-data="chartPanel(@js($chart), @js($exportFilename), @js($exportMeta), @js($chartPanelDomId), @js($compact))"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between px-3 py-2.5 sm:py-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
            <div class="min-w-0 w-full text-center sm:flex-1 sm:text-left sm:pr-2">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 break-words">{{ $chart['title'] ?? '' }}</h4>
                @if ($chartSubtitle)
                    <p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400 leading-relaxed text-center sm:text-left">{{ $chartSubtitle }}</p>
                @endif
            </div>
            <div class="chart-panel-toolbar relative z-40 flex w-full flex-wrap items-stretch sm:items-center justify-end gap-2 shrink-0 sm:w-auto sm:flex-nowrap sm:overflow-visible sm:z-auto [scrollbar-width:thin]">
                <button
                    type="button"
                    @click="exportPng()"
                    class="inline-flex min-h-[44px] sm:min-h-0 shrink-0 items-center justify-center gap-1 whitespace-nowrap rounded-md bg-indigo-600 px-3 py-2 sm:px-2.5 sm:py-1.5 text-xs font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
                    title="{{ __('Exportar imagem PNG com cabeçalho e filtros') }}"
                >
                    {{ __('PNG') }}
                </button>
                {{-- Telemóvel: escurecer o ecrã e manter o menu acima do canvas --}}
                <div
                    x-show="menuOpen"
                    x-transition.opacity.duration.150ms
                    class="fixed inset-0 z-[190] bg-black/45 backdrop-blur-[1px] dark:bg-black/55 sm:hidden"
                    style="display: none;"
                    @click="menuOpen = false"
                    aria-hidden="true"
                ></div>
                <div class="relative z-[200] sm:z-50">
                    <button
                        type="button"
                        @click.stop="menuOpen = !menuOpen"
                        :aria-expanded="menuOpen"
                        class="inline-flex min-h-[44px] sm:min-h-0 max-w-full shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2 sm:px-2.5 sm:py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
                        title="{{ __('Opções de visualização do gráfico') }}"
                    >
                        <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                        </svg>
                        <span class="min-w-0">{{ __('Opções') }}</span>
                    </button>
                    {{-- Menu: painel fixo no telemóvel (acima do gráfico); dropdown no sm+ --}}
                    <div
                        x-show="menuOpen"
                        x-transition.opacity.duration.150ms
                        x-cloak
                        @click.stop
                        class="
                            flex flex-col text-left
                            max-sm:fixed max-sm:left-3 max-sm:right-3 max-sm:top-[max(0.75rem,env(safe-area-inset-top,0px))] max-sm:z-[210]
                            max-sm:max-h-[min(78vh,100dvh-2rem)] max-sm:overflow-y-auto max-sm:overflow-x-hidden
                            max-sm:rounded-xl max-sm:border max-sm:border-gray-200 max-sm:bg-white max-sm:shadow-2xl max-sm:ring-1 max-sm:ring-black/5
                            max-sm:dark:border-gray-600 max-sm:dark:bg-gray-900 max-sm:dark:ring-white/10
                            sm:absolute sm:right-0 sm:top-full sm:z-[60] sm:mt-1 sm:max-h-none sm:w-60 sm:overflow-visible
                            sm:rounded-md sm:border sm:border-gray-200 sm:bg-white sm:py-1 sm:shadow-lg dark:sm:border-gray-600 dark:sm:bg-gray-800
                        "
                        style="display: none;"
                        role="dialog"
                        aria-modal="true"
                        aria-label="{{ __('Opções do gráfico') }}"
                    >
                        <div class="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/95 sm:hidden">
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Opções do gráfico') }}</span>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-700 dark:hover:text-gray-100"
                                @click="menuOpen = false"
                                title="{{ __('Fechar') }}"
                            >
                                <span class="sr-only">{{ __('Fechar') }}</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="divide-y divide-gray-100 py-1 dark:divide-gray-700 sm:divide-y-0 sm:py-0">
                            <button
                                type="button"
                                class="block w-full px-3 py-3 text-left text-xs text-gray-800 hover:bg-indigo-50 dark:text-gray-100 dark:hover:bg-gray-800 sm:py-2 sm:text-gray-700 sm:hover:bg-gray-50 sm:dark:text-gray-200 sm:dark:hover:bg-gray-700/80"
                                @click="toggleExpanded(); menuOpen = false"
                            >
                                <span class="font-medium" x-show="!expanded">{{ __('Expandir área do gráfico') }}</span>
                                <span class="font-medium" x-show="expanded" x-cloak>{{ __('Modo compacto') }}</span>
                                <span class="mt-0.5 block text-[11px] font-normal text-gray-500 dark:text-gray-400">{{ __('Mais altura para caber mais dados e legenda') }}</span>
                            </button>
                            <button
                                type="button"
                                class="block w-full px-3 py-3 text-left text-xs text-gray-800 hover:bg-indigo-50 dark:text-gray-100 dark:hover:bg-gray-800 sm:py-2 sm:text-gray-700 sm:hover:bg-gray-50 sm:dark:text-gray-200 sm:dark:hover:bg-gray-700/80"
                                @click="toggleLegend(); menuOpen = false"
                            >
                                <span class="font-medium" x-show="legendVisible">{{ __('Ocultar legenda') }}</span>
                                <span class="font-medium" x-show="!legendVisible" x-cloak>{{ __('Mostrar legenda') }}</span>
                            </button>
                            <button
                                type="button"
                                class="block w-full px-3 py-3 text-left text-xs text-gray-800 hover:bg-indigo-50 dark:text-gray-100 dark:hover:bg-gray-800 sm:py-2 sm:text-gray-700 sm:hover:bg-gray-50 sm:dark:text-gray-200 sm:dark:hover:bg-gray-700/80"
                                @click="legendModalOpen = true; menuOpen = false"
                            >
                                <span class="font-medium">{{ __('Ver lista completa (rótulos)') }}</span>
                                <span class="mt-0.5 block text-[11px] font-normal text-gray-500 dark:text-gray-400">{{ __('Útil com muitas escolas ou nomes longos') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div
            class="relative z-0 isolate p-2 sm:p-4 w-full overflow-x-auto transition-[min-height] duration-200 ease-out"
            :class="panelBodyClass"
        >
            <canvas
                id="{{ $chartPanelDomId }}-canvas"
                x-ref="canvas"
                class="block w-full max-w-full chart-panel-canvas"
                :class="canvasExtraClass"
            ></canvas>
        </div>
        <p
            x-show="chartInteractive"
            x-cloak
            class="px-3 pb-1.5 pt-0 text-[10px] leading-snug text-gray-500 dark:text-gray-500 sm:text-[11px]"
        >
            {{ __('Interação: Ctrl + roda do rato para zoom · arrastar para mover · pinça no telemóvel para zoom.') }}
        </p>
        @if ($chartFootnote)
            <div class="px-3 py-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ $chartFootnote }}
            </div>
        @endif

        {{-- Modal: lista completa de rótulos / valores --}}
        <div
            x-show="legendModalOpen"
            x-transition.opacity.duration.150ms
            @keydown.escape.window="legendModalOpen = false"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            style="display: none;"
            x-cloak
        >
            <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="legendModalOpen = false"></div>
            <div
                class="relative z-10 max-h-[min(32rem,85vh)] w-full max-w-lg overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="'{{ $chartPanelDomId }}-legend-title'"
            >
                <div class="flex items-start justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
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
                <ul class="max-h-[min(24rem,70vh)] overflow-y-auto px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                    <template x-for="(row, idx) in legendRows()" :key="idx">
                        <li class="border-b border-gray-100 py-2 last:border-0 dark:border-gray-700">
                            <span class="block break-words font-medium" x-text="row.label"></span>
                            <span class="tabular-nums text-xs text-gray-600 dark:text-gray-400" x-show="row.value !== null && row.value !== undefined" x-text="row.valueText"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </div>
@endif
