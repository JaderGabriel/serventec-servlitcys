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
    public function metaIndexForUfs(array $ufs): array
    {
        $this->warmForUfs($ufs);

        $index = [];
        foreach (array_unique(array_filter(array_map('strtoupper', $ufs))) as $uf) {
            foreach ($this->municipalitiesForUf($uf) as $ibge => $meta) {
                $index[$ibge] = $meta;
            }
        }

        return $index;
    }

    /**
     * @param  list<string>  $ufs
     */
    public function warmForUfs(array $ufs): void
    {
        foreach (array_unique(array_filter(array_map('strtoupper', $ufs))) as $uf) {
            $this->municipalitiesForUf($uf);
        }
    }

    /**
     * @return array<string, array{ibge: string, name: string, uf: string, lat: float, lng: float}>
     */
    public function municipalitiesForUf(string $uf): array
    {
        $uf = strtoupper(trim($uf));
        if ($uf === '') {
            return [];
        }

        $cacheKey = 'ibge_municipality_catalog_uf:'.$uf;
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
                $meta = $this->metaFromApiItem($item, $uf, (int) $position, $total);
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
    private function metaFromApiItem(array $item, ?string $ufHint = null, int $index = 0, int $total = 1): ?array
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

        [$lat, $lng] = $this->coordinatesFromApiItem($item, $uf, $index, $total, (int) $ibge);

        return [
            'ibge' => $ibge,
            'name' => $name,
            'uf' => $uf,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * A API de localidades deixou de incluir centroide na listagem por UF; usa dispersão na UF como fallback.
     *
     * @param  array<string, mixed>  $item
     * @return array{0: float, 1: float}
     */
    private function coordinatesFromApiItem(array $item, string $uf, int $index, int $total, int $ibgeSeed): array
    {
        $centroide = $item['centroide'] ?? null;
        if (is_array($centroide) && ($centroide['type'] ?? '') === 'Point') {
            $coordinates = $centroide['coordinates'] ?? null;
            if (is_array($coordinates) && count($coordinates) >= 2) {
                $lat = (float) $coordinates[1];
                $lng = (float) $coordinates[0];
                if ($lat >= -34.0 && $lat <= 5.5 && $lng >= -74.5 && $lng <= -32.0) {
                    return [$lat, $lng];
                }
            }
        }

        return BrazilUfCentroids::latLngForIndex($uf, $index, max(1, $total), $ibgeSeed);
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
