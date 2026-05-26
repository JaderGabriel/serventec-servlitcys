<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Repositories\Ieducar\AttendanceRepository;
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
use App\Services\Analytics\AnalyticsReportExportService;
use App\Services\CityDataConnection;
use App\Services\Ieducar\FilterOptionsService;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
use App\Support\Dashboard\AnalyticsLoadProfiler;
use App\Support\Dashboard\AnalyticsFinanceTabPreload;
use App\Support\Dashboard\AnalyticsMunicipalityContext;
use App\Support\Dashboard\AnalyticsTabPayloadCache;
use App\Support\Dashboard\AnalyticsTabCatalog;
use App\Support\Dashboard\ConsultoriaFlow;
use App\Support\Dashboard\MunicipalityHealthSections;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use App\Support\Ieducar\IeducarAnalyticsMetricsScope;
use App\Support\Pulse\PulseOperationRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Painel de análise educacional: dados da base iEducar da cidade via repositórios e filtros.
 */
class AnalyticsDashboardController extends Controller
{
    public function index(
        Request $request,
        FilterOptionsService $filterOptionsService,
        OverviewRepository $overviewRepository,
        EnrollmentRepository $enrollmentRepository,
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

        if ($user?->isMunicipal() && ! $request->filled('city_id')) {
            $homeParams = $user->homeRouteParameters();
            if ($homeParams !== []) {
                return redirect()->route('dashboard.analytics', array_merge(
                    $request->query(),
                    $homeParams,
                ));
            }
        }

        $cities = UserCityAccess::citiesQuery($user)->get();

        $filters = IeducarFilterState::fromRequest($request);

        $city = null;
        if ($request->filled('city_id')) {
            $city = UserCityAccess::citiesQuery($request->user())
                ->whereKey($request->integer('city_id'))
                ->first();
        }

        $profiler = new AnalyticsLoadProfiler;
        $analyticsDebugEnabled = AnalyticsLoadProfiler::enabled() || (bool) config('app.debug');
        $indexLightFilters = (bool) config('analytics.index_light_filters', true);

        $yearOptions = $this->schoolYearOptionsFallback();
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
            $this->authorize('viewAnalytics', $city);

            try {
                $ieducarOptions = $profiler->measure('filter_options', function () use (
                    $filterOptionsService,
                    $city,
                    $filters,
                    $indexLightFilters,
                    $profiler,
                ) {
                    return $filterOptionsService->loadForAnalyticsIndex(
                        $city,
                        $filters,
                        $indexLightFilters,
                        $profiler,
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
                : $this->schoolYearOptionsFallback();
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
        $performanceData = AnalyticsEmptyPayloads::performance();
        $attendanceData = AnalyticsEmptyPayloads::attendance();
        $inclusionData = AnalyticsEmptyPayloads::inclusion();
        $networkData = AnalyticsEmptyPayloads::network();
        $fundebData = AnalyticsEmptyPayloads::fundeb();
        $otherFundingData = AnalyticsEmptyPayloads::otherFunding();
        $workDoneData = AnalyticsEmptyPayloads::workDone();
        $discrepanciesData = AnalyticsEmptyPayloads::discrepancies();
        $municipalityHealthData = AnalyticsEmptyPayloads::municipalityHealth();
        $municipalityContext = null;

        if ($yearFilterReady && $city !== null && $loadOverviewOnIndex) {
            try {
                $this->bindAnalyticsMetricsScope($city, $filters);
                $overviewData = $profiler->measure('overview', fn () => $this->safeAnalyticsLoad(
                    fn () => $overviewRepository->summary($city, $filters),
                    $overviewData,
                    __('Visão geral'),
                    $analyticsLoadWarnings,
                ));

                // Com lazy ativo, unidades (mapa/geo pesado) só na aba dedicada — evita 500/timeout no «Aplicar filtros».
                if (! $lazyTabLoading) {
                    $schoolUnitsData = $this->safeAnalyticsLoad(
                        fn () => $schoolUnitsRepository->snapshot($city, $filters),
                        $schoolUnitsData,
                        __('Unidades escolares'),
                        $analyticsLoadWarnings,
                    );
                }

                if (! $lazyTabLoading) {
                    $otherFundingData = $this->safeAnalyticsLoad(
                        fn () => $otherFundingRepository->buildReport($city, $filters),
                        $otherFundingData,
                        __('Financiamentos'),
                        $analyticsLoadWarnings,
                    );

                    $workDoneData = $this->safeAnalyticsLoad(
                        fn () => $workDoneRepository->buildReport($city, $filters),
                        $workDoneData,
                        __('Censo'),
                        $analyticsLoadWarnings,
                    );
                    $enrollmentData = $this->safeAnalyticsLoad(
                        fn () => $enrollmentRepository->sample($city, $filters),
                        $enrollmentData,
                        __('Matrículas'),
                        $analyticsLoadWarnings,
                    );
                    $performanceData = $this->safeAnalyticsLoad(
                        fn () => $performanceRepository->snapshot($city, $filters),
                        $performanceData,
                        __('Desempenho'),
                        $analyticsLoadWarnings,
                    );
                    $attendanceData = $this->safeAnalyticsLoad(
                        fn () => $attendanceRepository->snapshot($city, $filters),
                        $attendanceData,
                        __('Frequência'),
                        $analyticsLoadWarnings,
                    );
                    $inclusionData = $this->safeAnalyticsLoad(
                        fn () => $inclusionRepository->snapshot($city, $filters),
                        $inclusionData,
                        __('Inclusão'),
                        $analyticsLoadWarnings,
                    );
                    $networkData = $this->safeAnalyticsLoad(
                        fn () => $networkRepository->snapshot($city, $filters),
                        $networkData,
                        __('Rede'),
                        $analyticsLoadWarnings,
                    );
                    $discrepanciesData = $this->safeAnalyticsLoad(
                        fn () => $discrepanciesRepository->snapshot($city, $filters),
                        $discrepanciesData,
                        __('Discrepâncias'),
                        $analyticsLoadWarnings,
                    );
                    $fundebData = $this->safeAnalyticsLoad(
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
                    $municipalityHealthData = $this->safeAnalyticsLoad(
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
                        $fundingSnapshot = $this->safeAnalyticsLoad(
                            fn () => $discrepanciesRepository->fundingImpactSnapshot($city, $filters),
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

            return view('dashboard.analytics', $this->analyticsIndexViewData(
                $cities,
                $city,
                $filters,
                $yearOptions,
                $yearFilterReady,
                $ieducarOptions,
                $overviewData,
                $schoolUnitsData,
                $enrollmentData,
                $performanceData,
                $attendanceData,
                $inclusionData,
                $networkData,
                $fundebData,
                $otherFundingData,
                $workDoneData,
                $discrepanciesData,
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

            return view('dashboard.analytics', $this->analyticsIndexViewData(
                $cities,
                $city,
                $filters,
                $yearOptions,
                $yearFilterReady,
                $ieducarOptions,
                $overviewData,
                $schoolUnitsData,
                $enrollmentData,
                $performanceData,
                $attendanceData,
                $inclusionData,
                $networkData,
                $fundebData,
                $otherFundingData,
                $workDoneData,
                $discrepanciesData,
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
            ));
        }
    }

    /**
     * @param  list<string>  $analyticsLoadWarnings
     * @param  list<array{step: string, ms: float, meta?: array<string, mixed>}>  $analyticsDebugSteps
     * @return array<string, mixed>
     */
    private function analyticsIndexViewData(
        $cities,
        ?City $city,
        IeducarFilterState $filters,
        array $yearOptions,
        bool $yearFilterReady,
        array $ieducarOptions,
        array $overviewData,
        array $schoolUnitsData,
        array $enrollmentData,
        array $performanceData,
        array $attendanceData,
        array $inclusionData,
        array $networkData,
        array $fundebData,
        array $otherFundingData,
        array $workDoneData,
        array $discrepanciesData,
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
            'performanceData' => $performanceData,
            'attendanceData' => $attendanceData,
            'inclusionData' => $inclusionData,
            'networkData' => $networkData,
            'fundebData' => $fundebData,
            'otherFundingData' => $otherFundingData,
            'workDoneData' => $workDoneData,
            'discrepanciesData' => $discrepanciesData,
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
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $load
     * @param  T  $fallback
     * @param  list<string>  $warnings
     * @return T
     */
    private function safeAnalyticsLoad(callable $load, mixed $fallback, string $section, array &$warnings): mixed
    {
        try {
            return $load();
        } catch (Throwable $e) {
            Log::warning('analytics.section_load_failed', [
                'section' => $section,
                'message' => $e->getMessage(),
            ]);
            $warnings[] = __(':section: não foi possível carregar os dados (:msg).', [
                'section' => $section,
                'msg' => $e->getMessage(),
            ]);

            if (is_array($fallback)) {
                return array_merge($fallback, ['error' => $e->getMessage()]);
            }

            return $fallback;
        }
    }

    /**
     * HTML de uma aba pesada (carregamento lazy). Cada pedido aparece no Pulse como URL
     * distinta (`/dashboard/analytics/tab?tab=…`) para análise de tempo por aba.
     */
    public function tabPartial(
        Request $request,
        FilterOptionsService $filterOptionsService,
        OverviewRepository $overviewRepository,
        EnrollmentRepository $enrollmentRepository,
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
    ): Response {
        $tab = (string) $request->query('tab', '');
        if (! AnalyticsTabCatalog::isValidTab($tab)) {
            abort(404);
        }

        $city = $request->filled('city_id')
            ? UserCityAccess::citiesQuery($request->user())->whereKey($request->integer('city_id'))->first()
            : null;

        if ($city === null) {
            return response()
                ->view('dashboard.analytics.partials.tab-fetch-notice', [
                    'message' => __('Selecione uma cidade no painel acima.'),
                ])
                ->header('X-Analytics-Tab', $tab)
                ->header('X-Analytics-Tab-Status', 'no-city');
        }

        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);

        if ($tab === 'municipality_health' && $request->hasSession()) {
            $request->session()->save();
        }

        if (! $filters->hasYearSelected()) {
            return response()
                ->view('dashboard.analytics.partials.tab-fetch-notice', [
                    'message' => __('Aplique os filtros (ano letivo) no painel superior e confirme para carregar esta aba.'),
                ])
                ->header('X-Analytics-Tab', $tab)
                ->header('X-Analytics-Tab-Status', 'no-year');
        }

        $tabWarnings = [];
        $tabKey = PulseOperationRecorder::analyticsTabKey($tab, (int) $city->id);
        $healthSection = (string) $request->query('health_section', '');

        if ($tab === 'municipality_health' && $healthSection !== '') {
            if (! MunicipalityHealthSections::isValid($healthSection)) {
                abort(404);
            }

            return PulseOperationRecorder::measure(
                $tabKey.':section:'.$healthSection,
                fn (): Response => $this->renderMunicipalityHealthSection(
                    $healthSection,
                    $city,
                    $filters,
                    $municipalityHealthRepository,
                    $tabWarnings,
                ),
            );
        }

        try {
            $response = PulseOperationRecorder::measure($tabKey, fn (): Response => $this->renderAnalyticsTabPartial(
                $tab,
                $request,
                $city,
                $filters,
                $filterOptionsService,
                $overviewRepository,
                $enrollmentRepository,
                $performanceRepository,
                $attendanceRepository,
                $inclusionRepository,
                $networkRepository,
                $fundebRepository,
                $otherFundingRepository,
                $workDoneRepository,
                $discrepanciesRepository,
                $municipalityHealthRepository,
                $schoolUnitsRepository,
                $pdfExportService,
                $tabWarnings,
            ));

            if ($tabWarnings !== [] && $request->user() !== null) {
                app(NotificationDispatcher::class)->analyticsTabPartialWarnings(
                    $request->user(),
                    $city,
                    $tab,
                    $tabWarnings,
                );
            }

            return $response;
        } catch (Throwable $e) {
            PulseOperationRecorder::recordFailure($tabKey);
            Log::error('analytics.tab_partial_failed', [
                'tab' => $tab,
                'city_id' => $city->id,
                'message' => $e->getMessage(),
            ]);

            return response()
                ->view('dashboard.analytics.partials.tab-fetch-notice', [
                    'message' => __('Não foi possível carregar esta aba: :msg', ['msg' => $e->getMessage()]),
                ])
                ->header('X-Analytics-Tab', $tab)
                ->header('X-Analytics-Tab-Status', 'error');
        }
    }

    /**
     * @param  list<string>  $tabWarnings
     */
    private function renderAnalyticsTabPartial(
        string $tab,
        Request $request,
        City $city,
        IeducarFilterState $filters,
        FilterOptionsService $filterOptionsService,
        OverviewRepository $overviewRepository,
        EnrollmentRepository $enrollmentRepository,
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
        array &$tabWarnings,
    ): Response {
        try {
            $this->bindAnalyticsMetricsScope($city, $filters);

            return $this->renderAnalyticsTabPartialInner(
                $tab,
                $request,
                $city,
                $filters,
                $filterOptionsService,
                $overviewRepository,
                $enrollmentRepository,
                $performanceRepository,
                $attendanceRepository,
                $inclusionRepository,
                $networkRepository,
                $fundebRepository,
                $otherFundingRepository,
                $workDoneRepository,
                $discrepanciesRepository,
                $municipalityHealthRepository,
                $schoolUnitsRepository,
                $pdfExportService,
                $tabWarnings,
            );
        } finally {
            IeducarAnalyticsMetricsScope::forget();
        }
    }

    /**
     * @param  list<string>  $tabWarnings
     */
    private function renderAnalyticsTabPartialInner(
        string $tab,
        Request $request,
        City $city,
        IeducarFilterState $filters,
        FilterOptionsService $filterOptionsService,
        OverviewRepository $overviewRepository,
        EnrollmentRepository $enrollmentRepository,
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
        array &$tabWarnings,
    ): Response {
        $ieducarOptions = [
            'years' => [],
            'escolas' => [],
            'cursos' => [],
            'turnos' => [],
        ];
        if (! config('analytics.index_light_filters', true)) {
            try {
                $loaded = $filterOptionsService->loadAll($city, $filters);
                $ieducarOptions = array_merge($ieducarOptions, $loaded);
            } catch (Throwable $e) {
                Log::warning('analytics.tab_filter_options_failed', [
                    'tab' => $tab,
                    'city_id' => $city->id,
                    'message' => $e->getMessage(),
                ]);
                $tabWarnings[] = $e->getMessage();
            }
        }

        $chartExportContext = ChartExportMeta::forAnalytics($city, $filters, $ieducarOptions);
        $financePreload = $this->preloadFinanceTab(
            $tab,
            $city,
            $filters,
            $overviewRepository,
            $enrollmentRepository,
            $discrepanciesRepository,
            $fundebRepository,
            $municipalityHealthRepository,
            $otherFundingRepository,
            $workDoneRepository,
            $tabWarnings,
        );
        $healthDataForTab = $financePreload['healthData'];
        $discrepanciesDataForTab = $financePreload['discrepanciesData'];
        $fundebDataForTab = $financePreload['fundebData'];
        $otherFundingDataForTab = $financePreload['otherFundingData'];
        $workDoneDataForTab = $financePreload['workDoneData'];
        $municipalityContext = $financePreload['context'] ?? $this->resolveMunicipalityContextForTab(
            $tab,
            $city,
            $filters,
            $overviewRepository,
            $discrepanciesRepository,
            $tabWarnings,
        );

        $headers = [
            'X-Analytics-Tab' => $tab,
            'X-Analytics-Tab-Status' => 'ok',
        ];

        $yearReady = ['yearFilterReady' => true];
        $viewBase = [
            'chartExportContext' => $chartExportContext,
            'municipalityContext' => $municipalityContext,
            'selectedCity' => $city,
            'filters' => $filters,
        ];

        return match ($tab) {
            'overview' => response()
                ->view('dashboard.analytics.partials.overview', array_merge($viewBase, $yearReady, [
                    'overviewData' => $this->safeAnalyticsLoad(
                        fn () => $overviewRepository->summary($city, $filters),
                        ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null],
                        __('Visão geral'),
                        $tabWarnings,
                    ),
                    'schoolUnits' => null,
                ]))
                ->withHeaders($headers),
            'school_units' => response()
                ->view('dashboard.analytics.partials.school-units', array_merge($viewBase, $yearReady, [
                    'schoolUnitsData' => $this->safeAnalyticsLoad(
                        fn () => $schoolUnitsRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::schoolUnits(),
                        __('Unidades escolares'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'enrollment' => response()
                ->view('dashboard.analytics.partials.enrollment', array_merge($viewBase, $yearReady, [
                    'enrollmentData' => $this->safeAnalyticsLoad(
                        fn () => $enrollmentRepository->sample($city, $filters),
                        AnalyticsEmptyPayloads::enrollment(),
                        __('Matrículas'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'network' => response()
                ->view('dashboard.analytics.partials.network', array_merge($viewBase, $yearReady, [
                    'networkData' => $this->safeAnalyticsLoad(
                        fn () => $networkRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::network(),
                        __('Rede'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'inclusion' => response()
                ->view('dashboard.analytics.partials.inclusion', array_merge($viewBase, $yearReady, [
                    'inclusionData' => $this->safeAnalyticsLoad(
                        function () use ($inclusionRepository, $city, $filters): array {
                            $data = $inclusionRepository->snapshot($city, $filters);
                            AnalyticsTabPayloadCache::put(AnalyticsTabPayloadCache::INCLUSION, $city, $filters, $data);

                            return $data;
                        },
                        AnalyticsEmptyPayloads::inclusion(),
                        __('Inclusão'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'performance' => response()
                ->view('dashboard.analytics.partials.performance', array_merge($viewBase, $yearReady, [
                    'performanceData' => $this->safeAnalyticsLoad(
                        fn () => $performanceRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::performance(),
                        __('Desempenho'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'attendance' => response()
                ->view('dashboard.analytics.partials.attendance', array_merge($viewBase, $yearReady, [
                    'attendanceData' => $this->safeAnalyticsLoad(
                        fn () => $attendanceRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::attendance(),
                        __('Frequência'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'fundeb' => response()
                ->view('dashboard.analytics.partials.fundeb', array_merge($viewBase, $yearReady, [
                    'fundebData' => $fundebDataForTab ?? $this->safeAnalyticsLoad(
                        fn () => $this->buildFundebReportForTab(
                            $fundebRepository,
                            $overviewRepository,
                            $discrepanciesRepository,
                            $enrollmentRepository,
                            $city,
                            $filters,
                        ),
                        AnalyticsEmptyPayloads::fundeb(),
                        __('FUNDEB'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'other_funding' => response()
                ->view('dashboard.analytics.partials.other-funding', array_merge($viewBase, $yearReady, [
                    'otherFundingData' => $otherFundingDataForTab ?? $this->safeAnalyticsLoad(
                        fn () => $otherFundingRepository->buildReport($city, $filters),
                        AnalyticsEmptyPayloads::otherFunding(),
                        __('Financiamentos'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'work_done' => response()
                ->view('dashboard.analytics.partials.work-done', array_merge($viewBase, $yearReady, [
                    'workDoneData' => $workDoneDataForTab ?? $this->safeAnalyticsLoad(
                        fn () => $workDoneRepository->buildReport($city, $filters),
                        AnalyticsEmptyPayloads::workDone(),
                        __('Censo'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'municipality_health' => response()
                ->view('dashboard.analytics.partials.municipality-health', array_merge($viewBase, $yearReady, [
                    'healthData' => $healthDataForTab ?? $this->safeAnalyticsLoad(
                        fn () => $municipalityHealthRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::municipalityHealth(),
                        __('Diagnóstico'),
                        $tabWarnings,
                    ),
                    'selectedCity' => $city,
                    'filters' => $filters,
                    'pdfExportsRecent' => $request->user()->canExportAnalyticsPdf()
                        ? $pdfExportService->recentForUserCity($request->user(), $city, 6)
                        : [],
                ]))
                ->withHeaders($headers),
            'discrepancies' => response()
                ->view('dashboard.analytics.partials.discrepancies', array_merge($viewBase, $yearReady, [
                    'discrepanciesData' => $discrepanciesDataForTab ?? $this->safeAnalyticsLoad(
                        fn () => $discrepanciesRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::discrepancies(),
                        __('Discrepâncias'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            default => abort(404),
        };
    }

    /**
     * FUNDEB em lazy: visão geral + KPIs de matrículas (mesma base da aba Matrículas) + resumo Discrepâncias.
     *
     * @return array<string, mixed>
     */
    private function buildFundebReportForTab(
        FundebRepository $fundebRepository,
        OverviewRepository $overviewRepository,
        DiscrepanciesRepository $discrepanciesRepository,
        EnrollmentRepository $enrollmentRepository,
        City $city,
        IeducarFilterState $filters,
    ): array {
        return $this->buildFundebTabBundle(
            $fundebRepository,
            $overviewRepository,
            $discrepanciesRepository,
            $enrollmentRepository,
            $city,
            $filters,
        )['fundeb'];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{
     *   context: ?array<string, mixed>,
     *   healthData: ?array<string, mixed>,
     *   discrepanciesData: ?array<string, mixed>,
     *   fundebData: ?array<string, mixed>,
     *   otherFundingData: ?array<string, mixed>,
     *   workDoneData: ?array<string, mixed>
     * }
     */
    private function preloadFinanceTab(
        string $tab,
        City $city,
        IeducarFilterState $filters,
        OverviewRepository $overviewRepository,
        EnrollmentRepository $enrollmentRepository,
        DiscrepanciesRepository $discrepanciesRepository,
        FundebRepository $fundebRepository,
        MunicipalityHealthRepository $municipalityHealthRepository,
        OtherFundingRepository $otherFundingRepository,
        WorkDoneRepository $workDoneRepository,
        array &$warnings,
    ): array {
        $empty = [
            'context' => null,
            'healthData' => null,
            'discrepanciesData' => null,
            'fundebData' => null,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];

        if (! AnalyticsFinanceTabPreload::shouldReuseFundingContext($tab)) {
            return $empty;
        }

        return match ($tab) {
            'municipality_health' => $this->preloadMunicipalityHealthTab($municipalityHealthRepository, $city, $filters, $warnings),
            'discrepancies' => $this->preloadDiscrepanciesTab($discrepanciesRepository, $city, $filters, $warnings),
            'fundeb' => $this->preloadFundebTab(
                $fundebRepository,
                $overviewRepository,
                $discrepanciesRepository,
                $enrollmentRepository,
                $city,
                $filters,
                $warnings,
            ),
            'other_funding' => $this->preloadFinanceStripTab(
                'other_funding',
                $city,
                $filters,
                $discrepanciesRepository,
                $warnings,
                fn () => $otherFundingRepository->buildReport($city, $filters),
                AnalyticsEmptyPayloads::otherFunding(),
                __('Financiamentos'),
            ),
            'work_done' => $this->preloadFinanceStripTab(
                'work_done',
                $city,
                $filters,
                $discrepanciesRepository,
                $warnings,
                fn () => $workDoneRepository->buildReport($city, $filters),
                AnalyticsEmptyPayloads::workDone(),
                __('Censo'),
            ),
            default => $empty,
        };
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: ?array, discrepanciesData: null, fundebData: null, otherFundingData: null, workDoneData: null}
     */
    private function preloadMunicipalityHealthTab(
        MunicipalityHealthRepository $municipalityHealthRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $healthData = $this->safeAnalyticsLoad(
            fn () => $municipalityHealthRepository->snapshot($city, $filters),
            AnalyticsEmptyPayloads::municipalityHealth(),
            __('Diagnóstico'),
            $warnings,
        );

        return [
            'context' => AnalyticsMunicipalityContext::fromHealthSnapshot(is_array($healthData) ? $healthData : []),
            'healthData' => is_array($healthData) ? $healthData : null,
            'discrepanciesData' => null,
            'fundebData' => null,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: ?array, fundebData: null, otherFundingData: null, workDoneData: null}
     */
    private function preloadDiscrepanciesTab(
        DiscrepanciesRepository $discrepanciesRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $discrepanciesData = $this->safeAnalyticsLoad(
            fn () => $discrepanciesRepository->snapshot($city, $filters),
            AnalyticsEmptyPayloads::discrepancies(),
            __('Discrepâncias'),
            $warnings,
        );
        if (is_array($discrepanciesData)) {
            AnalyticsTabPayloadCache::put(AnalyticsTabPayloadCache::DISCREPANCIES, $city, $filters, $discrepanciesData);
        }

        return [
            'context' => is_array($discrepanciesData)
                ? AnalyticsFinanceTabPreload::contextFromDiscrepancies($discrepanciesData)
                : null,
            'healthData' => null,
            'discrepanciesData' => is_array($discrepanciesData) ? $discrepanciesData : null,
            'fundebData' => null,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: null, fundebData: ?array, otherFundingData: null, workDoneData: null}
     */
    private function preloadFundebTab(
        FundebRepository $fundebRepository,
        OverviewRepository $overviewRepository,
        DiscrepanciesRepository $discrepanciesRepository,
        EnrollmentRepository $enrollmentRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $bundle = $this->safeAnalyticsLoad(
            fn () => $this->buildFundebTabBundle(
                $fundebRepository,
                $overviewRepository,
                $discrepanciesRepository,
                $enrollmentRepository,
                $city,
                $filters,
            ),
            ['fundeb' => AnalyticsEmptyPayloads::fundeb(), 'context' => null],
            __('FUNDEB'),
            $warnings,
        );

        $fundebData = is_array($bundle['fundeb'] ?? null) ? $bundle['fundeb'] : null;
        if (is_array($fundebData)) {
            AnalyticsTabPayloadCache::put(AnalyticsTabPayloadCache::FUNDEB, $city, $filters, $fundebData);
        }

        return [
            'context' => is_array($bundle['context'] ?? null) ? $bundle['context'] : null,
            'healthData' => null,
            'discrepanciesData' => null,
            'fundebData' => $fundebData,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $loadTab
     * @param  array<string, mixed>  $emptyTab
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: null, fundebData: null, otherFundingData: ?array, workDoneData: ?array}
     */
    private function preloadFinanceStripTab(
        string $tab,
        City $city,
        IeducarFilterState $filters,
        DiscrepanciesRepository $discrepanciesRepository,
        array &$warnings,
        callable $loadTab,
        array $emptyTab,
        string $label,
    ): array {
        $tabData = $this->safeAnalyticsLoad($loadTab, $emptyTab, $label, $warnings);
        if (is_array($tabData)) {
            $cacheTab = match ($tab) {
                'other_funding' => AnalyticsTabPayloadCache::OTHER_FUNDING,
                'work_done' => AnalyticsTabPayloadCache::WORK_DONE,
                default => null,
            };
            if ($cacheTab !== null) {
                AnalyticsTabPayloadCache::put($cacheTab, $city, $filters, $tabData);
            }
        }
        $fundingSnapshot = $this->safeAnalyticsLoad(
            fn () => $discrepanciesRepository->fundingImpactSnapshot($city, $filters),
            null,
            __('Resumo financeiro'),
            $warnings,
        );
        if (! is_array($fundingSnapshot)) {
            $fundingSnapshot = [
                'summary' => [],
                'funding_reference' => DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters),
            ];
        } elseif (! is_array($fundingSnapshot['funding_reference'] ?? null)) {
            $fundingSnapshot['funding_reference'] = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
        }

        $totalMat = is_array($tabData) ? ($tabData['total_matriculas'] ?? null) : null;
        $overviewData = [
            'kpis' => ['matriculas' => $totalMat],
            'total_matriculas' => $totalMat,
        ];

        return [
            'context' => AnalyticsFinanceTabPreload::contextFromFundingSnapshot($fundingSnapshot, $overviewData),
            'healthData' => null,
            'discrepanciesData' => null,
            'fundebData' => null,
            'otherFundingData' => $tab === 'other_funding' && is_array($tabData) ? $tabData : null,
            'workDoneData' => $tab === 'work_done' && is_array($tabData) ? $tabData : null,
        ];
    }

    /**
     * @return array{fundeb: array<string, mixed>, context: ?array<string, mixed>}
     */
    private function buildFundebTabBundle(
        FundebRepository $fundebRepository,
        OverviewRepository $overviewRepository,
        DiscrepanciesRepository $discrepanciesRepository,
        EnrollmentRepository $enrollmentRepository,
        City $city,
        IeducarFilterState $filters,
    ): array {
        $overviewData = $overviewRepository->summary($city, $filters);
        $enrollmentData = $enrollmentRepository->sample($city, $filters);

        $fundingSnapshot = null;
        if (config('analytics.fundeb_load_discrepancies_summary', true)) {
            $fundingSnapshot = $discrepanciesRepository->fundingImpactSnapshot($city, $filters);
        }
        if (! is_array($fundingSnapshot)) {
            $fundingSnapshot = [
                'summary' => [],
                'funding_reference' => DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters),
            ];
        } elseif (! is_array($fundingSnapshot['funding_reference'] ?? null)) {
            $fundingSnapshot['funding_reference'] = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
        }

        $fundeb = $fundebRepository->buildReport(
            $city,
            $filters,
            $overviewData,
            $enrollmentData,
            AnalyticsEmptyPayloads::performance(),
            AnalyticsEmptyPayloads::attendance(),
            AnalyticsEmptyPayloads::inclusion(),
            AnalyticsEmptyPayloads::network(),
            $fundingSnapshot,
        );

        $context = AnalyticsFinanceTabPreload::financeTabsReuseEnabled()
            ? AnalyticsFinanceTabPreload::contextFromFundingSnapshot($fundingSnapshot, $overviewData)
            : null;

        return ['fundeb' => $fundeb, 'context' => $context];
    }

    /**
     * Fragmento HTML de uma secção diferida do Diagnóstico (AJAX progressivo).
     *
     * @param  list<string>  $warnings
     */
    private function renderMunicipalityHealthSection(
        string $section,
        City $city,
        IeducarFilterState $filters,
        MunicipalityHealthRepository $municipalityHealthRepository,
        array &$warnings,
    ): Response {
        $sectionData = $this->safeAnalyticsLoad(
            fn () => $municipalityHealthRepository->section($section, $city, $filters),
            ['error' => __('Secção indisponível.')],
            match ($section) {
                MunicipalityHealthSections::FUNDEB => __('VAAF e FUNDEB'),
                MunicipalityHealthSections::PROGRAMAS => __('Programas'),
                MunicipalityHealthSections::TEMATICO => __('Leitura temática'),
                default => $section,
            },
            $warnings,
        );

        $healthData = is_array($sectionData) ? $sectionData : [];
        $diagStep = ConsultoriaFlow::stepMap(ConsultoriaFlow::numberedSteps([
            ['label' => __('VAAF'), 'anchor' => 'diag-vaaf', 'visible' => $section === MunicipalityHealthSections::FUNDEB],
            ['label' => __('Programas'), 'anchor' => 'diag-programas', 'visible' => $section === MunicipalityHealthSections::PROGRAMAS],
            ['label' => __('Temático'), 'anchor' => 'diag-tematico', 'visible' => $section === MunicipalityHealthSections::TEMATICO],
            ['label' => __('Roteiro'), 'anchor' => 'diag-roteiro', 'visible' => $section === MunicipalityHealthSections::FUNDEB],
        ]));

        $view = match ($section) {
            MunicipalityHealthSections::FUNDEB => 'dashboard.analytics.partials.municipality-health-section-fundeb',
            MunicipalityHealthSections::PROGRAMAS => 'dashboard.analytics.partials.municipality-health-section-programas',
            MunicipalityHealthSections::TEMATICO => 'dashboard.analytics.partials.municipality-health-section-tematico',
            default => abort(404),
        };

        $status = isset($healthData['error']) && filled($healthData['error']) ? 'partial-error' : 'ok';

        return response()
            ->view($view, [
                'healthData' => $healthData,
                'diagStep' => $diagStep,
            ])
            ->header('X-Analytics-Tab', 'municipality_health')
            ->header('X-Analytics-Health-Section', $section)
            ->header('X-Analytics-Tab-Status', $status);
    }

    /**
     * Contexto de saldo indicativo só nas abas financeiras (evita Discrepâncias em cada lazy-load).
     *
     * @param  list<string>  $warnings
     * @return array<string, mixed>|null
     */
    private function resolveMunicipalityContextForTab(
        string $tab,
        City $city,
        IeducarFilterState $filters,
        OverviewRepository $overviewRepository,
        DiscrepanciesRepository $discrepanciesRepository,
        array &$warnings,
    ): ?array {
        $tabsWithFunding = [
            'enrollment',
            'network',
            'school_units',
            'inclusion',
            'performance',
            'attendance',
            'fundeb',
            'discrepancies',
        ];
        if (! in_array($tab, $tabsWithFunding, true)) {
            return null;
        }

        if (AnalyticsFinanceTabPreload::shouldReuseFundingContext($tab)) {
            return null;
        }

        $overviewData = $this->safeAnalyticsLoad(
            fn () => $overviewRepository->summary($city, $filters),
            ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null],
            __('Visão geral'),
            $warnings,
        );
        $fundingSnapshot = $this->safeAnalyticsLoad(
            fn () => $discrepanciesRepository->fundingImpactSnapshot($city, $filters),
            null,
            __('Resumo financeiro'),
            $warnings,
        );

        if (! is_array($fundingSnapshot)) {
            $fundingSnapshot = [
                'summary' => [],
                'funding_reference' => DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters),
            ];
        } elseif (! is_array($fundingSnapshot['funding_reference'] ?? null)) {
            $fundingSnapshot['funding_reference'] = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
        }

        return AnalyticsMunicipalityContext::fromFundingSnapshot(
            $fundingSnapshot,
            is_array($overviewData) ? $overviewData : [],
        );
    }

    /**
     * Opções para selects em cascata (AJAX).
     */
    public function filterOptions(Request $request, FilterOptionsService $filterOptionsService): JsonResponse
    {
        $request->validate([
            'city_id' => ['required', 'integer'],
            'kind' => ['required', 'string', 'max:32'],
            'ano_letivo' => ['nullable', 'string', 'max:32'],
        ]);

        $city = UserCityAccess::citiesQuery($request->user())->whereKey($request->integer('city_id'))->firstOrFail();

        $this->authorize('viewAnalytics', $city);

        $rawAno = $request->input('ano_letivo');
        $anoFiltro = null;
        if (is_string($rawAno) && $rawAno !== '' && $rawAno !== 'all' && ctype_digit($rawAno)) {
            $anoFiltro = (int) $rawAno;
        }

        $data = $filterOptionsService->loadByKind($city, $request->string('kind')->toString(), $anoFiltro);

        return response()->json(['data' => $data]);
    }

    /**
     * Anos letivos (AJAX) — quando o index não trouxe anos numéricos ou a conexão falhou no SSR.
     */
    public function filterOptionsYears(Request $request, FilterOptionsService $filterOptionsService): JsonResponse
    {
        $request->validate([
            'city_id' => ['required', 'integer'],
        ]);

        $city = UserCityAccess::citiesQuery($request->user())->whereKey($request->integer('city_id'))->firstOrFail();
        $this->authorize('viewAnalytics', $city);

        try {
            return response()->json($filterOptionsService->loadYearOptions($city));
        } catch (Throwable $e) {
            Log::warning('analytics.filter_years_failed', [
                'city_id' => $city->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'years' => $this->schoolYearOptionsFallback(),
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Escolas, cursos e turnos após o index (modo ANALYTICS_INDEX_LIGHT_FILTERS).
     */
    public function filterOptionsBootstrap(Request $request, FilterOptionsService $filterOptionsService): JsonResponse
    {
        $request->validate([
            'city_id' => ['required', 'integer'],
            'ano_letivo' => ['nullable', 'string', 'max:32'],
        ]);

        $city = UserCityAccess::citiesQuery($request->user())->whereKey($request->integer('city_id'))->firstOrFail();
        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);
        $profiler = new AnalyticsLoadProfiler;

        try {
            $payload = $profiler->measure('bootstrap', fn () => $filterOptionsService->loadBootstrap(
                $city,
                $filters,
                $profiler,
            ));
            $profiler->flush('filter_bootstrap', [
                'city_id' => $city->id,
                'ano_letivo' => $filters->ano_letivo,
            ]);

            return response()
                ->json($payload)
                ->header('X-Analytics-Bootstrap-Ms', (string) $profiler->totalMs());
        } catch (Throwable $e) {
            Log::warning('analytics.filter_bootstrap_failed', [
                'city_id' => $city->id,
                'message' => $e->getMessage(),
            ]);
            $profiler->flush('filter_bootstrap_failed', ['error' => $e->getMessage()]);

            return response()->json([
                'years' => $this->schoolYearOptionsFallback(),
                'escolas' => [],
                'cursos' => [],
                'turnos' => [],
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * @return array<string, string>
     */
    private function schoolYearOptionsFallback(): array
    {
        return [
            '' => __('— Selecione o ano letivo —'),
            'all' => __('Todos os anos'),
        ];
    }

    private function bindAnalyticsMetricsScope(City $city, IeducarFilterState $filters): void
    {
        IeducarAnalyticsMetricsScope::bindForRequest(
            app(CityDataConnection::class),
            $city,
            $filters,
            warm: true,
        );
    }
}
