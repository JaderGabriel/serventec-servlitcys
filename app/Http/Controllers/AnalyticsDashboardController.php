<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Repositories\Ieducar\AttendanceRepository;
use App\Repositories\Ieducar\EnrollmentRepository;
use App\Repositories\Ieducar\InclusionRepository;
use App\Repositories\Ieducar\NetworkRepository;
use App\Repositories\Ieducar\OverviewRepository;
use App\Repositories\Ieducar\PerformanceRepository;
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

            $ieducarOptions = $filterOptionsService->loadAll($city);
            if (! empty($ieducarOptions['years'])) {
                $yearOptions = $ieducarOptions['years'];
            }
        }

        $yearFilterReady = $city !== null && $filters->hasYearSelected();

        $overviewData = $yearFilterReady
            ? $overviewRepository->summary($city, $filters)
            : ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null];

        $enrollmentData = $yearFilterReady
            ? $enrollmentRepository->sample($city, $filters)
            : ['rows' => [], 'kpis' => null, 'error' => null, 'chart' => null, 'charts' => []];

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
            : ['charts' => [], 'gauges' => [], 'notes' => [], 'error' => null];

        $networkData = $yearFilterReady
            ? $networkRepository->snapshot($city, $filters)
            : ['charts' => [], 'notes' => [], 'error' => null];

        $chartExportContext = ChartExportMeta::forAnalytics($city, $filters, $ieducarOptions);

        return view('dashboard.analytics', [
            'cities' => $cities,
            'selectedCity' => $city,
            'filters' => $filters,
            'yearOptions' => $yearOptions,
            'yearFilterReady' => $yearFilterReady,
            'ieducarOptions' => $ieducarOptions,
            'overviewData' => $overviewData,
            'enrollmentData' => $enrollmentData,
            'performanceData' => $performanceData,
            'attendanceData' => $attendanceData,
            'inclusionData' => $inclusionData,
            'networkData' => $networkData,
            'chartExportContext' => $chartExportContext,
            'tabs' => [
                'overview' => __('Visão geral'),
                'enrollment' => __('Matrículas'),
                'network' => __('Rede e oferta'),
                'inclusion' => __('Inclusão & Diversidade'),
                'performance' => __('Desempenho'),
                'attendance' => __('Frequência'),
            ],
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
        ]);

        $city = City::query()->forAnalytics()->whereKey($request->integer('city_id'))->firstOrFail();

        $this->authorize('viewAnalytics', $city);

        $data = $filterOptionsService->loadByKind($city, $request->string('kind')->toString());

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
