<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\BrazilUfCentroids;

/**
 * Coordenadas e pontos fictícios para a demonstração animada do Horizonte (mapa SVG).
 */
final class HorizonteGuideDemo
{
    private const VIEW_W = 613.0;

    private const VIEW_H = 639.0;

    private const LAT_MAX = 5.27;

    private const LAT_MIN = -33.75;

    private const LNG_MIN = -73.99;

    private const LNG_MAX = -34.79;

    /** Recorte SVG da Bahia (viewBox regional). */
    private const BA_VIEW = ['x' => 425.0, 'y' => 225.0, 'w' => 185.0, 'h' => 185.0];

    /**
     * @return array{x: float, y: float}
     */
    public static function projectLatLng(float $lat, float $lng): array
    {
        $lngSpan = self::LNG_MAX - self::LNG_MIN;
        $latSpan = self::LAT_MAX - self::LAT_MIN;

        return [
            'x' => round(($lng - self::LNG_MIN) / $lngSpan * self::VIEW_W, 1),
            'y' => round((self::LAT_MAX - $lat) / $latSpan * self::VIEW_H, 1),
        ];
    }

    /**
     * Bolhas nacionais por UF (tamanho ∝ peso fictício de pressão).
     *
     * @return list<array{uf: string, x: float, y: float, r: float, heat: float}>
     */
    public static function nationalBubbles(): array
    {
        $weights = [
            'SP' => 1.0,
            'RJ' => 0.78,
            'MG' => 0.82,
            'BA' => 0.88,
            'RS' => 0.7,
            'PE' => 0.62,
            'CE' => 0.58,
            'PR' => 0.65,
            'GO' => 0.55,
            'PA' => 0.48,
            'SC' => 0.52,
            'MA' => 0.45,
        ];

        $bubbles = [];
        foreach ($weights as $uf => $heat) {
            [$lat, $lng] = BrazilUfCentroids::latLng($uf);
            $pt = self::projectLatLng($lat, $lng);
            $bubbles[] = [
                'uf' => $uf,
                'x' => $pt['x'],
                'y' => $pt['y'],
                'r' => round(8 + $heat * 10, 1),
                'heat' => $heat,
            ];
        }

        usort($bubbles, static fn (array $a, array $b) => $b['heat'] <=> $a['heat']);

        return $bubbles;
    }

    /**
     * @return array{x: float, y: float, r: float}
     */
    public static function highlightUf(string $uf = 'BA'): array
    {
        [$lat, $lng] = BrazilUfCentroids::latLng($uf);

        return array_merge(self::projectLatLng($lat, $lng), ['r' => 20.0]);
    }

    /**
     * Pontos municipais fictícios no recorte da Bahia.
     *
     * @return list<array{x: float, y: float, r: float, heat: float, label: string}>
     */
    public static function bahiaMunicipalDots(): array
    {
        $center = self::highlightUf('BA');
        $local = static function (float $dx, float $dy, float $heat, string $label) use ($center): array {
            return [
                'x' => round($center['x'] + $dx - self::BA_VIEW['x'], 1),
                'y' => round($center['y'] + $dy - self::BA_VIEW['y'], 1),
                'r' => round(4 + $heat * 5, 1),
                'heat' => $heat,
                'label' => $label,
            ];
        };

        return [
            $local(-28, -18, 0.92, 'Salvador'),
            $local(-8, 12, 0.78, 'Feira de Santana'),
            $local(22, -8, 0.71, 'Vitória da Conquista'),
            $local(-42, 28, 0.65, 'Ilhéus'),
            $local(8, 32, 0.58, 'Jequié'),
            $local(-18, -38, 0.84, 'Camaçari'),
            $local(38, 18, 0.52, 'Barreiras'),
            $local(-52, 4, 0.48, 'Itabuna'),
        ];
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function bahiaViewBox(): array
    {
        return self::BA_VIEW;
    }

    public static function heatColor(float $heat): string
    {
        if ($heat >= 0.8) {
            return '#be123c';
        }
        if ($heat >= 0.65) {
            return '#ea580c';
        }
        if ($heat >= 0.5) {
            return '#d97706';
        }

        return '#64748b';
    }
}
