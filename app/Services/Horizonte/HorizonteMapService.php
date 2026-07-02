<?php

namespace App\Services\Horizonte;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\MunicipalDemographySnapshot;
use App\Models\SaebIndicatorPoint;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\MunicipalAreaSnapshotRepository;
use App\Services\Cadunico\CadunicoVulnerabilidadeIndicators;
use App\Services\Horizonte\HorizonteTesouroTransferSyncService;
use App\Support\Brazil\BrazilStateCapitals;
use App\Support\Brazil\BrazilUfCentroids;
use App\Support\Brazil\BrazilUfNames;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Brazil\IbgeUfFromCode;
use App\Support\Brazil\MunicipalityMapOverlapResolver;
use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteFundebRepasseOutlook;
use App\Support\Horizonte\HorizonteManagerInsights;
use App\Support\Horizonte\HorizonteUfFundebInsights;
use App\Support\Horizonte\HorizonteMapPresenter;
use App\Support\Horizonte\HorizonteTransferScoring;
use App\Support\Horizonte\HorizonteMapCacheBuster;
use App\Support\Horizonte\HorizonteSaebLookupYears;
use App\Support\Horizonte\HorizonteMunicipalAlertsResolver;
use App\Support\Horizonte\HorizonteMunicipalSgeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        private readonly HorizonteMunicipalAlertsSyncService $municipalAlerts,
        private readonly HorizonteMunicipalAlertsResolver $municipalAlertsResolver,
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

        $payload = $this->assemble($refYear, requireCoordinates: true);
        AdminHomeMapCache::repository()->put(
            $cacheKey,
            $payload,
            max(60, (int) config('horizonte.cache_seconds', 900)),
        );

        return $payload;
    }

    /**
     * Resposta para a UI GIS: overview nacional (sem marcadores municipais) ou recorte regional por UF.
     *
     * @return array<string, mixed>
     */
    public function buildForRequest(string $scope, ?string $uf): array
    {
        if (! (bool) config('horizonte.enabled', true)) {
            return $this->asOverviewPayload($this->emptyPayload());
        }

        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);
        $fingerprint = $this->dataFingerprint();
        $ttl = max(60, (int) config('horizonte.cache_seconds', 900));
        $uf = \App\Support\Horizonte\HorizonteUfScope::normalize($uf);

            if ($scope === 'regional' && $uf !== null) {
            $responseKey = 'horizonte:map:regional-response:v3:'.$refYear.':'.$uf.':'.$fingerprint;
            $cachedResponse = AdminHomeMapCache::get($responseKey);
            if (is_array($cachedResponse)) {
                return $this->attachNationalUfRankings($cachedResponse, $refYear, $fingerprint);
            }

            $regKey = 'horizonte:map:regional:v3:'.$refYear.':'.$uf.':'.$fingerprint;
            $regional = AdminHomeMapCache::get($regKey);
            if (! is_array($regional)) {
                $regional = $this->assemble($refYear, requireCoordinates: true, scopeUf: $uf);
                AdminHomeMapCache::repository()->put($regKey, $regional, $ttl);
            }

            $payload = $this->asRegionalPayload($regional, $uf);
            $payload = $this->attachNationalUfRankings($payload, $refYear, $fingerprint);
            AdminHomeMapCache::repository()->put($responseKey, $payload, $ttl);

            return $payload;
        }

        $overviewKey = 'horizonte:map:overview:v2:'.$refYear.':'.$fingerprint;
        $cachedOverview = AdminHomeMapCache::get($overviewKey);
        if (is_array($cachedOverview)) {
            return $cachedOverview;
        }

        $assembled = $this->assemble($refYear, requireCoordinates: false);
        $payload = $this->asOverviewPayload($assembled);
        AdminHomeMapCache::repository()->put($overviewKey, $payload, $ttl);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $full
     * @return array<string, mixed>
     */
    private function asOverviewPayload(array $full): array
    {
        $markers = is_array($full['markers'] ?? null) ? $full['markers'] : [];
        $full['mode'] = 'overview';
        $ufPoints = $this->buildUfMapPoints($markers);
        $full['uf_map_points'] = $ufPoints !== [] ? $ufPoints : $this->buildDefaultUfMapPoints();
        $full['markers'] = [];

        return $full;
    }

    /**
     * @param  array<string, mixed>  $full
     * @return array<string, mixed>
     */
    private function asRegionalPayload(array $full, string $uf): array
    {
        $all = is_array($full['markers'] ?? null) ? $full['markers'] : [];
        $regional = array_values(array_filter(
            $all,
            static fn (array $m): bool => strtoupper((string) ($m['uf'] ?? '')) === $uf,
        ));

        $coverage = HorizonteManagerInsights::dataCoverage($regional);
        $topProspects = array_values(array_filter(
            $regional,
            static fn (array $m): bool => in_array($m['tier'] ?? '', ['prospect_high', 'prospect_medium'], true),
        ));

        $full['mode'] = 'regional';
        $full['scope_uf'] = $uf;
        $regional = $this->ensureRegionalMarkerCoordinates($regional, $uf);
        if ($this->shouldEnrichRegionalCoordinates($regional)) {
            $regional = $this->enrichRegionalCoordinates($regional, $uf);
        }
        if ($this->shouldResolveOverlaps($regional)) {
            $regional = $this->resolveApproximateOverlaps($regional);
        }
        $full['markers'] = $regional;
        $full['summary'] = array_merge($this->buildSummary($regional), ['coverage' => $coverage]);
        $full['focus_segments'] = HorizonteManagerInsights::focusSegments($regional);
        $full['sge_summary'] = HorizonteManagerInsights::sgeSummary($regional);
        $full['top_prospects'] = array_slice($topProspects, 0, 25);
        $full['uf_map_points'] = [];
        $mesoPoints = $this->buildMesoMapPoints($regional);
        $useMesoOverview = count($mesoPoints) >= 1;
        $full['meso_map_points'] = $mesoPoints;
        $full['meta'] = array_merge(
            is_array($full['meta'] ?? null) ? $full['meta'] : [],
            [
                'regional_display_policy' => HorizonteMapPresenter::regionalDisplayPolicy(count($regional)),
                'meso_overview' => [
                    'enabled' => $useMesoOverview,
                    'threshold' => 0,
                    'meso_count' => count($mesoPoints),
                    'reason' => $useMesoOverview
                        ? __('Escolha uma mesorregião IBGE para ver os municípios com filtros e qualidade (:total no estado).', [
                            'total' => number_format(count($regional), 0, ',', '.'),
                        ])
                        : null,
                ],
            ],
        );

        $refYear = (int) ($full['reference_year'] ?? config('horizonte.reference_year', (int) date('Y') - 1));
        $currentYear = (int) ($full['current_year'] ?? HorizonteFundebRepasseOutlook::currentYear());
        $nationalByUf = $this->nationalFundebByUf($refYear, $currentYear);
        $full['uf_fundeb_insights'] = HorizonteUfFundebInsights::forRegional(
            $uf,
            $regional,
            $refYear,
            $currentYear,
            $nationalByUf !== [] ? $nationalByUf : null,
        );

        return $full;
    }

    /**
     * Agregados FUNDEB por UF (Brasil) para comparativo no recorte regional.
     *
     * @return array<string, array<string, mixed>>
     */
    private function nationalFundebByUf(int $refYear, int $currentYear): array
    {
        $fingerprint = $this->dataFingerprint();
        $cacheKey = 'horizonte:map:uf-fundeb-national:v2:'.$refYear.':'.$fingerprint;
        $cached = AdminHomeMapCache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $markers = $this->nationalMarkersForInsights($refYear, $fingerprint);
        if ($markers === []) {
            return [];
        }

        $byUf = HorizonteUfFundebInsights::aggregateNationalByUf($markers, $refYear, $currentYear);
        $ttl = max(60, (int) config('horizonte.cache_seconds', 900));
        AdminHomeMapCache::repository()->put($cacheKey, $byUf, $ttl);

        return $byUf;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nationalMarkersForInsights(int $refYear, string $fingerprint): array
    {
        $cacheKey = 'horizonte:map:v2:'.$refYear.':'.$fingerprint;
        $cached = AdminHomeMapCache::get($cacheKey);
        if (is_array($cached)) {
            $markers = is_array($cached['markers'] ?? null) ? $cached['markers'] : [];
            if ($markers !== []) {
                return $markers;
            }
        }

        $assembled = $this->assemble($refYear, requireCoordinates: false);

        return is_array($assembled['markers'] ?? null) ? $assembled['markers'] : [];
    }

    /**
     * Mantém o ranking nacional de UFs na resposta regional (rail «UFs prioritárias»).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attachNationalUfRankings(array $payload, int $refYear, string $fingerprint): array
    {
        $current = is_array($payload['uf_rankings'] ?? null) ? $payload['uf_rankings'] : [];
        if (count($current) > 1) {
            return $payload;
        }

        foreach ([
            'horizonte:map:overview:v2:'.$refYear.':'.$fingerprint,
            'horizonte:map:v2:'.$refYear.':'.$fingerprint,
        ] as $cacheKey) {
            $cached = AdminHomeMapCache::get($cacheKey);
            if (! is_array($cached)) {
                continue;
            }
            $rankings = is_array($cached['uf_rankings'] ?? null) ? $cached['uf_rankings'] : [];
            if (count($rankings) > 1) {
                $payload['uf_rankings'] = $rankings;
                break;
            }
        }

        return $payload;
    }

    private function financialPressureMin(): int
    {
        return max(0, min(100, (int) config('horizonte.map_display.financial_pressure_min', 60)));
    }

    /**
     * @param  array<string, mixed>  $m
     */
    private function isHighPressureMarker(array $m): bool
    {
        if ($m['consultoria_active'] ?? false) {
            return false;
        }
        $tier = (string) ($m['tier'] ?? '');
        if (! str_starts_with($tier, 'prospect_')) {
            return false;
        }

        return $tier === 'prospect_high'
            || (int) ($m['financial_pressure'] ?? 0) >= $this->financialPressureMin();
    }

    /**
     * Garante coordenadas válidas no recorte regional (ex.: payload filtrado de cache nacional).
     *
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function ensureRegionalMarkerCoordinates(array $markers, string $uf): array
    {
        if ($markers === []) {
            return $markers;
        }

        $needsIndex = false;
        foreach ($markers as $m) {
            $lat = (float) ($m['lat'] ?? 0);
            $lng = (float) ($m['lng'] ?? 0);
            if (! BrazilUfCentroids::isValidBrazilCoord($lat, $lng)) {
                $needsIndex = true;
                break;
            }
        }

        if (! $needsIndex) {
            return $markers;
        }

        $ibgeIndex = $this->ibgeCatalog->metaIndexForUfs([$uf], false);

        foreach ($markers as &$m) {
            $lat = (float) ($m['lat'] ?? 0);
            $lng = (float) ($m['lng'] ?? 0);
            if (BrazilUfCentroids::isValidBrazilCoord($lat, $lng)) {
                continue;
            }
            $ibge = (string) ($m['ibge'] ?? '');
            if ($ibge === '') {
                continue;
            }
            $fromIbge = $ibgeIndex[$ibge] ?? null;
            if ($fromIbge === null
                || ! BrazilUfCentroids::isValidBrazilCoord((float) ($fromIbge['lat'] ?? 0), (float) ($fromIbge['lng'] ?? 0))) {
                continue;
            }
            $source = (string) ($fromIbge['coord_source'] ?? 'ibge');
            $m['lat'] = (float) $fromIbge['lat'];
            $m['lng'] = (float) $fromIbge['lng'];
            $m['coord_source'] = $source;
            $m['coord_approximate'] = in_array($source, ['uf_spread', 'overview'], true);
            if ($m['name'] === '' || str_starts_with((string) $m['name'], 'Município ')) {
                $m['name'] = (string) ($fromIbge['name'] ?? $m['name']);
            }
        }
        unset($m);

        return $markers;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<int>  $years
     * @return Collection<int, Model>
     */
    private function latestModelRowsPerIbge(
        string $modelClass,
        string $yearColumn,
        array $years,
        ?string $ibgePrefix,
        string $ibgeColumn = 'ibge_municipio',
    ): Collection {
        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        $latest = DB::table($table)
            ->select($ibgeColumn, DB::raw(sprintf('MAX(%s) as latest_year', $yearColumn)))
            ->whereIn($yearColumn, $years);
        if ($ibgePrefix !== null && $ibgePrefix !== '') {
            $latest->where($ibgeColumn, 'like', $ibgePrefix.'%');
        }
        $latest->groupBy($ibgeColumn);

        return $modelClass::query()
            ->joinSub($latest, 'latest_row', function ($join) use ($table, $ibgeColumn, $yearColumn): void {
                $join->on("{$table}.{$ibgeColumn}", '=', "latest_row.{$ibgeColumn}")
                    ->on("{$table}.{$yearColumn}", '=', 'latest_row.latest_year');
            })
            ->whereIn("{$table}.{$yearColumn}", $years)
            ->when(
                $ibgePrefix !== null && $ibgePrefix !== '',
                static fn ($q) => $q->where("{$table}.{$ibgeColumn}", 'like', $ibgePrefix.'%'),
            )
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     */
    private function shouldEnrichRegionalCoordinates(array $markers): bool
    {
        if ($markers === [] || ! (bool) config('horizonte.map_display.fetch_remote_centroids', false)) {
            return false;
        }

        $approx = 0;
        foreach ($markers as $m) {
            if ($m['coord_approximate'] ?? false) {
                $approx++;
            }
        }

        return $approx > 0 && ($approx / count($markers)) >= 0.05;
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     */
    private function shouldResolveOverlaps(array $markers): bool
    {
        $max = max(10, (int) config('horizonte.map_display.overlap_max_markers', 80));
        $approx = 0;
        foreach ($markers as $m) {
            if ($m['coord_approximate'] ?? false) {
                $approx++;
            }
        }

        return $approx >= 2 && $approx <= $max;
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function enrichRegionalCoordinates(array $markers, string $uf): array
    {
        if ($markers === []) {
            return $markers;
        }

        $ibgeIndex = $this->ibgeCatalog->metaIndexForUfs([$uf], true);

        foreach ($markers as &$m) {
            $ibge = (string) ($m['ibge'] ?? '');
            if ($ibge === '') {
                continue;
            }
            $fromIbge = $ibgeIndex[$ibge] ?? null;
            if ($fromIbge === null) {
                continue;
            }
            $source = (string) ($fromIbge['coord_source'] ?? '');
            if (in_array($source, ['uf_spread', 'overview'], true)) {
                continue;
            }
            if (! BrazilUfCentroids::isValidBrazilCoord((float) ($fromIbge['lat'] ?? 0), (float) ($fromIbge['lng'] ?? 0))) {
                continue;
            }
            $m['lat'] = (float) $fromIbge['lat'];
            $m['lng'] = (float) $fromIbge['lng'];
            $m['coord_source'] = $source;
            $m['coord_approximate'] = false;
        }
        unset($m);

        return $markers;
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function resolveApproximateOverlaps(array $markers): array
    {
        $approxIndices = [];
        $approxSlice = [];
        foreach ($markers as $i => $m) {
            if (! ($m['coord_approximate'] ?? false)) {
                continue;
            }
            $approxIndices[] = $i;
            $approxSlice[] = [
                'lat' => (float) ($m['lat'] ?? 0),
                'lng' => (float) ($m['lng'] ?? 0),
                'coord_source' => (string) ($m['coord_source'] ?? 'uf_spread'),
            ];
        }

        if (count($approxSlice) < 2) {
            return $markers;
        }

        $maxForOverlap = max(10, (int) config('horizonte.map_display.overlap_max_markers', 80));
        if (count($approxSlice) > $maxForOverlap) {
            return $markers;
        }

        $separated = (new MunicipalityMapOverlapResolver())->separate($approxSlice);
        foreach ($approxIndices as $j => $idx) {
            $markers[$idx]['lat'] = (float) ($separated[$j]['lat'] ?? $markers[$idx]['lat']);
            $markers[$idx]['lng'] = (float) ($separated[$j]['lng'] ?? $markers[$idx]['lng']);
            $markers[$idx]['coord_source'] = (string) ($separated[$j]['coord_source'] ?? $markers[$idx]['coord_source']);
        }

        return $markers;
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function buildUfMapPoints(array $markers): array
    {
        $byUf = [];
        foreach ($markers as $m) {
            $uf = strtoupper(trim((string) ($m['uf'] ?? '')));
            if ($uf === '') {
                continue;
            }
            if (! isset($byUf[$uf])) {
                $byUf[$uf] = [
                    'uf' => $uf,
                    'total' => 0,
                    'prospect_count' => 0,
                    'high_prospect' => 0,
                    'high_pressure' => 0,
                    'without_consultoria' => 0,
                    'success_sum' => 0,
                    'benefit_sum' => 0,
                ];
            }
            $byUf[$uf]['total']++;
            $byUf[$uf]['success_sum'] += (int) ($m['success_score'] ?? 0);
            $byUf[$uf]['benefit_sum'] += (int) ($m['benefit_score'] ?? 0);
            if (($m['tier'] ?? '') === 'prospect_high') {
                $byUf[$uf]['high_prospect']++;
            }
            if ($this->isHighPressureMarker($m)) {
                $byUf[$uf]['high_pressure']++;
            }
            if (str_starts_with((string) ($m['tier'] ?? ''), 'prospect_')) {
                $byUf[$uf]['prospect_count']++;
            }
            if (! ($m['consultoria_active'] ?? false)) {
                $byUf[$uf]['without_consultoria']++;
            }
        }

        $points = [];
        foreach ($byUf as $row) {
            [$capitalLat, $capitalLng] = BrazilStateCapitals::latLng($row['uf']);
            $total = max(1, (int) $row['total']);
            $points[] = [
                'uf' => $row['uf'],
                'uf_name' => BrazilUfNames::name($row['uf']),
                'lat' => $capitalLat,
                'lng' => $capitalLng,
                'capital_lat' => $capitalLat,
                'capital_lng' => $capitalLng,
                'total' => (int) $row['total'],
                'prospect_count' => (int) $row['prospect_count'],
                'high_prospect' => (int) $row['high_prospect'],
                'high_pressure' => (int) $row['high_pressure'],
                'without_consultoria' => (int) $row['without_consultoria'],
                'avg_success' => (int) round($row['success_sum'] / $total),
                'avg_benefit' => (int) round($row['benefit_sum'] / $total),
                'heat_intensity' => min(1.0, ((int) $row['high_pressure']) / max(1, (int) $row['prospect_count'])),
            ];
        }

        usort($points, static fn (array $a, array $b): int => ($b['high_pressure'] <=> $a['high_pressure'])
            ?: ($b['high_prospect'] <=> $a['high_prospect'])
            ?: ($b['avg_benefit'] <=> $a['avg_benefit'])
            ?: strcmp((string) $a['uf'], (string) $b['uf']));

        return $points;
    }

    /**
     * Pontos por UF só com capitais — garante mapa nacional clicável mesmo sem dados agregados.
     *
     * @return list<array<string, mixed>>
     */
    private function buildDefaultUfMapPoints(): array
    {
        $points = [];
        foreach (IbgeMunicipalityCatalog::brazilianUfs() as $uf) {
            [$capitalLat, $capitalLng] = BrazilStateCapitals::latLng($uf);
            $points[] = [
                'uf' => $uf,
                'uf_name' => BrazilUfNames::name($uf),
                'lat' => $capitalLat,
                'lng' => $capitalLng,
                'capital_lat' => $capitalLat,
                'capital_lng' => $capitalLng,
                'total' => 0,
                'prospect_count' => 0,
                'high_prospect' => 0,
                'high_pressure' => 0,
                'without_consultoria' => 0,
                'avg_success' => 0,
                'avg_benefit' => 0,
                'heat_intensity' => 0.08,
            ];
        }

        usort($points, static fn (array $a, array $b): int => strcmp((string) $a['uf'], (string) $b['uf']));

        return $points;
    }

    /**
     * Agrega municípios por mesorregião IBGE (drill-down dentro da UF).
     *
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function buildMesoMapPoints(array $markers): array
    {
        $byMeso = [];
        foreach ($markers as $m) {
            $mesoId = trim((string) ($m['meso_id'] ?? ''));
            $mesoName = trim((string) ($m['meso_name'] ?? ''));
            if ($mesoId === '') {
                continue;
            }
            if (! isset($byMeso[$mesoId])) {
                $byMeso[$mesoId] = [
                    'meso_id' => $mesoId,
                    'meso_name' => $mesoName !== '' ? $mesoName : __('Mesorregião :id', ['id' => $mesoId]),
                    'uf' => strtoupper(trim((string) ($m['uf'] ?? ''))),
                    'total' => 0,
                    'prospect_count' => 0,
                    'high_prospect' => 0,
                    'high_pressure' => 0,
                    'without_consultoria' => 0,
                    'success_sum' => 0,
                    'benefit_sum' => 0,
                    'lat_sum' => 0.0,
                    'lng_sum' => 0.0,
                    'coord_count' => 0,
                ];
            }
            $byMeso[$mesoId]['total']++;
            $byMeso[$mesoId]['success_sum'] += (int) ($m['success_score'] ?? 0);
            $byMeso[$mesoId]['benefit_sum'] += (int) ($m['benefit_score'] ?? 0);
            if (($m['tier'] ?? '') === 'prospect_high') {
                $byMeso[$mesoId]['high_prospect']++;
            }
            if ($this->isHighPressureMarker($m)) {
                $byMeso[$mesoId]['high_pressure']++;
            }
            if (str_starts_with((string) ($m['tier'] ?? ''), 'prospect_')) {
                $byMeso[$mesoId]['prospect_count']++;
            }
            if (! ($m['consultoria_active'] ?? false)) {
                $byMeso[$mesoId]['without_consultoria']++;
            }
            $lat = (float) ($m['lat'] ?? 0);
            $lng = (float) ($m['lng'] ?? 0);
            if (BrazilUfCentroids::isValidBrazilCoord($lat, $lng)) {
                $byMeso[$mesoId]['lat_sum'] += $lat;
                $byMeso[$mesoId]['lng_sum'] += $lng;
                $byMeso[$mesoId]['coord_count']++;
            }
        }

        $points = [];
        foreach ($byMeso as $row) {
            $total = max(1, (int) $row['total']);
            $coordCount = (int) $row['coord_count'];
            if ($coordCount > 0) {
                $lat = $row['lat_sum'] / $coordCount;
                $lng = $row['lng_sum'] / $coordCount;
            } else {
                [$lat, $lng] = BrazilUfCentroids::latLng($row['uf'], count($points));
            }
            $displayPolicy = HorizonteMapPresenter::regionalDisplayPolicy($total);
            $points[] = [
                'meso_id' => $row['meso_id'],
                'meso_name' => $row['meso_name'],
                'uf' => $row['uf'],
                'lat' => $lat,
                'lng' => $lng,
                'total' => (int) $row['total'],
                'prospect_count' => (int) $row['prospect_count'],
                'high_prospect' => (int) $row['high_prospect'],
                'high_pressure' => (int) $row['high_pressure'],
                'without_consultoria' => (int) $row['without_consultoria'],
                'avg_success' => (int) round($row['success_sum'] / $total),
                'avg_benefit' => (int) round($row['benefit_sum'] / $total),
                'heat_intensity' => min(1.0, ((int) $row['high_pressure']) / max(1, (int) $row['prospect_count'])),
                'display_policy' => $displayPolicy,
            ];
        }

        usort($points, static fn (array $a, array $b): int => ($b['high_pressure'] <=> $a['high_pressure'])
            ?: ($b['high_prospect'] <=> $a['high_prospect'])
            ?: strcmp((string) $a['meso_name'], (string) $b['meso_name']));

        return $points;
    }

    /**
     * @return array<string, mixed>
     */
    private function assemble(int $refYear, bool $requireCoordinates = true, ?string $scopeUf = null): array
    {
        $scopedUf = $scopeUf !== null ? strtoupper(trim($scopeUf)) : null;
        $ibgePrefix = $scopedUf !== null ? IbgeUfFromCode::ibgePrefixForUf($scopedUf) : null;

        $citiesByIbge = $this->citiesByIbge($scopedUf);

        $fundebByIbge = $this->fundebByIbge($refYear, $ibgePrefix);
        $censoByIbge = $this->censoByIbge($refYear, $ibgePrefix);
        $saebByIbge = $this->saebByIbge($refYear, $ibgePrefix);
        $cadunicoByIbge = $this->cadunicoByIbge($refYear, $ibgePrefix);
        $demographyByIbge = $this->demographyByIbge($refYear, $ibgePrefix);
        $areaByIbge = $this->areaByIbge($ibgePrefix);
        $transfersByIbge = HorizonteTesouroTransferSyncService::aggregateByIbge($refYear, $ibgePrefix);

        $currentYear = HorizonteFundebRepasseOutlook::currentYear();
        $fundebCurrentByIbge = $currentYear > $refYear
            ? $this->fundebByIbge($currentYear, $ibgePrefix)
            : [];
        $fundebRealtimeByIbge = HorizonteFundebRepasseOutlook::byIbge(
            $refYear,
            $ibgePrefix,
            $fundebByIbge,
            $fundebCurrentByIbge,
            $censoByIbge,
        );

        $ibgeMetaIndex = [];
        if ($scopedUf !== null && $requireCoordinates) {
            $fetchGeo = (bool) config('horizonte.map_display.fetch_remote_centroids', false);
            $ibgeMetaIndex = $this->ibgeCatalog->metaIndexForUfs([$scopedUf], $fetchGeo);
            $ibgeSet = array_fill_keys(array_keys($ibgeMetaIndex), true);
        } else {
            $ibgeSet = $this->collectIbgeCodes(array_keys($citiesByIbge), $ibgePrefix);
        }

        foreach (array_keys($fundebByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }
        foreach (array_keys($censoByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }
        foreach (array_keys($saebByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }
        foreach (array_keys($cadunicoByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }
        foreach (array_keys($demographyByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }
        foreach (array_keys($transfersByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }
        foreach (array_keys($fundebRealtimeByIbge) as $ibge) {
            $ibgeSet[$ibge] = true;
        }

        if ($scopedUf !== null && $ibgeMetaIndex === []) {
            $ibgeSet = array_filter(
                $ibgeSet,
                static fn (bool $_present, string $ibge): bool => IbgeUfFromCode::ufFromIbge($ibge) === $scopedUf,
                ARRAY_FILTER_USE_BOTH,
            );
        }

        if ($requireCoordinates && $ibgeMetaIndex === []) {
            $ufs = $scopedUf !== null
                ? [$scopedUf]
                : IbgeUfFromCode::ufsFromIbgeCodes(array_keys($ibgeSet));
            foreach ($citiesByIbge as $city) {
                $ufs[] = strtoupper((string) $city['uf']);
            }
            $ufs = array_values(array_unique(array_filter($ufs)));
            if ($ufs === []) {
                $ufs = IbgeMunicipalityCatalog::brazilianUfs();
            }
            $fetchGeo = (bool) config('horizonte.map_display.fetch_remote_centroids', false);
            $ibgeMetaIndex = $this->ibgeCatalog->metaIndexForUfs($ufs, $fetchGeo);
        }

        $saebForBench = [];
        $complRatios = [];
        $transferRatios = [];
        foreach (array_keys($ibgeSet) as $ibge) {
            $fundeb = $fundebByIbge[$ibge] ?? null;
            $saeb = $saebByIbge[$ibge] ?? null;
            $transfer = $transfersByIbge[$ibge] ?? null;
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
            $transferRatio = HorizonteTransferScoring::ratioForBenchmark($transfer, $fundeb);
            if ($transferRatio !== null) {
                $transferRatios[] = $transferRatio;
            }
        }
        $benchmarks = $this->scorer->benchmarks($saebForBench, $complRatios, $transferRatios);

        $sgeRegistry = $this->sgeRegistry->indexedFromCache();
        $alertsRegistry = $this->municipalAlerts->indexedFromCache();
        $alertsMeta = $this->municipalAlerts->metaFromCache();

        $high = (int) config('horizonte.high_opportunity_threshold', 70);
        $medium = (int) config('horizonte.medium_opportunity_threshold', 40);

        $markers = [];
        foreach (array_keys($ibgeSet) as $ibge) {
            $city = $citiesByIbge[$ibge] ?? null;
            $fundeb = $fundebByIbge[$ibge] ?? null;
            $censo = $censoByIbge[$ibge] ?? null;
            $saeb = $saebByIbge[$ibge] ?? null;
            $cadunico = $cadunicoByIbge[$ibge] ?? null;
            $demography = $demographyByIbge[$ibge] ?? null;
            $area = $areaByIbge[$ibge] ?? null;
            $transfer = $transfersByIbge[$ibge] ?? null;
            $fundebRealtime = $fundebRealtimeByIbge[$ibge] ?? null;

            $meta = null;
            $fromIbge = $ibgeMetaIndex[$ibge] ?? null;

            if ($requireCoordinates) {
                $meta = $this->resolveMarkerCoordinates($ibge, $city, $fromIbge);
                if ($meta === null) {
                    continue;
                }
            } else {
                $ufCode = strtoupper((string) ($city['uf'] ?? IbgeUfFromCode::ufFromIbge($ibge) ?? ''));
                if ($ufCode === '') {
                    continue;
                }
                $meta = [
                    'ibge' => $ibge,
                    'name' => $city !== null
                        ? (string) $city['name']
                        : (string) ($fromIbge['name'] ?? __('Município :ibge', ['ibge' => $ibge])),
                    'uf' => $ufCode,
                    'lat' => (float) ($fromIbge['lat'] ?? 0),
                    'lng' => (float) ($fromIbge['lng'] ?? 0),
                    'coord_source' => 'overview',
                ];
            }

            $inCatalog = $city !== null;

            $consultoriaActive = (bool) ($city['consultoria_active'] ?? false);

            $scoreInput = [
                'matriculas_censo' => $censo['matriculas_total'] ?? null,
                'complementacao_total' => $fundeb['complementacao_total'] ?? null,
                'receita_total' => $fundeb['receita_total'] ?? null,
                'saeb_lp' => $saeb['lp'] ?? null,
                'saeb_mat' => $saeb['mat'] ?? null,
                'saeb_lp_series' => array_values($saeb['lp_series'] ?? []),
                'saeb_mat_series' => array_values($saeb['mat_series'] ?? []),
                'cadunico_escolar' => $cadunico['escolar'] ?? null,
                'sidra_pop_4_17' => $demography['populacao_4_17'] ?? null,
                'pct_criancas_pbf' => $cadunico['pct_pbf'] ?? null,
                'transfer_total' => HorizonteTransferScoring::resolveTotalForScoring($transfer, $fundeb),
                'has_fundeb' => $fundeb !== null,
                'has_censo' => $censo !== null,
                'has_saeb' => $saeb !== null,
                'has_cadunico' => $cadunico !== null,
                'has_demography' => $demography !== null,
                'has_transfers' => $transfer !== null,
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
            $muniAlerts = $this->municipalAlertsResolver->resolve(
                $alertsRegistry[$ibge] ?? null,
                $alertsMeta,
            );

            $markers[] = $this->withMarkerGeoContext([
                'ibge' => $ibge,
                'city_id' => $city['id'] ?? null,
                'name' => (string) $meta['name'],
                'uf' => strtoupper((string) ($meta['uf'] ?? $city['uf'] ?? '')),
                'meso_id' => (string) ($fromIbge['meso_id'] ?? ''),
                'meso_name' => (string) ($fromIbge['meso_name'] ?? ''),
                'micro_id' => (string) ($fromIbge['micro_id'] ?? ''),
                'micro_name' => (string) ($fromIbge['micro_name'] ?? ''),
                'regiao_imediata_id' => (string) ($fromIbge['regiao_imediata_id'] ?? ''),
                'regiao_imediata_name' => (string) ($fromIbge['regiao_imediata_name'] ?? ''),
                'lat' => (float) $meta['lat'],
                'lng' => (float) $meta['lng'],
                'tier' => $scores['tier'],
                'tier_label' => $scores['tier_label'],
                'success_score' => $scores['success_score'],
                'benefit_score' => $scores['benefit_score'],
                'financial_pressure' => $scores['financial_pressure'],
                'pedagogical_gap' => $scores['pedagogical_gap'],
                'scale_score' => $scores['scale_score'],
                'social_demand' => $scores['social_demand'],
                'transfer_dependency' => $scores['transfer_dependency'],
                'data_readiness' => $scores['data_readiness'],
                'heat_intensity' => round($heatIntensity, 3),
                'consultoria_active' => $consultoriaActive,
                'in_catalog' => $inCatalog,
                'has_fundeb' => $fundeb !== null,
                'has_censo' => $censo !== null,
                'has_saeb' => $saeb !== null,
                'has_cadunico' => $cadunico !== null,
                'has_demography' => $demography !== null,
                'has_transfers' => $transfer !== null,
                'matriculas_censo' => $censo['matriculas_total'] ?? null,
                'censo_ano' => $censo['ano'] ?? null,
                'cadunico_escolar' => $cadunico['escolar'] ?? null,
                'cadunico_ano' => $cadunico['ano'] ?? null,
                'populacao_total' => $demography['populacao_total'] ?? null,
                'demography_ano' => $demography['ano'] ?? null,
                'area_km2' => $area['area_km2'] ?? null,
                'area_ano' => $area['ano'] ?? null,
                'sidra_pop_4_17' => $demography['populacao_4_17'] ?? null,
                'pct_criancas_pbf' => $cadunico['pct_pbf'] ?? null,
                'transfer_total' => $transfer['total'] ?? null,
                'transfer_ano' => $transfer['ano'] ?? null,
                'transfer_fundeb' => $transfer['fundeb'] ?? null,
                'transfer_educacao' => $transfer['educacao'] ?? null,
                'transfer_pct_fundeb' => $transfer['pct_fundeb'] ?? null,
                'transfer_pct_educacao' => $transfer['pct_educacao'] ?? null,
                'complementacao_fundeb' => $fundeb['complementacao_total'] ?? null,
                'fundeb_vaaf' => $fundeb['vaaf'] ?? null,
                'fundeb_receita_total' => $fundeb['receita_total'] ?? null,
                'fundeb_ano' => $fundeb['ano'] ?? null,
                'fundeb_matriculas_base' => $fundeb['matriculas_base'] ?? null,
                'fundeb_matriculas_fonte' => $fundeb['matriculas_fonte'] ?? null,
                'fundeb_realtime_ano' => $fundebRealtime['ano'] ?? null,
                'fundeb_realtime_observed' => $fundebRealtime['observed'] ?? null,
                'fundeb_realtime_expected' => $fundebRealtime['expected'] ?? null,
                'fundeb_realtime_projected' => $fundebRealtime['projected'] ?? null,
                'fundeb_realtime_balance' => $fundebRealtime['balance'] ?? null,
                'fundeb_realtime_pct_done' => $fundebRealtime['pct_done'] ?? null,
                'fundeb_realtime_months' => $fundebRealtime['months_with_transfers'] ?? null,
                'fundeb_realtime_last_transfer_month' => $fundebRealtime['last_transfer_month'] ?? null,
                'fundeb_realtime_last_transfer_label' => $fundebRealtime['last_transfer_label'] ?? null,
                'fundeb_realtime_last_recorded_at' => $fundebRealtime['last_recorded_at'] ?? null,
                'fundeb_realtime_outlook' => $fundebRealtime['outlook'] ?? null,
                'fundeb_realtime_outlook_label' => $fundebRealtime['outlook_label'] ?? null,
                'fundeb_realtime_outlook_detail' => $fundebRealtime['outlook_detail'] ?? null,
                'fundeb_realtime_gap' => $fundebRealtime['gap'] ?? null,
                'fundeb_realtime_gap_sign' => $fundebRealtime['gap_sign'] ?? null,
                'fundeb_realtime_expected_source' => $fundebRealtime['expected_source'] ?? null,
                'fundeb_realtime_portaria_receita' => $fundebRealtime['portaria_receita'] ?? null,
                'fundeb_realtime_portaria_complementacao_total' => $fundebRealtime['portaria_complementacao_total'] ?? null,
                'fundeb_realtime_portaria_total_previsto' => $fundebRealtime['portaria_total_previsto'] ?? null,
                'fundeb_realtime_base_mat_vaaf' => $fundebRealtime['portaria_base_mat_vaaf'] ?? null,
                'fundeb_realtime_vaaf' => $fundebRealtime['portaria_vaaf'] ?? null,
                'fundeb_realtime_matriculas' => $fundebRealtime['portaria_matriculas'] ?? null,
                'fundeb_realtime_matriculas_fonte' => $fundebRealtime['portaria_matriculas_fonte'] ?? null,
                'fundeb_realtime_portaria_ano' => $fundebRealtime['portaria_ref_ano'] ?? null,
                'fundeb_realtime_portaria_adjustments' => $fundebRealtime['portaria_adjustments'] ?? [],
                'fundeb_realtime_portaria_note' => $fundebRealtime['portaria_adjustments_note'] ?? null,
                'saeb_lp' => $saeb['lp'] ?? null,
                'saeb_mat' => $saeb['mat'] ?? null,
                'saeb_lp_series' => array_values($saeb['lp_series'] ?? []),
                'saeb_mat_series' => array_values($saeb['mat_series'] ?? []),
                'analytics_url' => $city !== null && $consultoriaActive
                    ? route('dashboard.analytics', ['city_id' => $city['id']])
                    : null,
                'cities_url' => $city !== null ? route('cities.edit', $city['id']) : null,
                'sge_editable' => ! $inCatalog && ! $consultoriaActive,
                'sge' => $sge,
                'sge_found' => (bool) ($sge['found'] ?? false),
                'sge_system' => $sge['system'] ?? null,
                'sge_status' => $sge['status'] ?? 'not_found',
                'muni_alerts' => $muniAlerts,
                'muni_alerts_status' => $muniAlerts['status'] ?? 'unavailable',
                'coord_source' => $meta['coord_source'] ?? null,
                'coord_approximate' => in_array((string) ($meta['coord_source'] ?? ''), ['uf_spread', 'overview'], true),
            ]);
        }

        usort($markers, static fn (array $a, array $b): int => ($b['success_score'] <=> $a['success_score']) ?: strcasecmp((string) $a['name'], (string) $b['name']));

        if ($scopedUf !== null && $this->shouldResolveOverlaps($markers)) {
            $markers = $this->resolveApproximateOverlaps($markers);
            foreach ($markers as &$m) {
                $m = $this->withMarkerGeoContext($m);
            }
            unset($m);
        }

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
            'current_year' => $currentYear,
            'generated_at' => now()->toIso8601String(),
            'markers' => $markers,
            'summary' => array_merge($summary, ['coverage' => $coverage]),
            'uf_rankings' => $ufRankings,
            'top_prospects' => $topProspects,
            'focus_segments' => $focusSegments,
            'sge_summary' => HorizonteManagerInsights::sgeSummary($markers),
            'meta' => array_merge(
                HorizonteMapPresenter::refreshMeta(count($markers), $coverage),
                [
                    'display_policy' => HorizonteMapPresenter::displayPolicy(count($markers), $ufRankings),
                    'default_filter' => HorizonteMapPresenter::defaultViewFilter(),
                ],
            ),
            'colors' => HorizonteMapPresenter::tierColors(),
            'legend' => HorizonteMapPresenter::legendItems(),
            'heat_legend' => HorizonteMapPresenter::heatLegendItems(),
        ];
    }

    /**
     * @return array<string, array{id: int, name: string, uf: string, consultoria_active: bool, lat: ?float, lng: ?float}>
     */
    private function citiesByIbge(?string $scopeUf = null): array
    {
        $coords = app(\App\Support\Brazil\MunicipalityMapCoordinates::class);
        $query = City::query()->orderBy('uf')->orderBy('name');
        if ($scopeUf !== null && $scopeUf !== '') {
            $query->where('uf', strtoupper(trim($scopeUf)));
        }
        $allCities = $query->get();
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
            [$lat, $lng, $source] = array_pad($coords->forCity($city, $index, max(1, $inUf->count())), 3, 'uf_spread');

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
                'coord_source' => (string) $source,
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $seed
     * @return array<string, true>
     */
    private function collectIbgeCodes(array $seed, ?string $ibgePrefix = null): array
    {
        $set = [];
        foreach ($seed as $ibge) {
            $set[$ibge] = true;
        }

        $ibgeFilter = static function ($query, string $column) use ($ibgePrefix): void {
            if ($ibgePrefix !== null && $ibgePrefix !== '') {
                $query->where($column, 'like', $ibgePrefix.'%');
            }
        };

        foreach ([FundebMunicipioReference::class, InepCensoMunicipioMatricula::class] as $model) {
            $table = (new $model)->getTable();
            $query = DB::table($table)->distinct();
            $ibgeFilter($query, 'ibge_municipio');
            foreach ($query->pluck('ibge_municipio') as $raw) {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $raw);
                if ($ibge !== null) {
                    $set[$ibge] = true;
                }
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('saeb_indicator_points')) {
            $query = DB::table('saeb_indicator_points')->whereNotNull('ibge_municipio')->distinct();
            $ibgeFilter($query, 'ibge_municipio');
            foreach ($query->pluck('ibge_municipio') as $raw) {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $raw);
                if ($ibge !== null) {
                    $set[$ibge] = true;
                }
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('cadunico_municipio_snapshots')) {
            $query = CadunicoMunicipioSnapshot::query()->distinct();
            $ibgeFilter($query, 'ibge_municipio');
            foreach ($query->pluck('ibge_municipio') as $raw) {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $raw);
                if ($ibge !== null) {
                    $set[$ibge] = true;
                }
            }
        }

        return $set;
    }

    /**
     * @return array<string, array{
     *     complementacao_total: float,
     *     complementacao_vaaf: ?float,
     *     complementacao_vaat: ?float,
     *     complementacao_vaar: ?float,
     *     receita_total: ?float,
     *     matriculas_base: ?int,
     *     matriculas_fonte: ?string,
     *     vaaf: ?float,
     *     ano: int
     * }>
     */
    private function fundebByIbge(int $refYear, ?string $ibgePrefix = null): array
    {
        $years = [$refYear, $refYear - 1, $refYear - 2];
        $rows = $this->latestModelRowsPerIbge(FundebMunicipioReference::class, 'ano', $years, $ibgePrefix);

        $out = [];
        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $compl = (float) ($row->complementacao_vaaf ?? 0)
                + (float) ($row->complementacao_vaat ?? 0)
                + (float) ($row->complementacao_vaar ?? 0);
            $out[$ibge] = [
                'complementacao_total' => $compl,
                'complementacao_vaaf' => $row->complementacao_vaaf !== null ? (float) $row->complementacao_vaaf : null,
                'complementacao_vaat' => $row->complementacao_vaat !== null ? (float) $row->complementacao_vaat : null,
                'complementacao_vaar' => $row->complementacao_vaar !== null ? (float) $row->complementacao_vaar : null,
                'receita_total' => $row->receita_total !== null ? (float) $row->receita_total : null,
                'matriculas_base' => $row->matriculas_base !== null ? (int) $row->matriculas_base : null,
                'matriculas_fonte' => is_string($row->matriculas_fonte) && trim($row->matriculas_fonte) !== ''
                    ? trim($row->matriculas_fonte)
                    : null,
                'vaaf' => $row->vaaf !== null ? (float) $row->vaaf : null,
                'ano' => (int) $row->ano,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{matriculas_total: int}>
     */
    private function censoByIbge(int $refYear, ?string $ibgePrefix = null): array
    {
        $years = [$refYear, $refYear - 1, $refYear - 2];
        $rows = $this->latestModelRowsPerIbge(InepCensoMunicipioMatricula::class, 'ano', $years, $ibgePrefix);

        $out = [];
        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $out[$ibge] = [
                'matriculas_total' => (int) $row->matriculas_total,
                'ano' => (int) $row->ano,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{lp: ?float, mat: ?float, lp_series: list<array{year: int, value: float}>, mat_series: list<array{year: int, value: float}>}>
     */
    private function saebByIbge(int $refYear, ?string $ibgePrefix = null): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('saeb_indicator_points')) {
            return [];
        }

        $years = HorizonteSaebLookupYears::forReferenceYear($refYear);

        $query = SaebIndicatorPoint::query()
            ->whereIn('ano', $years)
            ->whereNotNull('ibge_municipio')
            ->orderByDesc('ano');
        if ($ibgePrefix !== null && $ibgePrefix !== '') {
            $query->where('ibge_municipio', 'like', $ibgePrefix.'%');
        }
        $rows = $query->get(['ibge_municipio', 'ano', 'disciplina', 'valor']);

        /** @var array<string, array{lp: list<array{year: int, value: float}>, mat: list<array{year: int, value: float}>}> $series */
        $series = [];
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
            if (! isset($series[$ibge])) {
                $series[$ibge] = ['lp' => [], 'mat' => []];
            }
            $year = (int) $row->ano;
            $value = (float) $row->valor;
            $bucket = &$series[$ibge][$key];
            foreach ($bucket as $entry) {
                if ($entry['year'] === $year) {
                    continue 2;
                }
            }
            if (count($bucket) < 2) {
                $bucket[] = ['year' => $year, 'value' => $value];
            }
        }

        $out = [];
        foreach ($series as $ibge => $data) {
            $out[$ibge] = [
                'lp' => $data['lp'][0]['value'] ?? null,
                'mat' => $data['mat'][0]['value'] ?? null,
                'lp_series' => $data['lp'],
                'mat_series' => $data['mat'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{escolar: int, pct_pbf: ?float}>
     */
    private function cadunicoByIbge(int $refYear, ?string $ibgePrefix = null): array
    {
        if (! Schema::hasTable('cadunico_municipio_snapshots')) {
            return [];
        }

        $years = [$refYear, $refYear - 1, $refYear - 2];
        $rows = $this->latestModelRowsPerIbge(
            CadunicoMunicipioSnapshot::class,
            'ano_referencia',
            $years,
            $ibgePrefix,
        );

        $out = [];
        foreach ($rows as $row) {
            if (! $row instanceof CadunicoMunicipioSnapshot) {
                continue;
            }
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $indicators = CadunicoVulnerabilidadeIndicators::fromSnapshot($row);
            $out[$ibge] = [
                'escolar' => (int) ($indicators['criancas_escolar_cadunico'] ?? $row->totalCriancasEscolaridade()),
                'pct_pbf' => isset($indicators['pct_criancas_pbf']) ? (float) $indicators['pct_criancas_pbf'] : null,
                'ano' => (int) $row->ano_referencia,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{populacao_4_17: int}>
     */
    private function demographyByIbge(int $refYear, ?string $ibgePrefix = null): array
    {
        if (! Schema::hasTable('municipal_demography_snapshots')) {
            return [];
        }

        $sidraYear = (int) config('horizonte.sidra.periodo', 2022);
        $years = array_values(array_unique([$sidraYear, $refYear, $refYear - 1]));
        $rows = $this->latestModelRowsPerIbge(
            MunicipalDemographySnapshot::class,
            'ano_referencia',
            $years,
            $ibgePrefix,
        );

        $out = [];
        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            if ($row->populacao_4_17 === null && $row->populacao_total === null) {
                continue;
            }
            $out[$ibge] = [
                'populacao_4_17' => $row->populacao_4_17 !== null ? (int) $row->populacao_4_17 : null,
                'populacao_total' => $row->populacao_total !== null ? (int) $row->populacao_total : null,
                'ano' => (int) $row->ano_referencia,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{area_km2: ?float, ano: int}>
     */
    private function areaByIbge(?string $ibgePrefix = null): array
    {
        return app(MunicipalAreaSnapshotRepository::class)->latestByIbge($ibgePrefix);
    }

    /**
     * @param  array<string, mixed>  $marker
     * @return array<string, mixed>
     */
    private function withMarkerGeoContext(array $marker): array
    {
        $uf = strtoupper(trim((string) ($marker['uf'] ?? '')));
        $lat = (float) ($marker['lat'] ?? 0);
        $lng = (float) ($marker['lng'] ?? 0);
        $marker['capital_nome'] = $uf !== '' ? BrazilStateCapitals::name($uf) : '';
        $marker['distancia_capital_km'] = $uf !== ''
            ? BrazilStateCapitals::distanceKm($lat, $lng, $uf)
            : null;

        return $marker;
    }

    /**
     * @param  array<string, mixed>|null  $city
     * @param  array<string, mixed>|null  $fromIbge
     * @return array{ibge: string, name: string, uf: string, lat: float, lng: float, coord_source: string}|null
     */
    private function resolveMarkerCoordinates(string $ibge, ?array $city, ?array $fromIbge): ?array
    {
        if ($city !== null
            && BrazilUfCentroids::isValidBrazilCoord((float) ($city['lat'] ?? 0), (float) ($city['lng'] ?? 0))) {
            return [
                'ibge' => $ibge,
                'name' => (string) $city['name'],
                'uf' => strtoupper((string) $city['uf']),
                'lat' => (float) $city['lat'],
                'lng' => (float) $city['lng'],
                'coord_source' => (string) ($city['coord_source'] ?? 'catalog'),
            ];
        }

        if ($fromIbge !== null
            && BrazilUfCentroids::isValidBrazilCoord((float) ($fromIbge['lat'] ?? 0), (float) ($fromIbge['lng'] ?? 0))) {
            return [
                'ibge' => $ibge,
                'name' => (string) ($fromIbge['name'] ?? __('Município :ibge', ['ibge' => $ibge])),
                'uf' => strtoupper((string) ($fromIbge['uf'] ?? '')),
                'lat' => (float) $fromIbge['lat'],
                'lng' => (float) $fromIbge['lng'],
                'coord_source' => (string) ($fromIbge['coord_source'] ?? 'ibge'),
            ];
        }

        return null;
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
        $highPressure = 0;

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
            if ($this->isHighPressureMarker($m)) {
                $highPressure++;
            }
        }

        return [
            'total' => $total,
            'without_consultoria' => $withoutConsultoria,
            'consultoria_active' => $byTier['consultoria_active'] ?? 0,
            'high_prospect' => $highProspect,
            'high_pressure' => $highPressure,
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
                    'uf_name' => BrazilUfNames::name($uf),
                    'total' => 0,
                    'benefit_sum' => 0,
                    'high_prospect' => 0,
                    'high_pressure' => 0,
                    'without_consultoria' => 0,
                ];
            }
            $byUf[$uf]['total']++;
            $byUf[$uf]['benefit_sum'] += (int) ($m['benefit_score'] ?? 0);
            if (($m['tier'] ?? '') === 'prospect_high') {
                $byUf[$uf]['high_prospect']++;
            }
            if ($this->isHighPressureMarker($m)) {
                $byUf[$uf]['high_pressure']++;
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

        usort($ranked, static fn (array $a, array $b): int => ($b['high_pressure'] ?? 0) <=> ($a['high_pressure'] ?? 0)
            ?: ($b['avg_benefit'] <=> $a['avg_benefit'])
            ?: ($b['high_prospect'] <=> $a['high_prospect']));

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
            [CadunicoMunicipioSnapshot::class, 'imported_at'],
            [MunicipalDemographySnapshot::class, 'imported_at'],
        ] as [$model, $col]) {
            if (! \Illuminate\Support\Facades\Schema::hasTable((new $model)->getTable())) {
                continue;
            }
            $row = DB::table((new $model)->getTable())
                ->selectRaw('count(*) as c, max('.$col.') as m')
                ->first();
            $parts[] = (new $model)->getTable().':'.((int) ($row->c ?? 0)).':'.(string) ($row->m ?? '');
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('municipal_transfer_snapshots')) {
            $row = DB::table('municipal_transfer_snapshots')
                ->selectRaw('count(*) as c, max(imported_at) as m')
                ->first();
            $parts[] = 'municipal_transfer_snapshots:'.((int) ($row->c ?? 0)).':'.(string) ($row->m ?? '');
        }

        $parts[] = 'sge_bust:'.HorizonteMapCacheBuster::token();
        $sgePath = trim((string) config('horizonte.sge.registry_path', 'horizonte/sge_registry.json'));
        if ($sgePath !== '' && Storage::disk('local')->exists($sgePath)) {
            $parts[] = 'sge_mtime:'.(string) Storage::disk('local')->lastModified($sgePath);
        }

        $alertsMeta = $this->municipalAlerts->metaFromCache();
        $parts[] = 'alerts_sync:'.(string) ($alertsMeta['synced_at'] ?? '');
        $alertsSnapshot = trim((string) config('horizonte.municipal_alerts.snapshot_path', 'horizonte/municipal_alerts_snapshot.json'));
        if ($alertsSnapshot !== '' && Storage::disk('local')->exists($alertsSnapshot)) {
            $parts[] = 'alerts_mtime:'.(string) Storage::disk('local')->lastModified($alertsSnapshot);
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
            'uf_fundeb_insights' => null,
            'sge_summary' => HorizonteManagerInsights::sgeSummary([]),
            'meta' => array_merge(
                HorizonteMapPresenter::refreshMeta(0, HorizonteManagerInsights::dataCoverage([])),
                [
                    'display_policy' => HorizonteMapPresenter::displayPolicy(0, []),
                    'default_filter' => HorizonteMapPresenter::defaultViewFilter(),
                ],
            ),
            'colors' => HorizonteMapPresenter::tierColors(),
            'legend' => HorizonteMapPresenter::legendItems(),
            'heat_legend' => HorizonteMapPresenter::heatLegendItems(),
        ];
    }
}
