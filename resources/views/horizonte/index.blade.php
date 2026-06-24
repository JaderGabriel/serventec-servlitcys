@php
    $refYear = (int) ($refYear ?? config('horizonte.reference_year', (int) date('Y') - 1));
    $currentYear = (int) date('Y');
    $legend = is_array($legend ?? null) ? $legend : [];
    $colors = is_array($colors ?? null) ? $colors : [];
    $methodology = is_array($methodology ?? null) ? $methodology : \App\Support\Horizonte\HorizonteMapPresenter::methodologyUi();
    $kpiHints = collect($methodology['kpis'] ?? [])->keyBy('key');
    $heatLegend = \App\Support\Horizonte\HorizonteMapPresenter::heatLegendItems();
    $mapDataUrl = $mapDataUrl ?? route('dashboard.horizonte.map-data');
    $docUrl = route(auth()->user()?->isAdmin() ? 'admin.documentation.show' : 'documentation.show', ['doc' => 'docs/HORIZONTE.md']);
    $canRefreshData = (bool) ($canRefreshData ?? auth()->user()?->canImportOrConfigure());
    $canManageSge = (bool) ($canManageSge ?? false);
    $sgeShowUrl = (string) ($sgeShowUrl ?? '');
    $sgeRegistryUrl = (string) ($sgeRegistryUrl ?? '');
    $initialUf = (string) ($initialUf ?? '');
    $defaultViewFilter = is_array($defaultViewFilter ?? null)
        ? $defaultViewFilter
        : \App\Support\Horizonte\HorizonteMapPresenter::defaultViewFilter();
    $pressureMin = (int) ($defaultViewFilter['pressure_min'] ?? 60);
    $viewPresets = [
        'high_pressure' => ['label' => __('Alta pressão FUNDEB'), 'color' => '#be123c'],
        'prospects' => ['label' => __('Todos prospectos'), 'color' => $colors['prospect_medium'] ?? '#b45309'],
        'prospect_high' => ['label' => __('Alta propensão'), 'color' => $colors['prospect_high'] ?? '#be123c'],
        'all' => ['label' => __('Todos os municípios'), 'color' => '#64748b'],
    ];
    $tierPresets = [
        'consultoria_active' => ['label' => __('Consultoria'), 'color' => $colors['consultoria_active'] ?? '#0d9488'],
        'catalog_pending' => ['label' => __('Catálogo pendente'), 'color' => $colors['catalog_pending'] ?? '#ea580c'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Horizonte') }}</p>
                <h2 class="font-display font-semibold text-xl sm:text-2xl text-serv-navy dark:text-slate-100 leading-tight">
                    {{ __('Centro de decisão comercial') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
                    {{ __('Priorize municípios por pressão FUNDEB e propensão — mapa GIS + lista de abordagem.') }}
                    <span class="tabular-nums font-medium text-slate-700 dark:text-slate-300">{{ __('Ref. :ano', ['ano' => $refYear]) }}</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                @include('horizonte.partials.help-nav', ['docUrl' => $docUrl])
            </div>
        </div>
    </x-slot>

    <div
        class="py-6 sm:py-8"
        x-data="horizonteMap([], @js($colors), @js([
            'loadUrl' => $mapDataUrl,
            'refYear' => $refYear,
            'currentYear' => $currentYear,
            'legend' => $legend,
            'heatLegend' => $heatLegend,
            'methodology' => $methodology,
            'defaultViewFilter' => $defaultViewFilter,
            'canRefreshData' => $canRefreshData,
            'canManageSge' => $canManageSge,
            'sgeShowUrl' => $sgeShowUrl,
            'sgeRegistryUrl' => $sgeRegistryUrl,
            'initialUf' => $initialUf,
            'ufNames' => $ufNames,
        ]))"
        x-init="init()"
        @horizonte-guide.window="onHorizonteGuide($event.detail)"
    >
        <div class="max-w-[100rem] mx-auto sm:px-6 lg:px-8 space-y-5">

            <div x-show="pageError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200" role="alert">
                <p class="font-medium">{{ __('Não foi possível carregar o mapa Horizonte.') }}</p>
                <p class="mt-1" x-text="pageError"></p>
            </div>

            {{-- Barra de comando — decisão imediata --}}
            <section class="serv-horizonte-cmd" aria-label="{{ __('Painel de decisão') }}" data-horizonte-tour="kpi">
                <div class="serv-horizonte-cmd__hero">
                    <div class="serv-horizonte-cmd__primary">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">{{ __('Prioridade comercial') }}</p>
                        <p class="serv-horizonte-cmd__primary-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.high_pressure)">…</p>
                        <p class="mt-1 text-sm text-rose-900/80 dark:text-rose-100/80 line-clamp-3">
                            {{ __('Municípios em alta pressão FUNDEB (≥ :min) ou alta propensão — camada inicial do mapa.', ['min' => $pressureMin]) }}
                        </p>
                    </div>
                    <div class="serv-horizonte-cmd__metrics">
                        <div class="serv-horizonte-cmd__metric" title="{{ $kpiHints['prospect_count']['hint'] ?? '' }}">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 truncate">{{ __('Prospectos') }}</p>
                            <p class="serv-horizonte-cmd__metric-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.prospect_count)">…</p>
                        </div>
                        <div class="serv-horizonte-cmd__metric" title="{{ $kpiHints['high_prospect']['hint'] ?? '' }}">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 truncate">{{ __('Alta propensão') }}</p>
                            <p class="serv-horizonte-cmd__metric-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.high_prospect)">…</p>
                        </div>
                        <div class="serv-horizonte-cmd__metric" title="{{ $kpiHints['consultoria_active']['hint'] ?? '' }}">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 truncate">{{ __('Consultoria') }}</p>
                            <p class="serv-horizonte-cmd__metric-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.consultoria_active)">…</p>
                        </div>
                        <div class="serv-horizonte-cmd__metric" title="{{ $kpiHints['prospect_matriculas']['hint'] ?? '' }}">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 truncate">{{ __('Matr. prospecto') }}</p>
                            <p class="serv-horizonte-cmd__metric-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(coverage.prospect_matriculas_censo)">…</p>
                        </div>
                    </div>
                </div>

                <div class="serv-horizonte-cmd__segments" data-horizonte-tour="segments" x-show="!kpiLoading && focusSegments.length > 0" x-cloak>
                    <template x-for="seg in focusSegments" :key="seg.key">
                        <button
                            type="button"
                            class="serv-horizonte-cmd__segment"
                            :disabled="pageLoading"
                            @click="applyFocusSegment(seg); workspaceTab = 'list'"
                        >
                            <span class="text-[10px] font-bold uppercase tracking-wide text-teal-800 dark:text-teal-300" x-text="seg.label"></span>
                            <span class="serv-horizonte-cmd__segment-count" x-text="Number(seg.count ?? 0).toLocaleString('pt-BR')"></span>
                            <span class="mt-0.5 line-clamp-2 text-[11px] text-slate-500 dark:text-slate-400" x-text="seg.description"></span>
                        </button>
                    </template>
                </div>

                <div class="serv-horizonte-cmd__controls" data-horizonte-tour="recorte">
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 text-xs">
                            <span class="text-slate-500 shrink-0">{{ __('Recorte') }}</span>
                            <select
                                x-model="scopeUf"
                                @change="onScopeUfPick($event)"
                                :disabled="pageLoading || regionalLoading"
                                class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm min-w-[11rem]"
                            >
                                <option value="">{{ __('Brasil (por UF)') }}</option>
                                @foreach ($ufNames as $code => $name)
                                    <option value="{{ $code }}">{{ $code }} — {{ $name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <span class="serv-horizonte-gis__mode-pill" :class="isOverviewMode ? 'is-national' : 'is-regional'">
                            <span x-show="isOverviewMode">{{ __('Visão nacional') }}</span>
                            <span x-show="isRegionalMode" x-cloak x-text="ufLabel(scopeUf)"></span>
                        </span>
                        <button
                            type="button"
                            x-show="isRegionalMode"
                            x-cloak
                            class="serv-btn-secondary text-xs"
                            @click="backToOverview()"
                            :disabled="pageLoading || regionalLoading"
                        >{{ __('← Brasil') }}</button>
                    </div>
                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        <button
                            type="button"
                            class="serv-btn-secondary text-xs xl:hidden"
                            data-horizonte-tour="filters-hint"
                            @click="openFiltersDock()"
                            :disabled="pageLoading || isOverviewMode"
                        >
                            {{ __('Filtros') }}
                            <span x-show="activeFilterCount > 0" x-cloak class="ms-1 rounded-full bg-teal-600 px-1.5 py-0.5 text-[10px] font-bold text-white tabular-nums" x-text="activeFilterCount"></span>
                        </button>
                        <button type="button" class="serv-btn-secondary text-xs" @click="resetFilters()" :disabled="pageLoading">
                            {{ __('Vista padrão') }}
                        </button>
                    </div>
                </div>
            </section>

            {{-- Mapa + rail de acção --}}
            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_18rem] 2xl:grid-cols-[minmax(0,1fr)_20rem]">
                <section class="serv-panel overflow-hidden min-w-0 serv-horizonte-gis" aria-labelledby="horizonte-map-heading">
                    <div class="serv-horizonte-map-toolbar">
                        <h3 id="horizonte-map-heading" class="text-sm font-semibold text-serv-navy dark:text-slate-100 me-auto min-w-0">
                            <span x-show="isOverviewMode">{{ __('Mapa — alta pressão por UF') }}</span>
                            <span x-show="isRegionalMode" x-cloak>{{ __('Detalhe municipal') }}</span>
                        </h3>
                        <div class="serv-horizonte-lens-bar" x-show="isRegionalMode" x-cloak role="group" aria-label="{{ __('Lente rápida') }}">
                            <template x-for="opt in decisionLensOptions.filter(o => ['high_pressure','prospects','prospect_high','all'].includes(o.key))" :key="opt.key">
                                <button
                                    type="button"
                                    class="serv-horizonte-lens-bar__btn"
                                    :class="decisionLensKey === opt.key ? 'is-active' : ''"
                                    :disabled="pageLoading || opt.disabled"
                                    @click="applyDecisionLens(opt.key)"
                                    x-text="opt.short"
                                ></button>
                            </template>
                        </div>
                        <div class="flex gap-1 rounded-lg ring-1 ring-slate-200/80 dark:ring-slate-600 p-0.5" x-show="isRegionalMode" x-cloak>
                            <button type="button" class="rounded-md px-2 py-1 text-xs font-medium transition" :class="mapView === 'markers' ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50' : 'text-slate-600'" @click="setMapView('markers')">{{ __('Pontos') }}</button>
                            <button type="button" class="rounded-md px-2 py-1 text-xs font-medium transition" :class="mapView === 'heat' ? 'bg-rose-100 text-rose-900 dark:bg-rose-950/50' : 'text-slate-600'" @click="setMapView('heat')" :disabled="regionalDisplayPolicy?.heavy_regional">{{ __('Calor') }}</button>
                        </div>
                    </div>

                    <div class="serv-horizonte-result-bar" x-show="isRegionalMode && !pageLoading" x-cloak>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span class="font-semibold text-serv-navy dark:text-slate-100 tabular-nums">
                                <span x-text="filteredCount.toLocaleString('pt-BR')"></span> {{ __('no recorte') }}
                            </span>
                            <span class="text-slate-400">·</span>
                            <span class="text-slate-600 dark:text-slate-300 tabular-nums">
                                <span x-text="mapInteractionStats.onMap.toLocaleString('pt-BR')"></span> {{ __('desenhados no mapa') }}
                            </span>
                            <span class="text-slate-400">·</span>
                            <span class="text-slate-500" x-text="decisionLensLabel"></span>
                        </div>
                        <button type="button" class="serv-link text-[11px] shrink-0" @click="openFiltersDock()" x-show="!filterDockOpen">{{ __('Abrir filtros') }}</button>
                    </div>

                    <div class="px-4 py-2 border-b border-slate-200/90 dark:border-slate-700/90 space-y-2">
                        <div
                            x-show="decisionViewBanner"
                            x-cloak
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="decisionViewBanner?.kind === 'overview'
                                ? 'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100'
                                : 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100'"
                            role="status"
                        >
                            <p class="font-semibold text-xs" x-text="decisionViewBanner?.title"></p>
                            <p class="mt-0.5 text-[11px] leading-relaxed opacity-90" x-text="decisionViewBanner?.message"></p>
                        </div>
                        <p
                            x-show="regionalDisplayPolicy?.reason && isRegionalMode"
                            x-cloak
                            class="rounded-lg border border-amber-200/90 bg-amber-50/80 px-3 py-2 text-[11px] text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                            x-text="regionalDisplayPolicy.reason"
                        ></p>

                        <div x-show="activeFilterChips.length > 1" x-cloak class="flex flex-wrap gap-1.5 items-center">
                            <template x-for="chip in activeFilterChips" :key="chip.key">
                                <span class="serv-horizonte-filter-chip" :class="chip.key === 'lens' ? 'serv-horizonte-filter-chip--lens' : ''">
                                    <span x-text="chip.label"></span>
                                    <button
                                        type="button"
                                        x-show="chip.removable"
                                        x-cloak
                                        class="serv-horizonte-filter-chip__remove"
                                        @click="removeFilterChip(chip.key)"
                                        aria-label="{{ __('Remover filtro') }}"
                                    >×</button>
                                </span>
                            </template>
                        </div>

                        <div class="serv-map-legend flex flex-wrap gap-x-3 gap-y-1.5 text-[11px]" x-show="isRegionalMode" x-cloak>
                            <template x-if="mapView === 'heat'">
                                <template x-for="item in heatLegend" :key="item.key">
                                    <span class="serv-map-legend__item" :title="item.description || ''">
                                        <span class="serv-map-legend-swatch serv-map-legend-swatch--connection" :style="'background-color:' + (item.color || '#64748b')" aria-hidden="true"></span>
                                        <span class="text-slate-600 dark:text-slate-300" x-text="item.label"></span>
                                    </span>
                                </template>
                            </template>
                            <p x-show="mapView === 'heat'" class="w-full text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                                {{ __('Cores relativas à pressão FUNDEB no recorte visível (escala automática).') }}
                            </p>
                            <template x-if="mapView === 'markers'">
                                <template x-for="item in legend" :key="item.key">
                                    <span class="serv-map-legend__item" :title="item.description || ''">
                                        <span class="serv-map-legend-swatch serv-map-legend-swatch--connection" :style="'background-color:' + (item.color || '#64748b')" aria-hidden="true"></span>
                                        <span class="text-slate-600 dark:text-slate-300" x-text="item.label"></span>
                                    </span>
                                </template>
                            </template>
                        </div>
                        <div class="serv-map-legend flex flex-wrap gap-x-3 gap-y-1.5 text-[11px]" x-show="isOverviewMode" x-cloak>
                            <span class="serv-map-legend__item">
                                <span class="serv-map-legend-swatch serv-map-legend-swatch--connection bg-amber-300" aria-hidden="true"></span>
                                <span class="text-slate-600 dark:text-slate-300">{{ __('Tamanho = volume') }}</span>
                            </span>
                            <span class="serv-map-legend__item">
                                <span class="serv-map-legend-swatch serv-map-legend-swatch--connection bg-rose-700" aria-hidden="true"></span>
                                <span class="text-slate-600 dark:text-slate-300">{{ __('Cor = alta pressão') }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="serv-horizonte-map-stage">
                        <aside
                            class="serv-horizonte-filter-dock"
                            data-horizonte-tour="filters"
                            :class="filterDockOpen ? 'is-open' : ''"
                            x-show="isRegionalMode"
                            x-cloak
                            aria-label="{{ __('Filtros do mapa') }}"
                        >
                            <div class="serv-horizonte-filter-dock__head">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Filtros') }}</p>
                                <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none xl:hidden" @click="filterDockOpen = false" aria-label="{{ __('Fechar') }}">×</button>
                            </div>
                            @include('horizonte.partials.filters-panel', [
                                'viewPresets' => $viewPresets,
                                'tierPresets' => $tierPresets,
                                'canManageSge' => $canManageSge,
                                'compact' => true,
                            ])
                        </aside>

                        <div class="relative px-4 pb-4 min-w-0 flex-1">
                        <div
                            x-show="pageLoading || regionalLoading"
                            x-cloak
                            class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 rounded-lg bg-white/85 dark:bg-slate-900/85 backdrop-blur-sm mx-4"
                            role="status"
                            aria-live="polite"
                        >
                            <div class="h-8 w-8 animate-spin rounded-full border-2 border-teal-600 border-t-transparent" aria-hidden="true"></div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200" x-text="loadingMessage || (regionalLoading ? '{{ __('A carregar UF…') }}' : '{{ __('A carregar…') }}')"></p>
                        </div>
                        <div
                            x-show="!pageLoading && !regionalLoading && mapRendering"
                            x-cloak
                            class="absolute top-3 right-7 z-10 flex items-center gap-2 rounded-lg bg-white/90 dark:bg-slate-900/90 px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 shadow-sm ring-1 ring-slate-200/80 dark:ring-slate-700"
                            role="status"
                        >
                            <span class="h-3 w-3 animate-spin rounded-full border-2 border-teal-600 border-t-transparent" aria-hidden="true"></span>
                            <span>{{ __('A actualizar mapa…') }}</span>
                        </div>
                        <div
                            x-show="!pageLoading && !regionalLoading && totalMarkers === 0 && !isOverviewMode"
                            x-cloak
                            class="absolute inset-x-4 z-[5] flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/95 dark:bg-slate-900/90 px-6 py-8 text-center min-h-[280px]"
                            role="status"
                        >
                            <p class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Mapa vazio') }}</p>
                            <p class="text-sm text-slate-600 dark:text-slate-400 max-w-md" x-text="meta.message || '{{ __('Importe dados públicos nacionais.') }}'"></p>
                        </div>
                        <div
                            x-show="mapHiddenByFilters"
                            x-cloak
                            class="absolute inset-x-4 top-3 z-[5] rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 flex justify-between gap-2"
                            role="status"
                        >
                            <p class="font-medium">{{ __('Filtros ocultam todos os municípios.') }}</p>
                            <button type="button" class="serv-btn-secondary text-xs shrink-0" @click="resetFilters()">{{ __('Vista padrão') }}</button>
                        </div>
                        <div
                            x-show="isRegionalMode && !pageLoading && mapRenderTruncated && !renderCapDismissed"
                            x-cloak
                            class="absolute inset-x-4 top-3 z-[5] rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-950"
                            role="status"
                        >
                            <span class="font-medium">{{ __('Mapa limitado') }}:</span>
                            <span class="tabular-nums" x-text="' ' + mapRenderShownCount.toLocaleString('pt-BR') + ' / ' + filteredCount.toLocaleString('pt-BR')"></span>
                            <button type="button" class="ms-2 underline" x-show="canShowAllOnMap" @click="enableFullMapRender()">{{ __('Mostrar todos') }}</button>
                            <span x-show="!canShowAllOnMap" class="ms-2 text-amber-800/80">{{ __('Use a lista ou zoom nos clusters.') }}</span>
                        </div>

                        <div x-ref="map" data-horizonte-tour="map" class="serv-brazil-map serv-horizonte-gis__map serv-horizonte-gis__map--tall w-full mt-3" role="application" aria-label="{{ __('Mapa Horizonte GIS') }}"></div>

                        @include('horizonte.partials.map-tooltip-sge')
                        </div>
                    </div>
                </section>

                <aside class="serv-horizonte-rail" aria-label="{{ __('Próximas acções') }}" data-horizonte-tour="rail">
                    <div class="serv-horizonte-rail__card">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Abordar primeiro') }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ __('Clique para centrar no mapa.') }}</p>
                        <p x-show="!pageLoading && topProspects.length === 0" x-cloak class="mt-3 text-sm text-slate-500">{{ __('Seleccione uma UF ou aguarde dados.') }}</p>
                        <ol x-show="topProspects.length > 0" class="mt-2 space-y-1.5">
                            <template x-for="(p, idx) in topProspects.slice(0, 8)" :key="p.ibge">
                                <li>
                                    <button type="button" class="flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left hover:bg-teal-50/80 dark:hover:bg-teal-950/30" @click="flyToMarker(p); workspaceTab = 'list'">
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-rose-100 text-[10px] font-bold text-rose-800 dark:bg-rose-950/50 dark:text-rose-200 tabular-nums" x-text="idx + 1"></span>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100 truncate" x-text="p.name + ' (' + p.uf + ')'"></span>
                                            <span class="text-[11px] text-slate-500 tabular-nums" x-text="formatScoreDisplay(p.success_score) + '/100 · ' + (p.tier_label || '')"></span>
                                        </span>
                                    </button>
                                </li>
                            </template>
                        </ol>
                    </div>

                    <div class="serv-horizonte-rail__card">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('UFs prioritárias') }}</h3>
                        <ol x-show="ufRankings.length > 0" class="mt-2 space-y-1">
                            <template x-for="row in ufRankings.slice(0, 6)" :key="row.uf">
                                <li>
                                    <button type="button" class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800/50" @click="selectPriorityUf(row.uf)">
                                        <span class="font-medium truncate" x-text="ufLabel(row.uf)"></span>
                                        <span class="shrink-0 text-[11px] tabular-nums text-rose-700 dark:text-rose-300" x-text="Number(row.high_pressure ?? row.high_prospect ?? 0).toLocaleString('pt-BR')"></span>
                                    </button>
                                </li>
                            </template>
                        </ol>
                    </div>

                    <div class="serv-horizonte-rail__card">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Cobertura') }}</h3>
                        <dl class="mt-2 space-y-1 text-xs">
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">FUNDEB</dt><dd class="font-medium tabular-nums" x-text="formatKpiCount(coverage.with_fundeb)">…</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">SAEB</dt><dd class="font-medium tabular-nums" x-text="formatKpiCount(coverage.with_saeb)">…</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Triad completa') }}</dt><dd class="font-medium tabular-nums" x-text="formatKpiCount(coverage.with_full_triad)">…</dd></div>
                        </dl>
                    </div>
                </aside>
            </div>

            {{-- Área de trabalho — abas --}}
            <section class="serv-panel p-4 sm:p-5" aria-label="{{ __('Área de trabalho') }}" data-horizonte-tour="workspace">
                <nav class="serv-horizonte-workspace__tabs" role="tablist">
                    <button type="button" role="tab" class="serv-horizonte-workspace__tab" :class="workspaceTab === 'actions' ? 'is-active' : ''" @click="workspaceTab = 'actions'">{{ __('Resumo') }}</button>
                    <button type="button" role="tab" class="serv-horizonte-workspace__tab" :class="workspaceTab === 'list' ? 'is-active' : ''" @click="workspaceTab = 'list'">{{ __('Lista de prospecção') }}</button>
                    <button type="button" role="tab" class="serv-horizonte-workspace__tab" :class="workspaceTab === 'data' ? 'is-active' : ''" @click="workspaceTab = 'data'">{{ __('Dados & SGE') }}</button>
                    <button type="button" role="tab" class="serv-horizonte-workspace__tab" :class="workspaceTab === 'methodology' ? 'is-active' : ''" @click="workspaceTab = 'methodology'; methodologyPanelOpen = true">{{ __('Metodologia') }}</button>
                </nav>

                <div class="serv-horizonte-workspace__body">
                    {{-- Resumo --}}
                    <div x-show="workspaceTab === 'actions'" role="tabpanel">
                        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Os filtros do mapa ficam no painel lateral — abra uma UF para refiná-los.') }}</p>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($methodology['map_guide'] ?? [] as $step)
                                <div class="rounded-xl border border-slate-200/90 bg-slate-50/60 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/40">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-teal-700 dark:text-teal-300">{{ __('Passo :n', ['n' => $step['step']]) }}</p>
                                    <p class="mt-1 text-sm font-semibold text-serv-navy dark:text-slate-100">{{ $step['title'] }}</p>
                                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $step['text'] }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div
                            x-show="isOverviewMode && !pageLoading && initialViewNotice?.message"
                            x-cloak
                            class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100"
                        >
                            <p class="font-medium">{{ __('Próximo passo') }}</p>
                            <p x-text="initialViewNotice?.message"></p>
                        </div>
                    </div>

                    {{-- Lista --}}
                    <div x-show="workspaceTab === 'list'" role="tabpanel">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <p class="text-xs text-slate-500">{{ __('Até 50 municípios do recorte, ordenados para abordagem comercial.') }}</p>
                            <div class="flex flex-wrap gap-2">
                                <label class="inline-flex items-center gap-2 text-xs">
                                    <span class="text-slate-500">{{ __('Ordenar') }}</span>
                                    <select x-model="prospectSort" :disabled="pageLoading" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                        <option value="success_score">{{ __('Propensão') }}</option>
                                        <option value="benefit_score">{{ __('Benefício') }}</option>
                                        <option value="matriculas_censo">{{ __('Matrículas') }}</option>
                                        <option value="financial_pressure">{{ __('Pressão FUNDEB') }}</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                        <p x-show="!pageLoading && isOverviewMode" x-cloak class="text-sm text-slate-500">{{ __('Seleccione uma UF para ver a lista filtrada.') }}</p>
                        <p x-show="!pageLoading && isRegionalMode && sortedProspects.length === 0" x-cloak class="text-sm text-slate-500">{{ __('Nenhum município no recorte.') }}</p>
                        <div x-show="sortedProspects.length > 0 && isRegionalMode" class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-left text-slate-500 border-b border-slate-200 dark:border-slate-700">
                                        <th class="py-2 pe-3 font-medium">{{ __('Município') }}</th>
                                        <th class="py-2 px-2 font-medium">{{ __('Prop.') }}</th>
                                        <th class="py-2 px-2 font-medium">{{ __('Benef.') }}</th>
                                        <th class="py-2 px-2 font-medium">{{ __('Matr.') }}</th>
                                        <th class="py-2 px-2 font-medium">{{ __('Press. FUNDEB') }}</th>
                                        <th class="py-2 px-2 font-medium">{{ __('Fontes') }}</th>
                                        <th class="py-2 ps-2 font-medium">{{ __('SGE') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="p in sortedProspects" :key="p.ibge">
                                        <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50/80 dark:hover:bg-slate-900/40 cursor-pointer" @click="flyToMarker(p)">
                                            <td class="py-2 pe-3">
                                                <span class="font-medium text-slate-900 dark:text-slate-100" x-text="p.name"></span>
                                                <span class="text-slate-500" x-text="' (' + p.uf + ')'"></span>
                                            </td>
                                            <td class="py-2 px-2 tabular-nums font-semibold" x-text="formatScoreDisplay(p.success_score)"></td>
                                            <td class="py-2 px-2 tabular-nums" x-text="formatScoreDisplay(p.benefit_score)"></td>
                                            <td class="py-2 px-2 tabular-nums" x-text="p.matriculas_censo != null ? formatCount(p.matriculas_censo) : '—'"></td>
                                            <td class="py-2 px-2 tabular-nums" x-text="formatScoreDisplay(p.financial_pressure)"></td>
                                            <td class="py-2 px-2 text-slate-500" x-text="[p.has_fundeb ? 'F' : null, p.has_censo ? 'C' : null, p.has_saeb ? 'S' : null].filter(Boolean).join('·') || '—'"></td>
                                            <td class="py-2 ps-2">
                                                <button
                                                    type="button"
                                                    class="text-left hover:underline"
                                                    :class="canEditSgeFor(p) ? 'text-amber-700 dark:text-amber-300 font-semibold' : 'text-slate-600 dark:text-slate-300'"
                                                    @click.stop="handleSgeCellClick(p)"
                                                    x-text="canEditSgeFor(p) ? sgeRegistryActionLabel(p) : (p.sge?.system_label || (p.sge_found ? '—' : '{{ __('N/I') }}'))"
                                                ></button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Dados & SGE --}}
                    <div x-show="workspaceTab === 'data'" role="tabpanel" class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <h4 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Cobertura de dados') }}</h4>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Com dados públicos') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_public_data ?? summary.total ?? 0).toLocaleString('pt-BR')"></dd></div>
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">FUNDEB</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_fundeb ?? 0).toLocaleString('pt-BR')"></dd></div>
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">Censo</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_censo ?? 0).toLocaleString('pt-BR')"></dd></div>
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">SAEB</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_saeb ?? 0).toLocaleString('pt-BR')"></dd></div>
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Triad completa') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_full_triad ?? 0).toLocaleString('pt-BR')"></dd></div>
                            </dl>
                            <div x-show="!pageLoading && (meta.needs_refresh || totalMarkers === 0)" x-cloak class="mt-4 rounded-lg bg-slate-900 dark:bg-slate-950 px-4 py-3">
                                <p class="text-xs text-slate-400 mb-2">{{ __('Actualizar dados') }}</p>
                                <code class="block text-xs text-emerald-300 font-mono break-all" x-text="meta.refresh_command || 'php artisan horizonte:fortnightly-feed'"></code>
                                @if ($canRefreshData)
                                    <a :href="meta.hub_url || @js(route('admin.public-data.index', ['hub' => 'horizonte']))" class="mt-2 inline-block text-xs font-medium text-indigo-300 hover:underline">{{ __('Hub Horizonte') }} →</a>
                                @endif
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Sistemas de gestão (SGE)') }}</h4>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Identificados') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(sgeSummary.with_sge ?? 0).toLocaleString('pt-BR')"></dd></div>
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Consultoria i-Educar') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(sgeSummary.consultoria_active ?? 0).toLocaleString('pt-BR')"></dd></div>
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Registo externo') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(sgeSummary.registry ?? 0).toLocaleString('pt-BR')"></dd></div>
                                <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Não identificados') }}</dt><dd class="font-medium tabular-nums text-amber-800 dark:text-amber-300" x-text="pageLoading ? '…' : Number(sgeSummary.not_found ?? 0).toLocaleString('pt-BR')"></dd></div>
                            </dl>
                            @if ($canManageSge)
                                <button type="button" class="serv-btn-secondary text-xs mt-3" :disabled="pageLoading" @click="applyFocusSegment(focusSegments.find(s => s.key === 'missing_sge') || { filter: { tier: 'prospects', only_missing_sge: true } }); workspaceTab = 'list'">{{ __('Ver sem SGE') }}</button>
                            @endif
                        </div>
                    </div>

                    {{-- Metodologia --}}
                    <div x-show="workspaceTab === 'methodology'" role="tabpanel" class="serv-horizonte-methodology">
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $methodology['disclaimer'] ?? '' }}</p>

                        <div class="serv-horizonte-methodology__grid">
                            {{-- Coluna 1: fontes e fórmulas --}}
                            <div class="serv-horizonte-methodology__col">
                                <div>
                                    <p class="font-semibold text-serv-navy dark:text-slate-200">{{ $methodology['detection_title'] ?? __('O que é detectado') }}</p>
                                    <p class="mt-1 text-sm">{{ $methodology['detection_intro'] ?? '' }}</p>
                                    <ul class="mt-2 space-y-1.5">
                                        @foreach ($methodology['detection_sources'] ?? [] as $src)
                                            <li class="serv-horizonte-methodology__dim">
                                                <span class="font-medium text-serv-navy dark:text-slate-200">{{ $src['label'] }}</span>
                                                <span class="text-slate-500 dark:text-slate-400"> — </span>
                                                <span>{{ $src['feeds'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div class="rounded-lg border border-teal-200/80 bg-teal-50/50 dark:border-teal-900/50 dark:bg-teal-950/20 px-3 py-2 space-y-2">
                                    <div>
                                        <p class="font-semibold text-teal-900 dark:text-teal-100">{{ $methodology['success_title'] ?? __('Propensão') }}</p>
                                        <p class="mt-0.5 text-sm">{{ $methodology['success_formula'] ?? '' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-teal-900 dark:text-teal-100">{{ $methodology['benefit_title'] ?? __('Benefício') }}</p>
                                        <p class="mt-0.5 text-sm">{{ $methodology['benefit_formula'] ?? '' }}</p>
                                    </div>
                                    <p class="text-[11px] text-teal-800/80 dark:text-teal-200/80">{{ $methodology['tier_rules'] ?? '' }}</p>
                                </div>
                            </div>

                            {{-- Coluna 2: cenários e dimensões --}}
                            <div class="serv-horizonte-methodology__col">
                                <div>
                                    <p class="font-semibold text-serv-navy dark:text-slate-200">{{ $methodology['scenarios_title'] ?? __('Cenários') }}</p>
                                    <p class="mt-1 text-sm">{{ $methodology['scenarios_intro'] ?? '' }}</p>
                                    <div class="mt-2 space-y-2">
                                        @foreach ($methodology['tier_scenarios'] ?? [] as $scenario)
                                            <div class="serv-horizonte-methodology__dim">
                                                <p class="font-medium text-serv-navy dark:text-slate-200">{{ $scenario['label'] }}</p>
                                                <p class="mt-0.5"><span class="font-medium text-slate-500 dark:text-slate-400">{{ __('Quando:') }}</span> {{ $scenario['when'] }}</p>
                                                <p class="mt-0.5"><span class="font-medium text-slate-500 dark:text-slate-400">{{ __('Efeito:') }}</span> {{ $scenario['effect'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <p class="font-semibold text-serv-navy dark:text-slate-200">{{ __('Dimensões (peso)') }}</p>
                                    @foreach ($methodology['dimensions'] ?? [] as $dim)
                                        <div class="serv-horizonte-methodology__dim mt-2">
                                            <p class="font-medium text-serv-navy dark:text-slate-200">
                                                {{ $dim['label'] }}
                                                <span class="text-teal-700 dark:text-teal-300 tabular-nums">{{ $dim['weight'] }}%</span>
                                            </p>
                                            <p class="mt-0.5 text-sm">{{ $dim['formula'] }}</p>
                                            @if (! empty($dim['detects']))
                                                <p class="mt-1.5 text-[11px]"><span class="font-semibold text-slate-500 dark:text-slate-400">{{ __('Detecta:') }}</span> {{ $dim['detects'] }}</p>
                                            @endif
                                            @if (! empty($dim['scenarios']) && is_array($dim['scenarios']))
                                                <ul class="mt-1 list-disc list-inside space-y-0.5 text-[11px] text-slate-600 dark:text-slate-400">
                                                    @foreach ($dim['scenarios'] as $scenarioLine)
                                                        <li>{{ $scenarioLine }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Coluna 3: discrepâncias fora da fórmula --}}
                            <div class="serv-horizonte-methodology__col">
                                <div class="rounded-lg border border-amber-200/80 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/20 px-3 py-2 h-full">
                                    <p class="font-semibold text-amber-900 dark:text-amber-100">{{ $methodology['outside_formula_title'] ?? '' }}</p>
                                    <p class="mt-1 text-sm">{{ $methodology['outside_formula_intro'] ?? '' }}</p>
                                    @foreach ($methodology['discrepancy_groups'] ?? [] as $group)
                                        <div class="mt-2">
                                            <p class="font-medium text-amber-900/90 dark:text-amber-200/90 text-[11px] uppercase tracking-wide">{{ $group['label'] }}</p>
                                            <ul class="mt-1 list-disc list-inside space-y-0.5 text-[11px]">
                                                @foreach ($group['items'] ?? [] as $item)
                                                    <li>{{ $item['title'] }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                    @if (! empty($methodology['outside_formula_footer']))
                                        <p class="mt-2 text-[11px] text-amber-800/90 dark:text-amber-200/80">{{ $methodology['outside_formula_footer'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Demonstração animada --}}
            <section
                x-show="guideOpen"
                x-cloak
                x-transition
                data-horizonte-guide="demo"
                class="serv-panel p-4 sm:p-5"
                aria-label="{{ __('Demonstração Horizonte') }}"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Como funciona o Horizonte') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Fluxo típico de decisão comercial — do Brasil ao município.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="serv-btn-secondary text-xs" @click="startTour()">{{ __('Tour guiado') }}</button>
                        <button type="button" class="text-slate-400 hover:text-slate-600 text-xs" @click="guideOpen = false">{{ __('Fechar') }}</button>
                    </div>
                </div>

                @include('horizonte.partials.guide-demo-stage', ['methodology' => $methodology])
            </section>

            {{-- Tour guiado in-app --}}
            <div
                x-show="tourActive"
                x-cloak
                class="serv-horizonte-tour"
                role="dialog"
                aria-modal="true"
                aria-labelledby="horizonte-tour-title"
                @keydown.escape.window="skipTour()"
            >
                <div class="serv-horizonte-tour__backdrop" @click="skipTour()"></div>
                <div class="serv-horizonte-tour__spotlight" :style="tourSpotlightStyle"></div>
                <div class="serv-horizonte-tour__card" x-ref="tourCard" :style="tourCardStyle">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-teal-700 dark:text-teal-300">
                        {{ __('Guia') }}
                        <span class="tabular-nums" x-text="(tourStepIndex + 1) + ' / ' + tourStepsList.length"></span>
                    </p>
                    <h4 id="horizonte-tour-title" class="mt-1 text-sm font-semibold text-serv-navy dark:text-slate-100" x-text="currentTourStep?.title"></h4>
                    <p class="mt-1.5 text-xs text-slate-600 dark:text-slate-400 leading-relaxed" x-text="currentTourStep?.text"></p>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                        <button type="button" class="text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" @click="skipTour()">{{ __('Saltar') }}</button>
                        <div class="flex gap-2">
                            <button type="button" class="serv-btn-secondary text-xs" x-show="tourStepIndex > 0" @click="prevTourStep()">{{ __('Anterior') }}</button>
                            <button type="button" class="serv-btn-primary text-xs" @click="nextTourStep()">
                                <span x-show="tourStepIndex < tourStepsList.length - 1">{{ __('Seguinte') }}</span>
                                <span x-show="tourStepIndex >= tourStepsList.length - 1">{{ __('Concluir') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
