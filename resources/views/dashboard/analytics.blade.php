<x-app-layout>
    @php
        $analyticsPageHeader = \App\Support\Dashboard\ChartExportMeta::pageHeaderContext(
            $selectedCity ?? null,
            $filters,
            is_array($ieducarOptions ?? null) ? $ieducarOptions : [],
        );
    @endphp

    <x-slot name="header">
        <div class="flex flex-row items-center justify-between gap-3 sm:gap-4">
            <x-dashboard.analytics-page-heading />
            <div class="flex items-center gap-2 shrink-0">
                @if ($selectedCity ?? null)
                    <x-dashboard.analytics-export-hub
                        :selectedCity="$selectedCity"
                        :filters="$filters ?? null"
                        :yearFilterReady="$yearFilterReady ?? false"
                    />
                @endif
            @if (Auth::user())
                <a
                    href="{{ Auth::user()->homeUrl() }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-teal-700 transition hover:bg-teal-50 hover:text-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-500/40 dark:border-slate-600 dark:bg-slate-800 dark:text-teal-400 dark:hover:bg-teal-950/40 dark:hover:text-teal-300"
                    title="{{ __('Início') }}"
                    aria-label="{{ __('Início') }}"
                >
                    <x-ui.icon name="home" class="h-5 w-5" />
                </a>
            @endif
            </div>
        </div>
    </x-slot>

    <div class="serv-analytics-page py-8">
        <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('admin_sync_queued'))
                @include('admin.partials.sync-queued-alert')
            @endif
            @if (session('status') && session('pdf_export_id'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/95 dark:border-emerald-800 dark:bg-emerald-950/35 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-100" role="status">
                    <p class="font-semibold">{{ __('Enviado para a fila') }}</p>
                    <p class="mt-1">{{ session('status') }}</p>
                    <p class="mt-1 text-xs font-mono">#{{ session('pdf_export_id') }}</p>
                    <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', ['pdf_status' => 'pending']) }}#fila-pdf" class="mt-2 inline-block text-xs font-medium underline">{{ __('Abrir fila') }}</a>
                </div>
            @endif
            <div class="serv-panel serv-panel--info px-4 py-3 text-sm">
                <p class="font-medium text-serv-navy dark:text-teal-100">{{ __('Foco no município selecionado') }}</p>
                <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ __('Comece pelo Resumo (Diagnóstico executivo); depois Cadastro, Pedagógico, Censo ou Finanças conforme a prioridade. Finanças detalha discrepâncias, FUNDEB e repasses. Use o rodapé fixo para mudar município, contato e filtros.') }}
                </p>
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
                                    <div class="relative min-h-[14rem]" x-ref="panelOverview" data-analytics-tab-panel="overview"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelEnrollment" data-analytics-tab-panel="enrollment"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelCadunicoPrevisao" data-analytics-tab-panel="cadunico_previsao"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelSchoolUnits" data-analytics-tab-panel="school_units"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelNetwork" data-analytics-tab-panel="network"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelInclusion" data-analytics-tab-panel="inclusion"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelPerformance" data-analytics-tab-panel="performance"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelAttendance" data-analytics-tab-panel="attendance"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'comparativo'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.comparativo', [
                                        'comparativoData' => $comparativoData,
                                        'yearFilterReady' => $yearFilterReady
                                            || \App\Services\Analytics\FinanceComparativoService::resolveBaseYear(request(), $filters) !== null,
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
                                    <div class="relative min-h-[12rem]" x-ref="panelComparativo" data-analytics-tab-panel="comparativo"></div>
                                @endif
                            </div>
                            <div x-show="tab === 'finance_realtime'" x-cloak class="analytics-tab-panel">
                                @if (! $lazyTabLoading)
                                    @include('dashboard.analytics.partials.finance-realtime', [
                                        'realtimeData' => $financeRealtimeData ?? \App\Support\Dashboard\AnalyticsEmptyPayloads::financeRealtime(),
                                        'yearFilterReady' => $yearFilterReady,
                                        'municipalityContext' => $municipalityContext ?? null,
                                        'filters' => $filters,
                                    ])
                                @else
                                    <div class="relative min-h-[12rem]" x-ref="panelFinanceRealtime" data-analytics-tab-panel="finance_realtime"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelFundeb" data-analytics-tab-panel="fundeb"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelOtherFunding" data-analytics-tab-panel="other_funding"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelWorkDone" data-analytics-tab-panel="work_done"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelDiscrepancies" data-analytics-tab-panel="discrepancies"></div>
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
                                    <div class="relative min-h-[12rem]" x-ref="panelMunicipalityHealth" data-analytics-tab-panel="municipality_health"></div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <x-dashboard.analytics-filter-dock
                        :cities="$cities"
                        :selectedCity="$selectedCity"
                        :filters="$filters"
                        :yearOptions="$yearOptions"
                        :ieducarOptions="$ieducarOptions"
                        :yearFilterReady="$yearFilterReady"
                        :fundebDockMeter="$fundebDockMeter ?? []"
                        :qualityDockIndicator="$qualityDockIndicator ?? []"
                        :pageHeader="$analyticsPageHeader"
                        :formAction="route('dashboard.analytics')"
                        :filterOptionsTurnoUrl="route('dashboard.analytics.filter-options')"
                        :filterBootstrapUrl="route('dashboard.analytics.filter-options-bootstrap')"
                        :filterYearsUrl="route('dashboard.analytics.filter-options-years')"
                        :deferSecondaryFilters="$deferSecondaryFilters ?? false"
                    >
                        <x-slot name="filtersExtras">
                            <input type="hidden" name="tab" :value="tab" />
                        </x-slot>
                    </x-dashboard.analytics-filter-dock>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/30 p-12 text-center">
                    <p class="text-gray-600 dark:text-gray-400">{{ __('Selecione um município no rodapé fixo para carregar os filtros do iEducar e as áreas de análise.') }}</p>
                </div>

                <x-dashboard.analytics-filter-dock
                    :cities="$cities"
                    :selectedCity="null"
                    :filters="$filters"
                    :yearOptions="$yearOptions ?? []"
                    :ieducarOptions="$ieducarOptions ?? []"
                    :formAction="route('dashboard.analytics')"
                />
            @endif
        </div>
    </div>
</x-app-layout>
