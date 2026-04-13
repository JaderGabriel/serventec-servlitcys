@props([
    'chart' => null,
    'exportFilename' => 'grafico',
])

@php
    $hasChart = is_array($chart)
        && ! empty($chart['labels'])
        && ! empty($chart['datasets']);
@endphp

@if ($hasChart)
    <div
        class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
        x-data="chartPanel(@js($chart), @js($exportFilename))"
    >
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $chart['title'] ?? '' }}</h4>
            <button
                type="button"
                @click="exportPng()"
                class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-900"
            >
                {{ __('Exportar PNG') }}
            </button>
        </div>
        <div class="p-4 h-72 relative">
            <canvas x-ref="canvas" class="!max-h-64"></canvas>
        </div>
    </div>
@endif
