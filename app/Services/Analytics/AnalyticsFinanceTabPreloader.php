<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Repositories\Ieducar\EnrollmentRepository;
use App\Repositories\Ieducar\FundebRepository;
use App\Repositories\Ieducar\MunicipalityHealthRepository;
use App\Repositories\Ieducar\OtherFundingRepository;
use App\Repositories\Ieducar\OverviewRepository;
use App\Repositories\Ieducar\WorkDoneRepository;
use App\Services\Analytics\FinanceComparativoService;
use App\Services\Ieducar\FilterOptionsService;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
use App\Support\Dashboard\AnalyticsFinanceTabPreload;
use App\Support\Dashboard\AnalyticsMunicipalityContext;
use App\Support\Dashboard\AnalyticsTabPayloadCache;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Dashboard\AnalyticsFundingContextResolver;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use Illuminate\Http\Request;
use Throwable;

/**
 * Pré-carga de abas financeiras e bundles FUNDEB (extraído do AnalyticsDashboardController).
 */
final class AnalyticsFinanceTabPreloader
{
    public function __construct(
        private readonly AnalyticsSafeLoader $safeLoader,
        private readonly AnalyticsFundingContextResolver $fundingContext,
    ) {}

    public function buildComparativoForTab(
        FinanceComparativoService $financeComparativoService,
        FilterOptionsService $filterOptionsService,
        City $city,
        IeducarFilterState $filters,
        Request $request,
    ): array {
        $baseYear = FinanceComparativoService::resolveBaseYear($request, $filters);
        $yearOptions = $this->comparativoYearOptions($filterOptionsService, $city, $filters);

        if ($baseYear === null) {
            return $this->comparativoShellPayload($filterOptionsService, $city, $filters, $yearOptions);
        }

        return $financeComparativoService->build($city, $baseYear, $filters, $yearOptions);
    }

