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
@endphp

@if ($hasChart)
    <div
        id="{{ $chartPanelDomId }}"
        data-chart-panel-root="1"
        data-chart-panel-id="{{ $chartPanelDomId }}"
        class="chart-panel-host rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
        x-data="chartPanel(@js($chart), @js($exportFilename), @js($exportMeta), @js($chartPanelDomId))"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between px-3 py-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 pr-2 min-w-0 break-words">{{ $chart['title'] ?? '' }}</h4>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="exportPng()"
                    class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
                    title="{{ __('Exportar imagem PNG com cabeçalho e filtros') }}"
                >
                    {{ __('PNG') }}
                </button>
            </div>
        </div>
        <div
            class="p-2 sm:p-4 relative w-full overflow-x-auto
            {{ $compact ? 'min-h-[220px] h-[min(22rem,calc(100vw-2.5rem))] sm:h-72 md:min-h-[18rem]' : 'min-h-[min(28rem,70vh)] h-[min(28rem,70vh)]' }}"
        >
            <canvas
                id="{{ $chartPanelDomId }}-canvas"
                x-ref="canvas"
                class="block w-full max-w-full chart-panel-canvas {{ $compact ? 'max-h-[min(20rem,55vw)] sm:max-h-64' : 'h-full' }}"
            ></canvas>
        </div>
    </div>
@endif
