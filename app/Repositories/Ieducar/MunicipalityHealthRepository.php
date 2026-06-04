<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Support\Dashboard\AnalyticsTabPayloadCache;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Dashboard\MunicipalityHealthSections;
use App\Support\Dashboard\PublicDataSourcesCatalog;
use App\Support\Ieducar\ConsultoriaThematicBridge;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\FundebReferenceDisplay;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Diagnóstico Geral: conformidade consolidada (cadastro/Censo + eixos FUNDEB/VAAR) no ano filtrado.
 */
class MunicipalityHealthRepository
{
    public function __construct(
        private DiscrepanciesRepository $discrepancies,
        private FundebRepository $fundeb,
        private OtherFundingRepository $otherFunding,
        private WorkDoneRepository $workDone,
        private OverviewRepository $overview,
        private EnrollmentRepository $enrollment,
        private PerformanceRepository $performance,
        private AttendanceRepository $attendance,
        private InclusionRepository $inclusion,
        private NetworkRepository $network,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        $empty = [
            'intro' => '',
            'footnote' => '',
            'year_label' => '',
            'city_name' => '',
            'compliance_score' => null,
            'compliance_status' => 'neutral',
            'compliance_label' => '',
            'summary' => [
                'pendencias_cadastro' => 0,
                'modulos_fundeb_alerta' => 0,
                'perda_estimada_anual' => 0.0,
                'ganho_potencial_anual' => 0.0,
            ],
            'cadastro_dimensions' => [],
            'thematic_blocks' => [],
            'active_check_ids' => [],
            'public_data_sources' => PublicDataSourcesCatalog::build(null, 'all'),
            'fundeb_modules' => [],
            'top_problems' => [],
            'chart_pendencias' => null,
            'error' => null,
        ];

        if ($city === null) {
            return $empty;
        }

        $variant = match (true) {
            MunicipalityHealthSections::progressiveEnabled() => 'shell',
            MunicipalityHealthSections::mode() === MunicipalityHealthSections::MODE_FULL => 'full',
            default => 'strategic',
        };

        return $this->rememberSnapshot($city, $filters, $variant, $empty, function () use ($city, $filters, $empty, $variant): array {
            if ($variant === 'shell') {
                return $this->snapshotShell($city, $filters, $empty);
            }

            if ($variant === 'strategic') {
                return $this->snapshotStrategic($city, $filters, $empty);
            }

            return $this->snapshotFresh($city, $filters, $empty);
        });
    }

    /**
     * Snapshot completo (PDF / exportação) — ignora carregamento progressivo.
     *
     * @return array<string, mixed>
     */
    public function snapshotFull(?City $city, IeducarFilterState $filters): array
    {
        $empty = [
            'intro' => '',
            'footnote' => '',
            'year_label' => '',
            'city_name' => '',
            'compliance_score' => null,
            'compliance_status' => 'neutral',
            'compliance_label' => '',
            'summary' => [],
            'cadastro_dimensions' => [],
            'thematic_blocks' => [],
            'active_check_ids' => [],
            'public_data_sources' => PublicDataSourcesCatalog::build(null, 'all'),
            'fundeb_modules' => [],
            'top_problems' => [],
            'chart_pendencias' => null,
            'error' => null,
        ];

        if ($city === null) {
            return $empty;
        }

        return $this->rememberSnapshot($city, $filters, 'full', $empty, fn (): array => $this->snapshotFresh($city, $filters, $empty));
    }

    /**
     * HTML/JSON de uma secção diferida do Diagnóstico.
     *
     * @return array<string, mixed>
     */
    public function section(string $section, ?City $city, IeducarFilterState $filters): array
    {
        if ($city === null || ! MunicipalityHealthSections::isValid($section)) {
            return ['error' => __('Secção inválida.')];
        }

        $empty = ['error' => null];

        return $this->rememberSnapshot($city, $filters, 'section:'.$section, $empty, function () use ($section, $city, $filters): array {
            return match ($section) {
                MunicipalityHealthSections::FUNDEB => $this->loadSectionFundeb($city, $filters),
                MunicipalityHealthSections::PROGRAMAS => $this->loadSectionProgramas($city, $filters),
                MunicipalityHealthSections::TEMATICO => $this->loadSectionTematico($city, $filters),
                default => ['error' => __('Secção inválida.')],
            };
        });
    }