    /**
     * @return list<int|string>
     */
    public function comparativoYearOptions(
        FilterOptionsService $filterOptionsService,
        City $city,
        IeducarFilterState $filters,
    ): array {
        try {
            $loaded = $filterOptionsService->loadYearOptions($city);

            return is_array($loaded['years'] ?? null) ? $loaded['years'] : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Payload mínimo da aba Comparativo (seletor de ano base + orientações).
     *
     * @param  list<int|string>  $yearOptions
     * @return array<string, mixed>
     */
    public function comparativoShellPayload(
        FilterOptionsService $filterOptionsService,
        City $city,
        IeducarFilterState $filters,
        ?array $yearOptions = null,
    ): array {
        $yearOptions ??= $this->comparativoYearOptions($filterOptionsService, $city, $filters);
        $anchorYear = (int) date('Y');
        $payload = AnalyticsEmptyPayloads::comparativo();
        $payload['city_name'] = (string) ($city->name ?? '');
        $payload['year_options'] = FinanceComparativoService::normalizeYearOptions($yearOptions, $anchorYear);
        $payload['footnote'] = __('Valores indicativos para apoio à consultoria municipal. Não substituem portaria FNDE, extrato Simec/VAAR nem prestação de contas.');

        if ($filters->hasYearSelected() && $filters->isAllSchoolYears()) {
            $payload['error'] = __('O comparativo exige um ano base específico. Escolha um exercício abaixo ou um ano letivo concreto (não «Todos os anos») nos filtros superiores.');
            $payload['alerts'] = [[
                'tone' => 'warning',
                'title' => __('Ano base necessário'),
                'message' => __('Selecione o ano base do comparativo no formulário abaixo ou aplique um ano letivo específico.'),
            ]];
        }

        return $payload;
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: null, fundebData: null, comparativoData: ?array, otherFundingData: null, workDoneData: null}
     */
    public function preloadComparativoTab(
        FinanceComparativoService $financeComparativoService,
        FilterOptionsService $filterOptionsService,
        DiscrepanciesRepository $discrepanciesRepository,
        City $city,
        IeducarFilterState $filters,
        Request $request,
        array &$warnings,
    ): array {
        $preloaded = $this->preloadLightFundingContext($discrepanciesRepository, $city, $filters, $warnings);

        return [
            'context' => $preloaded['context'],
            'healthData' => null,
            'discrepanciesData' => null,
            'fundebData' => null,
            'comparativoData' => null,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];
    }

    /**
     * FUNDEB em lazy: visão geral + KPIs de matrículas (mesma base da aba Matrículas) + resumo Discrepâncias.
     *
     * @return array<string, mixed>
     */
    public function buildFundebReportForTab(
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
     *   comparativoData: ?array<string, mixed>,
     *   otherFundingData: ?array<string, mixed>,
     *   workDoneData: ?array<string, mixed>
     * }
     */
    public function preloadFinanceTab(
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
        Request $request,
        FinanceComparativoService $financeComparativoService,
        FilterOptionsService $filterOptionsService,
        array &$warnings,
    ): array {
        $empty = [
            'context' => null,
            'healthData' => null,
            'discrepanciesData' => null,
            'fundebData' => null,
            'comparativoData' => null,
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
            'finance_realtime' => $this->preloadFinanceRealtimeTab(
                $discrepanciesRepository,
                $city,
                $filters,
                $warnings,
            ),
            'comparativo' => $this->preloadComparativoTab(
                $financeComparativoService,
                $filterOptionsService,
                $discrepanciesRepository,
                $city,
                $filters,
                $request,
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
            'work_done' => $this->preloadCensoTab(
                $workDoneRepository,
                $overviewRepository,
                $city,
                $filters,
                $warnings,
            ),
            default => $empty,
        };
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: ?array, discrepanciesData: null, fundebData: null, otherFundingData: null, workDoneData: null}
     */
    public function preloadMunicipalityHealthTab(
        MunicipalityHealthRepository $municipalityHealthRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $healthData = $this->safeLoader->load(
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
            'comparativoData' => null,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: ?array, fundebData: null, otherFundingData: null, workDoneData: null}
     */
    public function preloadDiscrepanciesTab(
        DiscrepanciesRepository $discrepanciesRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $discrepanciesData = $this->safeLoader->load(
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
            'comparativoData' => null,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: null, fundebData: ?array, otherFundingData: null, workDoneData: null}
     */
    public function preloadFundebTab(
        FundebRepository $fundebRepository,
        OverviewRepository $overviewRepository,
        DiscrepanciesRepository $discrepanciesRepository,
        EnrollmentRepository $enrollmentRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $bundle = $this->safeLoader->load(
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
            'comparativoData' => null,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: null, fundebData: null, comparativoData: null, otherFundingData: null, workDoneData: null}
     */
    public function preloadFinanceRealtimeTab(
        DiscrepanciesRepository $discrepanciesRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $preloaded = $this->preloadLightFundingContext($discrepanciesRepository, $city, $filters, $warnings);

        return [
            'context' => $preloaded['context'],
            'healthData' => null,
            'discrepanciesData' => null,
            'fundebData' => null,
            'comparativoData' => null,
            'otherFundingData' => null,
            'workDoneData' => null,
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: null, fundebData: null, otherFundingData: null, workDoneData: ?array}
     */
    public function preloadCensoTab(
        WorkDoneRepository $workDoneRepository,
        OverviewRepository $overviewRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $workDoneData = $this->safeLoader->load(
            fn () => $workDoneRepository->buildReport($city, $filters),
            AnalyticsEmptyPayloads::workDone(),
            __('Censo'),
            $warnings,
        );
        if (is_array($workDoneData)) {
            AnalyticsTabPayloadCache::put(AnalyticsTabPayloadCache::WORK_DONE, $city, $filters, $workDoneData);
        }

        $overviewData = $this->safeLoader->load(
            fn () => $overviewRepository->summary($city, $filters),
            ['kpis' => null, 'error' => null],
            __('Visão geral'),
            $warnings,
        );
        $totalMat = is_array($workDoneData) ? ($workDoneData['total_matriculas'] ?? null) : null;
        if ($totalMat === null && is_array($overviewData)) {
            $totalMat = $overviewData['kpis']['matriculas'] ?? null;
        }

        return [
            'context' => AnalyticsMunicipalityContext::fromWorkDoneSnapshot(
                is_array($workDoneData) ? $workDoneData : [],
                is_array($overviewData) ? $overviewData : ['kpis' => ['matriculas' => $totalMat]],
            ),
            'healthData' => null,
            'discrepanciesData' => null,
            'fundebData' => null,
            'comparativoData' => null,
            'otherFundingData' => null,
            'workDoneData' => is_array($workDoneData) ? $workDoneData : null,
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $loadTab
     * @param  array<string, mixed>  $emptyTab
     * @param  list<string>  $warnings
     * @return array{context: ?array, healthData: null, discrepanciesData: null, fundebData: null, otherFundingData: ?array, workDoneData: ?array}
     */
    public function preloadFinanceStripTab(
        string $tab,
        City $city,
        IeducarFilterState $filters,
        DiscrepanciesRepository $discrepanciesRepository,
        array &$warnings,
        callable $loadTab,
        array $emptyTab,
        string $label,
    ): array {
        $tabData = $this->safeLoader->load($loadTab, $emptyTab, $label, $warnings);
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
        $fundingSnapshot = $this->safeLoader->load(
            fn () => $this->fundingImpactSnapshot($discrepanciesRepository, $city, $filters),
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
            'comparativoData' => null,
            'otherFundingData' => $tab === 'other_funding' && is_array($tabData) ? $tabData : null,
            'workDoneData' => $tab === 'work_done' && is_array($tabData) ? $tabData : null,
        ];
    }

    /**
     * @return array{fundeb: array<string, mixed>, context: ?array<string, mixed>}
     */
    public function buildFundebTabBundle(
        FundebRepository $fundebRepository,
        OverviewRepository $overviewRepository,
        DiscrepanciesRepository $discrepanciesRepository,
        EnrollmentRepository $enrollmentRepository,
        City $city,
        IeducarFilterState $filters,
    ): array {
        $lightBundle = filter_var(config('analytics.fundeb_tab_light_bundle', true), FILTER_VALIDATE_BOOL);
        $fundingSnapshot = $this->resolveFundebFundingSnapshot(
            $discrepanciesRepository,
            $city,
            $filters,
            $lightBundle,
        );

        if ($lightBundle) {
            $matHint = (int) ($fundingSnapshot['total_matriculas'] ?? 0);
            $overviewData = [
                'kpis' => ['matriculas' => $matHint > 0 ? $matHint : null],
                'total_matriculas' => $matHint > 0 ? $matHint : null,
            ];
            $enrollmentData = ['kpis' => ['matriculas' => $matHint > 0 ? $matHint : null]];
        } else {
            $overviewData = $overviewRepository->summary($city, $filters);
            $enrollmentData = $enrollmentRepository->sample($city, $filters);
        }

        $volume = FundebRepository::resolveVolumeCountsForFilter(
            $city,
            $filters,
            $overviewData,
            $enrollmentData,
            $fundingSnapshot,
        );
        $matTotal = $volume['matriculas'];
        if ($matTotal > 0) {
            $alunos = $volume['alunos_available'] ? (int) ($volume['alunos'] ?? 0) : null;
            $overviewData = [
                'kpis' => [
                    'matriculas' => $matTotal,
                    'alunos_distintos' => $alunos > 0 ? $alunos : null,
                ],
                'total_matriculas' => $matTotal,
                'total_alunos_distintos' => $alunos > 0 ? $alunos : null,
            ];
            $enrollmentData = [
                'kpis' => [
                    'matriculas' => $matTotal,
                    'alunos_distintos' => $alunos > 0 ? $alunos : null,
                ],
            ];
            if (is_array($fundingSnapshot)) {
                $fundingSnapshot['total_matriculas'] = $matTotal;
                $fundingSnapshot['total_alunos_distintos'] = $alunos > 0 ? $alunos : null;
                $fundingSnapshot['base_calculo_fundeb'] = $volume['base_calculo'] > 0 ? $volume['base_calculo'] : null;
            }
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
     * Snapshot financeiro para FUNDEB: leve (matrículas + VAAF) ou impacto Discrepâncias conforme config.
     *
     * @return array<string, mixed>
     */
    public function resolveFundebFundingSnapshot(
        DiscrepanciesRepository $discrepanciesRepository,
        City $city,
        IeducarFilterState $filters,
        bool $lightBundle,
    ): array {
        $fallbackRef = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
        $empty = [
            'summary' => [],
            'funding_reference' => $fallbackRef,
            'total_matriculas' => null,
            'year_label' => null,
        ];

        if ($lightBundle || ! config('analytics.fundeb_load_discrepancies_summary', true)) {
            $light = $this->lightFundingContext($discrepanciesRepository, $city, $filters);

            return array_merge($empty, AnalyticsFinanceTabPreload::normalizeLightFunding($light));
        }

        $fundingSnapshot = $this->fundingImpactSnapshot($discrepanciesRepository, $city, $filters);
        if (! is_array($fundingSnapshot)) {
            return $empty;
        }
        if (! is_array($fundingSnapshot['funding_reference'] ?? null)) {
            $fundingSnapshot['funding_reference'] = $fallbackRef;
        }

        return $fundingSnapshot;
    }

    /**
     * Fragmento HTML de uma secção diferida do Diagnóstico (AJAX progressivo).
     *
     * @param  list<string>  $warnings
     */
    public function renderMunicipalityHealthSection(
        string $section,
        City $city,
        IeducarFilterState $filters,
        MunicipalityHealthRepository $municipalityHealthRepository,
        array &$warnings,
    ): Response {
        $sectionData = $this->safeLoader->load(
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
            return $this->preloadLightFundingContext($discrepanciesRepository, $city, $filters, $warnings)['context'];
        }

        $overviewData = $this->safeLoader->load(
            fn () => $overviewRepository->summary($city, $filters),
            ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null],
            __('Visão geral'),
            $warnings,
        );
        $fundingSnapshot = $this->safeLoader->load(
            fn () => $this->fundingImpactSnapshot($discrepanciesRepository, $city, $filters),
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
     * Pré-carga do contexto municipal (matrículas + VAAF) para abas que não precisam do resumo Discrepâncias.
     *
     * @param  list<string>  $warnings
     * @return array{context: ?array<string, mixed>, light: array<string, mixed>}
     */
    public function preloadLightFundingContext(
        DiscrepanciesRepository $discrepanciesRepository,
        City $city,
        IeducarFilterState $filters,
        array &$warnings,
    ): array {
        $fallback = [
            'summary' => [],
            'funding_reference' => DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters),
            'total_matriculas' => null,
            'year_label' => '',
        ];
        $light = $this->safeLoader->load(
            fn () => $this->lightFundingContext($discrepanciesRepository, $city, $filters),
            $fallback,
            __('Resumo financeiro'),
            $warnings,
        );
        if (! is_array($light)) {
            $light = $fallback;
        } elseif (! is_array($light['funding_reference'] ?? null)) {
            $light['funding_reference'] = DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
        }

        $totalMat = (int) ($light['total_matriculas'] ?? 0);
        $overviewData = [
            'kpis' => ['matriculas' => $totalMat > 0 ? $totalMat : null],
            'total_matriculas' => $totalMat > 0 ? $totalMat : null,
        ];

        return [
            'context' => AnalyticsFinanceTabPreload::contextFromLightFunding($light, $overviewData),
            'light' => $light,
        ];
    }
    public function lightFundingContext(
        DiscrepanciesRepository $discrepanciesRepository,
        City $city,
        IeducarFilterState $filters,
    ): array {
        return $this->fundingContext
            ->lightContext($city, $filters, $discrepanciesRepository);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fundingImpactSnapshot(
        DiscrepanciesRepository $discrepanciesRepository,
        City $city,
        IeducarFilterState $filters,
    ): ?array {
        return $this->fundingContext
            ->snapshot($city, $filters, $discrepanciesRepository);
    }
}

