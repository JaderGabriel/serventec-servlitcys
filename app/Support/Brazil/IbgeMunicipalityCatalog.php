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

            $meta = $this->metaFromApiItem($item);
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

        return AdminHomeMapCache::repository()->remember(
            'ibge_municipality_catalog_uf:'.$uf,
            self::CACHE_TTL_SECONDS,
            function () use ($uf): array {
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

                    $index = [];
                    foreach ($items as $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $meta = $this->metaFromApiItem($item);
                        if ($meta !== null) {
                            $index[$meta['ibge']] = $meta;
                            AdminHomeMapCache::repository()->put('ibge_municipality_meta:'.$meta['ibge'], $meta, self::CACHE_TTL_SECONDS);
                        }
                    }

                    return $index;
                } catch (\Throwable $e) {
                    Log::debug('horizonte.ibge_uf_failed', ['uf' => $uf, 'message' => $e->getMessage()]);

                    return [];
                }
            },
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{ibge: string, name: string, uf: string, lat: float, lng: float}|null
     */
    private function metaFromApiItem(array $item): ?array
    {
        $ibge = $this->normalizeIbge((string) ($item['id'] ?? ''));
        $name = trim((string) ($item['nome'] ?? ''));
        if ($ibge === null || $name === '') {
            return null;
        }

        $uf = strtoupper(trim((string) (
            $item['microrregiao']['mesorregiao']['UF']['sigla']
            ?? $item['regiao-imediata']['regiao-intermediaria']['UF']['sigla']
            ?? ''
        )));

        $centroide = $item['centroide'] ?? null;
        if (! is_array($centroide) || ($centroide['type'] ?? '') !== 'Point') {
            return null;
        }

        $coordinates = $centroide['coordinates'] ?? null;
        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $lat = (float) $coordinates[1];
        $lng = (float) $coordinates[0];
        if ($lat < -34.0 || $lat > 5.5 || $lng < -74.5 || $lng > -32.0) {
            return null;
        }

        return [
            'ibge' => $ibge,
            'name' => $name,
            'uf' => $uf,
            'lat' => $lat,
            'lng' => $lng,
        ];
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
