<?php

namespace App\Services\Analytics;

use App\Http\Requests\AnalyticsFilterRequest;
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
use App\Services\Analytics\FinanceComparativoService;
use App\Services\Analytics\FinanceRealtimeFundebService;
use App\Services\Ieducar\FilterOptionsService;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\Auth\UserCityAccess;
use App\Support\Dashboard\AnalyticsTabCatalog;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\MunicipalityHealthSections;
use App\Support\Pulse\PulseOperationRecorder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquestração HTTP do carregamento lazy de abas analytics.
 */
final class AnalyticsTabPartialDispatcher
{
    public function __construct(
        private readonly AnalyticsFilterResolver $filterResolver,
        private readonly AnalyticsFinanceTabPreloader $financePreloader,
        private readonly AnalyticsTabPartialRenderer $tabRenderer,
    ) {}

    public function dispatch(
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

        Gate::authorize('viewAnalytics', $city);

        $filters = $this->filterResolver->resolve($request, $filterOptionsService, $city);

        if ($tab === 'municipality_health' && $request->hasSession()) {
            $request->session()->save();
        }

        $comparativoBaseYear = $tab === 'comparativo'
            ? FinanceComparativoService::resolveBaseYear($request, $filters)
            : null;

        if (! $filters->hasYearSelected() && $comparativoBaseYear === null) {
            if ($tab === 'comparativo') {
                return response()
                    ->view('dashboard.analytics.partials.comparativo', [
                        'comparativoData' => $this->financePreloader->comparativoShellPayload($filterOptionsService, $city, $filters),
                        'yearFilterReady' => false,
                        'chartExportContext' => ChartExportMeta::forAnalytics($city, $filters, [
                            'escolas' => [],
                            'cursos' => [],
                            'turnos' => [],
                            'years' => [],
                        ]),
                        'municipalityContext' => null,
                        'selectedCity' => $city,
                        'filters' => $filters,
                        'baseYear' => null,
                        'pdfExportsRecent' => [],
                    ])
                    ->header('X-Analytics-Tab', $tab)
                    ->header('X-Analytics-Tab-Status', 'no-year');
            }

            if ($tab === 'finance_realtime') {
                return response()
                    ->view('dashboard.analytics.partials.finance-realtime', [
                        'realtimeData' => $financeRealtimeService->tabShell($city, $filters),
                        'yearFilterReady' => false,
                        'municipalityContext' => null,
                        'filters' => $filters,
                    ])
                    ->header('X-Analytics-Tab', $tab)
                    ->header('X-Analytics-Tab-Status', 'no-year');
            }

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
                fn (): Response => $this->tabRenderer->renderMunicipalityHealthSection(
                    $healthSection,
                    $city,
                    $filters,
                    $municipalityHealthRepository,
                    $tabWarnings,
                ),
            );
        }

        try {
            $response = PulseOperationRecorder::measure($tabKey, fn (): Response => $this->tabRenderer->renderAnalyticsTabPartial(
                $tab,
                $request,
                $city,
                $filters,
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
}
