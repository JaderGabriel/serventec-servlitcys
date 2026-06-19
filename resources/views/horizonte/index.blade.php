@php
    $refYear = (int) ($refYear ?? config('horizonte.reference_year', (int) date('Y') - 1));
    $legend = is_array($legend ?? null) ? $legend : [];
    $colors = is_array($colors ?? null) ? $colors : [];
    $heatLegend = \App\Support\Horizonte\HorizonteMapPresenter::heatLegendItems();
    $mapDataUrl = $mapDataUrl ?? route('dashboard.horizonte.map-data');
    $docUrl = route(auth()->user()?->isAdmin() ? 'admin.documentation.show' : 'documentation.show', ['doc' => 'docs/HORIZONTE.md']);
    $canRefreshData = (bool) ($canRefreshData ?? auth()->user()?->canImportOrConfigure());
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
                    {{ __('Mapa de calor e prospecção para gestores: municípios com dados públicos (FUNDEB, Censo, SAEB), scores de oportunidade e segmentos prioritários para Consultoria i-Educar + SERVLITCYS.') }}
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
            'canRefreshData' => $canRefreshData,
        ]))"
        x-init="init()"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div x-show="pageError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200" role="alert">
                <p class="font-medium">{{ __('Não foi possível carregar o mapa Horizonte.') }}</p>
                <p class="mt-1" x-text="pageError"></p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="serv-home-kpi serv-home-kpi--teal">
                    <p class="serv-home-kpi__label">{{ __('Com dados públicos') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(coverage.with_public_data ?? summary.total ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('FUNDEB, Censo ou SAEB') }}</p>
                </div>
                <div class="serv-home-kpi">
                    <p class="serv-home-kpi__label">{{ __('Prospectos') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.prospect_count ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Sem Consultoria activa') }}</p>
                </div>
                <div class="serv-home-kpi serv-home-kpi--amber">
                    <p class="serv-home-kpi__label">{{ __('Alta propensão') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.high_prospect ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Prioridade comercial') }}</p>
                </div>
                <div class="serv-home-kpi serv-home-kpi--teal">
                    <p class="serv-home-kpi__label">{{ __('Consultoria activa') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.consultoria_active ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Base i-Educar pronta') }}</p>
                </div>
                <div class="serv-home-kpi">
                    <p class="serv-home-kpi__label">{{ __('Matrículas prospecto') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(coverage.prospect_matriculas_censo ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Censo ref. :ano', ['ano' => $refYear]) }}</p>
                </div>
            </div>

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
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <section class="serv-panel overflow-hidden min-w-0" aria-labelledby="horizonte-map-heading">
                    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <h3 id="horizonte-map-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100">
                                {{ __('Mapa') }}
                            </h3>
                            <div class="flex flex-wrap gap-1.5 text-xs">
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-1 font-medium ring-1 ring-slate-200/80 dark:ring-slate-600 transition"
                                    :class="mapView === 'heat' ? 'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-200' : 'bg-white/80 text-slate-600 dark:bg-slate-900/60 dark:text-slate-300'"
                                    @click="setMapView('heat')"
                                    :disabled="pageLoading"
                                >{{ __('Calor') }}</button>
                                <button
                                    type="button"
                                    class="rounded-full px-3 py-1 font-medium ring-1 ring-slate-200/80 dark:ring-slate-600 transition"
                                    :class="mapView === 'markers' ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50 dark:text-indigo-200' : 'bg-white/80 text-slate-600 dark:bg-slate-900/60 dark:text-slate-300'"
                                    @click="setMapView('markers')"
                                    :disabled="pageLoading"
                                >{{ __('Marcadores') }}</button>
                            </div>
                        </div>

                        <div
                            @keydown.escape.window="closeTooltip()"
                            class="space-y-3"
                            :class="{ 'pointer-events-none opacity-60': pageLoading }"
                            :aria-busy="(pageLoading || mapRendering) ? 'true' : 'false'"
                        >
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="relative sm:col-span-2">
                                    <label for="horizonte-search" class="sr-only">{{ __('Buscar município') }}</label>
                                    <input
                                        id="horizonte-search"
                                        type="search"
                                        x-model="searchQuery"
                                        :disabled="pageLoading"
                                        placeholder="{{ __('Nome, UF ou IBGE…') }}"
                                        autocomplete="off"
                                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60"
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
                                <select
                                    x-model="filterUf"
                                    :disabled="pageLoading"
                                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60"
                                    aria-label="{{ __('Filtrar por UF') }}"
                                >
                                    <option value="">{{ __('Todas as UFs') }}</option>
                                    <template x-for="uf in ufList" :key="uf">
                                        <option :value="uf" x-text="uf"></option>
                                    </template>
                                </select>
                                <select
                                    x-model="prospectSort"
                                    :disabled="pageLoading"
                                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60"
                                    aria-label="{{ __('Ordenar lista') }}"
                                >
                                    <option value="success_score">{{ __('Propensão') }}</option>
                                    <option value="benefit_score">{{ __('Benefício') }}</option>
                                    <option value="matriculas_censo">{{ __('Matrículas') }}</option>
                                    <option value="financial_pressure">{{ __('Pressão FUNDEB') }}</option>
                                </select>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                                <label class="flex flex-col gap-1">
                                    <span class="text-slate-500">{{ __('Propensão mín.') }}</span>
                                    <input type="range" min="0" max="100" step="5" x-model.number="minSuccessScore" class="w-full accent-teal-600" :disabled="pageLoading" />
                                    <span class="tabular-nums font-medium" x-text="minSuccessScore + '/100'"></span>
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span class="text-slate-500">{{ __('Benefício mín.') }}</span>
                                    <input type="range" min="0" max="100" step="5" x-model.number="minBenefitScore" class="w-full accent-teal-600" :disabled="pageLoading" />
                                    <span class="tabular-nums font-medium" x-text="minBenefitScore + '/100'"></span>
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span class="text-slate-500">{{ __('Matrículas mín.') }}</span>
                                    <select x-model.number="minMatriculas" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm" :disabled="pageLoading">
                                        <option value="0">{{ __('Qualquer') }}</option>
                                        <option value="5000">5 000+</option>
                                        <option value="15000">15 000+</option>
                                        <option value="30000">30 000+</option>
                                    </select>
                                </label>
                                <div class="flex flex-wrap items-end gap-2">
                                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" x-model="requireFundeb" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>FUNDEB</span></label>
                                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" x-model="requireCenso" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>Censo</span></label>
                                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" x-model="requireSaeb" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" /><span>SAEB</span></label>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-1.5 text-xs">
                                @foreach ([
                                    'prospects' => __('Prospectos'),
                                    'prospect_high' => __('Alta propensão'),
                                    'all' => __('Todos'),
                                    'consultoria_active' => __('Consultoria'),
                                    'catalog_pending' => __('Catálogo pendente'),
                                ] as $key => $label)
                                    <button
                                        type="button"
                                        :disabled="pageLoading"
                                        class="rounded-full px-2.5 py-1 font-medium ring-1 ring-slate-200/80 dark:ring-slate-600 transition disabled:opacity-60"
                                        :class="filterTier === @js($key) ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50 dark:text-indigo-200' : 'bg-white/80 text-slate-600 dark:bg-slate-900/60 dark:text-slate-300'"
                                        @click="setFilterTier(@js($key))"
                                    >{{ $label }}</button>
                                @endforeach
                                <label class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 ring-1 ring-slate-200/80 dark:ring-slate-600">
                                    <input type="checkbox" x-model="hideConsultoria" class="rounded border-gray-300 text-teal-600" :disabled="pageLoading" />
                                    <span>{{ __('Ocultar consultoria') }}</span>
                                </label>
                            </div>

                            <div class="serv-map-legend flex flex-wrap gap-x-4 gap-y-2 text-xs">
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
                                <span class="text-slate-500 dark:text-slate-400 tabular-nums" x-show="!pageLoading" x-text="filteredMarkers.length.toLocaleString('pt-BR') + ' {{ __('no recorte') }}'"></span>
                            </div>

                            <div class="relative">
                                <div
                                    x-show="pageLoading || mapRendering"
                                    x-cloak
                                    class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 rounded-lg bg-white/85 dark:bg-slate-900/85 backdrop-blur-sm"
                                    role="status"
                                    aria-live="polite"
                                >
                                    <div class="h-8 w-8 animate-spin rounded-full border-2 border-teal-600 border-t-transparent" aria-hidden="true"></div>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200" x-text="pageLoading ? loadingMessage || '{{ __('A carregar dados públicos…') }}' : (loadingMessage || '{{ __('A desenhar mapa…') }}')"></p>
                                    <div x-show="mapRendering && renderProgress > 0" class="w-48 max-w-[70%] h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full bg-teal-600 transition-all duration-150" :style="'width:' + renderProgress + '%'"></div>
                                    </div>
                                </div>

                                <div
                                    x-show="!pageLoading && !mapRendering && totalMarkers === 0"
                                    x-cloak
                                    class="absolute inset-0 z-[5] flex flex-col items-center justify-center gap-4 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/95 dark:bg-slate-900/90 px-6 py-8 text-center"
                                    role="status"
                                >
                                    <p class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Mapa vazio') }}</p>
                                    <p class="text-sm text-slate-600 dark:text-slate-400 max-w-md" x-text="meta.message || '{{ __('Importe dados públicos nacionais ou cadastre municípios com IBGE no catálogo.') }}'"></p>
                                    <div class="w-full max-w-lg rounded-lg bg-slate-900 dark:bg-slate-950 px-4 py-3 text-left">
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ __('Abastecimento (servidor)') }}</p>
                                        <code class="mt-1 block text-xs text-emerald-300 break-all font-mono" x-text="meta.refresh_command || 'php artisan horizonte:fortnightly-feed'"></code>
                                        <p class="mt-2 text-[10px] text-slate-500">{{ __('Simular antes:') }} <code class="text-slate-400" x-text="meta.refresh_dry_run_command || 'php artisan horizonte:fortnightly-feed --dry-run'"></code></p>
                                    </div>
                                    @if ($canRefreshData)
                                        <a
                                            :href="meta.hub_url || @js(route('admin.public-data.index', ['hub' => 'horizonte']))"
                                            class="serv-btn-secondary text-sm"
                                        >{{ __('Abrir hub Dados públicos') }}</a>
                                    @endif
                                </div>

                                <div
                                    x-show="mapHiddenByFilters"
                                    x-cloak
                                    class="absolute inset-x-0 top-0 z-[5] mx-3 mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
                                    role="status"
                                >
                                    <p>
                                        <span class="font-medium">{{ __('Filtros ocultam todos os municípios.') }}</span>
                                        <span class="tabular-nums" x-text="' ' + totalMarkers.toLocaleString('pt-BR') + ' {{ __('carregados') }}.'"></span>
                                    </p>
                                    <button type="button" class="serv-btn-secondary text-xs shrink-0" @click="resetFilters()">{{ __('Mostrar todos') }}</button>
                                </div>

                                <div x-ref="map" class="serv-brazil-map w-full" role="application" aria-label="{{ __('Mapa Horizonte — oportunidade municipal') }}" style="height: min(70vh, 520px);"></div>
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
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Scores indicativos — não substituem o Diagnóstico i-Educar. Vista inicial: todos os municípios com coordenadas.') }}
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
                            {{ __('Rotina quinzenal (dias 1 e 15) ou execução manual no servidor:') }}
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
                                            <td class="py-2 ps-2 text-slate-600 dark:text-slate-300" x-text="p.sge?.system_label || (p.sge_found ? '—' : '{{ __('N/I') }}')"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <aside class="space-y-4 xl:sticky xl:top-4 xl:self-start">
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
                            {{ __('Registo SGE externo não configurado — coloque JSON em storage/app/horizonte/sge_registry.json ou defina HORIZONTE_SGE_REGISTRY_URL.') }}
                        </p>
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

                    <section class="serv-panel p-4" aria-labelledby="horizonte-regions">
                        <h3 id="horizonte-regions" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('UFs prioritárias') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Maior benefício médio estimado no recorte.') }}</p>
                        <p x-show="!pageLoading && ufRankings.length === 0" x-cloak class="mt-3 text-sm text-slate-500">{{ __('Sem dados regionais.') }}</p>
                        <ol x-show="ufRankings.length > 0" class="mt-3 space-y-2 text-sm">
                            <template x-for="row in ufRankings" :key="row.uf">
                                <li>
                                    <button type="button" class="w-full flex items-center justify-between gap-2 rounded-lg bg-slate-50/80 dark:bg-slate-900/50 px-3 py-2 text-left hover:bg-teal-50/80 dark:hover:bg-teal-950/30" @click="filterUf = row.uf">
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
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Melhor score composto nacional.') }}</p>
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
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
