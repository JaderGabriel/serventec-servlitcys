<?php

namespace App\Support\Geo;

/** Área aproximada de polígonos GeoJSON em km² (elipsoide WGS84 simplificado). */
final class GeoJsonFeatureAreaKm2
{
    private const EARTH_RADIUS_M = 6378137.0;

    /**
     * @param  array<string, mixed>  $feature
     */
    public static function fromFeature(array $feature): ?float
    {
        $geometry = $feature['geometry'] ?? null;
        if (! is_array($geometry)) {
            return null;
        }

        $type = (string) ($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? null;
        if (! is_array($coordinates)) {
            return null;
        }

        $areaM2 = match ($type) {
            'Polygon' => self::polygonAreaM2($coordinates),
            'MultiPolygon' => self::multiPolygonAreaM2($coordinates),
            default => null,
        };

        if ($areaM2 === null || $areaM2 <= 0) {
            return null;
        }

        return round($areaM2 / 1_000_000, 3);
    }

    /**
     * @param  array<int, mixed>  $rings
     */
    private static function polygonAreaM2(array $rings): ?float
    {
        if ($rings === []) {
            return null;
        }

        $area = 0.0;
        foreach ($rings as $index => $ring) {
            if (! is_array($ring)) {
                continue;
            }
            $ringArea = self::ringAreaM2($ring);
            if ($ringArea === null) {
                continue;
            }
            $area += $index === 0 ? $ringArea : -$ringArea;
        }

        return $area > 0 ? $area : null;
    }

    /**
     * @param  array<int, mixed>  $polygons
     */
    private static function multiPolygonAreaM2(array $polygons): ?float
    {
        $total = 0.0;
        $has = false;
        foreach ($polygons as $polygon) {
            if (! is_array($polygon)) {
                continue;
            }
            $part = self::polygonAreaM2($polygon);
            if ($part === null) {
                continue;
            }
            $total += $part;
            $has = true;
        }

        return $has ? $total : null;
    }

    /**
     * @param  array<int, mixed>  $ring
     */
    private static function ringAreaM2(array $ring): ?float
    {
        $points = [];
        foreach ($ring as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }
            $points[] = [(float) $point[0], (float) $point[1]];
        }

        $count = count($points);
        if ($count < 3) {
            return null;
        }

        if ($points[0][0] !== $points[$count - 1][0] || $points[0][1] !== $points[$count - 1][1]) {
            $points[] = $points[0];
            $count++;
        }

        $area = 0.0;
        for ($i = 0; $i < $count - 1; $i++) {
            $lon1 = deg2rad($points[$i][0]);
            $lat1 = deg2rad($points[$i][1]);
            $lon2 = deg2rad($points[$i + 1][0]);
            $lat2 = deg2rad($points[$i + 1][1]);
            $area += ($lon2 - $lon1) * (2 + sin($lat1) + sin($lat2));
        }

        $area = abs($area * self::EARTH_RADIUS_M * self::EARTH_RADIUS_M / 2.0);

        return $area > 0 ? $area : null;
    }
}
