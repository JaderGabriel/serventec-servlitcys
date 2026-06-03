<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="serv-eyebrow">{{ __('Consultoria educacional') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    @if (Auth::user()?->isMunicipal())
                        {{ __('Painel do município') }}
                    @elseif (Auth::user()?->canViewAdminDashboard())
                        {{ __('Consultoria municipal') }}
                    @else
                        {{ __('Análise por município') }}
                    @endif
                </h2>
            </div>
            @if (Auth::user()?->canViewAdminDashboard())
                <a href="{{ route('dashboard') }}" class="serv-link text-sm">{{ __('← Início') }}</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="serv-panel serv-panel--info px-4 py-3 text-sm">
                <p class="font-medium text-serv-navy dark:text-teal-100">{{ __('Foco no município selecionado') }}</p>
                <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ __('Navegue por área: Cadastro → Pedagógico → Censo → Finanças. O Diagnóstico consolida prioridades; Censo trata exportação Educacenso; Finanças detalha repasses e discrepâncias.') }}
                </p>
            </div>

            <div class="serv-panel p-6">
                <x-input-label for="analytics_city" :value="__('Município')" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Selecione o município cuja base i-Educar será analisada (cadastro ativo com conexão à base).') }}</p>
                <form
                    method="get"
                    action="{{ route('dashboard.analytics') }}"
                    class="mt-2 flex flex-col sm:flex-row gap-4 sm:items-end"
                    data-serv-loading-on-submit
                    data-serv-loading-title="{{ __('A carregar município') }}"
                    data-serv-loading-message="{{ __('A preparar o painel de consultoria para a cidade selecionada…') }}"
                >
                    <div class="flex-1 max-w-xl">
                        <select id="analytics_city" name="city_id" class="block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 shadow-sm focus:border-teal-600 focus:ring-teal-600" onchange="this.form.submit()">
                            <option value="">{{ __('— Selecione uma cidade —') }}</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}" @selected((string) ($selectedCity?->id) === (string) $c->id)>{{ $c->name }} ({{ $c->uf }})@if (filled($c->ibge_municipio)) — {{ __('IBGE') }} {{ $c->ibge_municipio }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button type="submit">{{ __('Confirmar') }}</x-primary-button>
                </form>
                @if ($cities->isEmpty())
                    <p class="mt-4 text-sm text-amber-700 dark:text-amber-300">{{ __('Não há cidades ativas com banco de dados configurado. Configure e ative uma cidade em Cidades.') }}</p>
                @endif
            </div>

            @if ($selectedCity)
                @php
                    $lazyTabLoading = $lazyTabLoading ?? false;
                @endphp
                <div
                    x-data="analyticsTabs(@js(array_keys($tabs)), @js($analyticsInitialTab ?? 'overview'), @js($lazyTabLoading), @js(route('dashboard.analytics.tab')), @js(\App\Support\Dashboard\AnalyticsTabCatalog::navigationPayload()))"
                    x-on:set-analytics-tab.window="if ($event.detail && @js(array_keys($tabs)).includes($event.detail)) { tab = $event.detail; afterTabChange(); }"
                    class="space-y-6"
                >
                    <x-dashboard.consultoria-municipality-strip
                        :city="$selectedCity"
                        :filters="$filters"
                        :yearFilterReady="$yearFilterReady"
                    />

                    <x-dashboard.ieducar-filter-bar
                        :city="$selectedCity"
                        :filters="$filters"
                        :yearOptions="$yearOptions"
                        :ieducarOptions="$ieducarOptions"
                        :formAction="route('dashboard.analytics')"
                        :filterOptionsTurnoUrl="route('dashboard.analytics.filter-options')"
                        :filterBootstrapUrl="route('dashboard.analytics.filter-options-bootstrap')"
                        :filterYearsUrl="route('dashboard.analytics.filter-options-years')"
                        :deferSecondaryFilters="$deferSecondaryFilters ?? false"
                    >
                        <x-slot name="filtersExtras">
                            <input type="hidden" name="tab" :value="tab" />
                        </x-slot>
                    </x-dashboard.ieducar-filter-bar>

                    @if (! empty($indexFatalMessage ?? null))
                        <div class="rounded-lg border border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-900 dark:text-red-100">
                            <p class="font-medium">{{ __('Erro ao abrir o painel') }}</p>
                            <p class="mt-1 font-mono text-xs break-all">{{ $indexFatalMessage }}</p>
                        </div>
                    @endif

                    @if (! $yearFilterReady)
                        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/90 dark:bg-amber-950/30 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                            {{ __('Selecione o ano letivo (ou «Todos os anos») e clique em Aplicar filtros para carregar os indicadores e gráficos.') }}
                        </div>
                    @else
                        @if (! empty($analyticsLoadWarnings ?? []))
                            <div class="serv-callout serv-callout--warning text-sm space-y-1">
                                <p class="font-medium">{{ __('Alguns blocos não carregaram por completo') }}</p>
                                @foreach ($analyticsLoadWarnings as $warn)
                                    <p>{{ $warn }}</p>
                                @endforeach
                            </div>
                        @endif
                        <x-dashboard.funding-loss-conditions-modal :data="$fundingLossModalData ?? []" />
                    @endif

                    <div class="serv-panel overflow-x-hidden overflow-y-visible">
                        <x-dashboard.analytics-tabs-nav :groups="$tabGroups ?? []" :tabs="$tabs" />

                        <div class="p-4 sm:p-6 min-h-[min(38rem,88vh)] relative min-w-0 bg-white/50 dark:bg-slate-900/30">
                            <div x-show="tab === 'overview'" x-cloak class="analytics-tab-panel">
                                @if ($yearFilterReady && ($deferOverviewOnIndex ?? false))
                                    <div class="relative min-h-[14rem]" x-ref="panelOverview"></div>
                                @else
                                    @include('dashboard.analytics.partials.overview', [
                                        'overviewData' => $overviewData,
                                        'schoolUnits' => $schoolUnitsData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                    ])
                                @endif
                            </div>
                            <div x-show="tab === 'enrollment'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.enrollment', [
                                        'enrollmentData' => $enrollmentData,
                                        'discrepanciesData' => $discrepanciesData,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'yearFilterReady' => $yearFilterReady,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelEnrollment"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'cadunico_previsao'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.cadunico-previsao', [
                                        'cadunicoPrevisaoData' => $cadunicoPrevisaoData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'selectedCity' => $selectedCity,
                                        'filters' => $filters,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelCadunicoPrevisao">
                                        <p
                                            x-show="loadingTab === 'cadunico_previsao'"
                                            x-cloak
                                            class="text-sm text-slate-600 dark:text-slate-400 px-2 py-6"
                                        >
                                            {{ __('A carregar previsão CadÚnico…') }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                            <div x-show="tab === 'school_units'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.school-units', [
                                        'schoolUnitsData' => $schoolUnitsData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelSchoolUnits"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'network'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.network', [
                                        'networkData' => $networkData,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'yearFilterReady' => $yearFilterReady,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelNetwork"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'inclusion'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.inclusion', [
                                        'inclusionData' => $inclusionData,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'yearFilterReady' => $yearFilterReady,
                                        'selectedCity' => $selectedCity,
                                        'filters' => $filters,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelInclusion"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'performance'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.performance', [
                                        'performanceData' => $performanceData,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'yearFilterReady' => $yearFilterReady,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelPerformance"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'attendance'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.attendance', [
                                        'attendanceData' => $attendanceData,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'yearFilterReady' => $yearFilterReady,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelAttendance"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'comparativo'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.comparativo', [
                                        'comparativoData' => $comparativoData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'selectedCity' => $selectedCity,
                                        'filters' => $filters,
                                        'baseYear' => \App\Services\Analytics\FinanceComparativoService::resolveBaseYear(request(), $filters),
                                        'pdfExportsRecent' => auth()->user()?->canExportAnalyticsPdf()
                                            ? ($pdfExportsRecent ?? [])
                                            : [],
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelComparativo"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'fundeb'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.fundeb', [
                                        'fundebData' => $fundebData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelFundeb"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'other_funding'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.other-funding', [
                                        'otherFundingData' => $otherFundingData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelOtherFunding"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'work_done'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.work-done', [
                                        'workDoneData' => $workDoneData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelWorkDone"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'discrepancies'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.discrepancies', [
                                        'discrepanciesData' => $discrepanciesData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelDiscrepancies"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'municipality_health'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.municipality-health', [
                                        'healthData' => $municipalityHealthData,
                                        'yearFilterReady' => $yearFilterReady,
                                        'chartExportContext' => $chartExportContext,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'selectedCity' => $selectedCity,
                                        'filters' => $filters,
                                        'pdfExportsRecent' => $pdfExportsRecent ?? [],
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelMunicipalityHealth"></div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/30 p-12 text-center">
                    <p class="text-gray-600 dark:text-gray-400">{{ __('Selecione uma cidade para carregar os filtros do iEducar e as áreas de análise.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
