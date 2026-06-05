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
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ __('Territórios no mapa') }}
                    </p>
                    <span class="flex gap-2 text-[10px]">
                        <button type="button" class="underline text-indigo-600 dark:text-indigo-400" @click="toggleAllTerritories(true)">{{ __('Todos') }}</button>
                        <button type="button" class="underline text-indigo-600 dark:text-indigo-400" @click="toggleAllTerritories(false)">{{ __('Nenhum') }}</button>
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
                <p class="leading-relaxed">
                    <span class="inline-block w-3 h-3 rounded-full bg-orange-500 align-middle mr-1"></span>
                    {{ __('Círculo ∝ lacuna; cor mais intensa = maior pressão.') }}
                </p>
                <p class="leading-relaxed">
                    <span class="inline-block w-3 h-3 rounded-full bg-green-500 align-middle mr-1"></span>
                    {{ __('Escola com vagas') }}
                    <span class="inline-block w-3 h-3 rounded-full bg-blue-500 align-middle mx-1"></span>
                    {{ __('rede') }}
                    <span class="inline-block w-3 h-3 rounded-full bg-amber-500 align-middle mx-1"></span>
                    {{ __('quase lotada') }}
                </p>
                <p class="leading-relaxed text-slate-600 dark:text-slate-400">
                    {{ __('Linhas tracejadas: verde ≤2 km, amarelo ≤5 km, laranja ≤10 km, vermelho >10 km até a escola de referência. Malha índigo: distâncias entre escolas candidatas a atender cada zona.') }}
                </p>
            </div>
        </aside>
    </div>
</div>

<style>
    .serv-cadunico-map-dist-label {
        background: transparent;
        border: none;
    }
    .serv-cadunico-map-dist-label__text {
        display: inline-block;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        line-height: 1.2;
        color: #1e293b;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(148, 163, 184, 0.55);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        white-space: nowrap;
    }
    .dark .serv-cadunico-map-dist-label__text {
        color: #e2e8f0;
        background: rgba(15, 23, 42, 0.88);
        border-color: rgba(71, 85, 105, 0.7);
    }
</style>
