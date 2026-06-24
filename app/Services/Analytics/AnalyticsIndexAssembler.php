<?php

namespace App\Services\Analytics;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\City;
use App\Models\User;
use App\Repositories\Ieducar\AttendanceRepository;
use App\Repositories\Ieducar\CadunicoPrevisaoRepository;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Repositories\Ieducar\EnrollmentRepository;
use App\Repositories\Ieducar\FundebRepository;
use App\Repositories\Ieducar\InclusionRepository;
use App\Repositories\Ieducar\MunicipalityHealthRepository;
use App\Repositories\Ieducar\NetworkRepository;
use App\Repositories\Ieducar\OtherFundingRepository;
use App\Repositories\Ieducar\OverviewRepository;
use App\Repositories\Ieducar\PerformanceRepository;
use App\Repositories\Ieducar\SchoolUnitsRepository;
use App\Repositories\Ieducar\WorkDoneRepository;
use App\Services\Educacenso\EducacensoAnalysisCache;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\AnalyticsDockQualityIndicator;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
use App\Support\Dashboard\AnalyticsFinanceTabPreload;
use App\Support\Dashboard\AnalyticsLoadProfiler;
use App\Support\Dashboard\AnalyticsMunicipalityContext;
use App\Support\Dashboard\AnalyticsTabCatalog;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebVaafProfileBuilder;
use App\Support\Ieducar\IeducarAnalyticsMetricsScope;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class AnalyticsIndexAssembler
{
    public function __construct(
        private readonly AnalyticsFilterResolver $filterResolver,
        private readonly AnalyticsSafeLoader $safeLoader,
        private readonly AnalyticsFinanceTabPreloader $financePreloader,
        private readonly AnalyticsMunicipalAccess $municipalAccess,
    ) {}

    public function assemble(
        AnalyticsFilterRequest $request,
        FilterOptionsService $filterOptionsService,
        OverviewRepository $overviewRepository,
        EnrollmentRepository $enrollmentRepository,
        CadunicoPrevisaoRepository $cadunicoPrevisaoRepository,
        PerformanceRepository $performanceRepository,
        AttendanceRepository $attendanceRepository,
        InclusionRepository $inclusionRepository,
        NetworkRepository $networkRepository,
        FundebRepository $fundebRepository,
        OtherFundingRepository $otherFundingRepository,
        WorkDoneRepository $workDoneRepository,
        DiscrepanciesRepository $discrepanciesRepository,
        MunicipalityHealthRepository $municipalityHealthRepository,
        SchoolUnitsRepository $schoolUnitsRepository,
        AnalyticsReportExportService $pdfExportService,
    ): View|RedirectResponse {
        $user = $request->user();

        if ($redirect = $this->municipalAccess->municipalHomeRedirect($request)) {
            return $redirect;
        }

        $cities = UserCityAccess::citiesQuery($user)->get();

        $filters = $request->filters();

        $city = null;
        if ($request->filled('city_id')) {
            $city = UserCityAccess::citiesQuery($request->user())
                ->whereKey($request->integer('city_id'))
                ->first();
        }

        $profiler = new AnalyticsLoadProfiler;
        $analyticsDebugEnabled = AnalyticsLoadProfiler::enabled() || (bool) config('app.debug');
        $indexLightFilters = (bool) config('analytics.index_light_filters', true);

        $yearOptions = $this->filterResolver->schoolYearOptionsFallback();
        $ieducarOptions = [
            'years' => [],
            'escolas' => [],
            'cursos' => [],
            'series' => [],
            'segmentos' => [],
            'etapas' => [],
            'turnos' => [],
            'errors' => [],
        ];
        $analyticsLoadWarnings = [];
        $indexFatalMessage = null;

        if ($city) {
            Gate::authorize('viewAnalytics', $city);

            $yearPayload = null;
            $filters = $this->filterResolver->resolve($request, $filterOptionsService, $city, $yearPayload);

            try {
                $ieducarOptions = $profiler->measure('filter_options', function () use (
                    $filterOptionsService,
                    $city,
                    $filters,
                    $indexLightFilters,
                    $profiler,
                    $yearPayload,
                ) {
                    return $filterOptionsService->loadForAnalyticsIndex(
                        $city,
                        $filters,
                        $indexLightFilters,
                        $profiler,
                        $yearPayload,
                    );
                }, ['light' => $indexLightFilters]);
            } catch (Throwable $e) {
                Log::warning('analytics.filter_options_failed', [
                    'city_id' => $city->id,
                    'message' => $e->getMessage(),
                ]);
                $analyticsLoadWarnings[] = __('Filtros i-Educar: não foi possível carregar todas as opções (:msg).', [
                    'msg' => $e->getMessage(),
                ]);
                $ieducarOptions['errors'][] = $e->getMessage();
            }

            $yearOptions = $ieducarOptions['years'] !== []
                ? $ieducarOptions['years']
                : $this->filterResolver->schoolYearOptionsFallback();
        }

        $yearFilterReady = $city !== null && $filters->hasYearSelected();
        $lazyTabLoading = (bool) config('analytics.lazy_tab_loading', true);
        $loadOverviewOnIndex = ! $lazyTabLoading || (bool) config('analytics.index_load_overview', false);
        $deferOverviewOnIndex = $lazyTabLoading && ! $loadOverviewOnIndex;

        $overviewData = ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null];
        $schoolUnitsData = [
            'overview' => [
                'year_global_rows' => [],
                'school_year_rows' => [],
                'units_rows' => [],
                'notes' => [],
            ],
            'tab' => [
                'markers' => [],
                'transport' => null,
                'waiting' => null,
                'geo_note' => null,
                'geo_source' => null,
                'geo_attribution' => [],
                'geo_distribution' => null,
                'map_scope' => 'matricula',
                'show_waiting_capacity' => true,
                'error' => null,
            ],
            'error' => null,
        ];

        $enrollmentData = AnalyticsEmptyPayloads::enrollment();
        $cadunicoPrevisaoData = AnalyticsEmptyPayloads::cadunicoPrevisao();
        $performanceData = AnalyticsEmptyPayloads::performance();
        $attendanceData = AnalyticsEmptyPayloads::attendance();
        $inclusionData = AnalyticsEmptyPayloads::inclusion();
        $networkData = AnalyticsEmptyPayloads::network();
        $fundebData = AnalyticsEmptyPayloads::fundeb();
        $otherFundingData = AnalyticsEmptyPayloads::otherFunding();
        $workDoneData = AnalyticsEmptyPayloads::workDone();
        $discrepanciesData = AnalyticsEmptyPayloads::discrepancies();
        $comparativoData = AnalyticsEmptyPayloads::comparativo();
        $municipalityHealthData = AnalyticsEmptyPayloads::municipalityHealth();
        $municipalityContext = null;
        $fundebDockMeter = [
            'available' => false,
            'partial' => false,
            'anchor_ano' => null,
            'title' => __('FUNDEB'),
            'hint' => __('Valor publicado pelo FNDE ou consolidado do ano letivo. O ano seguinte não é projetado se houver pendências.'),
            'status' => 'neutral',
            'status_label' => __('Indisponível'),
            'primary_value' => '—',
            'primary_label' => __('Exercício'),
            'phase_label' => '',
            'secondary' => [],
            'variation_pct' => null,
            'alert' => null,
            'projection_blocked' => false,
            'next_year_note' => null,
        ];
        $qualityDockIndicator = \App\Support\Dashboard\AnalyticsDockQualityIndicator::empty();

        if ($yearFilterReady && $city !== null && $loadOverviewOnIndex) {
            try {
                $this->filterResolver->bindMetricsScope($city, $filters);
                $overviewData = $profiler->measure('overview', fn () => $this->safeLoader->load(
                    fn () => $overviewRepository->summary($city, $filters),
                    $overviewData,
                    __('Visão geral'),
                    $analyticsLoadWarnings,
                ));

                // Com lazy ativo, unidades (mapa/geo pesado) só na aba dedicada — evita 500/timeout no «Aplicar filtros».
                if (! $lazyTabLoading) {
                    $schoolUnitsData = $this->safeLoader->load(
                        fn () => $schoolUnitsRepository->snapshot($city, $filters),
                        $schoolUnitsData,
                        __('Unidades escolares'),
                        $analyticsLoadWarnings,
                    );
                }

                if (! $lazyTabLoading) {
                    $otherFundingData = $this->safeLoader->load(
                        fn () => $otherFundingRepository->buildReport($city, $filters),
                        $otherFundingData,
                        __('Financiamentos'),
                        $analyticsLoadWarnings,
                    );

                    $workDoneData = $this->safeLoader->load(
                        fn () => $workDoneRepository->buildReport($city, $filters),
                        $workDoneData,
                        __('Censo'),
                        $analyticsLoadWarnings,
                    );
                    $enrollmentData = $this->safeLoader->load(
                        fn () => $enrollmentRepository->sample($city, $filters),
                        $enrollmentData,
                        __('Matrículas'),
                        $analyticsLoadWarnings,
                    );
                    $cadunicoPrevisaoData = $this->safeLoader->load(
                        fn () => $cadunicoPrevisaoRepository->buildReport($city, $filters),
                        $cadunicoPrevisaoData,
                        __('CadÚnico'),
                        $analyticsLoadWarnings,
                    );
                    $performanceData = $this->safeLoader->load(
                        fn () => $performanceRepository->snapshot($city, $filters),
                        $performanceData,
                        __('Desempenho'),
                        $analyticsLoadWarnings,
                    );
                    $attendanceData = $this->safeLoader->load(
                        fn () => $attendanceRepository->snapshot($city, $filters),
                        $attendanceData,
                        __('Frequência'),
                        $analyticsLoadWarnings,
                    );
                    $inclusionData = $this->safeLoader->load(
                        fn () => $inclusionRepository->snapshot($city, $filters),
                        $inclusionData,
                        __('Inclusão'),
                        $analyticsLoadWarnings,
                    );
                    $networkData = $this->safeLoader->load(
                        fn () => $networkRepository->snapshot($city, $filters),
                        $networkData,
                        __('Rede'),
                        $analyticsLoadWarnings,
                    );
                    $discrepanciesData = $this->safeLoader->load(
                        fn () => $discrepanciesRepository->snapshot($city, $filters),
                        $discrepanciesData,
                        __('Discrepâncias'),
                        $analyticsLoadWarnings,
                    );
                    $fundebData = $this->safeLoader->load(
                        fn () => $fundebRepository->buildReport(
                            $city,
                            $filters,
                            $overviewData,
                            $enrollmentData,
                            $performanceData,
                            $attendanceData,
                            $inclusionData,
                            $networkData,
                            $discrepanciesData,
                        ),
                        $fundebData,
                        __('FUNDEB'),
                        $analyticsLoadWarnings,
                    );
                    $municipalityHealthData = $this->safeLoader->load(
                        fn () => $municipalityHealthRepository->snapshot($city, $filters),
                        $municipalityHealthData,
                        __('Diagnóstico'),
                        $analyticsLoadWarnings,
                    );
                }

                if (config('analytics.index_funding_context', false)) {
                    if (
                        is_array($municipalityHealthData)
                        && AnalyticsFinanceTabPreload::municipalityHealthReuseEnabled()
                    ) {
                        $municipalityContext = AnalyticsMunicipalityContext::fromHealthSnapshot($municipalityHealthData);
                    } else {
                        $fundingSnapshot = $this->safeLoader->load(
                            fn () => $this->financePreloader->fundingImpactSnapshot($discrepanciesRepository, $city, $filters),
                            null,
                            __('Resumo financeiro'),
                            $analyticsLoadWarnings,
                        );
                        if (! is_array($fundingSnapshot)) {
                            $fundingSnapshot = [
                                'summary' => [],
                                'funding_reference' => DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters),
                            ];
                        } elseif (! is_array($fundingSnapshot['funding_reference'] ?? null)) {
                            $fundingSnapshot['funding_reference'] = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
                        }
                        $municipalityContext = AnalyticsMunicipalityContext::fromFundingSnapshot(
                            $fundingSnapshot,
                            is_array($overviewData) ? $overviewData : [],
                        );
                    }
                }
            } catch (Throwable $e) {
                Log::error('analytics.index_load_failed', [
                    'city_id' => $city->id,
                    'ano_letivo' => $filters->ano_letivo,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $analyticsLoadWarnings[] = __('Não foi possível carregar todos os indicadores: :msg', [
                    'msg' => $e->getMessage(),
                ]);
                if (is_array($overviewData)) {
                    $overviewData['error'] = $overviewData['error'] ?? $e->getMessage();
                }
            } finally {
                IeducarAnalyticsMetricsScope::forget();
            }
        }

        if ($city !== null && $yearFilterReady) {
            $dockMetersEnabled = filter_var(config('analytics.fundeb_dock_meter', true), FILTER_VALIDATE_BOOL)
                || filter_var(config('analytics.quality_dock_indicator', true), FILTER_VALIDATE_BOOL);

            if ($dockMetersEnabled) {
                try {
                    $this->filterResolver->bindMetricsScope($city, $filters);
                    $fundingSnapshotForDock = null;
                    $discSummary = is_array($discrepanciesData['summary'] ?? null)
                        ? $discrepanciesData['summary']
                        : null;
                    if ($discSummary === null && filter_var(config('analytics.fundeb_load_discrepancies_summary', true), FILTER_VALIDATE_BOOL)) {
                        $fundingSnapshotForDock = $this->financePreloader->fundingImpactSnapshot($discrepanciesRepository, $city, $filters);
                        if (is_array($fundingSnapshotForDock['summary'] ?? null)) {
                            $discSummary = $fundingSnapshotForDock['summary'];
                        }
                    }

                    if (filter_var(config('analytics.quality_dock_indicator', true), FILTER_VALIDATE_BOOL)) {
                        $healthForDock = is_array($municipalityHealthData)
                            && is_numeric($municipalityHealthData['compliance_score'] ?? null)
                            ? $municipalityHealthData
                            : null;
                        if ($healthForDock === null) {
                            $healthForDock = $profiler->measure(
                                'quality_dock_health',
                                fn () => $this->safeLoader->load(
                                    fn () => $municipalityHealthRepository->snapshot($city, $filters),
                                    AnalyticsEmptyPayloads::municipalityHealth(),
                                    __('Diagnóstico (rodapé)'),
                                    $analyticsLoadWarnings,
                                ),
                            );
                        }

                        $qualityDockIndicator = \App\Support\Dashboard\AnalyticsDockQualityIndicator::build(
                            $healthForDock,
                            $municipalityContext,
                            $fundingSnapshotForDock,
                            true,
                        );
                    }

                    if (filter_var(config('analytics.fundeb_dock_meter', true), FILTER_VALIDATE_BOOL)) {
                        $matFiltro = FundebRepository::resolveMatriculasAtivasForFilter(
                            $city,
                            $filters,
                            is_array($overviewData) ? $overviewData : [],
                            is_array($enrollmentData) ? $enrollmentData : [],
                        );

                        $fundebDockMeter = $profiler->measure(
                            'fundeb_dock_meter',
                            fn () => $this->safeLoader->load(
                                fn () => app(FundebVaafProfileBuilder::class)->buildDockMeter(
                                    $city,
                                    $filters,
                                    $matFiltro > 0 ? $matFiltro : null,
                                    is_array($discSummary) ? $discSummary : null,
                                ),
                                $fundebDockMeter,
                                __('FUNDEB (rodapé)'),
                                $analyticsLoadWarnings,
                            ),
                        );
                    }
                } catch (Throwable $e) {
                    Log::warning('analytics.dock_meters_failed', [
                        'city_id' => $city->id,
                        'ano_letivo' => $filters->ano_letivo,
                        'message' => $e->getMessage(),
                    ]);
                } finally {
                    IeducarAnalyticsMetricsScope::forget();
                }
            }
        }

        $analyticsDebugSteps = $profiler->steps();
        $analyticsDebugTotalMs = $profiler->totalMs();
        $profiler->flush('index', [
            'city_id' => $city?->id,
            'ano_letivo' => $filters->ano_letivo,
            'year_filter_ready' => $yearFilterReady,
            'light_filters' => $indexLightFilters,
        ]);

        try {
            $chartExportContext = ChartExportMeta::forAnalytics($city, $filters, $ieducarOptions);

            $tabs = AnalyticsTabCatalog::tabsOrdered();
            $qTab = (string) $request->query('tab', '');
            $analyticsInitialTab = AnalyticsTabCatalog::resolveInitialTab(
                $qTab,
                $user,
                $yearFilterReady,
            );

            $pdfExportsRecent = ($city !== null && $user !== null && $user->canExportAnalyticsPdf())
                ? $pdfExportService->recentForUserCity($user, $city, 6)
                : [];

            return view('dashboard.analytics', array_merge(
                $this->viewData(
                    $cities,
                    $city,
                    $filters,
                    $yearOptions,
                    $yearFilterReady,
                    $ieducarOptions,
                    $overviewData,
                    $schoolUnitsData,
                    $enrollmentData,
                    $cadunicoPrevisaoData,
                    $performanceData,
                    $attendanceData,
                    $inclusionData,
                    $networkData,
                    $fundebData,
                    $otherFundingData,
                    $workDoneData,
                    $discrepanciesData,
                    $comparativoData,
                    $municipalityHealthData,
                    $chartExportContext,
                    $tabs,
                    $analyticsInitialTab,
                    $lazyTabLoading,
                    $pdfExportsRecent,
                    $municipalityContext,
                    $analyticsLoadWarnings,
                    $indexLightFilters,
                    $deferOverviewOnIndex,
                    $analyticsDebugEnabled,
                    $analyticsDebugSteps,
                    $analyticsDebugTotalMs,
                    $indexFatalMessage,
                    $fundebDockMeter,
                    $qualityDockIndicator,
                ),
                [
                    'educacensoAnalysis' => $this->resolveEducacensoAnalysis($user, $city),
                ],
            ));
        } catch (Throwable $e) {
            Log::error('analytics.index_fatal', [
                'city_id' => $city?->id,
                'ano_letivo' => $filters->ano_letivo,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $profiler->flush('index_fatal', ['error' => $e->getMessage()]);
            $indexFatalMessage = $e->getMessage();
            $analyticsLoadWarnings[] = __('Erro ao renderizar o painel: :msg', ['msg' => $e->getMessage()]);
            $analyticsDebugSteps = $profiler->steps();

            return view('dashboard.analytics', array_merge(
                $this->viewData(
                    $cities,
                    $city,
                    $filters,
                    $yearOptions,
                    $yearFilterReady,
                    $ieducarOptions,
                    $overviewData,
                    $schoolUnitsData,
                    $enrollmentData,
                    $cadunicoPrevisaoData,
                    $performanceData,
                    $attendanceData,
                    $inclusionData,
                    $networkData,
                    $fundebData,
                    $otherFundingData,
                    $workDoneData,
                    $discrepanciesData,
                    $comparativoData,
                    $municipalityHealthData,
                    ChartExportMeta::forAnalytics($city, $filters, $ieducarOptions),
                    AnalyticsTabCatalog::tabsOrdered(),
                    AnalyticsTabCatalog::resolveInitialTab(
                        (string) $request->query('tab', ''),
                        $user,
                        $yearFilterReady,
                    ),
                    $lazyTabLoading,
                    [],
                    $municipalityContext,
                    $analyticsLoadWarnings,
                    $indexLightFilters,
                    $deferOverviewOnIndex,
                    $analyticsDebugEnabled,
                    $analyticsDebugSteps,
                    $profiler->totalMs(),
                    $indexFatalMessage,
                    $fundebDockMeter,
                    $qualityDockIndicator,
                ),
                [
                    'educacensoAnalysis' => $this->resolveEducacensoAnalysis($user, $city),
                ],
            ));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveEducacensoAnalysis(?Authenticatable $user, ?City $city): ?array
    {
        if ($user === null || ! $city instanceof City || ! $user instanceof \App\Models\User) {
            return null;
        }

        return EducacensoAnalysisCache::get($user, $city);
    }

    /**
     * @param  list<string>  $analyticsLoadWarnings
     * @param  list<array{step: string, ms: float, meta?: array<string, mixed>}>  $analyticsDebugSteps
     * @param  array<string, mixed>  $fundebDockMeter
     * @param  array<string, mixed>  $qualityDockIndicator
     * @return array<string, mixed>
     */
    private function viewData(
        $cities,
        ?City $city,
        IeducarFilterState $filters,
        array $yearOptions,
        bool $yearFilterReady,
        array $ieducarOptions,
        array $overviewData,
        array $schoolUnitsData,
        array $enrollmentData,
        array $cadunicoPrevisaoData,
        array $performanceData,
        array $attendanceData,
        array $inclusionData,
        array $networkData,
        array $fundebData,
        array $otherFundingData,
        array $workDoneData,
        array $discrepanciesData,
        array $comparativoData,
        array $municipalityHealthData,
        array $chartExportContext,
        array $tabs,
        string $analyticsInitialTab,
        bool $lazyTabLoading,
        array $pdfExportsRecent,
        ?array $municipalityContext,
        array $analyticsLoadWarnings,
        bool $indexLightFilters,
        bool $deferOverviewOnIndex,
        bool $analyticsDebugEnabled,
        array $analyticsDebugSteps,
        float $analyticsDebugTotalMs,
        ?string $indexFatalMessage,
        array $fundebDockMeter,
        array $qualityDockIndicator,
    ): array {
        return [
            'cities' => $cities,
            'selectedCity' => $city,
            'filters' => $filters,
            'yearOptions' => $yearOptions,
            'yearFilterReady' => $yearFilterReady,
            'ieducarOptions' => $ieducarOptions,
            'overviewData' => $overviewData,
            'schoolUnitsData' => $schoolUnitsData,
            'enrollmentData' => $enrollmentData,
            'cadunicoPrevisaoData' => $cadunicoPrevisaoData,
            'performanceData' => $performanceData,
            'attendanceData' => $attendanceData,
            'inclusionData' => $inclusionData,
            'networkData' => $networkData,
            'fundebData' => $fundebData,
            'otherFundingData' => $otherFundingData,
            'workDoneData' => $workDoneData,
            'discrepanciesData' => $discrepanciesData,
            'comparativoData' => $comparativoData,
            'municipalityHealthData' => $municipalityHealthData,
            'fundingLossModalData' => DiscrepanciesCheckCatalog::modalPayload($city, $filters),
            'chartExportContext' => $chartExportContext,
            'tabs' => $tabs,
            'tabGroups' => AnalyticsTabCatalog::groups(),
            'analyticsInitialTab' => $analyticsInitialTab,
            'lazyTabLoading' => $lazyTabLoading,
            'pdfExportsRecent' => $pdfExportsRecent,
            'municipalityContext' => $municipalityContext,
            'analyticsLoadWarnings' => $analyticsLoadWarnings,
            'deferSecondaryFilters' => $indexLightFilters && $city !== null,
            'deferOverviewOnIndex' => $deferOverviewOnIndex && $yearFilterReady,
            'analyticsDebugEnabled' => $analyticsDebugEnabled,
            'analyticsDebugSteps' => $analyticsDebugSteps,
            'analyticsDebugTotalMs' => $analyticsDebugTotalMs,
            'indexFatalMessage' => $indexFatalMessage,
            'fundebDockMeter' => $fundebDockMeter,
            'qualityDockIndicator' => $qualityDockIndicator,
        ];
    }
}
