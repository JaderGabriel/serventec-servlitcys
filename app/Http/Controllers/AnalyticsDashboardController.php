<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Repositories\Ieducar\AttendanceRepository;
use App\Repositories\Ieducar\EnrollmentRepository;
use App\Repositories\Ieducar\FundebRepository;
use App\Repositories\Ieducar\InclusionRepository;
use App\Repositories\Ieducar\NetworkRepository;
use App\Repositories\Ieducar\OverviewRepository;
use App\Repositories\Ieducar\PerformanceRepository;
use App\Repositories\Ieducar\SchoolUnitsRepository;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        SchoolUnitsRepository $schoolUnitsRepository,
    ): View {
        $cities = City::query()->forAnalytics()->orderBy('name')->get();

        $filters = IeducarFilterState::fromRequest($request);

        $city = null;
        if ($request->filled('city_id')) {
            $city = City::query()
                ->forAnalytics()
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

        $enrollmentData = $yearFilterReady
            ? $enrollmentRepository->sample($city, $filters)
            : [
                'rows' => [],
                'kpis' => null,
                'distorcao' => null,
                'unidades_escolares' => null,
                'error' => null,
                'chart' => null,
                'charts' => [],
            ];

        $performanceData = $yearFilterReady
            ? $performanceRepository->snapshot($city, $filters)
            : [
                'rows' => [],
                'message' => '',
                'error' => null,
                'chart' => null,
                'charts' => [],
                'kpis' => [],
                'kpi_meta' => [
                    'total_matriculas' => 0,
                    'campo_situacao' => '',
                    'denominador_texto' => '',
                    'alerta_ano_encerrado' => null,
                ],
            ];

        $attendanceData = $yearFilterReady
            ? $attendanceRepository->snapshot($city, $filters)
            : ['rows' => [], 'message' => '', 'error' => null, 'chart' => null, 'charts' => []];

        $inclusionData = $yearFilterReady
            ? $inclusionRepository->snapshot($city, $filters)
            : [
                'charts' => [],
                'nee_charts_count' => 0,
                'aee_cross' => null,
                'gauges' => [],
                'notes' => [],
                'error' => null,
                'total_matriculas' => null,
                'equidade_fonte' => null,
                'methodology' => [],
            ];

        $networkData = $yearFilterReady
            ? $networkRepository->snapshot($city, $filters)
            : ['charts' => [], 'vagas_por_unidade_chart' => null, 'notes' => [], 'error' => null];

        $fundebData = $yearFilterReady && $city !== null
            ? $fundebRepository->buildReport(
                $city,
                $filters,
                $overviewData,
                $enrollmentData,
                $performanceData,
                $attendanceData,
                $inclusionData,
                $networkData,
            )
            : [
                'year_label' => '',
                'city_name' => '',
                'intro' => '',
                'footnote' => '',
                'modules' => [],
            ];

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
            'chartExportContext' => $chartExportContext,
            'tabs' => $tabs,
            'analyticsInitialTab' => $analyticsInitialTab,
        ]);
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

        $city = City::query()->forAnalytics()->whereKey($request->integer('city_id'))->firstOrFail();

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
