<?php

namespace App\Services\Horizonte;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\SaebIndicatorPoint;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Brazil\IbgeUfFromCode;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteManagerInsights;
use App\Support\Horizonte\HorizonteMapPresenter;
use App\Support\Horizonte\HorizonteMunicipalSgeResolver;
use Illuminate\Support\Facades\DB;

/**
 * Mapa Horizonte — municípios com e sem Consultoria, scores de oportunidade e regiões prioritárias.
 */
final class HorizonteMapService
{
    public function __construct(
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
        private readonly HorizonteOpportunityScorer $scorer,
        private readonly HorizonteMunicipalSgeResolver $sgeResolver,
        private readonly HorizonteMunicipalSgeRegistryService $sgeRegistry,
    ) {}

    /**
     * @return array{
     *     reference_year: int,
     *     generated_at: string,
     *     markers: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     uf_rankings: list<array<string, mixed>>,
     *     top_prospects: list<array<string, mixed>>,
     *     colors: array<string, string>,
     *     legend: list<array<string, mixed>>
     * }
     */
    public function build(): array
    {
        if (! (bool) config('horizonte.enabled', true)) {
            return $this->emptyPayload();
        }

        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);
        $cacheKey = 'horizonte:map:v2:'.$refYear.':'.$this->dataFingerprint();

