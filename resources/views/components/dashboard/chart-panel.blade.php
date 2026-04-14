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
        class="chart-panel-host rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
        x-data="chartPanel(@js($chart), @js($exportFilename), @js($exportMeta), @js($chartPanelDomId), @js($compact))"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between px-3 py-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
            <div class="min-w-0 flex-1 pr-2">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 break-words">{{ $chart['title'] ?? '' }}</h4>
                @if ($chartSubtitle)
                    <p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ $chartSubtitle }}</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0 relative z-30">
                <button
                    type="button"
                    @click="exportPng()"
                    class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
                    title="{{ __('Exportar imagem PNG com cabeçalho e filtros') }}"
                >
                    {{ __('PNG') }}
                </button>
                <div
                    x-show="menuOpen"
                    x-transition.opacity.duration.150ms
                    class="fixed inset-0 z-[45]"
                    style="display: none;"
                    @click="menuOpen = false"
                    aria-hidden="true"
                ></div>
                <div class="relative z-50">
                    <button
                        type="button"
                        @click.stop="menuOpen = !menuOpen"
                        :aria-expanded="menuOpen"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
                        title="{{ __('Opções de visualização do gráfico') }}"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                        </svg>
                        <span class="hidden sm:inline">{{ __('Opções') }}</span>
                    </button>
                    <div
                        x-show="menuOpen"
                        x-transition.opacity.duration.150ms
                        x-cloak
                        @click.stop
                        class="absolute right-0 top-full mt-1 w-60 rounded-md border border-gray-200 bg-white py-1 text-left shadow-lg dark:border-gray-600 dark:bg-gray-800"
                        style="display: none;"
                    >
                        <button
                            type="button"
                            class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/80"
                            @click="toggleExpanded(); menuOpen = false"
                        >
                            <span class="font-medium" x-show="!expanded">{{ __('Expandir área do gráfico') }}</span>
                            <span class="font-medium" x-show="expanded" x-cloak>{{ __('Modo compacto') }}</span>
                            <span class="mt-0.5 block text-[11px] font-normal text-gray-500 dark:text-gray-400">{{ __('Mais altura para caber mais dados e legenda') }}</span>
                        </button>
                        <button
                            type="button"
                            class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/80"
                            @click="toggleLegend(); menuOpen = false"
                        >
                            <span class="font-medium" x-show="legendVisible">{{ __('Ocultar legenda') }}</span>
                            <span class="font-medium" x-show="!legendVisible" x-cloak>{{ __('Mostrar legenda') }}</span>
                        </button>
                        <button
                            type="button"
                            class="block w-full px-3 py-2 text-left text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/80"
                            @click="legendModalOpen = true; menuOpen = false"
                        >
                            <span class="font-medium">{{ __('Ver lista completa (rótulos)') }}</span>
                            <span class="mt-0.5 block text-[11px] font-normal text-gray-500 dark:text-gray-400">{{ __('Útil com muitas escolas ou nomes longos') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div
            class="p-2 sm:p-4 relative w-full overflow-x-auto transition-[min-height] duration-200 ease-out"
            :class="bodyClass()"
        >
            <canvas
                id="{{ $chartPanelDomId }}-canvas"
                x-ref="canvas"
                class="block w-full max-w-full chart-panel-canvas"
                :class="canvasClass()"
            ></canvas>
        </div>
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
