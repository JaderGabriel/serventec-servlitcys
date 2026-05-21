<?php

namespace App\Support\Brazil;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Coordenadas para o mapa de municípios no Início (geos locais → IBGE → dispersão na UF).
 */
final class MunicipalityMapCoordinates
{
    private const CACHE_TTL_SECONDS = 604800;

    /** @var ?array<int, array{lat: float, lng: float}> */
    private ?array $schoolGeoCache = null;

    /**
     * @return array{0: float, 1: float, source: string}
     */
    public function forCity(City $city, int $indexInUf = 0, int $totalInUf = 1): array
    {
        $fromGeos = $this->coordsFromSchoolGeos((int) $city->id);
        if ($fromGeos !== null) {
            return [$fromGeos['lat'], $fromGeos['lng'], 'school_geos'];
        }

        $ibge = $this->normalizeIbge((string) ($city->ibge_municipio ?? ''));
        if ($ibge !== null) {
            $fromIbge = $this->coordsFromIbgeCached((int) $city->id, $ibge);
            if ($fromIbge !== null) {
                return [$fromIbge['lat'], $fromIbge['lng'], 'ibge'];
            }
        }

        [$lat, $lng] = BrazilUfCentroids::latLngForIndex(
            (string) $city->uf,
            max(0, $indexInUf),
            max(1, $totalInUf),
            (int) $city->id,
        );

        return [$lat, $lng, 'uf_spread'];
    }

    /**
     * Médias de coordenadas por cidade (uma query para o mapa inteiro).
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    public function schoolGeoAveragesByCityId(): array
    {
        $rows = SchoolUnitGeo::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->groupBy('city_id')
            ->selectRaw('city_id, avg(lat) as lat, avg(lng) as lng, count(*) as n')
            ->having('n', '>', 0)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $lat = (float) $row->lat;
            $lng = (float) $row->lng;
            if (abs($lat) < 0.01 && abs($lng) < 0.01) {
                continue;
            }
            $out[(int) $row->city_id] = ['lat' => $lat, 'lng' => $lng];
        }

        return $out;
    }

    /**
     * @return ?array{lat: float, lng: float}
     */
    private function coordsFromSchoolGeos(int $cityId): ?array
    {
        if ($cityId <= 0) {
            return null;
        }

        $this->schoolGeoCache ??= $this->schoolGeoAveragesByCityId();

        return $this->schoolGeoCache[$cityId] ?? null;
    }

    /**
     * @return ?array{lat: float, lng: float}
     */
    private function coordsFromIbgeCached(int $cityId, string $ibge7): ?array
    {
        return Cache::remember(
            'municipality_map_coords:'.$cityId.':'.$ibge7,
            self::CACHE_TTL_SECONDS,
            fn (): ?array => $this->fetchIbgeCentroid($ibge7),
        );
    }

    /**
     * @return ?array{lat: float, lng: float}
     */
    private function fetchIbgeCentroid(string $ibge7): ?array
    {
        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios/'.$ibge7);

            if (! $response->successful()) {
                return null;
            }

            $centroide = $response->json('centroide');
            if (! is_array($centroide) || ($centroide['type'] ?? '') !== 'Point') {
                return null;
            }

            $coordinates = $centroide['coordinates'] ?? null;
            if (! is_array($coordinates) || count($coordinates) < 2) {
                return null;
            }

            return [
                'lat' => (float) $coordinates[1],
                'lng' => (float) $coordinates[0],
            ];
        } catch (\Throwable $e) {
            Log::debug('dashboard.map.ibge_failed', ['ibge' => $ibge7, 'message' => $e->getMessage()]);

            return null;
        }
    }

    private function normalizeIbge(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === null || strlen($digits) < 6) {
            return null;
        }

        return str_pad($digits, 7, '0', STR_PAD_LEFT);
    }
}
