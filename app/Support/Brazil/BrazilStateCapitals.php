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

    /** @var array<string, string> */
    private const CAPITAL_NAMES = [
        'AC' => 'Rio Branco',
        'AL' => 'Maceió',
        'AP' => 'Macapá',
        'AM' => 'Manaus',
        'BA' => 'Salvador',
        'CE' => 'Fortaleza',
        'DF' => 'Brasília',
        'ES' => 'Vitória',
        'GO' => 'Goiânia',
        'MA' => 'São Luís',
        'MT' => 'Cuiabá',
        'MS' => 'Campo Grande',
        'MG' => 'Belo Horizonte',
        'PA' => 'Belém',
        'PB' => 'João Pessoa',
        'PR' => 'Curitiba',
        'PE' => 'Recife',
        'PI' => 'Teresina',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Natal',
        'RS' => 'Porto Alegre',
        'RO' => 'Porto Velho',
        'RR' => 'Boa Vista',
        'SC' => 'Florianópolis',
        'SP' => 'São Paulo',
        'SE' => 'Aracaju',
        'TO' => 'Palmas',
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

    public static function name(string $uf): string
    {
        $uf = strtoupper(trim($uf));

        return self::CAPITAL_NAMES[$uf] ?? '';
    }

    public static function distanceKm(float $lat, float $lng, string $uf): ?float
    {
        if (! BrazilUfCentroids::isValidBrazilCoord($lat, $lng)) {
            return null;
        }

        [$capLat, $capLng] = self::latLng($uf);
        $km = self::haversineKm($lat, $lng, $capLat, $capLng);

        return $km !== null ? round($km, 1) : null;
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        if (abs($lat1) > 90 || abs($lat2) > 90) {
            return null;
        }

        $r = 6371.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
