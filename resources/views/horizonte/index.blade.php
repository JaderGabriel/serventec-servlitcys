@php
    $refYear = (int) ($refYear ?? config('horizonte.reference_year', (int) date('Y') - 1));
    $legend = is_array($legend ?? null) ? $legend : [];
    $colors = is_array($colors ?? null) ? $colors : [];
    $mapDataUrl = $mapDataUrl ?? route('dashboard.horizonte.map-data');
    $docUrl = route(auth()->user()?->isAdmin() ? 'admin.documentation.show' : 'documentation.show', ['doc' => 'docs/HORIZONTE.md']);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Horizonte') }}</p>
                <h2 class="font-display font-semibold text-xl sm:text-2xl text-serv-navy dark:text-slate-100 leading-tight">
                    {{ __('Mapa de oportunidade municipal') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-3xl">
                    {{ __('Municípios com e sem Consultoria, déficits educacionais estimados a partir de dados oficiais (FUNDEB, Censo, SAEB) e propensão indicativa de benefício com i-Educar + SERVLITCYS.') }}
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
        ]))"
        x-init="init()"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div x-show="pageError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200" role="alert">
                <p class="font-medium">{{ __('Não foi possível carregar o mapa Horizonte.') }}</p>
                <p class="mt-1" x-text="pageError"></p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="serv-home-kpi serv-home-kpi--teal">
                    <p class="serv-home-kpi__label">{{ __('No mapa') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.total ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Exercício ref. :ano', ['ano' => $refYear]) }}</p>
                </div>
                <div class="serv-home-kpi">
                    <p class="serv-home-kpi__label">{{ __('Sem Consultoria') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.without_consultoria ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Prospectos ou catálogo pendente') }}</p>
                </div>
                <div class="serv-home-kpi serv-home-kpi--teal">
                    <p class="serv-home-kpi__label">{{ __('Consultoria activa') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.consultoria_active ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Base i-Educar configurada') }}</p>
                </div>
                <div class="serv-home-kpi serv-home-kpi--amber">
                    <p class="serv-home-kpi__label">{{ __('Alta propensão') }}</p>
                    <p class="serv-home-kpi__value tabular-nums" x-text="pageLoading ? '…' : Number(summary.high_prospect ?? 0).toLocaleString('pt-BR')">…</p>
                    <p class="serv-home-kpi__hint">{{ __('Prioridade comercial / expansão') }}</p>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <section class="serv-panel overflow-hidden min-w-0" aria-labelledby="horizonte-map-heading">
                    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90 space-y-3">
                        <h3 id="horizonte-map-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100">
                            {{ __('Mapa') }}
                        </h3>
                        <div
                            @keydown.escape.window="closeTooltip()"
                            class="space-y-3"
                            :class="{ 'pointer-events-none opacity-60': pageLoading }"
                            :aria-busy="pageLoading ? 'true' : 'false'"
                        >
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="relative flex-1">
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
                            </div>
                            <div class="flex flex-wrap gap-1.5 text-xs">
                                @foreach ([
                                    'all' => __('Todos'),
                                    'prospects' => __('Prospectos'),
                                    'prospect_high' => __('Alta propensão'),
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
                            </div>
                            <div class="serv-map-legend flex flex-wrap gap-x-4 gap-y-2 text-xs">
                                <template x-for="item in legend" :key="item.key">
                                    <span class="serv-map-legend__item" :title="item.description || ''">
                                        <span class="serv-map-legend-swatch serv-map-legend-swatch--connection" :style="'background-color:' + (item.color || '#64748b')" aria-hidden="true"></span>
                                        <span class="text-slate-600 dark:text-slate-300" x-text="item.label"></span>
                                    </span>
                                </template>
                            </div>
                            <div class="relative">
                                <div x-ref="map" class="serv-brazil-map w-full" role="application" aria-label="{{ __('Mapa Horizonte — oportunidade municipal') }}" style="height: min(70vh, 520px);"></div>
                                <div
                                    x-show="active"
                                    x-cloak
                                    class="absolute z-20 max-w-xs rounded-lg border border-gray-200 dark:border-gray-600 bg-white/95 dark:bg-gray-900/95 p-3 shadow-lg text-sm pointer-events-auto"
                                    :style="tooltipStyle"
                                    @click.outside="closeTooltip()"
                                >
                                    <div x-html="tooltipHtml(active)"></div>
                                    <button type="button" class="mt-2 text-xs text-gray-500 hover:underline" @click="closeTooltip()">{{ __('Fechar') }}</button>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Scores indicativos — não substituem o Diagnóstico i-Educar. Importe fontes em Dados públicos para enriquecer municípios fora do catálogo.') }}
                            </p>
                        </div>
                    </div>
                </section>

                <aside class="space-y-4 xl:sticky xl:top-4 xl:self-start">
                    <section class="serv-panel p-4" aria-labelledby="horizonte-regions">
                        <h3 id="horizonte-regions" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Regiões mais afectadas') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('UFs com maior benefício médio estimado (déficit × escala).') }}</p>
                        <p x-show="!pageLoading && ufRankings.length === 0" x-cloak class="mt-3 text-sm text-slate-500">{{ __('Sem dados regionais — importe FUNDEB/SAEB/Censo.') }}</p>
                        <ol x-show="ufRankings.length > 0" class="mt-3 space-y-2 text-sm">
                            <template x-for="row in ufRankings" :key="row.uf">
                                <li class="flex items-center justify-between gap-2 rounded-lg bg-slate-50/80 dark:bg-slate-900/50 px-3 py-2">
                                    <span class="font-medium" x-text="row.uf"></span>
                                    <span class="text-xs text-slate-500 tabular-nums" x-text="'{{ __('benefício') }} ' + Number(row.avg_benefit ?? 0).toLocaleString('pt-BR')"></span>
                                    <span class="text-xs text-rose-700 dark:text-rose-300 tabular-nums" x-text="Number(row.high_prospect ?? 0).toLocaleString('pt-BR') + ' {{ __('alta') }}'"></span>
                                </li>
                            </template>
                        </ol>
                    </section>

                    <section class="serv-panel p-4" aria-labelledby="horizonte-top">
                        <h3 id="horizonte-top" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Mais propensos a sucesso') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Prospectos com melhor score composto (financeiro + pedagógico + escala).') }}</p>
                        <p x-show="!pageLoading && topProspects.length === 0" x-cloak class="mt-3 text-sm text-slate-500">{{ __('Nenhum prospecto classificado.') }}</p>
                        <ul x-show="topProspects.length > 0" class="mt-3 space-y-2 text-sm">
                            <template x-for="p in topProspects" :key="p.ibge">
                                <li class="rounded-lg border border-slate-200/80 dark:border-slate-700/80 px-3 py-2">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        <span x-text="p.name"></span>
                                        <span class="text-slate-500 font-normal" x-text="'(' + p.uf + ')'"></span>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-0.5" x-text="(p.tier_label || '') + ' · {{ __('propensão') }} ' + Number(p.success_score ?? 0).toLocaleString('pt-BR') + ' · {{ __('benefício') }} ' + Number(p.benefit_score ?? 0).toLocaleString('pt-BR')"></p>
                                </li>
                            </template>
                        </ul>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
