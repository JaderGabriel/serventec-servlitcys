<?php

namespace App\Support\Analytics;

/**
 * Projeção lat/lng → coordenadas SVG para mapas no PDF.
 */
final class AnalyticsReportMapProjection
{
    /**
     * @param  list<array{lat: float, lng: float}>  $points
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float, lat: float, lng: float}
     */
    public static function bounds(array $points, float $padFactor = 0.12): array
    {
        if ($points === []) {
            return [
                'min_lat' => -1, 'max_lat' => 1, 'min_lng' => -1, 'max_lng' => 1,
                'lat' => 0, 'lng' => 0,
            ];
        }

        $minLat = $maxLat = $points[0]['lat'];
        $minLng = $maxLng = $points[0]['lng'];
        foreach ($points as $p) {
            $minLat = min($minLat, $p['lat']);
            $maxLat = max($maxLat, $p['lat']);
            $minLng = min($minLng, $p['lng']);
            $maxLng = max($maxLng, $p['lng']);
        }

        $latSpan = max(0.008, $maxLat - $minLat);
        $lngSpan = max(0.008, $maxLng - $minLng);
        $padLat = $latSpan * $padFactor;
        $padLng = $lngSpan * $padFactor;

        return [
            'min_lat' => $minLat - $padLat,
            'max_lat' => $maxLat + $padLat,
            'min_lng' => $minLng - $padLng,
            'max_lng' => $maxLng + $padLng,
            'lat' => ($minLat + $maxLat) / 2,
            'lng' => ($minLng + $maxLng) / 2,
        ];
    }

    /**
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $bounds
     * @return array{x: float, y: float}
     */
    public static function project(float $lat, float $lng, array $bounds, int $width, int $height, int $pad = 24): array
    {
        $latSpan = max(0.0001, $bounds['max_lat'] - $bounds['min_lat']);
        $lngSpan = max(0.0001, $bounds['max_lng'] - $bounds['min_lng']);
        $plotW = $width - 2 * $pad;
        $plotH = $height - 2 * $pad;

        $x = $pad + (($lng - $bounds['min_lng']) / $lngSpan) * $plotW;
        $y = $pad + (1 - (($lat - $bounds['min_lat']) / $latSpan)) * $plotH;

        return ['x' => $x, 'y' => $y];
    }
}
