@php
    $h = is_array($horizonte ?? null) ? $horizonte : [];
    $markers = is_array($h['markers'] ?? null) ? $h['markers'] : [];
    $summary = is_array($h['summary'] ?? null) ? $h['summary'] : [];
    $ufRankings = is_array($h['uf_rankings'] ?? null) ? $h['uf_rankings'] : [];
    $topProspects = is_array($h['top_prospects'] ?? null) ? $h['top_prospects'] : [];
    $legend = is_array($h['legend'] ?? null) ? $h['legend'] : [];
    $colors = is_array($h['colors'] ?? null) ? $h['colors'] : [];
    $refYear = (int) ($h['reference_year'] ?? config('horizonte.reference_year', (int) date('Y') - 1));
    $ufList = collect($markers)->pluck('uf')->filter()->unique()->sort()->values()->all();
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

    <div class="py-8 sm:py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="serv-home-kpi serv-home-kpi--teal">
                    <p class="serv-home-kpi__label">{{ __('No mapa') }}</p>
                    <p class="serv-home-kpi__value">{{ number_format((int) ($summary['total'] ?? 0)) }}</p>
                    <p class="serv-home-kpi__hint">{{ __('Exercício ref. :ano', ['ano' => $refYear]) }}</p>
                </div>
                <div class="serv-home-kpi">
                    <p class="serv-home-kpi__label">{{ __('Sem Consultoria') }}</p>
                    <p class="serv-home-kpi__value">{{ number_format((int) ($summary['without_consultoria'] ?? 0)) }}</p>
                    <p class="serv-home-kpi__hint">{{ __('Prospectos ou catálogo pendente') }}</p>
                </div>
                <div class="serv-home-kpi serv-home-kpi--teal">
                    <p class="serv-home-kpi__label">{{ __('Consultoria activa') }}</p>
                    <p class="serv-home-kpi__value">{{ number_format((int) ($summary['consultoria_active'] ?? 0)) }}</p>
                    <p class="serv-home-kpi__hint">{{ __('Base i-Educar configurada') }}</p>
                </div>
                <div class="serv-home-kpi serv-home-kpi--amber">
                    <p class="serv-home-kpi__label">{{ __('Alta propensão') }}</p>
                    <p class="serv-home-kpi__value">{{ number_format((int) ($summary['high_prospect'] ?? 0)) }}</p>
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
                            x-data="horizonteMap(@js($markers), @js($colors), @js(['ufList' => $ufList]))"
                            x-init="init()"
                            @keydown.escape.window="closeTooltip()"
                            class="space-y-3"
                        >
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="relative flex-1">
                                    <label for="horizonte-search" class="sr-only">{{ __('Buscar município') }}</label>
                                    <input
                                        id="horizonte-search"
                                        type="search"
                                        x-model="searchQuery"
                                        placeholder="{{ __('Nome, UF ou IBGE…') }}"
                                        autocomplete="off"
                                        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    aria-label="{{ __('Filtrar por UF') }}"
                                >
                                    <option value="">{{ __('Todas as UFs') }}</option>
                                    @foreach ($ufList as $uf)
                                        <option value="{{ $uf }}">{{ $uf }}</option>
                                    @endforeach
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
                                        class="rounded-full px-2.5 py-1 font-medium ring-1 ring-slate-200/80 dark:ring-slate-600 transition"
                                        :class="filterTier === @js($key) ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50 dark:text-indigo-200' : 'bg-white/80 text-slate-600 dark:bg-slate-900/60 dark:text-slate-300'"
                                        @click="setFilterTier(@js($key))"
                                    >{{ $label }}</button>
                                @endforeach
                            </div>
                            <div class="serv-map-legend flex flex-wrap gap-x-4 gap-y-2 text-xs">
                                @foreach ($legend as $item)
                                    <span class="serv-map-legend__item" title="{{ $item['description'] ?? '' }}">
                                        <span class="serv-map-legend-swatch serv-map-legend-swatch--connection" style="background-color: {{ $item['color'] ?? '#64748b' }}" aria-hidden="true"></span>
                                        <span class="text-slate-600 dark:text-slate-300">{{ $item['label'] ?? '' }}</span>
                                    </span>
                                @endforeach
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
                        @if ($ufRankings === [])
                            <p class="mt-3 text-sm text-slate-500">{{ __('Sem dados regionais — importe FUNDEB/SAEB/Censo.') }}</p>
                        @else
                            <ol class="mt-3 space-y-2 text-sm">
                                @foreach ($ufRankings as $row)
                                    <li class="flex items-center justify-between gap-2 rounded-lg bg-slate-50/80 dark:bg-slate-900/50 px-3 py-2">
                                        <span class="font-medium">{{ $row['uf'] }}</span>
                                        <span class="text-xs text-slate-500 tabular-nums">{{ __('benefício :n', ['n' => (int) ($row['avg_benefit'] ?? 0)]) }}</span>
                                        <span class="text-xs text-rose-700 dark:text-rose-300 tabular-nums">{{ (int) ($row['high_prospect'] ?? 0) }} {{ __('alta') }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </section>

                    <section class="serv-panel p-4" aria-labelledby="horizonte-top">
                        <h3 id="horizonte-top" class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Mais propensos a sucesso') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Prospectos com melhor score composto (financeiro + pedagógico + escala).') }}</p>
                        @if ($topProspects === [])
                            <p class="mt-3 text-sm text-slate-500">{{ __('Nenhum prospecto classificado.') }}</p>
                        @else
                            <ul class="mt-3 space-y-2 text-sm">
                                @foreach ($topProspects as $p)
                                    <li class="rounded-lg border border-slate-200/80 dark:border-slate-700/80 px-3 py-2">
                                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ $p['name'] ?? '' }} <span class="text-slate-500 font-normal">({{ $p['uf'] ?? '' }})</span></p>
                                        <p class="text-xs text-slate-500 mt-0.5">{{ $p['tier_label'] ?? '' }} · {{ __('propensão :n', ['n' => (int) ($p['success_score'] ?? 0)]) }} · {{ __('benefício :n', ['n' => (int) ($p['benefit_score'] ?? 0)]) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
