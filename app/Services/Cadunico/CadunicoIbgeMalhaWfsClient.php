<?php

namespace App\Services\Cadunico;

use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;

/**
 * Malha oficial IBGE (GeoServer WFS) — bairros e setores censitários 2022.
 */
final class CadunicoIbgeMalhaWfsClient
{
    /**
     * @return array<string, array{lat: float, lng: float}>
     */
    /**
     * @param  (callable(string): void)|null  $log
     * @return array<string, array{lat: float, lng: float}>
     */
    public function centroidsByCodigo(string $ibge, string $tipo, ?callable $log = null): array
    {
        $cfg = config('ieducar.cadunico.territorio.ibge_wfs', []);
        $base = rtrim((string) ($cfg['base_url'] ?? 'https://geoservicos.ibge.gov.br/geoserver/CGMAT/wfs'), '/');
        $typeName = $tipo === 'bairro'
            ? (string) ($cfg['layer_bairro'] ?? 'CGMAT:qg_2022_650_bairro_agreg')
            : (string) ($cfg['layer_setor'] ?? 'CGMAT:qg_2022_600_setcensitario__v02');
        $codigoField = $tipo === 'bairro' ? 'cd_bairro' : 'cd_setor';

        if (! SafeOutboundUrl::isAllowedHttpUrl($base)) {
            self::logStep($log, __('   WFS: URL não permitida pela política de saída.'));

            return [];
        }

        $timeout = max(15, (int) ($cfg['timeout'] ?? 60));
        $out = [];

        self::logStep($log, __('   WFS GetFeature — camada :layer (cd_mun=:ibge)…', [
            'layer' => $typeName,
            'ibge' => $ibge,
        ]));

        try {
            $response = Http::timeout($timeout)->get($base, [
                'service' => 'WFS',
                'version' => '1.1.0',
                'request' => 'GetFeature',
                'typeName' => $typeName,
                'outputFormat' => 'application/json',
                'CQL_FILTER' => "cd_mun='{$ibge}'",
                'propertyName' => $codigoField.',geom',
                'maxFeatures' => max(50, min(2000, (int) ($cfg['max_features'] ?? 1500))),
            ]);
        } catch (\Throwable $e) {
            self::logStep($log, __('   WFS indisponível: :msg', ['msg' => $e->getMessage()]));

            return [];
        }

        if (! $response->successful()) {
            self::logStep($log, __('   WFS HTTP :status (mapa pode ficar sem coordenadas).', ['status' => (string) $response->status()]));

            return [];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }

        foreach (is_array($json['features'] ?? null) ? $json['features'] : [] as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $codigo = trim((string) ($props[$codigoField] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $geom = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;
            $centroid = $geom !== null ? self::centroidFromGeometry($geom) : null;
            if ($centroid !== null) {
                $out[$codigo] = $centroid;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return ?array{lat: float, lng: float}
     */
    private static function centroidFromGeometry(array $geometry): ?array
    {
        $points = [];
        self::collectPoints($geometry, $points);
        if ($points === []) {
            return null;
        }

        $lng = 0.0;
        $lat = 0.0;
        foreach ($points as $p) {
            $lng += $p[0];
            $lat += $p[1];
        }
        $n = count($points);

        return ['lng' => $lng / $n, 'lat' => $lat / $n];
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function collectPoints(array $geometry, array &$points): void
    {
        $type = (string) ($geometry['type'] ?? '');
        $coords = $geometry['coordinates'] ?? null;
        if (! is_array($coords)) {
            return;
        }

        if ($type === 'Point' && count($coords) >= 2) {
            $points[] = [(float) $coords[0], (float) $coords[1]];

            return;
        }

        if ($type === 'Polygon') {
            $ring = $coords[0] ?? [];
            if (is_array($ring)) {
                foreach ($ring as $pair) {
                    if (is_array($pair) && count($pair) >= 2) {
                        $points[] = [(float) $pair[0], (float) $pair[1]];
                    }
                }
            }

            return;
        }

        if ($type === 'MultiPolygon') {
            foreach ($coords as $poly) {
                if (! is_array($poly)) {
                    continue;
                }
                self::collectPoints(['type' => 'Polygon', 'coordinates' => $poly], $points);
            }
        }
    }

    /**
     * @param  (callable(string): void)|null  $log
     */
    private static function logStep(?callable $log, string $message): void
    {
        if ($log !== null) {
            $log($message);
        }
    }
}
