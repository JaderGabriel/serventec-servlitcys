{{-- Horizonte — interface optimizada para dispositivos de mão (partilha o mesmo mapa Leaflet). --}}
<div
    x-show="isMobileLayout"
    x-cloak
    class="serv-horizonte-mobile"
    :data-mobile-tab="mobileTab"
    aria-label="{{ __('Horizonte — versão mão') }}"
>
    <header class="serv-horizonte-mobile__header">
        <div class="serv-horizonte-mobile__header-row">
            <div class="min-w-0 flex-1">
                <p class="serv-horizonte-mobile__eyebrow">
                    {{ __('Horizonte') }}
                    <span class="serv-horizonte-mobile__mode-badge" x-text="layoutModeBadge()"></span>
                </p>
                <p class="serv-horizonte-mobile__scope" x-show="isOverviewMode">{{ __('Brasil · por UF') }}</p>
                <p class="serv-horizonte-mobile__scope" x-show="isMesoOverviewMode" x-cloak x-text="'{{ __('Mesorregiões') }} · ' + ufLabel(scopeUf)"></p>
                <p class="serv-horizonte-mobile__scope" x-show="isRegionalMode" x-cloak>
                    <span x-show="scopeMeso" x-text="mesoScopeLabel()"></span>
                    <span x-show="!scopeMeso" x-text="ufLabel(scopeUf)"></span>
                </p>
            </div>
            <button
                type="button"
                class="serv-horizonte-mobile__layout-btn"
                @click="toggleLayoutVariant()"
                :title="layoutToggleHint()"
                :aria-label="layoutToggleHint()"
            >
                <span class="serv-horizonte-mobile__layout-btn-icon" aria-hidden="true">
                    <svg x-show="isMobileLayout" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105a3.501 3.501 0 004.247 2.75.75.75 0 01-.584.985.75.75 0 01-.832-.667A2.001 2.001 0 0010 15.25h-3a2 2 0 00-1.832 1.117.75.75 0 01-.832.667.75.75 0 01-.584-.985A3.501 3.501 0 007.355 15H4.25A2.25 2.25 0 012 12.75v-8.5z" clip-rule="evenodd" /></svg>
                    <svg x-show="!isMobileLayout" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M7 1a2 2 0 00-2 2v14a2 2 0 002 2h6a2 2 0 002-2V3a2 2 0 00-2-2H7zm1 14.5a.75.75 0 100 1.5h4a.75.75 0 100-1.5H8z" /></svg>
                </span>
                <span x-text="layoutToggleLabel()"></span>
            </button>
        </div>

        <label class="serv-horizonte-mobile__uf-select">
            <span class="sr-only">{{ __('Recorte UF') }}</span>
            <select
                :value="scopeUf"
                @change="onScopeUfPick($event)"
                :disabled="pageLoading || regionalLoading"
            >
                <option value="">{{ __('Brasil (por UF)') }}</option>
                @foreach ($ufNames as $code => $name)
                    <option value="{{ $code }}">{{ $code }} — {{ $name }}</option>
                @endforeach
            </select>
        </label>

        <div class="serv-horizonte-mobile__kpis" role="status">
            <div class="serv-horizonte-mobile__kpi">
                <span class="serv-horizonte-mobile__kpi-label">{{ __('Pressão') }}</span>
                <span class="serv-horizonte-mobile__kpi-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.high_pressure)"></span>
            </div>
            <div class="serv-horizonte-mobile__kpi">
                <span class="serv-horizonte-mobile__kpi-label">{{ __('Prospectos') }}</span>
                <span class="serv-horizonte-mobile__kpi-value" :class="kpiLoading ? 'is-loading' : ''" x-text="formatKpiCount(summary.prospect_count)"></span>
            </div>
            <div class="serv-horizonte-mobile__kpi" x-show="isRegionalMode" x-cloak>
                <span class="serv-horizonte-mobile__kpi-label">{{ __('Recorte') }}</span>
                <span class="serv-horizonte-mobile__kpi-value tabular-nums" x-text="filteredCount.toLocaleString('pt-BR')"></span>
            </div>
        </div>

        <div class="serv-horizonte-mobile__lenses" x-show="isRegionalMode" x-cloak role="group" aria-label="{{ __('Lente rápida') }}">
            <template x-for="opt in decisionLensOptions.filter(o => ['high_pressure','prospects','prospect_high','all'].includes(o.key))" :key="'m-lens-' + opt.key">
                <button
                    type="button"
                    class="serv-horizonte-mobile__lens"
                    :class="decisionLensKey === opt.key ? 'is-active' : ''"
                    :disabled="pageLoading || opt.disabled"
                    @click="applyDecisionLens(opt.key)"
                    x-text="opt.short"
                ></button>
            </template>
        </div>

        <div class="serv-horizonte-mobile__map-views" x-show="isRegionalMode && mobileTab === 'map'" x-cloak role="group" aria-label="{{ __('Vista do mapa') }}">
            <button type="button" class="serv-horizonte-mobile__map-view" :class="mapView === 'markers' ? 'is-active' : ''" @click="setMapView('markers')">{{ __('Pontos') }}</button>
            <button type="button" class="serv-horizonte-mobile__map-view" :class="mapView === 'heat' ? 'is-active' : ''" @click="setMapView('heat')" :disabled="regionalDisplayPolicy?.heavy_regional">{{ __('Calor') }}</button>
            <button type="button" class="serv-horizonte-mobile__map-view" :class="mapView === 'boundaries' ? 'is-active' : ''" @click="setMapView('boundaries')">{{ __('Contornos') }}</button>
        </div>
    </header>

    {{-- Lista de prospecção (tab Lista) --}}
    <section
        x-show="mobileTab === 'list'"
        x-cloak
        class="serv-horizonte-mobile__panel serv-horizonte-mobile__panel--list"
        aria-label="{{ __('Lista de prospecção') }}"
    >
        <div class="serv-horizonte-mobile__panel-head">
            <h3 class="serv-horizonte-mobile__panel-title">{{ __('Lista de prospecção') }}</h3>
            <label class="serv-horizonte-mobile__sort">
                <span class="text-slate-500">{{ __('Ordenar') }}</span>
                <select x-model="prospectSort" :disabled="pageLoading" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                    <option value="success_score">{{ __('Propensão') }}</option>
                    <option value="benefit_score">{{ __('Benefício') }}</option>
                    <option value="matriculas_censo">{{ __('Matrículas') }}</option>
                    <option value="financial_pressure">{{ __('Pressão FUNDEB') }}</option>
                </select>
            </label>
        </div>

        <p x-show="!pageLoading && isOverviewMode" x-cloak class="text-sm text-slate-500 px-1">{{ __('Selecione uma UF para ver municípios.') }}</p>
        <p x-show="!pageLoading && isRegionalMode && sortedProspects.length === 0" x-cloak class="text-sm text-slate-500 px-1">{{ __('Nenhum município no recorte.') }}</p>

        <ul x-show="sortedProspects.length > 0 && isRegionalMode" class="serv-horizonte-mobile__prospect-list">
            <template x-for="(p, idx) in sortedProspects.slice(0, 50)" :key="'m-prospect-' + p.ibge">
                <li>
                    <button type="button" class="serv-horizonte-mobile__prospect-card" @click="openMunicipalityFromMobile(p)">
                        <span class="serv-horizonte-mobile__prospect-rank" x-text="idx + 1"></span>
                        <span class="min-w-0 flex-1 text-left">
                            <span class="block font-semibold text-slate-900 dark:text-slate-100 truncate" x-text="p.name"></span>
                            <span class="block text-xs text-slate-500" x-text="p.uf + ' · ' + formatScoreDisplay(p.success_score) + '/100 · ' + (p.tier_label || '')"></span>
                        </span>
                        <span class="text-[11px] tabular-nums text-slate-500 shrink-0" x-text="p.matriculas_censo != null ? formatCount(p.matriculas_censo) : '—'"></span>
                    </button>
                </li>
            </template>
        </ul>

        <div x-show="topProspects.length > 0 && isOverviewMode" class="mt-3">
            <h4 class="text-xs font-bold uppercase tracking-wide text-slate-500 px-1">{{ __('Abordar primeiro') }}</h4>
            <ul class="serv-horizonte-mobile__prospect-list mt-2">
                <template x-for="(p, idx) in topProspects.slice(0, 8)" :key="'m-top-' + p.ibge">
                    <li>
                        <button type="button" class="serv-horizonte-mobile__prospect-card" @click="selectPriorityUf(p.uf); setMobileTab('map')">
                            <span class="serv-horizonte-mobile__prospect-rank" x-text="idx + 1"></span>
                            <span class="min-w-0 flex-1 text-left">
                                <span class="block font-semibold truncate" x-text="p.name + ' (' + p.uf + ')'"></span>
                                <span class="block text-xs text-slate-500" x-text="formatScoreDisplay(p.success_score) + '/100'"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>
    </section>

    {{-- Filtros (bottom sheet) --}}
    <aside
        x-show="mobileTab === 'filters' && filterDockOpen"
        x-cloak
        class="serv-horizonte-mobile__filters-sheet"
        aria-label="{{ __('Filtros do mapa') }}"
    >
        <div class="serv-horizonte-mobile__filters-sheet-head">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Filtros') }}</p>
            <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="toggleFiltersPanel()" aria-label="{{ __('Fechar filtros') }}">×</button>
        </div>
        @include('horizonte.partials.filters-panel', [
            'viewPresets' => $viewPresets,
            'tierPresets' => $tierPresets,
            'canManageSge' => $canManageSge,
            'compact' => true,
        ])
    </aside>

    {{-- Mais: cobertura, tecnologias, preferências --}}
    <section
        x-show="mobileTab === 'more'"
        x-cloak
        class="serv-horizonte-mobile__panel serv-horizonte-mobile__panel--more"
        aria-label="{{ __('Mais opções') }}"
    >
        <h3 class="serv-horizonte-mobile__panel-title">{{ __('Recursos activos') }}</h3>
        <ul class="serv-horizonte-mobile__tech-list">
            <li>{{ __('Mapa Leaflet — coroplético nacional, calor, pontos e contornos IBGE') }}</li>
            <li>{{ __('Malha municipal GeoJSON + modal com Educacenso (Chart.js)') }}</li>
            <li>{{ __('Scores FUNDEB / propensão / benefício e filtros comerciais') }}</li>
            <li>{{ __('Cache regional resiliente e série histórica de matrículas') }}</li>
            <li>{{ __('Registo SGE e resumo estadual FUNDEB') }}</li>
        </ul>

        <h4 class="serv-horizonte-mobile__panel-subtitle">{{ __('UFs prioritárias') }}</h4>
        <ul x-show="ufRankings.length > 0" class="serv-horizonte-mobile__uf-list">
            <template x-for="row in ufRankings.slice(0, 6)" :key="'m-uf-' + row.uf">
                <li>
                    <button type="button" class="serv-horizonte-mobile__uf-btn" @click="selectPriorityUf(row.uf); setMobileTab('map')">
                        <span x-text="ufLabel(row.uf)"></span>
                        <span class="tabular-nums text-rose-700 dark:text-rose-300" x-text="Number(row.high_pressure ?? row.high_prospect ?? 0).toLocaleString('pt-BR')"></span>
                    </button>
                </li>
            </template>
        </ul>

        <h4 class="serv-horizonte-mobile__panel-subtitle">{{ __('Cobertura') }}</h4>
        <dl class="serv-horizonte-mobile__coverage">
            <div><dt>FUNDEB</dt><dd x-text="formatKpiCount(coverage.with_fundeb)">…</dd></div>
            <div><dt>SAEB</dt><dd x-text="formatKpiCount(coverage.with_saeb)">…</dd></div>
            <div><dt>{{ __('Triad completa') }}</dt><dd x-text="formatKpiCount(coverage.with_full_triad)">…</dd></div>
        </dl>

        <div class="serv-horizonte-mobile__more-actions">
            <button type="button" class="serv-btn-secondary text-xs w-full" @click="toggleLayoutVariant()" x-text="layoutToggleLabel()"></button>
            <button type="button" class="serv-link text-xs w-full text-center" @click="resetLayoutPreference()">{{ __('Voltar à detecção automática') }}</button>
            <button type="button" class="serv-btn-secondary text-xs w-full" @click="startTour()">{{ __('Tour guiado') }}</button>
            <a href="{{ $docUrl }}" class="serv-link text-xs block text-center">{{ __('Documentação Horizonte') }}</a>
        </div>
    </section>

    <nav class="serv-horizonte-mobile__tabbar" role="tablist" aria-label="{{ __('Navegação Horizonte') }}">
        <button
            type="button"
            role="tab"
            class="serv-horizonte-mobile__tab"
            :class="mobileTab === 'map' ? 'is-active' : ''"
            :aria-selected="mobileTab === 'map'"
            @click="setMobileTab('map')"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" /></svg>
            <span>{{ __('Mapa') }}</span>
        </button>
        <button
            type="button"
            role="tab"
            class="serv-horizonte-mobile__tab"
            :class="mobileTab === 'list' ? 'is-active' : ''"
            :aria-selected="mobileTab === 'list'"
            @click="setMobileTab('list')"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75v-.008Zm0 5.25h.007v.008H3.75v-.008Z" /></svg>
            <span>{{ __('Lista') }}</span>
        </button>
        <button
            type="button"
            role="tab"
            class="serv-horizonte-mobile__tab"
            :class="mobileTab === 'filters' ? 'is-active' : ''"
            :aria-selected="mobileTab === 'filters'"
            @click="setMobileTab('filters')"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
            <span>{{ __('Filtros') }}</span>
            <span x-show="activeFilterCount > 0" x-cloak class="serv-horizonte-mobile__tab-badge" x-text="activeFilterCount"></span>
        </button>
        <button
            type="button"
            role="tab"
            class="serv-horizonte-mobile__tab"
            :class="mobileTab === 'more' ? 'is-active' : ''"
            :aria-selected="mobileTab === 'more'"
            @click="setMobileTab('more')"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" /></svg>
            <span>{{ __('Mais') }}</span>
        </button>
    </nav>
</div>
