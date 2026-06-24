<?php

namespace App\Http\Controllers;

use App\Models\City;
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
use App\Services\Analytics\AnalyticsReportExportService;
use App\Services\Analytics\FinanceComparativoService;
use App\Services\Analytics\FinanceRealtimeFundebService;
use App\Services\CityDataConnection;
use App\Services\Educacenso\EducacensoAnalysisCache;
use App\Services\Ieducar\FilterOptionsService;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
use App\Support\Dashboard\AnalyticsLoadProfiler;
use App\Support\Dashboard\AnalyticsFinanceTabPreload;
use App\Support\Dashboard\AnalyticsFundingContextResolver;
use App\Support\Dashboard\AnalyticsMunicipalityContext;
use App\Support\Dashboard\AnalyticsTabPayloadCache;
use App\Support\Dashboard\AnalyticsTabCatalog;
use App\Support\Dashboard\ConsultoriaFlow;
use App\Support\Dashboard\MunicipalityHealthSections;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesCheckCatalog;
use App\Support\Ieducar\FundebVaafProfileBuilder;
use App\Support\Ieducar\IeducarAnalyticsMetricsScope;
use App\Support\Pulse\PulseOperationRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Services\Analytics\AnalyticsTabPartialRenderer;
use App\Services\Analytics\AnalyticsSafeLoader;
use App\Services\Analytics\AnalyticsMunicipalAccess;
use App\Services\Analytics\AnalyticsFinanceTabPreloader;
use App\Services\Analytics\AnalyticsFilterResolver;
use App\Services\Analytics\AnalyticsIndexAssembler;
use App\Services\Analytics\AnalyticsTabPartialDispatcher;
use App\Http\Requests\AnalyticsFilterRequest;
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

    public function __construct(
        private readonly AnalyticsFilterResolver $filterResolver,
        private readonly AnalyticsSafeLoader $safeLoader,
        private readonly AnalyticsFinanceTabPreloader $financePreloader,
        private readonly AnalyticsTabPartialRenderer $tabRenderer,
        private readonly AnalyticsMunicipalAccess $municipalAccess,
    ) {}


    public function index(
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
        AnalyticsIndexAssembler $indexAssembler,
    ): View|RedirectResponse {
        return $indexAssembler->assemble(
            $request,
            $filterOptionsService,
            $overviewRepository,
            $enrollmentRepository,
            $cadunicoPrevisaoRepository,
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
        );
    }


    /**
     * @template T
     *
     * @param  callable(): T  $load
     * @param  T  $fallback
     * @param  list<string>  $warnings
     * @return T
     */

    /**
     * HTML de uma aba pesada (carregamento lazy). Cada pedido aparece no Pulse como URL
     * distinta (`/dashboard/analytics/tab?tab=…`) para análise de tempo por aba.
     */
    public function tabPartial(
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
        FinanceComparativoService $financeComparativoService,
        FinanceRealtimeFundebService $financeRealtimeService,
        AnalyticsTabPartialDispatcher $tabDispatcher,
    ): Response {
        return $tabDispatcher->dispatch(
            $request,
            $filterOptionsService,
            $overviewRepository,
            $enrollmentRepository,
            $cadunicoPrevisaoRepository,
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
            $financeComparativoService,
            $financeRealtimeService,
        );
    }


    /**
     * @param  list<string>  $tabWarnings
     */

    /**
     * @return array<string, mixed>
     */

    /**
     * Opções para selects em cascata (AJAX).
     */
    public function filterOptions(AnalyticsFilterRequest $request, FilterOptionsService $filterOptionsService): JsonResponse
    {
        $request->validate([
            'city_id' => ['required', 'integer'],
            'kind' => ['required', 'string', 'max:32'],
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
    public function filterOptionsYears(AnalyticsFilterRequest $request, FilterOptionsService $filterOptionsService): JsonResponse
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
                'years' => $this->filterResolver->schoolYearOptionsFallback(),
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Escolas, cursos e turnos após o index (modo ANALYTICS_INDEX_LIGHT_FILTERS).
     */
    public function filterOptionsBootstrap(AnalyticsFilterRequest $request, FilterOptionsService $filterOptionsService): JsonResponse
    {
        $request->validate([
            'city_id' => ['required', 'integer'],
            'ano_letivo' => ['nullable', 'string', 'max:32'],
        ]);

        $city = UserCityAccess::citiesQuery($request->user())->whereKey($request->integer('city_id'))->firstOrFail();
        $this->authorize('viewAnalytics', $city);

        $filters = $this->filterResolver->resolve($request, $filterOptionsService, $city);
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
                'years' => $this->filterResolver->schoolYearOptionsFallback(),
                'escolas' => [],
                'cursos' => [],
                'turnos' => [],
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}
