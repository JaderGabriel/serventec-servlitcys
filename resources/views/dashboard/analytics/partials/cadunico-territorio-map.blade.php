@props([
    'territorial' => [],
    'schoolMarkers' => [],
])

@php
    $t = is_array($territorial) ? $territorial : [];
    $markers = is_array($t['markers'] ?? null) ? $t['markers'] : [];
    $schools = is_array($schoolMarkers) ? $schoolMarkers : [];
    $ranking = is_array($t['ranking'] ?? null) ? $t['ranking'] : [];
    $footnote = filled($t['nota'] ?? null) ? (string) $t['nota'] : null;
    $territoriosTotal = (int) ($t['territorios_count'] ?? count($ranking));
    $territoriosMapa = (int) ($t['territorios_no_mapa'] ?? count($markers));
    $semCoords = (int) ($t['territorios_sem_coordenadas'] ?? max(0, $territoriosTotal - $territoriosMapa));
@endphp

<div
    class="space-y-3"
    x-data="cadunicoTerritoryMap(@js($markers), @js($schools), @js($footnote), @js($ranking))"
    x-init="init()"
    @destroy.window="destroy()"
>
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,280px)]">
        <div class="space-y-2 min-w-0">
            <div
                x-ref="mapContainer"
                class="z-0 h-[min(32rem,58vh)] w-full min-h-[260px] rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900 [&_.leaflet-container]:h-full [&_.leaflet-container]:z-[1]"
            ></div>
            @if ($footnote)
                <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">{{ $footnote }}</p>
            @endif
        </div>

        <aside class="serv-panel p-3 space-y-3 text-xs text-slate-700 dark:text-slate-300 max-h-[min(32rem,58vh)] overflow-y-auto">
            <div class="rounded-lg border border-amber-300/80 dark:border-amber-600/50 bg-amber-50/80 dark:bg-amber-950/30 px-2.5 py-2">
                <p class="text-[11px] font-bold text-amber-950 dark:text-amber-100 leading-snug">
                    {{ __('Pressão = prioridade territorial') }}
                </p>
                <p class="text-[10px] text-amber-900/90 dark:text-amber-200/90 mt-1 leading-snug">
                    {{ __('Lacuna estimada × vulnerabilidade × distância à escola. Círculos maiores/vermelhos = maior urgência para busca ativa ou oferta.') }}
                </p>
            </div>

            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                    {{ __('Camadas') }}
                </p>
                <div class="space-y-1.5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="rounded border-slate-300" x-model="filters.showTerritories">
                        <span>{{ __('Pressão territorial (lacuna)') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="rounded border-slate-300" x-model="filters.showSchools">
                        <span>{{ __('Escolas da rede') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="rounded border-slate-300" x-model="filters.showAllocationLinks">
                        <span>{{ __('Lacuna → escola mais próxima') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="rounded border-slate-300" x-model="filters.showZoneSchoolMesh">
                        <span>{{ __('Distâncias entre escolas por zona') }}</span>
                    </label>
                </div>
            </div>

            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                    {{ __('Filtros') }}
                </p>
                <label class="block mb-2">
                    <span class="text-slate-600 dark:text-slate-400">{{ __('Lacuna mínima') }}</span>
                    <select
                        class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm"
                        x-model.number="filters.minGap"
                    >
                        <option value="0">{{ __('Qualquer') }}</option>
                        <option value="5">5+</option>
                        <option value="10">10+</option>
                        <option value="20">20+</option>
                        <option value="50">50+</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" class="rounded border-slate-300" x-model="filters.highlightPressureOnly">
                    <span>{{ __('Destacar só alta pressão') }}</span>
                </label>
            </div>

            <template x-if="tipoList().length > 1">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">
                        {{ __('Tipo de território') }}
                    </p>
                    <div class="space-y-1.5">
                        <template x-for="tipo in tipoList()" :key="tipo">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="rounded border-slate-300" x-model="territoryTypes[tipo]">
                                <span x-text="tipo"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>

            <div>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mb-2 leading-snug">
                    {{ trans_choice(
                        ':total território com CadÚnico|:total territórios com CadÚnico',
                        $territoriosTotal,
                        ['total' => number_format($territoriosTotal, 0, ',', '.')]
                    ) }}
                    ·
                    {{ trans_choice(
                        ':n no mapa|:n no mapa',
                        $territoriosMapa,
                        ['n' => number_format($territoriosMapa, 0, ',', '.')]
                    ) }}
                    @if ($semCoords > 0)
                        <span class="text-amber-700 dark:text-amber-300">
                            ({{ trans_choice(
                                ':n sem coordenadas|:n sem coordenadas',
                                $semCoords,
                                ['n' => number_format($semCoords, 0, ',', '.')]
                            ) }})
                        </span>
                    @endif
                </p>
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ __('Territórios no mapa') }}
                    </p>
                    <span class="flex gap-2 text-[10px]">
                        <button type="button" class="underline text-sky-600 dark:text-sky-400" @click="toggleAllTerritories(true)">{{ __('Todos') }}</button>
                        <button type="button" class="underline text-sky-600 dark:text-sky-400" @click="toggleAllTerritories(false)">{{ __('Nenhum') }}</button>
                    </span>
                </div>
                <div class="space-y-1 max-h-36 overflow-y-auto pr-1">
                    <template x-for="t in topTerritoriesForFilter()" :key="territoryKey(t)">
                        <label class="flex items-start gap-2 cursor-pointer leading-snug">
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 mt-0.5 shrink-0"
                                x-model="territoryVisible[territoryKey(t)]"
                            >
                            <span>
                                <span class="font-medium" x-text="t.label"></span>
                                <span
                                    class="block text-[10px] text-slate-500 dark:text-slate-400 font-mono"
                                    x-show="t.codigo"
                                    x-text="t.codigo"
                                ></span>
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400" x-text="(t.gap ?? 0).toLocaleString('pt-BR') + ' {{ __('lacuna est.') }}'"></span>
                            </span>
                        </label>
                    </template>
                </div>
            </div>

            <div class="border-t border-slate-200 dark:border-slate-700 pt-2 space-y-1.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ __('Legenda') }}
                </p>
                <p class="leading-relaxed space-y-1">
                    <span class="block">{{ __('Intensidade visual (tamanho e cor ∝ pressão):') }}</span>
                    <span class="inline-flex items-center gap-1 mr-2"><span class="inline-block w-3 h-3 rounded-full bg-yellow-300 border border-yellow-700"></span>{{ __('baixa') }}</span>
                    <span class="inline-flex items-center gap-1 mr-2"><span class="inline-block w-3 h-3 rounded-full bg-orange-500 border border-orange-900"></span>{{ __('média') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full bg-red-600 border border-red-950"></span>{{ __('alta pressão') }}</span>
                </p>
                <p class="leading-relaxed">
                    <span class="inline-block w-3 h-3 rounded-full bg-green-500 align-middle mr-1"></span>
                    {{ __('Escola com vagas') }}
                    <span class="inline-block w-3 h-3 rounded-full bg-blue-600 align-middle mx-1"></span>
                    {{ __('rede') }}
                    <span class="inline-block w-3 h-3 rounded-full bg-violet-600 align-middle mx-1"></span>
                    {{ __('quase lotada (≥90% capacidade)') }}
                </p>
                <p class="leading-relaxed text-slate-600 dark:text-slate-400">
                    {{ __('Linhas tracejadas: verde ≤2 km, amarelo ≤5 km, laranja ≤10 km, vermelho >10 km até a escola de referência. Malha índigo: distâncias entre escolas candidatas a atender cada zona.') }}
                </p>
            </div>
        </aside>
    </div>
</div>

<style>
    .serv-cadunico-map-dist-tooltip {
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        line-height: 1.25;
        color: #1e293b;
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(148, 163, 184, 0.6);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
        white-space: nowrap;
        pointer-events: none;
    }
    .serv-cadunico-map-dist-tooltip::before {
        display: none;
    }
    .dark .serv-cadunico-map-dist-tooltip {
        color: #e2e8f0;
        background: rgba(15, 23, 42, 0.92);
        border-color: rgba(71, 85, 105, 0.75);
    }
</style>
