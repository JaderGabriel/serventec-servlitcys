<?php

namespace App\Support\Brazil;

/**
 * Coordenadas das capitais estaduais (sede administrativa da UF).
 */
final class BrazilStateCapitals
{
    /** @var array<string, array{0: float, 1: float}> lat, lng */
    private const CAPITALS = [
        'AC' => [-9.974, -67.824],
        'AL' => [-9.665, -35.735],
        'AP' => [0.034, -51.069],
        'AM' => [-3.119, -60.021],
        'BA' => [-12.971, -38.501],
        'CE' => [-3.717, -38.543],
        'DF' => [-15.794, -47.882],
        'ES' => [-20.315, -40.292],
        'GO' => [-16.686, -49.265],
        'MA' => [-2.530, -44.306],
        'MT' => [-15.601, -56.098],
        'MS' => [-20.469, -54.622],
        'MG' => [-19.916, -43.934],
        'PA' => [-1.456, -48.504],
        'PB' => [-7.119, -34.845],
        'PR' => [-25.428, -42.983],
        'PE' => [-8.047, -34.877],
        'PI' => [-5.089, -42.801],
        'RJ' => [-22.907, -43.173],
        'RN' => [-5.794, -35.211],
        'RS' => [-30.034, -51.217],
        'RO' => [-8.761, -63.904],
        'RR' => [2.824, -60.675],
        'SC' => [-27.595, -48.549],
        'SP' => [-23.550, -46.633],
        'SE' => [-10.947, -37.073],
        'TO' => [-10.184, -48.334],
    ];

    /**
     * @return array{0: float, 1: float}
     */
    public static function latLng(string $uf): array
    {
        $uf = strtoupper(trim($uf));
        $coords = self::CAPITALS[$uf] ?? [-14.5, -52.0];

        return BrazilUfCentroids::clampBrazil($coords[0], $coords[1]);
    }
}
