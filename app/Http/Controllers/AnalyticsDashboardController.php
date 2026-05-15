<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Support\Auth\UserCityAccess;
use App\Repositories\Ieducar\AttendanceRepository;
use App\Repositories\Ieducar\EnrollmentRepository;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Repositories\Ieducar\FundebRepository;
use App\Repositories\Ieducar\MunicipalityHealthRepository;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use App\Repositories\Ieducar\InclusionRepository;
use App\Repositories\Ieducar\NetworkRepository;
use App\Repositories\Ieducar\OverviewRepository;
use App\Repositories\Ieducar\PerformanceRepository;
use App\Repositories\Ieducar\SchoolUnitsRepository;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

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
        DiscrepanciesRepository $discrepanciesRepository,
        MunicipalityHealthRepository $municipalityHealthRepository,
        SchoolUnitsRepository $schoolUnitsRepository,
    ): View {
        $cities = UserCityAccess::citiesQuery($request->user())->get();

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

        $overviewData = $yearFilterReady
            ? $overviewRepository->summary($city, $filters)
            : ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null];

        $schoolUnitsData = $yearFilterReady && $city !== null
            ? $schoolUnitsRepository->snapshot($city, $filters)
            : [
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

        if ($yearFilterReady && $city !== null && ! $lazyTabLoading) {
            $enrollmentData = $enrollmentRepository->sample($city, $filters);
            $performanceData = $performanceRepository->snapshot($city, $filters);
            $attendanceData = $attendanceRepository->snapshot($city, $filters);
            $inclusionData = $inclusionRepository->snapshot($city, $filters);
            $networkData = $networkRepository->snapshot($city, $filters);
            $discrepanciesData = $discrepanciesRepository->snapshot($city, $filters);
            $fundebData = $fundebRepository->buildReport(
                $city,
                $filters,
                $overviewData,
                $enrollmentData,
                $performanceData,
                $attendanceData,
                $inclusionData,
                $networkData,
                $discrepanciesData,
            );
            $municipalityHealthData = $municipalityHealthRepository->snapshot($city, $filters);
        } else {
            $enrollmentData = AnalyticsEmptyPayloads::enrollment();
            $performanceData = AnalyticsEmptyPayloads::performance();
            $attendanceData = AnalyticsEmptyPayloads::attendance();
            $inclusionData = AnalyticsEmptyPayloads::inclusion();
            $networkData = AnalyticsEmptyPayloads::network();
            $fundebData = AnalyticsEmptyPayloads::fundeb();
            $discrepanciesData = AnalyticsEmptyPayloads::discrepancies();
            $municipalityHealthData = AnalyticsEmptyPayloads::municipalityHealth();
        }

        $chartExportContext = ChartExportMeta::forAnalytics($city, $filters, $ieducarOptions);

        $tabs = [
            'overview' => __('Visão Geral'),
            'enrollment' => __('Matrículas'),
            'network' => __('Rede & Oferta'),
            'school_units' => __('Unidades Escolares'),
            'inclusion' => __('Inclusão & Diversidade'),
            'performance' => __('Desempenho'),
            'attendance' => __('Frequência'),
            'fundeb' => __('FUNDEB'),
            'discrepancies' => __('Discrepâncias e Erros'),
            'municipality_health' => __('Diagnóstico Geral'),
        ];
        $tabKeys = array_keys($tabs);
        $qTab = (string) $request->query('tab', '');
        $analyticsInitialTab = in_array($qTab, $tabKeys, true) ? $qTab : 'overview';

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
            'discrepanciesData' => $discrepanciesData,
            'municipalityHealthData' => $municipalityHealthData,
            'fundingLossModalData' => DiscrepanciesCheckCatalog::modalPayload(),
            'chartExportContext' => $chartExportContext,
            'tabs' => $tabs,
            'analyticsInitialTab' => $analyticsInitialTab,
            'lazyTabLoading' => $lazyTabLoading,
        ]);
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
        DiscrepanciesRepository $discrepanciesRepository,
        MunicipalityHealthRepository $municipalityHealthRepository,
    ): Response {
        $tab = (string) $request->query('tab', '');
        $allowed = ['enrollment', 'network', 'inclusion', 'performance', 'attendance', 'fundeb', 'discrepancies', 'municipality_health'];
        if (! in_array($tab, $allowed, true)) {
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

        $headers = [
            'X-Analytics-Tab' => $tab,
            'X-Analytics-Tab-Status' => 'ok',
        ];

        return match ($tab) {
            'enrollment' => response()
                ->view('dashboard.analytics.partials.enrollment', [
                    'enrollmentData' => $enrollmentRepository->sample($city, $filters),
                    'chartExportContext' => $chartExportContext,
                ])
                ->withHeaders($headers),
            'network' => response()
                ->view('dashboard.analytics.partials.network', [
                    'networkData' => $networkRepository->snapshot($city, $filters),
                    'chartExportContext' => $chartExportContext,
                ])
                ->withHeaders($headers),
            'inclusion' => response()
                ->view('dashboard.analytics.partials.inclusion', [
                    'inclusionData' => $inclusionRepository->snapshot($city, $filters),
                    'chartExportContext' => $chartExportContext,
                ])
                ->withHeaders($headers),
            'performance' => response()
                ->view('dashboard.analytics.partials.performance', [
                    'performanceData' => $performanceRepository->snapshot($city, $filters),
                    'chartExportContext' => $chartExportContext,
                ])
                ->withHeaders($headers),
            'attendance' => response()
                ->view('dashboard.analytics.partials.attendance', [
                    'attendanceData' => $attendanceRepository->snapshot($city, $filters),
                    'chartExportContext' => $chartExportContext,
                ])
                ->withHeaders($headers),
            'fundeb' => response()
                ->view('dashboard.analytics.partials.fundeb', [
                    'fundebData' => $fundebRepository->buildReport(
                        $city,
                        $filters,
                        $overviewRepository->summary($city, $filters),
                        $enrollmentRepository->sample($city, $filters),
                        $performanceRepository->snapshot($city, $filters),
                        $attendanceRepository->snapshot($city, $filters),
                        $inclusionRepository->snapshot($city, $filters),
                        $networkRepository->snapshot($city, $filters),
                        $discrepanciesRepository->snapshot($city, $filters),
                    ),
                    'yearFilterReady' => true,
                    'chartExportContext' => $chartExportContext,
                ])
                ->withHeaders($headers),
            'municipality_health' => response()
                ->view('dashboard.analytics.partials.municipality-health', [
                    'healthData' => $municipalityHealthRepository->snapshot($city, $filters),
                    'yearFilterReady' => true,
                    'chartExportContext' => $chartExportContext,
                ])
                ->withHeaders($headers),
            'discrepancies' => response()
                ->view('dashboard.analytics.partials.discrepancies', [
                    'discrepanciesData' => $discrepanciesRepository->snapshot($city, $filters),
                    'yearFilterReady' => true,
                    'chartExportContext' => $chartExportContext,
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
