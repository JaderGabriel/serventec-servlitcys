@php
    $refYear = (int) ($refYear ?? config('horizonte.reference_year', (int) date('Y') - 1));
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
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Horizonte') }}</p>
                <h2 class="font-display font-semibold text-xl sm:text-2xl text-serv-navy dark:text-slate-100 leading-tight">
                    {{ __('Inteligência comercial municipal') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-3xl">
                    {{ __('Painel GIS gerencial: visão nacional por UF e detalhe municipal com filtros interactivos, clusters e scores de oportunidade.') }}
                </p>
            </div>
            <a href="{{ $docUrl }}" class="serv-link text-sm shrink-0">{{ __('Documentação Horizonte') }}</a>
        </div>
    </x-slot>

    <div
        class="py-8 sm:py-10"
        x-data="horizonteMap([], @js($colors), @js([
            'loadUrl' => $mapDataUrl,
            'refYear' => $refYear,
            'legend' => $legend,
            'heatLegend' => $heatLegend,
            'methodology' => $methodology,
            'canRefreshData' => $canRefreshData,
            'canManageSge' => $canManageSge,
            'sgeShowUrl' => $sgeShowUrl,
            'sgeRegistryUrl' => $sgeRegistryUrl,
            'initialUf' => $initialUf,
        ]))"
        x-init="init()"
    >
        <div class="max-w-[100rem] mx-auto sm:px-6 lg:px-8 space-y-6">
            <div x-show="pageError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200" role="alert">
                <p class="font-medium">{{ __('Não foi possível carregar o mapa Horizonte.') }}</p>
                <p class="mt-1" x-text="pageError"></p>
            </div>

            <section class="serv-horizonte-guide grid gap-3 sm:grid-cols-3" aria-label="{{ __('Como usar o Horizonte') }}">
                @foreach ($methodology['map_guide'] ?? [] as $step)
                    <div class="serv-horizonte-guide__step rounded-xl border border-slate-200/90 bg-white/90 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/60">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-teal-700 dark:text-teal-300">{{ __('Passo :n', ['n' => $step['step']]) }}</p>
                        <p class="mt-1 text-sm font-semibold text-serv-navy dark:text-slate-100">{{ $step['title'] }}</p>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $step['text'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="serv-panel p-4 sm:p-5" aria-labelledby="horizonte-kpis-heading">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3 mb-4">
                    <div>
                        <h3 id="horizonte-kpis-heading" class="font-display text-base font-semibold text-serv-navy dark:text-slate-100">{{ __('Indicadores do recorte') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Resumo nacional ou da UF seleccionada — passe o rato nos ⓘ para detalhes.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-[10px]">
                        @foreach ($legend as $item)
                            @if (in_array($item['key'], ['prospect_high', 'prospect_medium', 'prospect_low', 'consultoria_active', 'data_sparse'], true))
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 ring-1 ring-slate-200/80 dark:bg-slate-900/60 dark:ring-slate-600" title="{{ $item['description'] ?? '' }}">
                                    <span class="h-2 w-2 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                    <span class="text-slate-600 dark:text-slate-300">{{ $item['label'] }}</span>
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
                <div class="serv-horizonte-kpi serv-home-kpi--teal" title="{{ $kpiHints['with_public_data']['hint'] ?? '' }}">
                    <span class="serv-horizonte-kpi__info" aria-hidden="true" title="{{ $kpiHints['with_public_data']['hint'] ?? '' }}">ⓘ</span>
                    <p class="serv-home-kpi__label">{{ $kpiHints['with_public_data']['label'] ?? __('Com dados públicos') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_public_data ?? summary.total ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('FUNDEB, Censo ou SAEB') }}</p>
                </div>
                <div class="serv-horizonte-kpi" title="{{ $kpiHints['prospect_count']['hint'] ?? '' }}">
                    <span class="serv-horizonte-kpi__info" aria-hidden="true">ⓘ</span>
                    <p class="serv-home-kpi__label">{{ $kpiHints['prospect_count']['label'] ?? __('Prospectos') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.prospect_count ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Sem Consultoria activa') }}</p>
                </div>
                <div class="serv-horizonte-kpi serv-home-kpi--amber" title="{{ $kpiHints['high_prospect']['hint'] ?? '' }}">
                    <span class="serv-horizonte-kpi__info" aria-hidden="true">ⓘ</span>
                    <p class="serv-home-kpi__label">{{ $kpiHints['high_prospect']['label'] ?? __('Alta propensão') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.high_prospect ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('≥ :n pts', ['n' => $methodology['thresholds']['high'] ?? 70]) }}</p>
                </div>
                <div class="serv-horizonte-kpi serv-home-kpi--teal" title="{{ $kpiHints['consultoria_active']['hint'] ?? '' }}">
                    <span class="serv-horizonte-kpi__info" aria-hidden="true">ⓘ</span>
                    <p class="serv-home-kpi__label">{{ $kpiHints['consultoria_active']['label'] ?? __('Consultoria activa') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.consultoria_active ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Base i-Educar pronta') }}</p>
                </div>
                <div class="serv-horizonte-kpi md:col-span-3 xl:col-span-1" title="{{ $kpiHints['prospect_matriculas']['hint'] ?? '' }}">
                    <span class="serv-horizonte-kpi__info" aria-hidden="true">ⓘ</span>
                    <p class="serv-home-kpi__label">{{ $kpiHints['prospect_matriculas']['label'] ?? __('Matrículas prospecto') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(coverage.prospect_matriculas_censo ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Censo ref. :ano', ['ano' => $refYear]) }}</p>
                </div>
                </div>
            </section>

            <section class="serv-panel p-4 sm:p-5" aria-labelledby="horizonte-segments-heading">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div>
                        <h3 id="horizonte-segments-heading" class="font-display text-base font-semibold text-serv-navy dark:text-slate-100">
                            {{ __('Onde buscar clientes') }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 max-w-2xl">
                            {{ __('Segmentos com critérios pré-definidos — clique para aplicar filtros e mapa de calor.') }}
                        </p>
                    </div>
                    <button type="button" class="serv-btn-secondary text-xs shrink-0" @click="resetFilters()" :disabled="pageLoading">
                        {{ __('Limpar filtros') }}
                    </button>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="seg in focusSegments" :key="seg.key">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-3 text-left transition hover:border-teal-300 hover:bg-teal-50/50 dark:border-slate-700 dark:bg-slate-900/50 dark:hover:border-teal-700 dark:hover:bg-teal-950/30 disabled:opacity-60"
                            :disabled="pageLoading"
                            @click="applyFocusSegment(seg)"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-teal-800 dark:text-teal-300" x-text="seg.label"></p>
                            <p class="mt-1 text-2xl font-display font-semibold tabular-nums text-serv-navy dark:text-slate-100" x-text="Number(seg.count ?? 0).toLocaleString('pt-BR')"></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed" x-text="seg.description"></p>
                        </button>
                    </template>
                </div>
            </section>

            <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_24rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
                <section class="serv-panel overflow-hidden min-w-0 serv-horizonte-gis" aria-labelledby="horizonte-map-heading">
                    <div class="serv-horizonte-gis__toolbar px-5 py-3 border-b border-slate-200/90 dark:border-slate-700/90 bg-slate-50/80 dark:bg-slate-900/50">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="serv-horizonte-gis__mode-pill" :class="isOverviewMode ? 'is-national' : 'is-regional'">
                                    <span x-show="isOverviewMode">{{ __('Visão nacional') }}</span>
                                    <span x-show="isRegionalMode" x-cloak x-text="'UF ' + scopeUf"></span>
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
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <label class="inline-flex items-center gap-2">
                                    <span class="text-slate-500 shrink-0">{{ __('UF') }}</span>
                                    <select
                                        x-model="scopeUf"
                                        @change="onScopeUfPick($event)"
                                        :disabled="pageLoading || regionalLoading"
                                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm min-w-[5rem]"
                                    >
                                        <option value="">{{ __('Nacional') }}</option>
                                        <template x-for="uf in ufList.length ? ufList : ufRankings.map(r => r.uf)" :key="uf">
                                            <option :value="uf" x-text="uf"></option>
                                        </template>
                                    </select>
                                </label>
                                <div class="flex gap-1 rounded-lg ring-1 ring-slate-200/80 dark:ring-slate-600 p-0.5" x-show="isRegionalMode" x-cloak>
                                    <button type="button" class="rounded-md px-2.5 py-1 font-medium transition" :class="mapView === 'markers' ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50' : 'text-slate-600'" @click="setMapView('markers')">{{ __('Pontos') }}</button>
                                    <button type="button" class="rounded-md px-2.5 py-1 font-medium transition" :class="mapView === 'heat' ? 'bg-rose-100 text-rose-900 dark:bg-rose-950/50' : 'text-slate-600'" @click="setMapView('heat')">{{ __('Calor') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <h3 id="horizonte-map-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100">
                                <span x-show="isOverviewMode">{{ __('Mapa — agregado por UF') }}</span>
                                <span x-show="isRegionalMode" x-cloak>{{ __('Mapa municipal') }}</span>
                            </h3>
                            <p class="text-xs text-slate-500 tabular-nums" x-show="!pageLoading">
                                <span x-show="isOverviewMode">{{ __('Clique num estado para detalhar municípios') }}</span>
                                <span x-show="isRegionalMode" x-cloak>
                                    <span x-text="filteredCount.toLocaleString('pt-BR')"></span> {{ __('no recorte') }} ·
                                    <span x-text="totalMarkers.toLocaleString('pt-BR')"></span> {{ __('nacional') }}
                                </span>
                            </p>
                        </div>

                        <div
                            @keydown.escape.window="closeTooltip()"
                            class="serv-horizonte-filters"
                            :class="{ 'pointer-events-none opacity-60': pageLoading || regionalLoading }"
                            :aria-busy="(pageLoading || regionalLoading || mapRendering) ? 'true' : 'false'"
                            x-show="isRegionalMode"
                            x-cloak
                        >
                            <button
                                type="button"
                                class="serv-horizonte-filters__toggle"
                                @click="filterPanelOpen = !filterPanelOpen"
                                :aria-expanded="filterPanelOpen"
                            >
                                <span class="flex items-center gap-2">
                                    {{ __('Filtros interactivos') }}
                                    <span
                                        x-show="activeFilterCount > 0"
                                        x-cloak
                                        class="serv-horizonte-filter-chip serv-horizonte-filter-chip--count tabular-nums"
                                        x-text="activeFilterCount + ' {{ __('activos') }}'"
                                    ></span>
                                </span>
                                <span class="text-slate-400 text-xs" x-text="filterPanelOpen ? '▲' : '▼'"></span>
                            </button>

                            <div x-show="activeFilterChips.length > 0" x-cloak class="px-4 pb-2 flex flex-wrap gap-1.5">
                                <template x-for="chip in activeFilterChips" :key="chip.key">
                                    <span class="serv-horizonte-filter-chip" x-text="chip.label"></span>
                                </template>
                                <button type="button" class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline ms-1" @click="resetFilters()">{{ __('Limpar') }}</button>
                            </div>

                            <div x-show="filterPanelOpen" x-transition.opacity.duration.200ms class="serv-horizonte-filters__body">
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <div class="relative sm:col-span-2 lg:col-span-2">
                                        <label for="horizonte-search" class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Buscar município') }}</label>
                                        <input
                                            id="horizonte-search"
                                            type="search"
                                            x-model="searchQuery"
                                            :disabled="pageLoading"
                                            placeholder="{{ __('Nome ou IBGE…') }}"
                                            autocomplete="off"
                                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 disabled:opacity-60"
                                        />
                                        <ul
                                            x-show="searchSuggestions.length > 0 && searchQuery.trim().length >= 2"
                                            x-cloak
                                            class="absolute z-30 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-lg text-sm"
                                        >
                                            <template x-for="item in searchSuggestions" :key="item.ibge">
                                                <li>
                                                    <button
                                                        type="button"
                                                        class="w-full px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700/80"
                                                        @click="pickSearch(item)"
                                                    >
                                                        <span x-text="item.name + ' — ' + item.uf"></span>
                                                        <span class="block text-xs text-gray-500" x-text="'IBGE ' + item.ibge"></span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Ordenar lista') }}</label>
                                        <select
                                            x-model="prospectSort"
                                            :disabled="pageLoading"
                                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 disabled:opacity-60"
                                        >
                                            <option value="success_score">{{ __('Propensão') }}</option>
                                            <option value="benefit_score">{{ __('Benefício') }}</option>
                                            <option value="matriculas_censo">{{ __('Matrículas') }}</option>
                                            <option value="financial_pressure">{{ __('Pressão FUNDEB') }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">{{ __('Segmento / camada') }}</p>
                                    <div class="flex flex-wrap gap-1.5 text-xs">
                                        @php
                                            $tierPresets = [
                                                'prospects' => ['label' => __('Prospectos'), 'color' => $colors['prospect_medium'] ?? '#b45309'],
                                                'prospect_high' => ['label' => __('Alta propensão'), 'color' => $colors['prospect_high'] ?? '#be123c'],
                                                'all' => ['label' => __('Todos'), 'color' => '#64748b'],
                                                'consultoria_active' => ['label' => __('Consultoria'), 'color' => $colors['consultoria_active'] ?? '#0d9488'],
                                                'catalog_pending' => ['label' => __('Catálogo pendente'), 'color' => $colors['catalog_pending'] ?? '#ea580c'],
                                            ];
                                        @endphp
                                        @foreach ($tierPresets as $key => $preset)
                                            <button
                                                type="button"
                                                :disabled="pageLoading"
                                                class="serv-horizonte-tier-pill bg-white/80 text-slate-700 ring-slate-200/80 dark:bg-slate-900/60 dark:text-slate-200 dark:ring-slate-600"
                                                :class="filterTier === @js($key) ? 'is-active bg-teal-50 dark:bg-teal-950/40' : ''"
                                                @click="setFilterTier(@js($key))"
                                            >
                                                <span class="inline-block h-2 w-2 rounded-full" style="background-color: {{ $preset['color'] }}"></span>
                                                {{ $preset['label'] }}
                                            </button>
                                        @endforeach
                                        <label class="inline-flex items-center gap-1.5 serv-horizonte-tier-pill bg-white/80 ring-slate-200/80 dark:bg-slate-900/60 dark:ring-slate-600">
                                            <input type="checkbox" x-model="hideConsultoria" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" />
                                            <span>{{ __('Ocultar consultoria') }}</span>
                                        </label>
                                        @if ($canManageSge)
                                            <label class="inline-flex items-center gap-1.5 serv-horizonte-tier-pill bg-amber-50/80 ring-amber-200/80 dark:bg-amber-950/30 dark:ring-amber-900/50">
                                                <input type="checkbox" x-model="onlyMissingSge" class="rounded border-gray-300 text-amber-600" :disabled="pageLoading" />
                                                <span>{{ __('Só sem SGE') }}</span>
                                            </label>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                                    <label class="flex flex-col gap-1">
                                        <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Propensão mínima') }}</span>
                                        <input type="range" min="0" max="100" step="5" x-model.number="minSuccessScore" class="w-full accent-rose-600" :disabled="pageLoading" />
                                        <span class="flex items-baseline justify-between gap-2">
                                            <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minSuccessScore + '/100'"></span>
                                            <span class="serv-horizonte-score-hint" x-text="'Alta ≥ ' + scoreThresholds.high"></span>
                                        </span>
                                    </label>
                                    <label class="flex flex-col gap-1">
                                        <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Benefício mínimo') }}</span>
                                        <input type="range" min="0" max="100" step="5" x-model.number="minBenefitScore" class="w-full accent-teal-600" :disabled="pageLoading" />
                                        <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minBenefitScore + '/100'"></span>
                                    </label>
                                    <label class="flex flex-col gap-1">
                                        <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Matrículas mín.') }}</span>
                                        <select x-model.number="minMatriculas" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm" :disabled="pageLoading">
                                            <option value="0">{{ __('Qualquer') }}</option>
                                            <option value="5000">5 000+</option>
                                            <option value="15000">15 000+</option>
                                            <option value="30000">30 000+</option>
                                        </select>
                                    </label>
                                    <label class="flex flex-col gap-1">
                                        <span class="text-slate-600 dark:text-slate-400 font-medium">{{ __('Demanda social mín.') }}</span>
                                        <input type="range" min="0" max="100" step="5" x-model.number="minSocialDemand" class="w-full accent-amber-600" :disabled="pageLoading" />
                                        <span class="tabular-nums font-semibold text-serv-navy dark:text-slate-100" x-text="minSocialDemand + '/100'"></span>
                                    </label>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">{{ __('Exigir fontes de dados') }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/60 dark:ring-slate-600 has-[:checked]:ring-teal-400 has-[:checked]:bg-teal-50/80 dark:has-[:checked]:bg-teal-950/30">
                                            <input type="checkbox" x-model="requireFundeb" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>FUNDEB</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/60 dark:ring-slate-600 has-[:checked]:ring-teal-400 has-[:checked]:bg-teal-50/80 dark:has-[:checked]:bg-teal-950/30">
                                            <input type="checkbox" x-model="requireCenso" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>Censo</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/60 dark:ring-slate-600 has-[:checked]:ring-teal-400 has-[:checked]:bg-teal-50/80 dark:has-[:checked]:bg-teal-950/30">
                                            <input type="checkbox" x-model="requireSaeb" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>SAEB</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 ring-1 ring-slate-200/80 bg-white/80 dark:bg-slate-900/60 dark:ring-slate-600 has-[:checked]:ring-teal-400 has-[:checked]:bg-teal-50/80 dark:has-[:checked]:bg-teal-950/30">
                                            <input type="checkbox" x-model="requireCadunico" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>CadÚnico</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            x-show="isOverviewMode && !pageLoading && initialViewNotice?.message"
                            x-cloak
                            class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100"
                            role="status"
                        >
                            <p class="font-medium">{{ __('Visão executiva nacional') }}</p>
                            <p x-text="initialViewNotice?.message"></p>
                            <p class="mt-1 text-xs tabular-nums text-sky-800/80 dark:text-sky-200/80">
                                <span x-text="totalMarkers.toLocaleString('pt-BR')"></span> {{ __('municípios na base') }} ·
                                <span x-text="ufMapPoints.length"></span> {{ __('UFs no mapa') }}
                            </p>
                        </div>

                        <div class="serv-map-legend flex flex-wrap gap-x-4 gap-y-2 text-xs" x-show="isRegionalMode" x-cloak>
                            <template x-if="mapView === 'heat'">
                                <template x-for="item in heatLegend" :key="item.key">
                                    <span class="serv-map-legend__item" :title="item.description || ''">
                                        <span class="serv-map-legend-swatch serv-map-legend-swatch--connection" :style="'background-color:' + (item.color || '#64748b')" aria-hidden="true"></span>
                                        <span class="text-slate-600 dark:text-slate-300" x-text="item.label"></span>
                                    </span>
                                </template>
                            </template>
                            <template x-if="mapView === 'markers'">
                                <template x-for="item in legend" :key="item.key">
                                    <span class="serv-map-legend__item" :title="item.description || ''">
                                        <span class="serv-map-legend-swatch serv-map-legend-swatch--connection" :style="'background-color:' + (item.color || '#64748b')" aria-hidden="true"></span>
                                        <span class="text-slate-600 dark:text-slate-300" x-text="item.label"></span>
                                    </span>
                                </template>
                            </template>
                            @foreach ($methodology['map_legend_notes'] ?? [] as $note)
                                <span class="serv-map-legend__item" title="{{ $note['description'] }}">
                                    @if ($note['key'] === 'approx')
                                        <span class="serv-map-legend-swatch serv-map-legend-swatch--connection serv-horizonte-legend-approx" aria-hidden="true"></span>
                                    @else
                                        <span class="serv-map-legend-swatch serv-map-legend-swatch--connection bg-slate-400" aria-hidden="true"></span>
                                    @endif
                                    <span class="text-slate-600 dark:text-slate-300">{{ $note['label'] }}</span>
                                </span>
                            @endforeach
                        </div>
                        <p
                            x-show="isRegionalMode && !pageLoading"
                            x-cloak
                            class="text-[11px] text-slate-500 dark:text-slate-400 tabular-nums"
                        >
                            <span x-text="mapInteractionStats.onMap.toLocaleString('pt-BR')"></span> {{ __('pontos no mapa') }}
                            · <span x-text="mapInteractionStats.total.toLocaleString('pt-BR')"></span> {{ __('no recorte') }}
                            <span x-show="mapInteractionStats.approximate > 0" x-cloak>
                                · <span x-text="mapInteractionStats.approximate.toLocaleString('pt-BR')"></span> {{ __('coord. aproximada') }}
                            </span>
                        </p>
                        <div class="serv-map-legend flex flex-wrap gap-x-4 gap-y-2 text-xs" x-show="isOverviewMode" x-cloak>
                            <span class="serv-map-legend__item">
                                <span class="serv-map-legend-swatch serv-map-legend-swatch--connection bg-amber-300" aria-hidden="true"></span>
                                <span class="text-slate-600 dark:text-slate-300">{{ __('Tamanho = volume municipal') }}</span>
                            </span>
                            <span class="serv-map-legend__item">
                                <span class="serv-map-legend-swatch serv-map-legend-swatch--connection bg-rose-700" aria-hidden="true"></span>
                                <span class="text-slate-600 dark:text-slate-300">{{ __('Cor = alta propensão') }}</span>
                            </span>
                        </div>

                        <div
                            x-show="isRegionalMode && !pageLoading && mapRenderTruncated && !renderCapDismissed"
                            x-cloak
                            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
                            role="status"
                        >
                            <span class="font-medium">{{ __('Mapa limitado') }}:</span>
                            <span class="tabular-nums" x-text="' ' + mapRenderShownCount.toLocaleString('pt-BR') + ' / ' + filteredCount.toLocaleString('pt-BR') + ' {{ __('pontos') }}.'"></span>
                            <button type="button" class="ms-2 text-xs underline" @click="enableFullMapRender()">{{ __('Mostrar todos') }}</button>
                        </div>

                        <div class="relative">
                            <div
                                x-show="pageLoading || regionalLoading"
                                x-cloak
                                class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 rounded-lg bg-white/85 dark:bg-slate-900/85 backdrop-blur-sm"
                                role="status"
                                aria-live="polite"
                            >
                                <div class="h-8 w-8 animate-spin rounded-full border-2 border-teal-600 border-t-transparent" aria-hidden="true"></div>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200" x-text="loadingMessage || (regionalLoading ? '{{ __('A carregar UF…') }}' : '{{ __('A carregar…') }}')"></p>
                            </div>
                            <div
                                x-show="!pageLoading && !regionalLoading && mapRendering"
                                x-cloak
                                class="absolute top-3 right-3 z-10 flex items-center gap-2 rounded-lg bg-white/90 dark:bg-slate-900/90 px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 shadow-sm ring-1 ring-slate-200/80 dark:ring-slate-700"
                                role="status"
                                aria-live="polite"
                            >
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-teal-600 border-t-transparent" aria-hidden="true"></span>
                                <span>{{ __('A actualizar mapa…') }}</span>
                            </div>

                            <div
                                x-show="!pageLoading && !regionalLoading && totalMarkers === 0 && !isOverviewMode"
                                x-cloak
                                class="absolute inset-0 z-[5] flex flex-col items-center justify-center gap-4 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/95 dark:bg-slate-900/90 px-6 py-8 text-center"
                                role="status"
                            >
                                <p class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Mapa vazio') }}</p>
                                <p class="text-sm text-slate-600 dark:text-slate-400 max-w-md" x-text="meta.message || '{{ __('Importe dados públicos nacionais.') }}'"></p>
                            </div>

                            <div
                                x-show="mapHiddenByFilters"
                                x-cloak
                                class="absolute inset-x-0 top-0 z-[5] mx-3 mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 flex justify-between gap-2"
                                role="status"
                            >
                                <p class="font-medium">{{ __('Filtros ocultam todos os municípios.') }}</p>
                                <button type="button" class="serv-btn-secondary text-xs shrink-0" @click="resetFilters()">{{ __('Limpar') }}</button>
                            </div>

                            <div x-ref="map" class="serv-brazil-map serv-horizonte-gis__map serv-horizonte-gis__map--tall w-full" role="application" aria-label="{{ __('Mapa Horizonte GIS') }}"></div>
                                <div
                                    x-show="active"
                                    x-cloak
                                    x-transition.opacity.duration.150ms
                                    class="serv-brazil-map-tooltip serv-brazil-map-tooltip--wide"
                                    :style="tooltipStyle"
                                    @click.outside="closeTooltip()"
                                >
                                    <template x-if="active">
                                        <div class="space-y-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-slate-900 dark:text-slate-100" x-text="active.name + ' — ' + active.uf"></p>
                                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="'IBGE ' + active.ibge + ' · ' + tierLabel(active)"></p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                                    x-on:click="closeTooltip()"
                                                    aria-label="{{ __('Fechar') }}"
                                                >&times;</button>
                                            </div>
                                            <div x-html="tooltipBodyHtml(active)"></div>
                                            <div x-show="canManageSge && active && canEditSgeFor(active)" x-cloak class="pt-2 border-t border-slate-200/80 dark:border-slate-700/80 space-y-2">
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                                                    {{ __('Inteligência de concorrência — não cadastra o município no catálogo Consultoria.') }}
                                                </p>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500"
                                                    @click.stop="openSgeForm(active)"
                                                >
                                                    {{ __('SGE') }}
                                                </button>
                                            </div>
                                            <div x-show="canManageSge && active && active.in_catalog" x-cloak class="pt-2 border-t border-slate-200/80 dark:border-slate-700/80">
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                                    {{ __('Município no catálogo Consultoria — o SGE é gerido na ficha da cidade.') }}
                                                    <a x-show="active.cities_url" :href="active.cities_url" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Abrir ficha') }}</a>
                                                </p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Visão nacional por UF; detalhe municipal com clusters. Clique num ponto para ver dimensões e fórmulas no tooltip.') }}
                            </p>
                        </div>
                    </div>

                    <div
                        x-show="!pageLoading && (meta.needs_refresh || totalMarkers === 0)"
                        x-cloak
                        class="px-5 py-4 border-t border-slate-200/90 dark:border-slate-700/90 bg-slate-50/60 dark:bg-slate-900/40"
                    >
                        <h4 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Actualizar dados do mapa') }}</h4>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 max-w-3xl" x-show="meta.message" x-text="meta.message"></p>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Rotina bimestral ou execução manual no servidor:') }}
                        </p>
                        <div class="mt-2 rounded-lg bg-slate-900 dark:bg-slate-950 px-4 py-3">
                            <code class="block text-xs sm:text-sm text-emerald-300 font-mono break-all" x-text="meta.refresh_command || 'php artisan horizonte:fortnightly-feed'"></code>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-3 text-xs">
                            @if ($canRefreshData)
                                <a
                                    :href="meta.hub_url || @js(route('admin.public-data.index', ['hub' => 'horizonte']))"
                                    class="font-medium text-indigo-700 dark:text-indigo-300 hover:underline"
                                >{{ __('Hub Horizonte — abastecer pela UI') }} →</a>
                            @endif
                            <span class="text-slate-500">{{ __('Após importar, o cache invalida-se pelo fingerprint dos dados.') }}</span>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-slate-200/90 dark:border-slate-700/90">
                        <h4 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Lista de prospecção') }}</h4>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Até 50 municípios do recorte actual, ordenados para abordagem comercial.') }}</p>
                        <p x-show="!pageLoading && sortedProspects.length === 0" x-cloak class="mt-3 text-sm text-slate-500">{{ __('Nenhum município no recorte — ajuste filtros ou importe dados públicos.') }}</p>
                        <div x-show="sortedProspects.length > 0" class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-left text-slate-500 border-b border-slate-200 dark:border-slate-700">
                                        <th class="py-2 pe-3 font-medium">{{ __('Município') }}</th>
                                        <th class="py-2 px-2 font-medium">{{ __('Prop.') }}</th>
                                        <th class="py-2 px-2 font-medium">{{ __('Benef.') }}</th>
                                        <th class="py-2 px-2 font-medium">{{ __('Matr.') }}</th>
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
                                            <td class="py-2 px-2 tabular-nums font-semibold" x-text="p.success_score"></td>
                                            <td class="py-2 px-2 tabular-nums" x-text="p.benefit_score"></td>
                                            <td class="py-2 px-2 tabular-nums" x-text="p.matriculas_censo != null ? Number(p.matriculas_censo).toLocaleString('pt-BR') : '—'"></td>
                                            <td class="py-2 px-2 text-slate-500" x-text="[p.has_fundeb ? 'F' : null, p.has_censo ? 'C' : null, p.has_saeb ? 'S' : null].filter(Boolean).join('·') || '—'"></td>
                                            <td class="py-2 ps-2 text-slate-600 dark:text-slate-300">
                                                <button
                                                    type="button"
                                                    class="text-left hover:underline"
                                                    :class="canEditSgeFor(p) ? 'text-amber-700 dark:text-amber-300 font-semibold' : (!(p.sge_found ?? false) ? 'text-slate-400' : '')"
                                                    @click.stop="handleSgeCellClick(p)"
                                                    x-text="canEditSgeFor(p) ? '{{ __('SGE') }}' : (p.sge?.system_label || (p.sge_found ? '—' : '{{ __('N/I') }}'))"
                                                ></button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <aside class="space-y-4 xl:sticky xl:top-4 xl:self-start xl:max-h-[calc(100dvh-2rem)] xl:overflow-y-auto">
                    <section class="serv-panel p-4" aria-labelledby="horizonte-regions">
                        <h3 id="horizonte-regions" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('UFs prioritárias') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Clique para abrir o detalhe municipal da UF.') }}</p>
                        <p x-show="!pageLoading && ufRankings.length === 0" x-cloak class="mt-3 text-sm text-slate-500">{{ __('Sem dados regionais.') }}</p>
                        <ol x-show="ufRankings.length > 0" class="mt-3 space-y-2 text-sm">
                            <template x-for="row in ufRankings" :key="row.uf">
                                <li>
                                    <button type="button" class="w-full flex items-center justify-between gap-2 rounded-lg bg-slate-50/80 dark:bg-slate-900/50 px-3 py-2 text-left hover:bg-teal-50/80 dark:hover:bg-teal-950/30" @click="selectUf(row.uf)">
                                        <span class="font-medium" x-text="row.uf"></span>
                                        <span class="text-xs text-slate-500 tabular-nums" x-text="Number(row.without_consultoria ?? 0).toLocaleString('pt-BR') + ' {{ __('sem consult.') }}'"></span>
                                        <span class="text-xs text-rose-700 dark:text-rose-300 tabular-nums" x-text="Number(row.high_prospect ?? 0).toLocaleString('pt-BR') + ' {{ __('alta') }}'"></span>
                                    </button>
                                </li>
                            </template>
                        </ol>
                    </section>

                    <section class="serv-panel p-4" aria-labelledby="horizonte-top">
                        <h3 id="horizonte-top" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Top prospectos') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Clique para centrar no mapa e abrir a ficha.') }}</p>
                        <ul x-show="topProspects.length > 0" class="mt-3 space-y-2 text-sm">
                            <template x-for="p in topProspects.slice(0, 10)" :key="p.ibge">
                                <li>
                                    <button type="button" class="w-full rounded-lg border border-slate-200/80 dark:border-slate-700/80 px-3 py-2 text-left hover:border-teal-300 dark:hover:border-teal-700" @click="flyToMarker(p)">
                                        <p class="font-medium text-slate-900 dark:text-slate-100">
                                            <span x-text="p.name"></span>
                                            <span class="text-slate-500 font-normal" x-text="'(' + p.uf + ')'"></span>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5" x-text="(p.tier_label || '') + ' · ' + Number(p.success_score ?? 0) + '/100'"></p>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </section>

                    <section class="serv-panel p-4" aria-labelledby="horizonte-sge">
                        <h3 id="horizonte-sge" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Sistemas de gestão (SGE)') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('i-Educar no catálogo ServLITCYS + registo externo opcional.') }}</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Identificados') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(sgeSummary.with_sge ?? 0).toLocaleString('pt-BR')"></dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Consultoria i-Educar') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(sgeSummary.consultoria_active ?? 0).toLocaleString('pt-BR')"></dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Registo externo') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(sgeSummary.registry ?? 0).toLocaleString('pt-BR')"></dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Não identificados') }}</dt><dd class="font-medium tabular-nums text-amber-800 dark:text-amber-300" x-text="pageLoading ? '…' : Number(sgeSummary.not_found ?? 0).toLocaleString('pt-BR')"></dd></div>
                        </dl>
                        <p x-show="!pageLoading && !(sgeSummary.registry_configured ?? false)" x-cloak class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Registo SGE externo vazio — use o segmento «Sem SGE (concorrência)» ou clique num município N/I no mapa / lista.') }}
                        </p>
                        @if ($canManageSge)
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="serv-btn-secondary text-xs"
                                    :disabled="pageLoading"
                                    @click="resetFilters(); onlyMissingSge = true; filterTier = 'prospects';"
                                >{{ __('Ver sem SGE') }}</button>
                            </div>
                            <p class="mt-2 text-[11px] text-indigo-700 dark:text-indigo-300 leading-relaxed">
                                {{ __('Administrador: registe GDAE, Proesc, SIGE próprio, etc. em prospectos fora do catálogo — só inteligência territorial, sem abrir Consultoria.') }}
                            </p>
                        @endif
                    </section>

                    <section class="serv-panel p-4" aria-labelledby="horizonte-coverage">
                        <h3 id="horizonte-coverage" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Cobertura de dados') }}</h3>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">FUNDEB</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_fundeb ?? 0).toLocaleString('pt-BR')"></dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">Censo</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_censo ?? 0).toLocaleString('pt-BR')"></dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">SAEB</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_saeb ?? 0).toLocaleString('pt-BR')"></dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('Triad completa') }}</dt><dd class="font-medium tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_full_triad ?? 0).toLocaleString('pt-BR')"></dd></div>
                        </dl>
                    </section>

                    <section class="serv-panel p-4" aria-labelledby="horizonte-methodology">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 text-left"
                            @click="methodologyPanelOpen = !methodologyPanelOpen"
                            :aria-expanded="methodologyPanelOpen"
                        >
                            <h3 id="horizonte-methodology" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Metodologia e fórmulas') }}</h3>
                            <span class="text-slate-400 text-xs shrink-0" x-text="methodologyPanelOpen ? '▲' : '▼'"></span>
                        </button>
                        <div x-show="methodologyPanelOpen" x-transition.opacity.duration.200ms class="serv-horizonte-methodology mt-3">
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $methodology['disclaimer'] ?? '' }}</p>
                            <div class="rounded-lg border border-teal-200/80 bg-teal-50/50 dark:border-teal-900/50 dark:bg-teal-950/20 px-3 py-2 space-y-2">
                                <div>
                                    <p class="font-semibold text-teal-900 dark:text-teal-100">{{ $methodology['success_title'] ?? __('Propensão') }}</p>
                                    <p class="mt-0.5">{{ $methodology['success_formula'] ?? '' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-teal-900 dark:text-teal-100">{{ $methodology['benefit_title'] ?? __('Benefício') }}</p>
                                    <p class="mt-0.5">{{ $methodology['benefit_formula'] ?? '' }}</p>
                                </div>
                                <p class="text-[11px] text-teal-800/80 dark:text-teal-200/80">{{ $methodology['tier_rules'] ?? '' }}</p>
                            </div>
                            <p class="font-semibold text-serv-navy dark:text-slate-200 mt-2">{{ __('Dimensões (peso)') }}</p>
                            @foreach ($methodology['dimensions'] ?? [] as $dim)
                                <div class="serv-horizonte-methodology__dim">
                                    <p class="font-medium text-serv-navy dark:text-slate-200">
                                        {{ $dim['label'] }}
                                        <span class="text-teal-700 dark:text-teal-300 tabular-nums">{{ $dim['weight'] }}%</span>
                                    </p>
                                    <p class="mt-0.5">{{ $dim['formula'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </aside>
            </div>
        </div>

        <div
            x-show="sgeFormOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-slate-900/50"
            role="dialog"
            aria-modal="true"
            aria-labelledby="horizonte-sge-form-title"
            @keydown.escape.window="closeSgeForm()"
            @click.self="closeSgeForm()"
        >
            <div class="w-full max-w-lg rounded-xl bg-white dark:bg-slate-900 shadow-xl border border-slate-200 dark:border-slate-700 p-5 space-y-4" @click.stop>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="horizonte-sge-form-title" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('SGE concorrente / próprio') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 max-w-md">
                            {{ __('Registo de inteligência Horizonte — acompanha sistemas rivais ou municipais. Não cria cidade nem activa Consultoria i-Educar.') }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-show="sgeForm.ibge">
                            <span x-text="sgeForm.name + ' — ' + sgeForm.uf"></span>
                            · IBGE <span x-text="sgeForm.ibge"></span>
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeSgeForm()" aria-label="{{ __('Fechar') }}">&times;</button>
                </div>

                <form class="space-y-3" @submit.prevent="saveSgeEntry()">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Sistema (SGE)') }} <span class="text-red-600">*</span></label>
                        <input type="text" x-model="sgeForm.system" required maxlength="120" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600" placeholder="Ex.: GDAE, Proesc, SIGE municipal…" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Fornecedor / secretaria') }}</label>
                        <input type="text" x-model="sgeForm.vendor" maxlength="120" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('URL do portal') }}</label>
                        <input type="url" x-model="sgeForm.app_url" maxlength="500" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600" placeholder="https://…" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Observações') }}</label>
                        <textarea x-model="sgeForm.notes" rows="3" maxlength="2000" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600" placeholder="{{ __('Ex.: sistema próprio da secretaria; concorrente regional; observações de campo…') }}"></textarea>
                    </div>
                    <p x-show="sgeFormError" x-text="sgeFormError" class="text-xs text-red-600 dark:text-red-400"></p>
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <button
                            type="button"
                            x-show="sgeForm.has_entry"
                            class="text-xs text-red-700 dark:text-red-400 hover:underline"
                            @click="deleteSgeEntry()"
                        >{{ __('Remover registo') }}</button>
                        <div class="flex gap-2 ms-auto">
                            <button type="button" class="serv-btn-secondary text-xs" @click="closeSgeForm()">{{ __('Cancelar') }}</button>
                            <button type="submit" class="serv-btn-primary text-xs" :disabled="sgeFormSaving">
                                <span x-show="!sgeFormSaving">{{ __('Gravar') }}</span>
                                <span x-show="sgeFormSaving">{{ __('A gravar…') }}</span>
                            </button>
                        </div>
                    </div>
                </form>
                <p class="text-[10px] text-slate-500">{{ __('Gravado em storage/app/horizonte/sge_registry.json — actualiza o mapa de imediato.') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
