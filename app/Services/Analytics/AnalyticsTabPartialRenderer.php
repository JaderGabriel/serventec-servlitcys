<?php

namespace App\Services\Analytics;

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
use App\Services\Educacenso\EducacensoAnalysisCache;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
use App\Support\Dashboard\AnalyticsFinanceTabPreload;
use App\Support\Dashboard\AnalyticsMunicipalityContext;
use App\Support\Dashboard\AnalyticsTabPayloadCache;
use App\Support\Dashboard\ChartExportMeta;
use App\Support\Dashboard\ConsultoriaFlow;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Dashboard\MunicipalityHealthSections;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\IeducarAnalyticsMetricsScope;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AnalyticsTabPartialRenderer
{
    public function __construct(
        private readonly AnalyticsSafeLoader $safeLoader,
        private readonly AnalyticsFilterResolver $filterResolver,
        private readonly AnalyticsFinanceTabPreloader $financePreloader,
    ) {}

    public function renderAnalyticsTabPartial(
        string $tab,
        Request $request,
        City $city,
        IeducarFilterState $filters,
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
        array &$tabWarnings,
    ): Response {
        try {
            $this->filterResolver->bindMetricsScope($city, $filters);

            return $this->renderAnalyticsTabPartialInner(
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
            );
        } finally {
            IeducarAnalyticsMetricsScope::forget();
        }
    }
    public function renderAnalyticsTabPartialInner(
        string $tab,
        Request $request,
        City $city,
        IeducarFilterState $filters,
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
        $financePreload = $this->financePreloader->preloadFinanceTab(
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
            $request,
            $financeComparativoService,
            $filterOptionsService,
            $tabWarnings,
        );
        $healthDataForTab = $financePreload['healthData'] ?? null;
        $discrepanciesDataForTab = $financePreload['discrepanciesData'] ?? null;
        $fundebDataForTab = $financePreload['fundebData'] ?? null;
        $otherFundingDataForTab = $financePreload['otherFundingData'] ?? null;
        $workDoneDataForTab = $financePreload['workDoneData'] ?? null;
        $comparativoBaseYear = FinanceComparativoService::resolveBaseYear($request, $filters);
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

        $yearReady = [
            'yearFilterReady' => $filters->hasYearSelected() || $comparativoBaseYear !== null,
        ];
        $viewBase = [
            'chartExportContext' => $chartExportContext,
            'municipalityContext' => $municipalityContext,
            'selectedCity' => $city,
            'filters' => $filters,
        ];

        return match ($tab) {
            'overview' => response()
                ->view('dashboard.analytics.partials.overview', array_merge($viewBase, $yearReady, [
                    'overviewData' => $this->safeLoader->load(
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
                    'schoolUnitsData' => $this->safeLoader->load(
                        fn () => $schoolUnitsRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::schoolUnits(),
                        __('Unidades escolares'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'enrollment' => response()
                ->view('dashboard.analytics.partials.enrollment', array_merge($viewBase, $yearReady, [
                    'enrollmentData' => $this->safeLoader->load(
                        fn () => $enrollmentRepository->sample($city, $filters),
                        AnalyticsEmptyPayloads::enrollment(),
                        __('Matrículas'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'cadunico_previsao' => response()
                ->view('dashboard.analytics.partials.cadunico-previsao', array_merge($viewBase, $yearReady, [
                    'cadunicoPrevisaoData' => $this->safeLoader->load(
                        fn () => $cadunicoPrevisaoRepository->buildReport($city, $filters),
                        AnalyticsEmptyPayloads::cadunicoPrevisao(),
                        __('CadÚnico'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'network' => response()
                ->view('dashboard.analytics.partials.network', array_merge($viewBase, $yearReady, [
                    'networkData' => $this->safeLoader->load(
                        fn () => $networkRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::network(),
                        __('Rede'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'inclusion' => response()
                ->view('dashboard.analytics.partials.inclusion', array_merge($viewBase, $yearReady, [
                    'inclusionData' => $this->safeLoader->load(
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
                    'performanceData' => $this->safeLoader->load(
                        fn () => $performanceRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::performance(),
                        __('Desempenho'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'attendance' => response()
                ->view('dashboard.analytics.partials.attendance', array_merge($viewBase, $yearReady, [
                    'attendanceData' => $this->safeLoader->load(
                        fn () => $attendanceRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::attendance(),
                        __('Frequência'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'fundeb' => response()
                ->view('dashboard.analytics.partials.fundeb', array_merge($viewBase, $yearReady, [
                    'fundebData' => $fundebDataForTab ?? $this->safeLoader->load(
                        fn () => $this->financePreloader->buildFundebReportForTab(
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
                    'otherFundingData' => $otherFundingDataForTab ?? $this->safeLoader->load(
                        fn () => $otherFundingRepository->buildReport($city, $filters),
                        AnalyticsEmptyPayloads::otherFunding(),
                        __('Financiamentos'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'work_done' => response()
                ->view('dashboard.analytics.partials.work-done', array_merge($viewBase, $yearReady, [
                    'workDoneData' => $workDoneDataForTab ?? $this->safeLoader->load(
                        fn () => $workDoneRepository->buildReport($city, $filters),
                        AnalyticsEmptyPayloads::workDone(),
                        __('Censo'),
                        $tabWarnings,
                    ),
                    'educacensoAnalysis' => $this->resolveEducacensoAnalysis($request->user(), $city),
                    'selectedCity' => $city,
                    'filters' => $filters,
                ]))
                ->withHeaders($headers),
            'municipality_health' => response()
                ->view('dashboard.analytics.partials.municipality-health', array_merge($viewBase, $yearReady, [
                    'healthData' => $healthDataForTab ?? $this->safeLoader->load(
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
                    'discrepanciesData' => $discrepanciesDataForTab ?? $this->safeLoader->load(
                        fn () => $discrepanciesRepository->snapshot($city, $filters),
                        AnalyticsEmptyPayloads::discrepancies(),
                        __('Discrepâncias'),
                        $tabWarnings,
                    ),
                ]))
                ->withHeaders($headers),
            'comparativo' => response()
                ->view('dashboard.analytics.partials.comparativo', array_merge($viewBase, $yearReady, [
                    'comparativoData' => $this->safeLoader->load(
                        fn () => $this->financePreloader->buildComparativoForTab(
                            $financeComparativoService,
                            $filterOptionsService,
                            $city,
                            $filters,
                            $request,
                        ),
                        AnalyticsEmptyPayloads::comparativo(),
                        __('Comparativo'),
                        $tabWarnings,
                    ),
                    'baseYear' => $comparativoBaseYear,
                    'pdfExportsRecent' => $request->user()->canExportAnalyticsPdf()
                        ? $pdfExportService->recentForUserCity($request->user(), $city, 4)
                        : [],
                ]))
                ->withHeaders($headers),
            'finance_realtime' => response()
                ->view('dashboard.analytics.partials.finance-realtime', array_merge($viewBase, $yearReady, [
                    'realtimeData' => $this->safeLoader->load(
                        fn () => $financeRealtimeService->buildReport($city, $filters, $municipalityContext),
                        $financeRealtimeService->tabShell($city, $filters),
                        __('Tempo Real'),
                        $tabWarnings,
                    ),
                    'filters' => $filters,
                ]))
                ->withHeaders($headers),
            default => abort(404),
        };
    }
    public function renderMunicipalityHealthSection(
        string $section,
        City $city,
        IeducarFilterState $filters,
        MunicipalityHealthRepository $municipalityHealthRepository,
        array &$warnings,
    ): Response {
        $sectionData = $this->safeLoader->load(
            fn () => $municipalityHealthRepository->section($section, $city, $filters),
            ['error' => __('Seção indisponível.')],
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
    public function resolveMunicipalityContextForTab(
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
            'finance_realtime',
            'discrepancies',
            'comparativo',
        ];
        if (! in_array($tab, $tabsWithFunding, true)) {
            return null;
        }

        if (AnalyticsFinanceTabPreload::shouldReuseFundingContext($tab)) {
            return null;
        }

        if (in_array($tab, ['finance_realtime', 'comparativo'], true)) {
            return $this->financePreloader->preloadLightFundingContext($discrepanciesRepository, $city, $filters, $warnings)['context'];
        }

        $overviewData = $this->safeLoader->load(
            fn () => $overviewRepository->summary($city, $filters),
            ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null],
            __('Visão geral'),
            $warnings,
        );
        $fundingSnapshot = $this->safeLoader->load(
            fn () => $this->financePreloader->fundingImpactSnapshot($discrepanciesRepository, $city, $filters),
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
     * @return array<string, mixed>|null
     */
    private function resolveEducacensoAnalysis(?Authenticatable $user, ?City $city): ?array
    {
        if ($user === null || ! $city instanceof City || ! $user instanceof \App\Models\User) {
            return null;
        }

        return EducacensoAnalysisCache::get($user, $city);
    }
}
