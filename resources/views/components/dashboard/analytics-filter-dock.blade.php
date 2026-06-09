@props([
    'cities',
    'selectedCity' => null,
    'filters' => null,
    'yearOptions' => [],
    'ieducarOptions' => [],
    'yearFilterReady' => false,
    'formAction' => null,
    'filterOptionsTurnoUrl' => null,
    'filterBootstrapUrl' => null,
    'filterYearsUrl' => null,
    'deferSecondaryFilters' => false,
    'pageHeader' => null,
    'fundebDockMeter' => null,
])

@php
    $filterCtx = is_array($pageHeader) ? $pageHeader : ['hasCity' => false, 'cityTitle' => '', 'parts' => [], 'labels' => []];
    $formAction = $formAction ?? route('dashboard.analytics');
    $showCityPicker = $cities->isNotEmpty() && ($selectedCity === null || $cities->count() > 1);
    $filtersOpenDefault = $selectedCity && ! $yearFilterReady;
@endphp

<div
    class="serv-analytics-filter-dock"
    role="region"
    aria-label="{{ __('Filtros e município') }}"
    @if ($selectedCity)
        x-data="analyticsFilterDock({ filtersOpen: @js($filtersOpenDefault), header: @js($filterCtx) })"
        x-on:analytics-filters-preview.window="refreshFromForm()"
    @endif
