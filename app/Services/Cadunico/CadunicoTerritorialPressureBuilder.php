<?php

namespace App\Services\Cadunico;

use App\Models\City;
use App\Repositories\CadunicoTerritorioSnapshotRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Pressão territorial da lacuna CadÚnico (bairro/setor) + proximidade às escolas.
 */
final class CadunicoTerritorialPressureBuilder
{
    public function __construct(
        private CadunicoTerritorioSnapshotRepository $territorios,
    ) {}

    /**
     * @param  array<string, mixed>  $gap
     * @param  list<array{lat: float|int, lng: float|int, label?: string}>  $schoolMarkers
     * @return array<string, mixed>
     */
    public function build(
        City $city,
        IeducarFilterState $filters,
        array $gap,
        array $schoolMarkers = [],
    ): array {
        $year = (int) $filters->ano_letivo;
        $rows = $this->territorios->forCityYear($city, $year);
        $gapTotal = max(0, (int) ($gap['gap_total'] ?? 0));
        $cadTotal = max(0, (int) ($gap['cadunico_total_escolar'] ?? 0));
        $vaaf = (float) ($gap['impacto_financeiro']['vaaf'] ?? 0);

        if ($rows->isEmpty()) {
            return [
                'available' => false,
                'markers' => [],
                'ranking' => [],
                'school_markers' => $schoolMarkers,
                'territorios_count' => 0,
                'schools_on_map' => count($schoolMarkers),
                'nota' => __('Importe agregados por bairro, setor censitário ou território CRAS (CSV) em Admin → CadÚnico ou `cadunico:import-territorio`.'),
            ];
        }

        $cadTerrSum = max(1, (int) $rows->sum(static fn ($r) => $r->totalEscolar()));
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];
        $ranking = [];
        $markers = [];

        foreach ($rows as $row) {
            $cadLocal = $row->totalEscolar();
            if ($cadLocal <= 0) {
                continue;
            }

            $share = $cadLocal / $cadTerrSum;
            $gapEst = $gapTotal > 0
                ? (int) round($gapTotal * $share)
                : max(0, $cadLocal - (int) round(($gap['ieducar_matriculas'] ?? 0) * $share));

            $lat = $row->latitude;
            $lng = $row->longitude;
            $distKm = self::nearestSchoolKm($lat, $lng, $schoolMarkers);
            $vuln = (float) ($row->indice_vulnerabilidade ?? 0);
            $pressure = round($gapEst * (1.0 + min(100.0, $vuln) / 100.0) * (1.0 + min(15.0, $distKm ?? 0) / 15.0 * 0.35), 2);

            $fundeb = ($gapEst > 0 && $vaaf > 0) ? MoneyMath::multiplyVaaf($gapEst, $vaaf) : 0.0;

            $ranking[] = [
                'codigo' => $row->territorio_codigo,
                'nome' => $row->territorio_nome,
                'tipo' => $row->territorio_tipo,
                'cadunico' => $cadLocal,
                'gap_estimado' => $gapEst,
                'gap_fmt' => number_format($gapEst, 0, ',', '.'),
                'fundeb_label' => $fundeb > 0 ? $fmt($fundeb) : '—',
                'distancia_escola_km' => $distKm !== null ? round($distKm, 1) : null,
                'indice_vulnerabilidade' => $vuln > 0 ? $vuln : null,
                'pressao' => $pressure,
            ];

            if ($lat !== null && $lng !== null && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
                $radius = max(8, min(28, (int) round(8 + sqrt(max(0, $gapEst)) * 0.6)));
                $markers[] = [
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'label' => $row->territorio_nome,
                    'tipo' => $row->territorio_tipo,
                    'gap' => $gapEst,
                    'cadunico' => $cadLocal,
                    'pressao' => $pressure,
                    'radius' => $radius,
                    'meta' => __('Lacuna est.: :g · CadÚnico: :c', [
                        'g' => number_format($gapEst, 0, ',', '.'),
                        'c' => number_format($cadLocal, 0, ',', '.'),
                    ]),
                ];
            }
        }

        usort($ranking, static fn ($a, $b) => ($b['pressao'] ?? 0) <=> ($a['pressao'] ?? 0));
        $ranking = array_slice($ranking, 0, 30);

        return [
            'available' => true,
            'markers' => $markers,
            'ranking' => $ranking,
            'school_markers' => $schoolMarkers,
            'territorios_count' => $rows->count(),
            'schools_on_map' => count($schoolMarkers),
            'cad_territorio_sum' => $cadTerrSum,
            'cad_municipal' => $cadTotal,
            'nota' => $cadTerrSum < $cadTotal
                ? __('A soma territorial (:t) é menor que o CadÚnico municipal (:m) — complete importações ou territórios sem cobertura.', [
                    't' => number_format($cadTerrSum, 0, ',', '.'),
                    'm' => number_format($cadTotal, 0, ',', '.'),
                ])
                : __('Lacuna municipal rateada pelo peso da população escolar CadÚnico em cada território.'),
        ];
    }

    /**
     * @param  list<array{lat: float|int, lng: float|int}>  $schoolMarkers
     */
    private static function nearestSchoolKm(?float $lat, ?float $lng, array $schoolMarkers): ?float
    {
        if ($lat === null || $lng === null || $schoolMarkers === []) {
            return null;
        }

        $best = null;
        foreach ($schoolMarkers as $s) {
            $slat = (float) ($s['lat'] ?? 0);
            $slng = (float) ($s['lng'] ?? 0);
            if (abs($slat) > 90 || abs($slng) > 180) {
                continue;
            }
            $m = self::haversineKm($lat, $lng, $slat, $slng);
            if ($m !== null && ($best === null || $m < $best)) {
                $best = $m;
            }
        }

        return $best;
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
