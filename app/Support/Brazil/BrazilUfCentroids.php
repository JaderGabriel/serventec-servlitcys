<?php

namespace App\Support\Brazil;

/**
 * Centróides aproximados por UF para posicionar municípios no mapa do Brasil (sem lat/lng por cidade).
 */
final class BrazilUfCentroids
{
    /** @var array<string, array{0: float, 1: float}> lat, lng */
    private const CENTROIDS = [
        'AC' => [-8.77, -70.55],
        'AL' => [-9.57, -36.78],
        'AP' => [1.41, -51.77],
        'AM' => [-3.47, -65.10],
        'BA' => [-12.58, -41.70],
        'CE' => [-5.20, -39.53],
        'DF' => [-15.83, -47.86],
        'ES' => [-19.57, -40.63],
        'GO' => [-15.98, -49.38],
        'MA' => [-5.42, -45.44],
        'MT' => [-12.64, -55.42],
        'MS' => [-20.51, -54.54],
        'MG' => [-18.59, -44.25],
        'PA' => [-3.79, -52.48],
        'PB' => [-7.24, -36.72],
        'PR' => [-24.89, -51.55],
        'PE' => [-8.38, -37.86],
        'PI' => [-6.60, -42.28],
        'RJ' => [-22.25, -42.66],
        'RN' => [-5.81, -36.59],
        'RS' => [-30.17, -53.50],
        'RO' => [-11.22, -62.80],
        'RR' => [1.99, -61.33],
        'SC' => [-27.45, -50.95],
        'SP' => [-22.19, -48.79],
        'SE' => [-10.57, -37.45],
        'TO' => [-9.46, -48.26],
    ];

    /**
     * @return array{0: float, 1: float}
     */
    public static function latLng(string $uf, int $scatterSeed = 0): array
    {
        return self::latLngForIndex($uf, $scatterSeed > 0 ? 1 : 0, $scatterSeed > 0 ? max(2, ($scatterSeed % 12) + 2) : 1, $scatterSeed);
    }

    /**
     * Dispersa municípios da mesma UF em anel, para não sobrepor no mapa.
     *
     * @return array{0: float, 1: float}
     */
    public static function latLngForIndex(string $uf, int $index, int $total, int $extraSeed = 0): array
    {
        $uf = strtoupper(trim($uf));
        $base = self::CENTROIDS[$uf] ?? [-14.5, -52.0];

        if ($total <= 1 && $extraSeed === 0) {
            return $base;
        }

        $slot = $index + ($extraSeed % max(1, $total));
        $angle = (2 * M_PI * $slot) / max(1, $total);
        $rings = (int) ceil($total / 8);
        $ring = (int) floor($slot / 8);
        $radius = 0.42 + ($ring * 0.28) + min(1.1, 0.12 * sqrt($total));

        return [
            round($base[0] + $radius * cos($angle), 5),
            round($base[1] + $radius * sin($angle) * 1.12, 5),
        ];
    }
}