>
    <div class="serv-analytics-filter-dock__inner serv-page-shell">
        <div class="serv-analytics-filter-dock__municipality">
            <div class="serv-analytics-filter-dock__municipality-main min-w-0 flex-1">
                @if ($selectedCity)
                    <p class="serv-analytics-filter-dock__city-name">
                        <span class="font-semibold text-serv-navy dark:text-white">{{ $selectedCity->name }}</span>
                        @if (filled($selectedCity->uf))
                            <span class="text-slate-500 dark:text-slate-400 font-normal">— {{ $selectedCity->uf }}</span>
                        @endif
                    </p>
                    @if (filled($selectedCity->ibge_municipio))
                        <p class="serv-analytics-filter-dock__city-meta">
                            {{ __('IBGE') }} {{ $selectedCity->ibge_municipio }}
                        </p>
                    @endif
                @else
                    <p class="serv-analytics-filter-dock__city-name font-medium text-serv-navy dark:text-white">
                        {{ __('Selecione o município para analisar') }}
                    </p>
                    <p class="serv-analytics-filter-dock__city-meta">
                        {{ __('Base i-Educar activa com conexão configurada.') }}
                    </p>
                @endif
            </div>

            @if ($selectedCity)
                <div class="serv-analytics-filter-dock__contact shrink-0">
                    <x-city.reference-contact :city="$selectedCity" variant="agenda" tone="light" layout="inline" />
                </div>
            @endif

            @if ($showCityPicker)
                <form
                    method="get"
                    action="{{ $formAction }}"
                    class="serv-analytics-filter-dock__city-form shrink-0"
                    id="analytics-city-switch"
                    data-serv-loading-on-submit
                    data-serv-loading-title="{{ __('A carregar município') }}"
                    data-serv-loading-message="{{ __('A preparar o painel de consultoria para a cidade selecionada…') }}"
                >
                    @if ($filters && $selectedCity)
                        @foreach ($filters->toQueryParams() as $key => $value)
                            @if ($value !== null && $value !== '' && ! in_array($key, ['city_id', 'ano_letivo'], true))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                            @endif
                        @endforeach
                    @endif
                    <label for="analytics_dock_city" class="sr-only">{{ __('Município') }}</label>
                    <select
                        id="analytics_dock_city"
                        name="city_id"
                        class="serv-analytics-filter-dock__city-select"
                        onchange="window.servDataLoading?.requestSubmit?.(this.form)"
                    >
                        <option value="">{{ __('— Município —') }}</option>
                        @foreach ($cities as $c)
                            <option value="{{ $c->id }}" @selected((string) ($selectedCity?->id) === (string) $c->id)>
                                {{ $c->name }} ({{ $c->uf }})@if (filled($c->ibge_municipio)) — {{ __('IBGE') }} {{ $c->ibge_municipio }}@endif
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($selectedCity)
            <div class="serv-analytics-filter-dock__summary">
                <button
                    type="button"
                    class="serv-analytics-filter-dock__summary-toggle"
                    x-on:click="filtersOpen = !filtersOpen"
                    :aria-expanded="filtersOpen"
                    aria-controls="analytics-filter-dock-panel"
                >
                    <span class="serv-analytics-filter-dock__summary-label">{{ __('Recorte activo') }}</span>
                    <span class="serv-analytics-filter-dock__summary-parts">
                        <template x-for="(part, index) in parts" :key="part.label + '-' + index">
                            <span class="serv-analytics-filter-dock__chip" :class="part.muted ? 'serv-analytics-filter-dock__chip--muted' : ''">
                                <span x-show="index > 0" class="serv-analytics-filter-dock__chip-sep" aria-hidden="true">·</span>
                                <span class="serv-analytics-filter-dock__chip-label" x-text="part.label"></span>
                                <span class="serv-analytics-filter-dock__chip-value" x-text="part.value"></span>
                            </span>
                        </template>
                    </span>
                </button>

                <x-dashboard.analytics-filter-dock-fundeb-meter
                    :meter="$fundebDockMeter ?? []"
                    :filters="$filters"
                    :selectedCity="$selectedCity"
                    :yearFilterReady="$yearFilterReady"
                />

                <div class="serv-analytics-filter-dock__summary-actions">
                    <button
                        type="submit"
                        form="analytics-ieducar-filters"
                        class="serv-analytics-filter-dock__apply"
                    >
                        {{ __('Aplicar') }}
                    </button>
                    <a
                        href="{{ route('dashboard.analytics', ['city_id' => $selectedCity->id]) }}"
                        class="serv-analytics-filter-dock__clear"
                    >
                        {{ __('Limpar') }}
                    </a>
                    <button
                        type="button"
                        class="serv-analytics-filter-dock__expand"
                        x-on:click="filtersOpen = !filtersOpen"
                        :aria-expanded="filtersOpen"
                        aria-controls="analytics-filter-dock-panel"
                        :title="filtersOpen ? @js(__('Ocultar filtros')) : @js(__('Editar filtros'))"
                    >
                        <svg
                            class="h-4 w-4 transition-transform duration-200"
                            :class="filtersOpen ? 'rotate-180' : ''"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                        </svg>
                        <span class="sr-only" x-text="filtersOpen ? @js(__('Ocultar filtros')) : @js(__('Editar filtros'))"></span>
                    </button>
                </div>
            </div>

            <div
                id="analytics-filter-dock-panel"
                class="serv-analytics-filter-dock__panel"
                x-show="filtersOpen"
                x-cloak
            >
                <x-dashboard.ieducar-filter-bar
                    variant="dock"
                    :city="$selectedCity"
                    :filters="$filters"
                    :yearOptions="$yearOptions"
                    :ieducarOptions="$ieducarOptions"
                    :formAction="$formAction"
                    :filterOptionsTurnoUrl="$filterOptionsTurnoUrl"
                    :filterBootstrapUrl="$filterBootstrapUrl"
                    :filterYearsUrl="$filterYearsUrl"
                    :deferSecondaryFilters="$deferSecondaryFilters"
                >
                    @isset($filtersExtras)
                        <x-slot name="filtersExtras">{{ $filtersExtras }}</x-slot>
                    @endisset
                </x-dashboard.ieducar-filter-bar>
            </div>
        @elseif ($cities->isEmpty())
            <p class="serv-analytics-filter-dock__empty text-sm text-amber-800 dark:text-amber-200">
                {{ __('Não há cidades activas com banco configurado. Configure e active uma cidade em Cidades.') }}
            </p>
        @endif
    </div>
</div>