    /**
     * @param  array<string, mixed>  $empty
     */
    private function rememberSnapshot(
        City $city,
        IeducarFilterState $filters,
        string $variant,
        array $empty,
        callable $loader,
    ): array {
        $ttl = (int) config('analytics.municipality_health_cache_seconds', 300);
        if ($ttl <= 0) {
            return $loader();
        }

        $params = $filters->toQueryParamsWithCity((int) $city->id);
        ksort($params);
        $cacheKey = 'analytics:municipality_health:v2:'.$variant.':'.(int) $city->id.':'.md5(json_encode($params));

        try {
            return Cache::remember($cacheKey, $ttl, $loader);
        } catch (\Throwable $e) {
            Log::warning('analytics.municipality_health_cache_failed', [
                'city_id' => $city->id,
                'variant' => $variant,
                'message' => $e->getMessage(),
            ]);

            return $loader();
        }
    }

    /**
     * Diagnóstico estratégico: uma passagem leve, reutilizando cache de outras abas quando existir.
     *
     * @param  array<string, mixed>  $empty
     * @return array<string, mixed>
     */
    private function snapshotStrategic(City $city, IeducarFilterState $filters, array $empty): array
    {
        try {
            $disc = $this->resolveDiscrepanciesPayload($city, $filters);
            $totalMat = (int) ($disc['total_matriculas'] ?? 0);
            $discForFundeb = [
                'summary' => is_array($disc['summary'] ?? null) ? $disc['summary'] : [],
                'funding_reference' => is_array($disc['funding_reference'] ?? null) ? $disc['funding_reference'] : null,
            ];

            $fundeb = $this->resolveFundebPayload($city, $filters, $totalMat, $discForFundeb);
            $otherFunding = $this->resolveOtherFundingPayload($city, $filters);
            $workDone = $this->resolveWorkDonePayload($city, $filters);
            $inclusion = AnalyticsTabPayloadCache::get(AnalyticsTabPayloadCache::INCLUSION, $city, $filters) ?? [];

            $thematicBlocks = ConsultoriaThematicBridge::buildStrategicBlocks(
                $disc,
                $fundeb,
                $totalMat,
                $otherFunding,
                $workDone,
                is_array($inclusion) ? $inclusion : [],
            );

            $payload = $this->assemble(
                $city,
                $filters,
                $disc,
                $fundeb,
                $otherFunding,
                $workDone,
                is_array($inclusion) ? $inclusion : [],
                [],
                [],
                $totalMat,
                strategicIntro: true,
            );

            return array_merge($payload, [
                'thematic_blocks' => $thematicBlocks,
                'strategic_mode' => true,
            ]);
        } catch (\Throwable $e) {
            return array_merge($empty, [
                'city_name' => $city->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDiscrepanciesPayload(City $city, IeducarFilterState $filters): array
    {
        $cached = AnalyticsTabPayloadCache::get(AnalyticsTabPayloadCache::DISCREPANCIES, $city, $filters);
        if (is_array($cached)) {
            return $cached;
        }

        $disc = $this->discrepancies->snapshot($city, $filters, forDiagnosis: true);
        $this->storeDiscInCache($city, $filters, $disc);

        return $disc;
    }

    /**
     * @param  array<string, mixed>  $discForFundeb
     * @return array<string, mixed>
     */
    private function resolveFundebPayload(
        City $city,
        IeducarFilterState $filters,
        int $totalMat,
        array $discForFundeb,
    ): array {
        $cached = AnalyticsTabPayloadCache::get(AnalyticsTabPayloadCache::FUNDEB, $city, $filters);
        if (is_array($cached) && is_array($cached['modules'] ?? null)) {
            return $cached;
        }

        return $this->fundeb->buildDiagnosisSlice($city, $filters, $totalMat, $discForFundeb);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOtherFundingPayload(City $city, IeducarFilterState $filters): array
    {
        $cached = AnalyticsTabPayloadCache::get(AnalyticsTabPayloadCache::OTHER_FUNDING, $city, $filters);
        if (is_array($cached)) {
            return $cached;
        }

        return [
            'programs' => [],
            'public_municipal' => ['queries' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveWorkDonePayload(City $city, IeducarFilterState $filters): array
    {
        $cached = AnalyticsTabPayloadCache::get(AnalyticsTabPayloadCache::WORK_DONE, $city, $filters);
        if (is_array($cached)) {
            return $cached;
        }

        return [
            'periods' => [],
            'estimativa' => [],
            'activity_available' => false,
            'activity_note' => __('Abra a aba Censo para ritmo de cadastro e exportação Educacenso.'),
            'censo' => ['available' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $empty
     * @return array<string, mixed>
     */
    private function snapshotShell(City $city, IeducarFilterState $filters, array $empty): array
    {
        try {
            $disc = $this->resolveDiscrepanciesPayload($city, $filters);
            $overview = $this->overview->summary($city, $filters);
            $totalMat = (int) ($disc['total_matriculas'] ?? $overview['kpis']['matriculas'] ?? 0);
            $fundebRef = DiscrepanciesFundingImpact::resolveReference($city, $filters);
            $fundebStub = [
                'modules' => [],
                'resource_projection' => [],
                'fundeb_reference' => $fundebRef,
            ];

            $payload = $this->assemble(
                $city,
                $filters,
                $disc,
                $fundebStub,
                ['programs' => [], 'public_municipal' => ['queries' => []]],
                ['periods' => [], 'estimativa' => [], 'activity_available' => false],
                [],
                [],
                [],
                $totalMat,
                shellOnly: true,
            );

            return array_merge($payload, [
                'progressive' => true,
                'sections_pending' => MunicipalityHealthSections::deferred(),
            ]);
        } catch (\Throwable $e) {
            return array_merge($empty, [
                'city_name' => $city->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSectionFundeb(City $city, IeducarFilterState $filters): array
    {
        $overview = $this->overview->summary($city, $filters);
        $enrollment = $this->enrollment->sample($city, $filters);
        $discForFundeb = $this->discrepancies->fundingImpactSnapshot($city, $filters);
        $fundeb = $this->fundeb->buildReport(
            $city,
            $filters,
            $overview,
            $enrollment,
            [],
            [],
            [],
            [],
            is_array($discForFundeb) ? $discForFundeb : null,
        );

        $modules = is_array($fundeb['modules'] ?? null) ? $fundeb['modules'] : [];
        $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : [];
        $fundebRef = is_array($fundeb['fundeb_reference'] ?? null)
            ? $fundeb['fundeb_reference']
            : DiscrepanciesFundingImpact::resolveReference($city, $filters);
        $fundingDisplay = is_array($discForFundeb) && is_array($discForFundeb['funding_reference'] ?? null)
            ? $discForFundeb['funding_reference']
            : DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);

        $shell = $this->cachedShellOrReload($city, $filters);
        $cadastro = is_array($shell['cadastro_dimensions'] ?? null) ? $shell['cadastro_dimensions'] : [];
        $score = $this->computeComplianceScore($cadastro, $modules);
        [$status, $label] = match (true) {
            $score >= 80 => ['success', __('Boa conformidade')],
            $score >= 55 => ['warning', __('Atenção — pendências relevantes')],
            default => ['danger', __('Situação crítica')],
        };
        $modulosAlerta = 0;
        foreach ($modules as $m) {
            if (in_array((string) ($m['status'] ?? ''), ['danger', 'warning'], true)) {
                $modulosAlerta++;
            }
        }

        return [
            'vaaf_comparacao' => is_array($proj['vaaf_comparacao'] ?? null)
                ? $proj['vaaf_comparacao']
                : FundebReferenceDisplay::vaafComparacao($fundebRef),
            'previsao_comparacao' => $proj['previsao_comparacao'] ?? null,
            'divergencia_vaaf' => is_array($proj['divergencia_vaaf'] ?? null)
                ? $proj['divergencia_vaaf']
                : (is_array($fundingDisplay['divergencia_vaaf'] ?? null)
                    ? $fundingDisplay['divergencia_vaaf']
                    : (is_array($fundebRef['divergencia'] ?? null) ? $fundebRef['divergencia'] : null)),
            'funding_reference' => $fundebRef,
            'fundeb_modules' => array_map(static fn (array $m): array => [
                'id' => (string) ($m['id'] ?? ''),
                'title' => (string) ($m['title'] ?? ''),
                'status' => (string) ($m['status'] ?? 'neutral'),
                'reference' => (string) ($m['reference'] ?? ''),
                'situacao' => (string) ($m['situacao'] ?? ''),
            ], $modules),
            'compliance_score' => $score,
            'compliance_status' => $status,
            'compliance_label' => $label,
            'summary_patch' => [
                'modulos_fundeb_alerta' => $modulosAlerta,
            ],
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSectionProgramas(City $city, IeducarFilterState $filters): array
    {
        $otherFunding = $this->otherFunding->buildReport($city, $filters);
        $complementaryPrograms = self::summarizeComplementaryPrograms($otherFunding);
        $activeProgramIds = array_values(array_filter(
            array_map(
                static fn (array $p): string => (string) ($p['id'] ?? ''),
                array_filter($complementaryPrograms, static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['warning', 'danger'], true))
            ),
            static fn (string $id): bool => $id !== ''
        ));
        $publicQueries = is_array($otherFunding['public_municipal'] ?? null) ? $otherFunding['public_municipal'] : [];
        $publicQueriesOk = (int) count(array_filter(
            is_array($publicQueries['queries'] ?? null) ? $publicQueries['queries'] : [],
            static fn ($q): bool => is_array($q) && ($q['status'] ?? '') === 'success'
        ));

        $shell = $this->cachedShellOrReload($city, $filters);

        return [
            'complementary_programs' => $complementaryPrograms,
            'programas_alerta' => count($activeProgramIds),
            'active_program_ids' => $activeProgramIds,
            'active_check_ids' => is_array($shell['active_check_ids'] ?? null) ? $shell['active_check_ids'] : [],
            'other_funding_programs' => count($complementaryPrograms),
            'public_queries_success' => $publicQueriesOk,
            'summary_patch' => [
                'programas_alerta' => count($activeProgramIds),
            ],
            'error' => $otherFunding['error'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSectionTematico(City $city, IeducarFilterState $filters): array
    {
        $overview = $this->overview->summary($city, $filters);
        $enrollment = $this->enrollment->sample($city, $filters);
        $performance = $this->performance->snapshot($city, $filters);
        $attendance = $this->attendance->snapshot($city, $filters);
        $inclusion = $this->inclusion->snapshot($city, $filters);
        $network = $this->network->snapshot($city, $filters);
        $disc = $this->cachedDiscPayload($city, $filters)
            ?? $this->discrepancies->snapshot($city, $filters);
        $discForFundeb = [
            'summary' => is_array($disc['summary'] ?? null) ? $disc['summary'] : [],
            'funding_reference' => is_array($disc['funding_reference'] ?? null) ? $disc['funding_reference'] : null,
        ];
        $fundeb = $this->fundeb->buildReport(
            $city,
            $filters,
            $overview,
            $enrollment,
            $performance,
            $attendance,
            $inclusion,
            $network,
            $discForFundeb,
        );
        $otherFunding = $this->otherFunding->buildReport($city, $filters);
        $workDone = $this->workDone->buildReport($city, $filters);
        $totalMat = (int) ($disc['total_matriculas'] ?? $overview['kpis']['matriculas'] ?? 0);
        $workPeriods = is_array($workDone['periods'] ?? null) ? $workDone['periods'] : [];

        return [
            'thematic_blocks' => ConsultoriaThematicBridge::buildBlocks(
                $inclusion,
                $fundeb,
                $performance,
                $disc,
                $totalMat,
                is_array($network['kpis'] ?? null) ? $network['kpis'] : null,
                $otherFunding,
                $workDone,
            ),
            'work_done_available' => (bool) ($workDone['activity_available'] ?? false),
            'summary_patch' => [
                'recurso_prova_sem_nee' => (int) data_get($inclusion, 'recurso_prova.sem_nee', 0),
                'cadastros_quinzena' => (int) ($workPeriods['fortnight'] ?? 0),
                'ritmo_cadastro_dia' => (float) ($workDone['estimativa']['ritmo_por_dia'] ?? 0),
            ],
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $disc
     */
    private function storeDiscInCache(City $city, IeducarFilterState $filters, array $disc): void
    {
        $ttl = (int) config('analytics.municipality_health_cache_seconds', 300);
        if ($ttl <= 0) {
            return;
        }

        try {
            Cache::put($this->discCacheKey($city, $filters), $disc, $ttl);
        } catch (\Throwable $e) {
            Log::warning('analytics.municipality_health_disc_cache_failed', [
                'city_id' => $city->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cachedDiscPayload(City $city, IeducarFilterState $filters): ?array
    {
        try {
            $cached = Cache::get($this->discCacheKey($city, $filters));

            return is_array($cached) ? $cached : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function discCacheKey(City $city, IeducarFilterState $filters): string
    {
        $params = $filters->toQueryParamsWithCity((int) $city->id);
        ksort($params);

        return 'analytics:municipality_health:disc:'.(int) $city->id.':'.md5(json_encode($params));
    }

    /**
     * @return array<string, mixed>
     */
    private function cachedShellOrReload(City $city, IeducarFilterState $filters): array
    {
        if (! MunicipalityHealthSections::progressiveEnabled()) {
            return [];
        }

        $params = $filters->toQueryParamsWithCity((int) $city->id);
        ksort($params);
        $cacheKey = 'analytics:municipality_health:shell:'.(int) $city->id.':'.md5(json_encode($params));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        return $this->snapshotShell($city, $filters, [
            'intro' => '',
            'footnote' => '',
            'year_label' => '',
            'city_name' => '',
            'compliance_score' => null,
            'compliance_status' => 'neutral',
            'compliance_label' => '',
            'summary' => [],
            'cadastro_dimensions' => [],
            'thematic_blocks' => [],
            'active_check_ids' => [],
            'public_data_sources' => [],
            'fundeb_modules' => [],
            'top_problems' => [],
            'chart_pendencias' => null,
            'error' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $empty
     * @return array<string, mixed>
     */
    private function snapshotFresh(City $city, IeducarFilterState $filters, array $empty): array
    {
        try {
            $overview = $this->overview->summary($city, $filters);
            $enrollment = $this->enrollment->sample($city, $filters);
            $performance = $this->performance->snapshot($city, $filters);
            $attendance = $this->attendance->snapshot($city, $filters);
            $inclusion = $this->inclusion->snapshot($city, $filters);
            $network = $this->network->snapshot($city, $filters);
            $disc = $this->discrepancies->snapshot($city, $filters);
            $fundeb = $this->fundeb->buildReport(
                $city,
                $filters,
                $overview,
                $enrollment,
                $performance,
                $attendance,
                $inclusion,
                $network,
                $disc,
            );
            $otherFunding = $this->otherFunding->buildReport($city, $filters);
            $workDone = $this->workDone->buildReport($city, $filters);

            $totalMat = (int) ($disc['total_matriculas'] ?? $overview['kpis']['matriculas'] ?? 0);

            return $this->assemble($city, $filters, $disc, $fundeb, $otherFunding, $workDone, $inclusion, $performance, $network, $totalMat);
        } catch (\Throwable $e) {
            return array_merge($empty, [
                'city_name' => $city->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $disc
     * @param  array<string, mixed>  $fundeb
     * @param  array<string, mixed>  $otherFunding
     * @param  array<string, mixed>  $workDone
     * @param  array<string, mixed>  $inclusion
     * @param  array<string, mixed>  $performance
     * @return array<string, mixed>
     */
    private function assemble(
        City $city,
        IeducarFilterState $filters,
        array $disc,
        array $fundeb,
        array $otherFunding,
        array $workDone,
        array $inclusion,
        array $performance,
        array $network,
        int $totalMat,
        bool $shellOnly = false,
        bool $strategicIntro = false,
    ): array {
        $checks = is_array($disc['checks'] ?? null) ? $disc['checks'] : [];
        $dimensions = is_array($disc['dimensions'] ?? null) ? $disc['dimensions'] : [];
        $discSummary = is_array($disc['summary'] ?? null) ? $disc['summary'] : [];
        $modules = is_array($fundeb['modules'] ?? null) ? $fundeb['modules'] : [];

        $cadastroDimensions = $dimensions !== [] ? $dimensions : $this->legacyDimensionsFromChecks($checks);

        $discLoadFailed = filled($disc['error'] ?? null) && $cadastroDimensions === [];

        $score = $discLoadFailed ? null : $this->computeComplianceScore($cadastroDimensions, $modules);

        if ($discLoadFailed) {
            $status = 'neutral';
            $label = __('Dados indisponíveis');
        } else {
            [$status, $label] = match (true) {
                $score >= 80 => ['success', __('Boa conformidade')],
                $score >= 55 => ['warning', __('Atenção — pendências relevantes')],
                default => ['danger', __('Situação crítica')],
            };
        }

        $topProblems = self::buildTopProblems($checks, $cadastroDimensions);

        $pendencias = count(array_filter(
            $cadastroDimensions,
            static fn (array $d): bool => ($d['has_issue'] ?? false) === true
        ));

        $modulosAlerta = 0;
        foreach ($modules as $m) {
            if (in_array((string) ($m['status'] ?? ''), ['danger', 'warning'], true)) {
                $modulosAlerta++;
            }
        }

        $proj = is_array($fundeb['resource_projection'] ?? null) ? $fundeb['resource_projection'] : [];
        $fundebRef = is_array($fundeb['fundeb_reference'] ?? null)
            ? $fundeb['fundeb_reference']
            : DiscrepanciesFundingImpact::resolveReference($city, $filters);
        $fundingDisplay = is_array($disc['funding_reference'] ?? null)
            ? $disc['funding_reference']
            : DiscrepanciesFundingImpact::fundingReferencePayload($city, $filters);
        $vaafComparacao = $shellOnly
            ? null
            : (is_array($proj['vaaf_comparacao'] ?? null)
                ? $proj['vaaf_comparacao']
                : FundebReferenceDisplay::vaafComparacao($fundebRef));
        $previsaoComparacao = $shellOnly
            ? null
            : (is_array($proj['previsao_comparacao'] ?? null)
                ? $proj['previsao_comparacao']
                : ($totalMat > 0 ? FundebReferenceDisplay::previsaoComparacao($totalMat, $fundebRef, $city, $filters) : null));
        $divergenciaVaaf = $shellOnly
            ? null
            : (is_array($proj['divergencia_vaaf'] ?? null)
                ? $proj['divergencia_vaaf']
                : (is_array($fundingDisplay['divergencia_vaaf'] ?? null)
                    ? $fundingDisplay['divergencia_vaaf']
                    : (is_array($fundebRef['divergencia'] ?? null) ? $fundebRef['divergencia'] : null)));

        $workPeriods = is_array($workDone['periods'] ?? null) ? $workDone['periods'] : [];
        $censoBlock = is_array($workDone['censo'] ?? null) ? $workDone['censo'] : [];
        $censoSummary = is_array($censoBlock['summary'] ?? null) ? $censoBlock['summary'] : [];
        $cadastrosQuinzena = $shellOnly ? 0 : (int) ($workPeriods['fortnight'] ?? 0);
        $censoPendentes = $shellOnly ? 0 : (int) ($censoSummary['pendentes'] ?? 0);
        $complementaryPrograms = $shellOnly ? [] : self::summarizeComplementaryPrograms($otherFunding);
        $activeProgramIds = array_values(array_filter(
            array_map(
                static fn (array $p): string => (string) ($p['id'] ?? ''),
                array_filter($complementaryPrograms, static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['warning', 'danger'], true))
            ),
            static fn (string $id): bool => $id !== ''
        ));
        $publicQueries = is_array($otherFunding['public_municipal'] ?? null) ? $otherFunding['public_municipal'] : [];
        $publicQueriesOk = (int) count(array_filter(
            is_array($publicQueries['queries'] ?? null) ? $publicQueries['queries'] : [],
            static fn ($q): bool => is_array($q) && ($q['status'] ?? '') === 'success'
        ));

        $intro = $strategicIntro
            ? __(
                'Visão estratégica: prioridades de cadastro (Discrepâncias), referência VAAF/FUNDEB e ligações às abas de detalhe. Dados de Financiamentos, Censo ou pedagógicos são reutilizados quando já abriu essas abas no mesmo filtro; caso contrário, aprofunde nelas sem repetir consultas aqui.'
            )
            : __(
                'Painel de consultoria municipal: consolida cadastro (Discrepâncias), VAAF municipal × prévia federal, programas complementares (PNAE/PNATE), ritmo de cadastro no i-Educar, FUNDEB/VAAR e indicadores INEP quando disponíveis.'
            );

        return [
            'intro' => $intro,
            'footnote' => __(
                'Índice 0–100: mesma base das discrepâncias (volume + gravidade) e alertas FUNDEB. Verde = rotina executada sem pendências; cinza = rotina indisponível nesta base; amarelo/vermelho = pendência detectada.'
            ),
            'year_label' => $this->yearLabel($filters),
            'city_name' => $city->name,
            'compliance_score' => $score,
            'compliance_status' => $status,
            'compliance_label' => $label,
            'summary' => [
                'pendencias_cadastro' => $pendencias,
                'modulos_fundeb_alerta' => $modulosAlerta,
                'com_problema' => (int) ($discSummary['com_problema'] ?? 0),
                'corrigiveis' => (int) ($discSummary['corrigiveis'] ?? 0),
                'perda_estimada_anual' => (float) ($discSummary['perda_estimada_anual'] ?? 0),
                'ganho_potencial_anual' => (float) ($discSummary['ganho_potencial_anual'] ?? 0),
                'escolas_afetadas' => (int) ($discSummary['escolas_afetadas'] ?? 0),
                'total_matriculas' => $totalMat > 0 ? $totalMat : ($disc['total_matriculas'] ?? null),
                'recurso_prova_sem_nee' => $shellOnly
                    ? 0
                    : (int) (data_get($inclusion, 'recurso_prova.sem_nee', 0) ?: self::dimensionOccurrenceTotal($disc, 'recurso_prova_sem_nee')),
                'cadastros_quinzena' => $cadastrosQuinzena,
                'censo_pendentes' => $censoPendentes,
                'ritmo_cadastro_dia' => $shellOnly ? 0.0 : (float) ($workDone['estimativa']['ritmo_por_dia'] ?? 0),
            ],
            'funding_reference' => $fundebRef,
            'funding_display' => $fundingDisplay,
            'vaaf_comparacao' => $vaafComparacao,
            'previsao_comparacao' => $previsaoComparacao,
            'divergencia_vaaf' => $divergenciaVaaf,
            'other_funding_programs' => count($complementaryPrograms),
            'programas_alerta' => count($activeProgramIds),
            'complementary_programs' => $complementaryPrograms,
            'active_program_ids' => $activeProgramIds,
            'public_queries_success' => $shellOnly ? 0 : $publicQueriesOk,
            'work_done_available' => $shellOnly ? false : (bool) ($workDone['activity_available'] ?? false),
            'cadastro_dimensions' => $cadastroDimensions,
            'active_check_ids' => is_array($disc['active_check_ids'] ?? null) ? $disc['active_check_ids'] : [],
            'thematic_blocks' => $shellOnly
                ? []
                : ConsultoriaThematicBridge::buildBlocks(
                    $inclusion,
                    $fundeb,
                    $performance,
                    $disc,
                    $totalMat,
                    is_array($network['kpis'] ?? null) ? $network['kpis'] : null,
                    $otherFunding,
                    $workDone,
                ),
            'public_data_sources' => PublicDataSourcesCatalog::build($city, 'all'),
            'fundeb_modules' => array_map(static fn (array $m): array => [
                'id' => (string) ($m['id'] ?? ''),
                'title' => (string) ($m['title'] ?? ''),
                'status' => (string) ($m['status'] ?? 'neutral'),
                'reference' => (string) ($m['reference'] ?? ''),
                'situacao' => (string) ($m['situacao'] ?? ''),
            ], $modules),
            'top_problems' => $topProblems,
            'funding_metodologia' => is_array($disc['funding_metodologia'] ?? null)
                ? $disc['funding_metodologia']
                : DiscrepanciesFundingImpact::metodologiaResumo($city, $filters),
            'funding_resumo_explicacao' => is_array($disc['funding_resumo_explicacao'] ?? null)
                ? $disc['funding_resumo_explicacao']
                : DiscrepanciesFundingImpact::explicacaoResumoAgregado(
                    (int) ($discSummary['com_problema'] ?? $pendencias),
                    (float) ($discSummary['perda_estimada_anual'] ?? 0),
                    (float) ($discSummary['ganho_potencial_anual'] ?? 0),
                    count(array_filter($cadastroDimensions, static fn (array $d): bool => (bool) ($d['has_issue'] ?? false))),
                ),
            'error' => $discLoadFailed ? (string) $disc['error'] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @param  list<array<string, mixed>>  $modules
     */
    private function computeComplianceScore(array $dimensions, array $modules): int
    {
        $score = 100.0;
        foreach ($dimensions as $d) {
            if (! is_array($d)) {
                continue;
            }
            $avail = (string) ($d['availability'] ?? '');
            if ($avail === 'unavailable' || $avail === 'no_data' || ($d['status'] ?? '') === 'no_data') {
                continue;
            }
            $status = (string) ($d['status'] ?? '');
            $hasIssue = ($d['has_issue'] ?? false) === true
                || in_array($status, ['danger', 'warning'], true);
            if (! $hasIssue) {
                continue;
            }
            $occurrences = (int) ($d['occurrences_total'] ?? $d['total'] ?? 0);
            $pct = (float) ($d['pct_rede'] ?? 0);
            if ($pct <= 0 && $occurrences > 0) {
                $pct = min(100.0, (float) $occurrences);
            }
            $perda = (float) ($d['perda_estimada_anual'] ?? 0);
            $severity = (string) ($d['severity'] ?? 'warning');
            $deduction = match ($severity) {
                'danger' => min(35.0, max(8.0, $pct * 1.15)),
                'warning' => min(22.0, max(5.0, $pct * 0.75)),
                default => min(12.0, max(3.0, $pct * 0.35)),
            };
            if ($perda > 0) {
                $deduction = max($deduction, min(28.0, 6.0 + log10($perda + 1) * 4.0));
            }
            $score -= $deduction;
        }
        foreach ($modules as $m) {
            if (! is_array($m)) {
                continue;
            }
            $score -= match ((string) ($m['status'] ?? 'neutral')) {
                'danger' => 8.0,
                'warning' => 4.0,
                default => 0.0,
            };
        }

        return (int) max(0, min(100, round($score)));
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return list<array<string, mixed>>
     */
    private function legacyDimensionsFromChecks(array $checks): array
    {
        $byId = [];
        foreach ($checks as $c) {
            $byId[(string) ($c['id'] ?? '')] = $c;
        }
        $out = [];
        foreach (\App\Support\Ieducar\DiscrepanciesCheckCatalog::definitions() as $id => $def) {
            $found = $byId[$id] ?? null;
            $out[] = [
                'id' => $id,
                'title' => (string) ($def['title'] ?? ''),
                'vaar_refs' => $def['vaar_refs'] ?? [],
                'availability' => $found !== null ? 'available' : 'unavailable',
                'has_issue' => $found !== null,
                'detected' => $found !== null,
                'total' => (int) ($found['total'] ?? 0),
                'pct_rede' => $found['pct_rede'] ?? null,
                'ganho_potencial_anual' => (float) ($found['ganho_potencial_anual'] ?? 0),
                'status' => $found === null ? 'unavailable' : ((string) ($found['severity'] ?? '') === 'danger' ? 'danger' : 'warning'),
                'unavailable_reason' => null,
                'severity' => (string) ($def['severity'] ?? 'warning'),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @param  list<array<string, mixed>>  $dimensions
     * @return list<array<string, mixed>>
     */
    private function buildTopProblems(array $checks, array $dimensions): array
    {
        $byId = [];
        foreach ($checks as $c) {
            if (! ($c['has_issue'] ?? $c['detected'] ?? false)) {
                continue;
            }
            $byId[(string) ($c['id'] ?? '')] = $c;
        }
        foreach ($dimensions as $d) {
            if (! ($d['has_issue'] ?? false)) {
                continue;
            }
            $id = (string) ($d['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (isset($byId[$id])) {
                if (($d['pct_rede'] ?? null) !== null && ! isset($byId[$id]['pct_rede'])) {
                    $byId[$id]['pct_rede'] = $d['pct_rede'];
                }

                continue;
            }
            $total = (int) ($d['total'] ?? 0);
            $byId[$id] = [
                'id' => $id,
                'title' => (string) ($d['title'] ?? ''),
                'total' => $total,
                'pct_rede' => $d['pct_rede'] ?? null,
                'perda_estimada_anual' => (float) ($d['perda_estimada_anual'] ?? 0),
                'ganho_potencial_anual' => (float) ($d['ganho_potencial_anual'] ?? 0),
                'funding_explicacao' => is_array($d['funding_explicacao'] ?? null) ? $d['funding_explicacao'] : null,
                'severity' => (string) ($d['severity'] ?? 'warning'),
                'is_erro' => ($d['severity'] ?? '') === 'danger',
            ];
        }

        $top = array_values(array_filter(
            $byId,
            static fn (array $row): bool => (int) ($row['total'] ?? 0) > 0
                || (float) ($row['perda_estimada_anual'] ?? 0) > 0
                || (float) ($row['ganho_potencial_anual'] ?? 0) > 0,
        ));
        usort($top, static fn (array $a, array $b): int => ((float) ($b['perda_estimada_anual'] ?? $b['ganho_potencial_anual'] ?? 0))
            <=> ((float) ($a['perda_estimada_anual'] ?? $a['ganho_potencial_anual'] ?? 0)));

        return array_slice($top, 0, 8);
    }

    /**
     * @param  array<string, mixed>  $disc
     */
    private static function dimensionOccurrenceTotal(array $disc, string $id): int
    {
        foreach ($disc['dimensions'] ?? [] as $d) {
            if (! is_array($d) || ($d['id'] ?? '') !== $id) {
                continue;
            }

            return (int) ($d['occurrences_total'] ?? $d['total'] ?? 0);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $otherFunding
     * @return list<array<string, mixed>>
     */
    private static function summarizeComplementaryPrograms(array $otherFunding): array
    {
        $programs = is_array($otherFunding['programs'] ?? null) ? $otherFunding['programs'] : [];
        $out = [];
        foreach ($programs as $prog) {
            if (! is_array($prog)) {
                continue;
            }
            $kpis = is_array($prog['kpis'] ?? null) ? $prog['kpis'] : [];
            $status = (string) ($prog['status'] ?? 'neutral');
            $cobertura = null;
            foreach ($kpis as $k) {
                if (is_array($k) && ($k['label'] ?? '') === __('Preenchimento indicativo')) {
                    $v = (string) ($k['value'] ?? '');
                    $cobertura = str_ends_with($v, '%') ? (float) rtrim($v, '%') : null;
                    break;
                }
            }
            $resumo = match ($status) {
                'success' => __('Cobertura de cadastro adequada no i-Educar.'),
                'warning' => __('Cobertura parcial — rever campos antes do Censo.'),
                'danger' => __('Cobertura baixa ou campo não detectado na base.'),
                default => filled($prog['descricao'] ?? null) ? (string) $prog['descricao'] : __('Sem colunas configuradas detectadas.'),
            };
            if ($cobertura !== null) {
                $resumo = __('Cobertura indicativa :pct% das matrículas no filtro.', ['pct' => number_format($cobertura, 1, ',', '.')]);
            }
            $out[] = [
                'id' => (string) ($prog['id'] ?? ''),
                'titulo' => (string) ($prog['titulo'] ?? ''),
                'status' => $status,
                'status_label' => match ($status) {
                    'success' => __('Adequado'),
                    'warning' => __('Atenção'),
                    'danger' => __('Crítico'),
                    default => __('Neutro'),
                },
                'resumo' => $resumo,
                'cobertura_pct' => $cobertura,
            ];
        }

        return $out;
    }

    private function yearLabel(IeducarFilterState $filters): string
    {
        if (! $filters->hasYearSelected()) {
            return '';
        }
        if ($filters->isAllSchoolYears()) {
            return __('Todos os anos (consolidado)');
        }

        return __('Ano letivo :year', ['year' => (string) $filters->ano_letivo]);
    }
}