        $cached = AdminHomeMapCache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->assemble($refYear);
        AdminHomeMapCache::repository()->put(
            $cacheKey,
            $payload,
            max(60, (int) config('horizonte.cache_seconds', 900)),
        );

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function assemble(int $refYear): array
    {
        $citiesByIbge = $this->citiesByIbge();
        $ibgeSet = $this->collectIbgeCodes(array_keys($citiesByIbge));

        $fundebByIbge = $this->fundebByIbge($refYear);
        $censoByIbge = $this->censoByIbge($refYear);
        $saebByIbge = $this->saebByIbge($refYear);

        foreach (array_keys($fundebByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }
        foreach (array_keys($censoByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }
        foreach (array_keys($saebByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }

        $ufs = IbgeUfFromCode::ufsFromIbgeCodes(array_keys($ibgeSet));
        foreach ($citiesByIbge as $city) {
            $ufs[] = strtoupper((string) $city['uf']);
        }
        $ufs = array_values(array_unique(array_filter($ufs)));
        if ($ufs === []) {
            $ufs = IbgeMunicipalityCatalog::brazilianUfs();
        }
        $ibgeMetaIndex = $this->ibgeCatalog->metaIndexForUfs($ufs);

        $saebForBench = [];
        $complRatios = [];
        foreach (array_keys($ibgeSet) as $ibge) {
            $fundeb = $fundebByIbge[$ibge] ?? null;
            $saeb = $saebByIbge[$ibge] ?? null;
            if ($saeb !== null) {
                foreach (['lp', 'mat'] as $k) {
                    if ($saeb[$k] !== null) {
                        $saebForBench[] = (float) $saeb[$k];
                    }
                }
            }
            if ($fundeb !== null && ($fundeb['complementacao_total'] ?? 0) > 0 && ($fundeb['receita_total'] ?? 0) > 0) {
                $complRatios[] = (float) $fundeb['complementacao_total'] / (float) $fundeb['receita_total'];
            }
        }
        $benchmarks = $this->scorer->benchmarks($saebForBench, $complRatios);

        $sgeRegistry = $this->sgeRegistry->indexedFromCache();

        $high = (int) config('horizonte.high_opportunity_threshold', 70);
        $medium = (int) config('horizonte.medium_opportunity_threshold', 40);

        $markers = [];
        foreach (array_keys($ibgeSet) as $ibge) {
            $city = $citiesByIbge[$ibge] ?? null;
            $fundeb = $fundebByIbge[$ibge] ?? null;
            $censo = $censoByIbge[$ibge] ?? null;
            $saeb = $saebByIbge[$ibge] ?? null;

            $meta = null;
            if ($city !== null) {
                $meta = [
                    'ibge' => $ibge,
                    'name' => (string) $city['name'],
                    'uf' => strtoupper((string) $city['uf']),
                    'lat' => (float) ($city['lat'] ?? 0),
                    'lng' => (float) ($city['lng'] ?? 0),
                ];
            }
            if ($meta === null || ! is_finite($meta['lat']) || ! is_finite($meta['lng']) || ($meta['lat'] === 0.0 && $meta['lng'] === 0.0)) {
                $fromIbge = $ibgeMetaIndex[$ibge] ?? null;
                if ($fromIbge !== null) {
                    $meta = $fromIbge;
                }
            }
            if ($meta === null || ! is_finite((float) ($meta['lat'] ?? 0))) {
                continue;
            }

            $consultoriaActive = (bool) ($city['consultoria_active'] ?? false);
            $inCatalog = $city !== null;

            $scoreInput = [
                'matriculas_censo' => $censo['matriculas_total'] ?? null,
                'complementacao_total' => $fundeb['complementacao_total'] ?? null,
                'receita_total' => $fundeb['receita_total'] ?? null,
                'saeb_lp' => $saeb['lp'] ?? null,
                'saeb_mat' => $saeb['mat'] ?? null,
                'has_fundeb' => $fundeb !== null,
                'has_censo' => $censo !== null,
                'has_saeb' => $saeb !== null,
                'consultoria_active' => $consultoriaActive,
                'in_catalog' => $inCatalog,
            ];
            $scores = $this->scorer->score($scoreInput, $benchmarks, $high, $medium);
            $rawHeat = $consultoriaActive ? 0.0 : ((int) $scores['success_score']) / 100;
            $minHeat = $consultoriaActive ? 0.0 : ($scores['tier'] === 'data_sparse' ? 0.08 : 0.0);
            $heatIntensity = max($minHeat, min(1.0, $rawHeat));

            $sge = $this->sgeResolver->resolve(
                $ibge,
                $city !== null ? array_merge($city, ['in_catalog' => true]) : null,
                $sgeRegistry[$ibge] ?? null,
            );

            $markers[] = [
                'ibge' => $ibge,
                'city_id' => $city['id'] ?? null,
                'name' => (string) $meta['name'],
                'uf' => strtoupper((string) ($meta['uf'] ?? $city['uf'] ?? '')),
                'lat' => (float) $meta['lat'],
                'lng' => (float) $meta['lng'],
                'tier' => $scores['tier'],
                'tier_label' => $scores['tier_label'],
                'success_score' => $scores['success_score'],
                'benefit_score' => $scores['benefit_score'],
                'financial_pressure' => $scores['financial_pressure'],
                'pedagogical_gap' => $scores['pedagogical_gap'],
                'scale_score' => $scores['scale_score'],
                'data_readiness' => $scores['data_readiness'],
                'heat_intensity' => round($heatIntensity, 3),
                'consultoria_active' => $consultoriaActive,
                'in_catalog' => $inCatalog,
                'has_fundeb' => $fundeb !== null,
                'has_censo' => $censo !== null,
                'has_saeb' => $saeb !== null,
                'matriculas_censo' => $censo['matriculas_total'] ?? null,
                'complementacao_fundeb' => $fundeb['complementacao_total'] ?? null,
                'saeb_lp' => $saeb['lp'] ?? null,
                'saeb_mat' => $saeb['mat'] ?? null,
                'analytics_url' => $city !== null && $consultoriaActive
                    ? route('dashboard.analytics', ['city_id' => $city['id']])
                    : null,
                'cities_url' => $city !== null ? route('cities.edit', $city['id']) : route('cities.create'),
                'sge' => $sge,
                'sge_found' => (bool) ($sge['found'] ?? false),
                'sge_system' => $sge['system'] ?? null,
                'sge_status' => $sge['status'] ?? 'not_found',
            ];
        }

        usort($markers, static fn (array $a, array $b): int => ($b['success_score'] <=> $a['success_score']) ?: strcasecmp((string) $a['name'], (string) $b['name']));

        $summary = $this->buildSummary($markers);
        $ufRankings = $this->buildUfRankings($markers);
        $topProspects = array_values(array_filter(
            $markers,
            static fn (array $m): bool => in_array($m['tier'], ['prospect_high', 'prospect_medium'], true),
        ));
        $topProspects = array_slice($topProspects, 0, 25);
        $coverage = HorizonteManagerInsights::dataCoverage($markers);
        $focusSegments = HorizonteManagerInsights::focusSegments($markers);

        return [
            'reference_year' => $refYear,
            'generated_at' => now()->toIso8601String(),
            'markers' => $markers,
            'summary' => array_merge($summary, ['coverage' => $coverage]),
            'uf_rankings' => $ufRankings,
            'top_prospects' => $topProspects,
            'focus_segments' => $focusSegments,
            'sge_summary' => HorizonteManagerInsights::sgeSummary($markers),
            'meta' => HorizonteMapPresenter::refreshMeta(count($markers), $coverage),
            'colors' => HorizonteMapPresenter::tierColors(),
            'legend' => HorizonteMapPresenter::legendItems(),
            'heat_legend' => HorizonteMapPresenter::heatLegendItems(),
        ];
    }

    /**
     * @return array<string, array{id: int, name: string, uf: string, consultoria_active: bool, lat: ?float, lng: ?float}>
     */
    private function citiesByIbge(): array
    {
        $coords = app(\App\Support\Brazil\MunicipalityMapCoordinates::class);
        $allCities = City::query()->orderBy('uf')->orderBy('name')->get();
        $byUf = $allCities->groupBy(fn (City $c) => strtoupper(trim((string) $c->uf)));

        $out = [];
        foreach ($allCities as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $uf = strtoupper(trim((string) $city->uf));
            $inUf = $byUf->get($uf, collect());
            $index = $inUf->search(fn (City $c): bool => (int) $c->id === (int) $city->id);
            $index = $index === false ? 0 : (int) $index;
            [$lat, $lng] = array_slice($coords->forCity($city, $index, max(1, $inUf->count())), 0, 2);

            $out[$ibge] = [
                'id' => (int) $city->id,
                'name' => (string) $city->name,
                'uf' => $uf,
                'consultoria_active' => (bool) $city->is_active && $city->hasDataSetup(),
                'has_data_setup' => $city->hasDataSetup(),
                'is_active' => (bool) $city->is_active,
                'ieducar_app_url' => $city->ieducar_app_url,
                'db_driver' => $city->effectiveIeducarDriver(),
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $seed
     * @return array<string, true>
     */
    private function collectIbgeCodes(array $seed): array
    {
        $set = [];
        foreach ($seed as $ibge) {
            $set[$ibge] = true;
        }

        foreach ([FundebMunicipioReference::class, InepCensoMunicipioMatricula::class] as $model) {
            $table = (new $model)->getTable();
            foreach (DB::table($table)->distinct()->pluck('ibge_municipio') as $raw) {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $raw);
                if ($ibge !== null) {
                    $set[$ibge] = true;
                }
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('saeb_indicator_points')) {
            foreach (DB::table('saeb_indicator_points')->whereNotNull('ibge_municipio')->distinct()->pluck('ibge_municipio') as $raw) {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $raw);
                if ($ibge !== null) {
                    $set[$ibge] = true;
                }
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('cadunico_municipio_snapshots')) {
            foreach (CadunicoMunicipioSnapshot::query()->distinct()->pluck('ibge_municipio') as $raw) {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $raw);
                if ($ibge !== null) {
                    $set[$ibge] = true;
                }
            }
        }

        return $set;
    }

    /**
     * @return array<string, array{complementacao_total: float, receita_total: ?float, matriculas_base: ?int}>
     */
    private function fundebByIbge(int $refYear): array
    {
        $years = [$refYear, $refYear - 1, $refYear - 2];
        $rows = FundebMunicipioReference::query()
            ->whereIn('ano', $years)
            ->orderByDesc('ano')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row->ibge_municipio);
            if ($ibge === null || isset($out[$ibge])) {
                continue;
            }
            $compl = (float) ($row->complementacao_vaaf ?? 0)
                + (float) ($row->complementacao_vaat ?? 0)
                + (float) ($row->complementacao_vaar ?? 0);
            $out[$ibge] = [
                'complementacao_total' => $compl,
                'receita_total' => $row->receita_total !== null ? (float) $row->receita_total : null,
                'matriculas_base' => $row->matriculas_base !== null ? (int) $row->matriculas_base : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{matriculas_total: int}>
     */
    private function censoByIbge(int $refYear): array
    {
        $years = [$refYear, $refYear - 1, $refYear - 2];
        $rows = InepCensoMunicipioMatricula::query()
            ->whereIn('ano', $years)
            ->orderByDesc('ano')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row->ibge_municipio);
            if ($ibge === null || isset($out[$ibge])) {
                continue;
            }
            $out[$ibge] = ['matriculas_total' => (int) $row->matriculas_total];
        }

        return $out;
    }

    /**
     * @return array<string, array{lp: ?float, mat: ?float}>
     */
    private function saebByIbge(int $refYear): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('saeb_indicator_points')) {
            return [];
        }

        $years = [$refYear, $refYear - 1, $refYear - 2, $refYear - 3];

        $rows = SaebIndicatorPoint::query()
            ->whereIn('ano', $years)
            ->whereNotNull('ibge_municipio')
            ->orderByDesc('ano')
            ->get(['ibge_municipio', 'disciplina', 'valor']);

        $out = [];
        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $disc = strtoupper(trim((string) $row->disciplina));
            $key = str_contains($disc, 'MAT') ? 'mat' : (str_contains($disc, 'LP') || str_contains($disc, 'LING') ? 'lp' : null);
            if ($key === null) {
                continue;
            }
            if (! isset($out[$ibge])) {
                $out[$ibge] = ['lp' => null, 'mat' => null];
            }
            if ($out[$ibge][$key] === null) {
                $out[$ibge][$key] = (float) $row->valor;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return array<string, mixed>
     */
    private function buildSummary(array $markers): array
    {
        $total = count($markers);
        $byTier = [];
        $withoutConsultoria = 0;
        $highProspect = 0;
        $prospectCount = 0;

        foreach ($markers as $m) {
            $tier = (string) ($m['tier'] ?? 'data_sparse');
            $byTier[$tier] = ($byTier[$tier] ?? 0) + 1;
            if (! ($m['consultoria_active'] ?? false)) {
                $withoutConsultoria++;
            }
            if ($tier === 'prospect_high') {
                $highProspect++;
            }
            if (str_starts_with($tier, 'prospect_')) {
                $prospectCount++;
            }
        }

        return [
            'total' => $total,
            'without_consultoria' => $withoutConsultoria,
            'consultoria_active' => $byTier['consultoria_active'] ?? 0,
            'high_prospect' => $highProspect,
            'prospect_count' => $prospectCount,
            'by_tier' => $byTier,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function buildUfRankings(array $markers): array
    {
        $byUf = [];
        foreach ($markers as $m) {
            $uf = (string) ($m['uf'] ?? '');
            if ($uf === '') {
                continue;
            }
            if (! isset($byUf[$uf])) {
                $byUf[$uf] = [
                    'uf' => $uf,
                    'total' => 0,
                    'benefit_sum' => 0,
                    'high_prospect' => 0,
                    'without_consultoria' => 0,
                ];
            }
            $byUf[$uf]['total']++;
            $byUf[$uf]['benefit_sum'] += (int) ($m['benefit_score'] ?? 0);
            if (($m['tier'] ?? '') === 'prospect_high') {
                $byUf[$uf]['high_prospect']++;
            }
            if (! ($m['consultoria_active'] ?? false)) {
                $byUf[$uf]['without_consultoria']++;
            }
        }

        $ranked = array_values($byUf);
        foreach ($ranked as &$row) {
            $row['avg_benefit'] = $row['total'] > 0
                ? (int) round($row['benefit_sum'] / $row['total'])
                : 0;
            unset($row['benefit_sum']);
        }
        unset($row);

        usort($ranked, static fn (array $a, array $b): int => ($b['avg_benefit'] <=> $a['avg_benefit']) ?: ($b['high_prospect'] <=> $a['high_prospect']));

        return array_slice($ranked, 0, 12);
    }

    private function dataFingerprint(): string
    {
        $parts = [];
        foreach ([
            [City::class, 'updated_at'],
            [FundebMunicipioReference::class, 'imported_at'],
            [InepCensoMunicipioMatricula::class, 'imported_at'],
            [SaebIndicatorPoint::class, 'updated_at'],
        ] as [$model, $col]) {
            if (! \Illuminate\Support\Facades\Schema::hasTable((new $model)->getTable())) {
                continue;
            }
            $row = DB::table((new $model)->getTable())
                ->selectRaw('count(*) as c, max('.$col.') as m')
                ->first();
            $parts[] = (new $model)->getTable().':'.((int) ($row->c ?? 0)).':'.(string) ($row->m ?? '');
        }

        return md5(implode('|', $parts));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'reference_year' => (int) config('horizonte.reference_year', (int) date('Y') - 1),
            'generated_at' => now()->toIso8601String(),
            'markers' => [],
            'summary' => [
                'total' => 0,
                'without_consultoria' => 0,
                'consultoria_active' => 0,
                'high_prospect' => 0,
                'prospect_count' => 0,
                'by_tier' => [],
                'coverage' => HorizonteManagerInsights::dataCoverage([]),
            ],
            'uf_rankings' => [],
            'top_prospects' => [],
            'focus_segments' => [],
            'sge_summary' => HorizonteManagerInsights::sgeSummary([]),
            'meta' => HorizonteMapPresenter::refreshMeta(0, HorizonteManagerInsights::dataCoverage([])),
            'colors' => HorizonteMapPresenter::tierColors(),
            'legend' => HorizonteMapPresenter::legendItems(),
            'heat_legend' => HorizonteMapPresenter::heatLegendItems(),
        ];
    }
}
