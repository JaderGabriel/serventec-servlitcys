<?php

namespace App\Support\Brazil;

use App\Support\Dashboard\AdminHomeMapCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Catálogo IBGE de municípios (nome, UF, centroide) — cache partilhado com mapas.
 */
final class IbgeMunicipalityCatalog
{
    private const CACHE_TTL_SECONDS = 604800;

    /**
     * @return array{ibge: string, name: string, uf: string, lat: float, lng: float}|null
     */
    public function metaByIbge(string $ibge): ?array
    {
        $ibge = $this->normalizeIbge($ibge);
        if ($ibge === null) {
            return null;
        }

        $cached = AdminHomeMapCache::get('ibge_municipality_meta:'.$ibge);
        if (is_array($cached) && isset($cached['ibge'], $cached['name'], $cached['uf'])) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios/'.$ibge);

            if (! $response->successful()) {
                return null;
            }

            $item = $response->json();
            if (! is_array($item)) {
                return null;
            }

            $ufHint = strtoupper(trim((string) (
                IbgeUfFromCode::ufFromIbge($ibge) ?? ''
            )));
            $meta = $this->metaFromApiItem($item, $ufHint !== '' ? $ufHint : null, 0, 1);
            if ($meta !== null) {
                AdminHomeMapCache::repository()->put('ibge_municipality_meta:'.$ibge, $meta, self::CACHE_TTL_SECONDS);
            }

            return $meta;
        } catch (\Throwable $e) {
            Log::debug('horizonte.ibge_meta_failed', ['ibge' => $ibge, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return list<string>
     */
    public static function brazilianUfs(): array
    {
        return [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
            'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
        ];
    }

    /**
     * Índice IBGE → metadados (nome, UF, centroide) a partir do cache por UF.
     *
     * @return array<string, array{ibge: string, name: string, uf: string, lat: float, lng: float}>
     */
    public function metaIndexForUfs(array $ufs, bool $fetchRemoteCentroids = false): array
    {
        $this->warmForUfs($ufs, $fetchRemoteCentroids);

        $index = [];
        foreach (array_unique(array_filter(array_map('strtoupper', $ufs))) as $uf) {
            foreach ($this->municipalitiesForUf($uf, $fetchRemoteCentroids) as $ibge => $meta) {
                $index[$ibge] = $meta;
            }
        }

        return $index;
    }

    /**
     * @param  list<string>  $ufs
     */
    public function warmForUfs(array $ufs, bool $fetchRemoteCentroids = false): void
    {
        foreach (array_unique(array_filter(array_map('strtoupper', $ufs))) as $uf) {
            $this->municipalitiesForUf($uf, $fetchRemoteCentroids);
        }
    }

    /**
     * @return array<string, array{ibge: string, name: string, uf: string, lat: float, lng: float}>
     */
    public function municipalitiesForUf(string $uf, bool $fetchRemoteCentroids = false): array
    {
        $uf = strtoupper(trim($uf));
        if ($uf === '') {
            return [];
        }

        $cacheKey = 'ibge_municipality_catalog_uf:v3:'.($fetchRemoteCentroids ? 'geo' : 'spread').':'.$uf;
        $cache = AdminHomeMapCache::repository();
        $cached = $cache->get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get('https://servicodados.ibge.gov.br/api/v1/localidades/estados/'.$uf.'/municipios');

            if (! $response->successful()) {
                return [];
            }

            $items = $response->json();
            if (! is_array($items)) {
                return [];
            }

            $total = count($items);
            $index = [];
            foreach ($items as $position => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $meta = $this->metaFromApiItem($item, $uf, (int) $position, $total, $fetchRemoteCentroids);
                if ($meta !== null) {
                    $index[$meta['ibge']] = $meta;
                    $cache->put('ibge_municipality_meta:'.$meta['ibge'], $meta, self::CACHE_TTL_SECONDS);
                }
            }

            if ($index !== []) {
                $cache->put($cacheKey, $index, self::CACHE_TTL_SECONDS);
            } else {
                $cache->forget($cacheKey);
            }

            return $index;
        } catch (\Throwable $e) {
            Log::debug('horizonte.ibge_uf_failed', ['uf' => $uf, 'message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{ibge: string, name: string, uf: string, lat: float, lng: float}|null
     */
    private function metaFromApiItem(array $item, ?string $ufHint = null, int $index = 0, int $total = 1, bool $fetchRemoteCentroids = false): ?array
    {
        $ibge = $this->normalizeIbge((string) ($item['id'] ?? ''));
        $name = trim((string) ($item['nome'] ?? ''));
        if ($ibge === null || $name === '') {
            return null;
        }

        $uf = strtoupper(trim((string) (
            $item['microrregiao']['mesorregiao']['UF']['sigla']
            ?? $item['regiao-imediata']['regiao-intermediaria']['UF']['sigla']
            ?? $ufHint
            ?? IbgeUfFromCode::ufFromIbge($ibge)
            ?? ''
        )));
        if ($uf === '') {
            return null;
        }

        [$lat, $lng, $source] = $this->coordinatesFromApiItem($item, $uf, $index, $total, $ibge, $fetchRemoteCentroids);

        return [
            'ibge' => $ibge,
            'name' => $name,
            'uf' => $uf,
            'lat' => $lat,
            'lng' => $lng,
            'coord_source' => $source,
        ];
    }

    /**
     * A API de localidades deixou de incluir centroide na listagem por UF; tenta endpoint individual (cacheado).
     *
     * @param  array<string, mixed>  $item
     * @return array{0: float, 1: float, 2: string}
     */
    private function coordinatesFromApiItem(array $item, string $uf, int $index, int $total, string $ibge, bool $fetchRemoteCentroids = false): array
    {
        $centroide = $item['centroide'] ?? null;
        if (is_array($centroide) && ($centroide['type'] ?? '') === 'Point') {
            $coordinates = $centroide['coordinates'] ?? null;
            if (is_array($coordinates) && count($coordinates) >= 2) {
                $lat = (float) $coordinates[1];
                $lng = (float) $coordinates[0];
                if (BrazilUfCentroids::isValidBrazilCoord($lat, $lng)) {
                    return [$lat, $lng, 'ibge_list'];
                }
            }
        }

        $cachedCentroid = AdminHomeMapCache::get('ibge_municipality_centroid:'.$ibge);
        if (is_array($cachedCentroid) && isset($cachedCentroid['lat'], $cachedCentroid['lng'])) {
            $lat = (float) $cachedCentroid['lat'];
            $lng = (float) $cachedCentroid['lng'];
            if (BrazilUfCentroids::isValidBrazilCoord($lat, $lng)) {
                return [$lat, $lng, 'ibge_cache'];
            }
        }

        if ($fetchRemoteCentroids) {
            $fromSingle = $this->fetchRawCentroidFromApi($ibge);
            if ($fromSingle !== null) {
                return [$fromSingle[0], $fromSingle[1], 'ibge_api'];
            }
        }

        [$lat, $lng] = BrazilUfCentroids::latLngForIndex($uf, $index, max(1, $total), (int) $ibge);

        return [$lat, $lng, 'uf_spread'];
    }

    /**
     * Centroide real via endpoint individual (sem recursão com metaByIbge).
     *
     * @return array{0: float, 1: float}|null
     */
    private function fetchRawCentroidFromApi(string $ibge): ?array
    {
        $ibge = $this->normalizeIbge($ibge);
        if ($ibge === null) {
            return null;
        }

        $cacheKey = 'ibge_municipality_centroid:'.$ibge;
        $cached = AdminHomeMapCache::get($cacheKey);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return [(float) $cached['lat'], (float) $cached['lng']];
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios/'.$ibge);

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

            $lat = (float) $coordinates[1];
            $lng = (float) $coordinates[0];
            if (! BrazilUfCentroids::isValidBrazilCoord($lat, $lng)) {
                return null;
            }

            AdminHomeMapCache::repository()->put($cacheKey, ['lat' => $lat, 'lng' => $lng], self::CACHE_TTL_SECONDS);

            return [$lat, $lng];
        } catch (\Throwable $e) {
            Log::debug('horizonte.ibge_centroid_failed', ['ibge' => $ibge, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @deprecated Use fetchRawCentroidFromApi
     * @return array{0: float, 1: float}|null
     */
    private function centroidFromSingleMunicipalityApi(string $ibge): ?array
    {
        return $this->fetchRawCentroidFromApi($ibge);
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
