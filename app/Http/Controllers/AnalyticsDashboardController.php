<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Repositories\Ieducar\AttendanceRepository;
use App\Repositories\Ieducar\EnrollmentRepository;
use App\Repositories\Ieducar\OverviewRepository;
use App\Repositories\Ieducar\PerformanceRepository;
use App\Services\Ieducar\FilterOptionsService;
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

        $yearOptions = $this->schoolYearOptions();
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

        $overviewData = $overviewRepository->summary($city, $filters);
        $enrollmentData = $enrollmentRepository->sample($city, $filters);
        $performanceData = $performanceRepository->placeholder($city, $filters);
        $attendanceData = $attendanceRepository->placeholder($city, $filters);

        return view('dashboard.analytics', [
            'cities' => $cities,
            'selectedCity' => $city,
            'filters' => $filters,
            'yearOptions' => $yearOptions,
            'ieducarOptions' => $ieducarOptions,
            'overviewData' => $overviewData,
            'enrollmentData' => $enrollmentData,
            'performanceData' => $performanceData,
            'attendanceData' => $attendanceData,
            'tabs' => [
                'overview' => __('Visão geral'),
                'enrollment' => __('Matrículas'),
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
     * Anos letivos quando a base ainda não respondeu.
     *
     * @return array<int, int>
     */
    private function schoolYearOptions(): array
    {
        $current = (int) date('Y');
        $years = [];
        for ($y = $current + 1; $y >= $current - 8; $y--) {
            $years[$y] = $y;
        }

        return $years;
    }
}
