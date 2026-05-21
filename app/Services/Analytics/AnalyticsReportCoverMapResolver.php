<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Georreferencia o município e obtém imagens de mapa (municipal + regional) para a capa do PDF.
 */
final class AnalyticsReportCoverMapResolver
{
    /** @var array<string, array{lat: float, lng: float}> */
    private const UF_CENTROIDS = [
        'AC' => [-9.0238, -70.8120],
        'AL' => [-9.5713, -36.7820],
        'AP' => [1.4142, -51.7713],
        'AM' => [-3.4168, -65.8561],
        'BA' => [-12.5797, -41.7007],
        'CE' => [-5.4984, -39.3206],
        'DF' => [-15.7998, -47.8645],
        'ES' => [-19.1834, -40.3089],
        'GO' => [-15.8270, -49.8362],
        'MA' => [-5.3549, -45.2743],
        'MT' => [-12.6819, -56.9211],
        'MS' => [-20.7722, -54.7852],
        'MG' => [-18.5122, -44.5550],
        'PA' => [-3.4168, -52.2160],
        'PB' => [-7.2399, -36.7819],
        'PR' => [-24.4842, -51.8149],
        'PE' => [-8.8137, -36.9541],
        'PI' => [-7.7183, -42.7289],
        'RJ' => [-22.2503, -42.6600],
        'RN' => [-5.4026, -36.9541],
        'RS' => [-30.0346, -51.2177],
        'RO' => [-10.83, -63.34],
        'RR' => [1.99, -61.33],
        'SC' => [-27.2423, -50.2189],
        'SP' => [-22.19, -48.79],
        'SE' => [-10.5741, -37.3857],
        'TO' => [-10.1753, -48.2982],
    ];

    /**
     * @return array{
     *   municipal: ?array{data_uri: string, source: string, caption: string},
     *   regional: ?array{data_uri: string, source: string, caption: string},
     *   coords: ?array{lat: float, lng: float, source: string}
     * }
     */
    public function resolve(City $city): array
    {
        $coords = $this->resolveCoordinates($city);
        $municipalZoom = max(8, min(13, (int) config('analytics.pdf_report.cover.map_zoom', 10)));
        $regionalZoom = max(5, $municipalZoom - 3);

        $municipal = null;
        $regional = null;

        if ($coords !== null) {
            $municipal = $this->fetchStaticMapDataUri(
                $coords['lat'],
                $coords['lng'],
                $municipalZoom,
                720,
                320,
            );
            $regional = $this->fetchStaticMapDataUri(
                $coords['lat'],
                $coords['lng'],
                $regionalZoom,
                720,
                240,
            );
        }

        if ($municipal === null) {
            $uf = strtoupper(trim((string) ($city->uf ?? '')));
            $ufCenter = self::UF_CENTROIDS[$uf] ?? null;
            if ($ufCenter !== null) {
                $municipal = $this->fetchStaticMapDataUri($ufCenter[0], $ufCenter[1], 6, 720, 320);
                if ($municipal !== null) {
                    $coords = $coords ?? ['lat' => $ufCenter[0], 'lng' => $ufCenter[1], 'source' => 'uf_centroid'];
                    $municipal['caption'] = __('Recorte regional (:uf — centro aproximado)', ['uf' => $uf]);
                }
                $regional = $regional ?? $this->fetchStaticMapDataUri($ufCenter[0], $ufCenter[1], 5, 720, 240);
            }
        }

        if ($regional === null && $municipal !== null && $coords !== null) {
            $regional = $this->fetchStaticMapDataUri(
                $coords['lat'],
                $coords['lng'],
                $regionalZoom,
                720,
                240,
            );
        }

        return [
            'municipal' => $municipal,
            'regional' => $regional,
            'coords' => $coords,
        ];
    }

    /**
     * @return ?array{lat: float, lng: float, source: string}
     */
    public function resolveCoordinates(City $city): ?array
    {
        $fromSchools = $this->coordsFromSchoolGeos($city);
        if ($fromSchools !== null) {
            return $fromSchools;
        }

        $ibge = $this->normalizeIbge((string) ($city->ibge_municipio ?? ''));
        if ($ibge !== null) {
            $fromIbge = $this->coordsFromIbgeApi($ibge);
            if ($fromIbge !== null) {
                return $fromIbge;
            }
        }

        $fromNominatim = $this->coordsFromNominatim($city);
        if ($fromNominatim !== null) {
            return $fromNominatim;
        }

        $uf = strtoupper(trim((string) ($city->uf ?? '')));
        if (isset(self::UF_CENTROIDS[$uf])) {
            return [
                'lat' => self::UF_CENTROIDS[$uf][0],
                'lng' => self::UF_CENTROIDS[$uf][1],
                'source' => 'uf_fallback',
            ];
        }

        return null;
    }

