<?php

namespace App\Http\Controllers;

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
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
use App\Support\Dashboard\AnalyticsMunicipalityContext;
use App\Support\Dashboard\AnalyticsTabCatalog;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
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

        if ($city) {
            $this->authorize('viewAnalytics', $city);

            $ieducarOptions = $filterOptionsService->loadAll($city, $filters);
            if (! empty($ieducarOptions['years'])) {
                $yearOptions = $ieducarOptions['years'];
            }
        }

        $yearFilterReady = $city !== null && $filters->hasYearSelected();
        $lazyTabLoading = (bool) config('analytics.lazy_tab_loading', true);
        $analyticsLoadWarnings = [];

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

        if ($yearFilterReady && $city !== null) {
            $overviewData = $this->safeAnalyticsLoad(
                fn () => $overviewRepository->summary($city, $filters),
                $overviewData,
                __('Visão geral'),
                $analyticsLoadWarnings,
            );

            $schoolUnitsData = $this->safeAnalyticsLoad(
                fn () => $schoolUnitsRepository->snapshot($city, $filters),
                $schoolUnitsData,
                __('Unidades escolares'),
                $analyticsLoadWarnings,
            );

            // Abas leves: sempre no HTML inicial (evita painel em branco com lazy + JS desatualizado).
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

            if (! $lazyTabLoading) {
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

            $fundingSnapshot = $this->safeAnalyticsLoad(
                fn () => $discrepanciesRepository->fundingImpactSnapshot($city, $filters),
                null,
                __('Resumo financeiro'),
                $analyticsLoadWarnings,
            );
            $municipalityContext = AnalyticsMunicipalityContext::fromFundingSnapshot(
                is_array($fundingSnapshot) ? $fundingSnapshot : null,
                $overviewData,
            );
        }

        $chartExportContext = ChartExportMeta::forAnalytics($city, $filters, $ieducarOptions);

        $tabs = AnalyticsTabCatalog::tabsOrdered();
        $tabKeys = AnalyticsTabCatalog::tabKeys();
        $qTab = (string) $request->query('tab', '');
        $analyticsInitialTab = AnalyticsTabCatalog::resolveInitialTab(
            $qTab,
            $user,
            $yearFilterReady,
        );

        $pdfExportsRecent = ($city !== null && $user !== null && $user->canExportAnalyticsPdf())
            ? $pdfExportService->recentForUserCity($user, $city, 6)
            : [];

        return view('dashboard.analytics', [
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
            'fundingLossModalData' => DiscrepanciesCheckCatalog::modalPayload(),
            'chartExportContext' => $chartExportContext,
            'tabs' => $tabs,
            'tabGroups' => AnalyticsTabCatalog::groups(),
            'analyticsInitialTab' => $analyticsInitialTab,
            'lazyTabLoading' => $lazyTabLoading,
            'pdfExportsRecent' => $pdfExportsRecent,
            'municipalityContext' => $municipalityContext,
            'analyticsLoadWarnings' => $analyticsLoadWarnings,
        ]);
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
        AnalyticsReportExportService $pdfExportService,
    ): Response {
        $tab = (string) $request->query('tab', '');
        $allowed = array_values(array_filter(
            AnalyticsTabCatalog::tabKeys(),
            static fn (string $k): bool => $k !== 'overview' && $k !== 'school_units',
        ));
        if (! AnalyticsTabCatalog::isValidTab($tab) || ! in_array($tab, $allowed, true)) {
            abort(404);
        }

        $city = $request->filled('city_id')
            ? UserCityAccess::citiesQuery($request->user())->whereKey($request->integer('city_id'))->first()
            : null;

        if ($city === null) {
            return response()
                ->view('dashboard.analytics.partials.tab-fetch-notice', [
                    'message' => __('Seleccione uma cidade no painel acima.'),
                ])
                ->header('X-Analytics-Tab', $tab)
                ->header('X-Analytics-Tab-Status', 'no-city');
        }

        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);

        if (! $filters->hasYearSelected()) {
            return response()
                ->view('dashboard.analytics.partials.tab-fetch-notice', [
                    'message' => __('Aplique os filtros (ano letivo) no painel superior e confirme para carregar esta aba.'),
                ])
                ->header('X-Analytics-Tab', $tab)
                ->header('X-Analytics-Tab-Status', 'no-year');
        }

        $ieducarOptions = $filterOptionsService->loadAll($city, $filters);
        $chartExportContext = ChartExportMeta::forAnalytics($city, $filters, $ieducarOptions);
        $overviewData = $overviewRepository->summary($city, $filters);
        $municipalityContext = AnalyticsMunicipalityContext::fromFundingSnapshot(
            $discrepanciesRepository->fundingImpactSnapshot($city, $filters),
            $overviewData,
        );

        $headers = [
            'X-Analytics-Tab' => $tab,
            'X-Analytics-Tab-Status' => 'ok',
        ];

        return match ($tab) {
            'enrollment' => response()
                ->view('dashboard.analytics.partials.enrollment', [
                    'enrollmentData' => $enrollmentRepository->sample($city, $filters),
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                    'yearFilterReady' => true,
                ])
                ->withHeaders($headers),
            'network' => response()
                ->view('dashboard.analytics.partials.network', [
                    'networkData' => $networkRepository->snapshot($city, $filters),
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                    'yearFilterReady' => true,
                ])
                ->withHeaders($headers),
            'inclusion' => response()
                ->view('dashboard.analytics.partials.inclusion', [
                    'inclusionData' => $inclusionRepository->snapshot($city, $filters),
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                    'yearFilterReady' => true,
                ])
                ->withHeaders($headers),
            'performance' => response()
                ->view('dashboard.analytics.partials.performance', [
                    'performanceData' => $performanceRepository->snapshot($city, $filters),
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                    'yearFilterReady' => true,
                ])
                ->withHeaders($headers),
            'attendance' => response()
                ->view('dashboard.analytics.partials.attendance', [
                    'attendanceData' => $attendanceRepository->snapshot($city, $filters),
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                    'yearFilterReady' => true,
                ])
                ->withHeaders($headers),
            'fundeb' => response()
                ->view('dashboard.analytics.partials.fundeb', [
                    'fundebData' => $fundebRepository->buildReport(
                        $city,
                        $filters,
                        $overviewData,
                        $enrollmentRepository->sample($city, $filters),
                        $performanceRepository->snapshot($city, $filters),
                        $attendanceRepository->snapshot($city, $filters),
                        $inclusionRepository->snapshot($city, $filters),
                        $networkRepository->snapshot($city, $filters),
                        $discrepanciesRepository->fundingImpactSnapshot($city, $filters),
                    ),
                    'yearFilterReady' => true,
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                ])
                ->withHeaders($headers),
            'other_funding' => response()
                ->view('dashboard.analytics.partials.other-funding', [
                    'otherFundingData' => $otherFundingRepository->buildReport($city, $filters),
                    'yearFilterReady' => true,
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                ])
                ->withHeaders($headers),
            'work_done' => response()
                ->view('dashboard.analytics.partials.work-done', [
                    'workDoneData' => $workDoneRepository->buildReport($city, $filters),
                    'yearFilterReady' => true,
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                ])
                ->withHeaders($headers),
            'municipality_health' => response()
                ->view('dashboard.analytics.partials.municipality-health', [
                    'healthData' => $municipalityHealthRepository->snapshot($city, $filters),
                    'yearFilterReady' => true,
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                    'selectedCity' => $city,
                    'filters' => $filters,
                    'pdfExportsRecent' => $request->user()->canExportAnalyticsPdf()
                        ? $pdfExportService->recentForUserCity($request->user(), $city, 6)
                        : [],
                ])
                ->withHeaders($headers),
            'discrepancies' => response()
                ->view('dashboard.analytics.partials.discrepancies', [
                    'discrepanciesData' => $discrepanciesRepository->snapshot($city, $filters),
                    'yearFilterReady' => true,
                    'chartExportContext' => $chartExportContext,
                    'municipalityContext' => $municipalityContext,
                ])
                ->withHeaders($headers),
            default => abort(404),
        };
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
     * @return array<string, string>
     */
    private function schoolYearOptionsFallback(): array
    {
        return [
            '' => __('— Selecione o ano letivo —'),
            'all' => __('Todos os anos'),
        ];
    }
}
