<?php

namespace App\Services\Inep;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Coordenadas a partir do Catálogo de Escolas (INEP/MEC) via serviço ArcGIS público.
 *
 * @see https://www.gov.br/inep/pt-br/acesso-a-informacao/dados-abertos/inep-data/catalogo-de-escolas
 */
class InepCatalogoEscolasGeoService
{
    /**
     * @param  list<int|string>  $codes  Códigos INEP (8 dígitos habituais)
     * @return array<int, array{lat: float, lng: float, nome_inep: string}>
     */
    public function lookupByInepCodes(array $codes): array
    {
        if (! filter_var(config('ieducar.inep_geocoding.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $normalized = [];
        foreach ($codes as $c) {
            $n = $this->normalizeInepCode($c);
            if ($n !== null) {
                $normalized[] = $n;
            }
        }
        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return [];
        }

        $url = (string) config(
            'ieducar.inep_geocoding.arcgis_layer_query_url',
            'https://services3.arcgis.com/ba17q0p2zHwzRK3B/arcgis/rest/services/inep_escolas_fmt_250609_geocode/FeatureServer/1/query'
        );
        $batchSize = max(5, min(100, (int) config('ieducar.inep_geocoding.batch_size', 40)));
        $ttl = max(3600, (int) config('ieducar.inep_geocoding.cache_ttl_seconds', 2592000));
        $missTtl = min(86400, $ttl);

        $out = [];
        foreach (array_chunk($normalized, $batchSize) as $chunk) {
            $toFetch = [];
            foreach ($chunk as $code) {
                $cached = Cache::get($this->cacheKey($code));
                if (is_array($cached)) {
                    if (! empty($cached['miss'])) {
                        continue;
                    }
                    if (isset($cached['lat'], $cached['lng']) && $this->validCoord((float) $cached['lat'], (float) $cached['lng'])) {
                        $out[$code] = [
                            'lat' => (float) $cached['lat'],
                            'lng' => (float) $cached['lng'],
                            'nome_inep' => (string) ($cached['nome_inep'] ?? ''),
                        ];
                    }

                    continue;
                }
                $toFetch[] = $code;
            }
            if ($toFetch === []) {
                continue;
            }

            $fetched = $this->fetchFromArcgis($url, $toFetch);
            foreach ($toFetch as $code) {
                if (isset($fetched[$code])) {
                    $row = $fetched[$code];
                    Cache::put($this->cacheKey($code), $row, $ttl);
                    $out[$code] = $row;
                } else {
                    Cache::put($this->cacheKey($code), ['miss' => true], $missTtl);
                }
            }
        }

        return $out;
    }

    public function cacheKey(int $code): string
    {
        return 'inep_geo_v1_'.$code;
    }

    private function normalizeInepCode(mixed $c): ?int
    {
        if ($c === null || $c === '') {
            return null;
        }
        if (is_int($c) || is_float($c)) {
            $n = (int) $c;

            return $n > 0 ? $n : null;
        }
        $digits = preg_replace('/\D/', '', (string) $c);
        if ($digits === '' || $digits === '0') {
            return null;
        }

        return (int) $digits;
    }

    /**
     * @param  list<int>  $codes
     * @return array<int, array{lat: float, lng: float, nome_inep: string}>
     */
    private function fetchFromArcgis(string $url, array $codes): array
    {
        $inList = implode(',', array_map(static fn (int $i) => (string) $i, array_map('intval', $codes)));
        $where = 'Código_INEP IN ('.$inList.')';

        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->get($url, [
                    'where' => $where,
                    'outFields' => 'Código_INEP,Escola,Latitude,Longitude',
                    'f' => 'json',
                    'returnGeometry' => 'false',
                ]);

            if (! $response->successful()) {
                Log::warning('INEP ArcGIS geocode: HTTP não OK', ['status' => $response->status()]);

                return [];
            }

            $data = $response->json();
            $features = is_array($data['features'] ?? null) ? $data['features'] : [];
            $out = [];
            foreach ($features as $f) {
                $attrs = is_array($f['attributes'] ?? null) ? $f['attributes'] : [];
                $code = (int) ($attrs['Código_INEP'] ?? 0);
                if ($code <= 0) {
                    continue;
                }
                $lat = (float) ($attrs['Latitude'] ?? 0);
                $lng = (float) ($attrs['Longitude'] ?? 0);
                if (! $this->validCoord($lat, $lng)) {
                    continue;
                }
                $out[$code] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'nome_inep' => (string) ($attrs['Escola'] ?? ''),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('INEP ArcGIS geocode: excepção', ['message' => $e->getMessage()]);

            return [];
        }
    }

    private function validCoord(float $lat, float $lng): bool
    {
        if (abs($lat) < 0.01 && abs($lng) < 0.01) {
            return false;
        }

        return abs($lat) <= 90 && abs($lng) <= 180;
    }
}