    /**
     * @return ?array{lat: float, lng: float, source: string}
     */
    private function coordsFromSchoolGeos(City $city): ?array
    {
        if ((int) ($city->id ?? 0) <= 0) {
            return null;
        }

        $row = SchoolUnitGeo::query()
            ->where('city_id', $city->id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->selectRaw('avg(lat) as lat, avg(lng) as lng, count(*) as n')
            ->first();

        if ($row === null || $row->lat === null || $row->lng === null || (int) ($row->n ?? 0) < 1) {
            return null;
        }

        $lat = (float) $row->lat;
        $lng = (float) $row->lng;
        if (abs($lat) < 0.01 && abs($lng) < 0.01) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng, 'source' => 'school_geos'];
    }

    /**
     * @return ?array{lat: float, lng: float, source: string}
     */
    private function coordsFromIbgeApi(string $ibge7): ?array
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios/'.$ibge7);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $centroide = $data['centroide'] ?? null;
            if (! is_array($centroide) || ($centroide['type'] ?? '') !== 'Point') {
                return null;
            }

            $coordinates = $centroide['coordinates'] ?? null;
            if (! is_array($coordinates) || count($coordinates) < 2) {
                return null;
            }

            $lng = (float) $coordinates[0];
            $lat = (float) $coordinates[1];

            return ['lat' => $lat, 'lng' => $lng, 'source' => 'ibge_api'];
        } catch (\Throwable $e) {
            Log::debug('pdf.cover.ibge_geocode_failed', ['ibge' => $ibge7, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return ?array{lat: float, lng: float, source: string}
     */
    private function coordsFromNominatim(City $city): ?array
    {
        $name = trim((string) $city->name);
        $uf = strtoupper(trim((string) ($city->uf ?? '')));
        if ($name === '') {
            return null;
        }

        $query = $uf !== '' ? "{$name}, {$uf}, Brasil" : "{$name}, Brasil";

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => trim((string) config('analytics.pdf_report.cover.nominatim_user_agent', 'servlitcys-pdf/1.0')),
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'br',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $hits = $response->json();
            if (! is_array($hits) || $hits === []) {
                return null;
            }

            $first = $hits[0];
            $lat = isset($first['lat']) ? (float) $first['lat'] : null;
            $lng = isset($first['lon']) ? (float) $first['lon'] : null;
            if ($lat === null || $lng === null) {
                return null;
            }

            return ['lat' => $lat, 'lng' => $lng, 'source' => 'nominatim'];
        } catch (\Throwable $e) {
            Log::debug('pdf.cover.nominatim_failed', ['q' => $query, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return ?array{data_uri: string, source: string, caption: string}
     */
    private function fetchStaticMapDataUri(float $lat, float $lng, int $zoom, int $width, int $height): ?array
    {
        $latStr = number_format($lat, 5, '.', '');
        $lngStr = number_format($lng, 5, '.', '');
        $size = $width.'x'.$height;

        $urls = [
            sprintf(
                'https://staticmap.openstreetmap.de/staticmap.php?center=%s,%s&zoom=%d&size=%s&maptype=mapnik&markers=%s,%s,red-pushpin',
                $latStr,
                $lngStr,
                $zoom,
                $size,
                $latStr,
                $lngStr,
            ),
            sprintf(
                'https://staticmap.openstreetmap.de/staticmap.php?center=%s,%s&zoom=%d&size=%s',
                $latStr,
                $lngStr,
                $zoom,
                $size,
            ),
        ];

        foreach ($urls as $url) {
            $binary = $this->downloadImage($url);
            if ($binary !== null) {
                return [
                    'data_uri' => 'data:image/png;base64,'.base64_encode($binary),
                    'source' => 'openstreetmap_static',
                    'caption' => __('Mapa OpenStreetMap'),
                ];
            }
        }

        return null;
    }

    private function downloadImage(string $url): ?string
    {
        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => trim((string) config('analytics.pdf_report.cover.nominatim_user_agent', 'servlitcys-pdf/1.0'))])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) < 80) {
                return null;
            }

            $type = strtolower((string) $response->header('Content-Type'));
            if (! str_contains($type, 'image')) {
                return null;
            }

            return $body;
        } catch (\Throwable) {
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
