<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\Ieducar\SchoolUnitsRepository;
use App\Support\Analytics\AnalyticsReportMapProjection;
use App\Support\Analytics\AnalyticsReportSchoolUnitsMapSvg;
use App\Support\Dashboard\IeducarFilterState;

final class AnalyticsReportSchoolMapBuilder
{
    public function __construct(
        private readonly SchoolUnitsRepository $schoolUnits,
        private readonly AnalyticsReportCoverMapResolver $mapResolver,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     svg: ?string,
     *     data_uri: ?string,
     *     caption: ?string,
     *     geo_note: ?string,
     *     stats: array<string, mixed>,
     *     map_scope: ?string
     * }
     */
    public function build(City $city, IeducarFilterState $filters): array
    {
        $snapshot = $this->schoolUnits->snapshot($city, $filters);
        $tab = is_array($snapshot['tab'] ?? null) ? $snapshot['tab'] : [];
        $markers = is_array($tab['markers'] ?? null) ? $tab['markers'] : [];

        if ($markers === []) {
            return [
                'available' => false,
                'svg' => null,
                'data_uri' => null,
                'caption' => null,
                'geo_note' => $tab['geo_note'] ?? $snapshot['error'] ?? __('Sem coordenadas para exibir o mapa de unidades.'),
                'stats' => [],
                'map_scope' => $tab['map_scope'] ?? null,
            ];
        }

        $colors = config('analytics.pdf_report.colors', []);
        $primary = (string) ($colors['primary'] ?? '#0f766e');
        $secondary = (string) ($colors['secondary'] ?? '#4338ca');

        $baseMap = $this->baseMapForMarkers($city, $markers);

        $rendered = AnalyticsReportSchoolUnitsMapSvg::render(
            $markers,
            $baseMap,
            680,
            360,
            $primary,
            $secondary,
        );

        if ($rendered === null) {
            return [
                'available' => false,
                'svg' => null,
                'data_uri' => null,
                'caption' => null,
                'geo_note' => $tab['geo_note'] ?? null,
                'stats' => [],
                'map_scope' => $tab['map_scope'] ?? null,
            ];
        }

        $stats = is_array($rendered['stats'] ?? null) ? $rendered['stats'] : [];

        return [
            'available' => true,
            'svg' => $rendered['svg'],
            'data_uri' => $rendered['data_uri'],
            'caption' => __('Mesma base do painel «Unidades escolares»: :n escola(s), :m matrícula(s) no recorte. Centro de abrangência ponderado por matrículas.',
                [
                    'n' => $stats['schools'] ?? count($markers),
                    'm' => number_format((int) ($stats['matriculas_total'] ?? 0), 0, ',', '.'),
                ]),
            'geo_note' => $tab['geo_note'] ?? null,
            'stats' => $stats,
            'map_scope' => $tab['map_scope'] ?? 'matricula',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     */
    private function baseMapForMarkers(City $city, array $markers): ?string
    {
        $pts = [];
        foreach ($markers as $m) {
            if (isset($m['lat'], $m['lng'])) {
                $pts[] = ['lat' => (float) $m['lat'], 'lng' => (float) $m['lng']];
            }
        }
        if ($pts === []) {
            return null;
        }

        $bounds = AnalyticsReportMapProjection::bounds($pts, 0.18);
        $centerLat = ($bounds['min_lat'] + $bounds['max_lat']) / 2;
        $centerLng = ($bounds['min_lng'] + $bounds['max_lng']) / 2;

        $latSpan = $bounds['max_lat'] - $bounds['min_lat'];
        $zoom = match (true) {
            $latSpan > 0.35 => 9,
            $latSpan > 0.15 => 10,
            $latSpan > 0.06 => 11,
            default => 12,
        };

        $tile = $this->mapResolver->fetchStaticMapAt($centerLat, $centerLng, $zoom, 680, 360);
        if ($tile !== null) {
            return $tile['data_uri'];
        }

        $maps = $this->mapResolver->resolve($city);

        return $maps['municipal']['data_uri'] ?? null;
    }
}
