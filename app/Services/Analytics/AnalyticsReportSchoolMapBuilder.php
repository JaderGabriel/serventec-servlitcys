<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\Ieducar\SchoolUnitsRepository;
use App\Support\Analytics\AnalyticsReportMapProjection;
use App\Support\Analytics\AnalyticsReportSchoolMapImageComposer;
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
                'image_data_uri' => null,
                'schools_table' => [],
            ];
        }

        $colors = config('analytics.pdf_report.colors', []);
        $primary = (string) ($colors['primary'] ?? '#0f766e');
        $secondary = (string) ($colors['secondary'] ?? '#4338ca');
        $mapWidth = max(400, (int) config('analytics.pdf_report.content_width_pt', 520));
        $mapHeight = max(220, (int) config('analytics.pdf_report.school_map_height_pt', 292));

        $baseMap = $this->baseMapForMarkers($city, $markers, $mapWidth, $mapHeight);
        $schoolsTable = $this->buildSchoolsTable($markers);

        $png = AnalyticsReportSchoolMapImageComposer::compose(
            $markers,
            $baseMap,
            $mapWidth,
            $mapHeight,
            $primary,
            $secondary,
        );

        $rendered = null;
        if ($png === null) {
            $rendered = AnalyticsReportSchoolUnitsMapSvg::render(
                $markers,
                $baseMap,
                $mapWidth,
                $mapHeight,
                $primary,
                $secondary,
            );
        }

        if ($png === null && $rendered === null) {
            return [
                'available' => false,
                'svg' => null,
                'data_uri' => null,
                'caption' => null,
                'geo_note' => $tab['geo_note'] ?? null,
                'stats' => [],
                'map_scope' => $tab['map_scope'] ?? null,
                'image_data_uri' => null,
                'schools_table' => $schoolsTable,
            ];
        }

        $stats = is_array($png['stats'] ?? null)
            ? $png['stats']
            : (is_array($rendered['stats'] ?? null) ? $rendered['stats'] : []);

        $imageUri = $png['data_uri'] ?? $rendered['data_uri'] ?? null;

        return [
            'available' => true,
            'width' => $mapWidth,
            'height' => $mapHeight,
            'svg' => $rendered['svg'] ?? null,
            'data_uri' => $imageUri,
            'image_data_uri' => $imageUri,
            'caption' => __('Mapa territorial com fundo OpenStreetMap: :n escola(s) e :m matrícula(s) no recorte. Círculos proporcionais ao volume; anel tracejado = centro de abrangência ponderado.',
                [
                    'n' => $stats['schools'] ?? count($markers),
                    'm' => number_format((int) ($stats['matriculas_total'] ?? 0), 0, ',', '.'),
                ]),
            'geo_note' => $tab['geo_note'] ?? null,
            'stats' => $stats,
            'map_scope' => $tab['map_scope'] ?? 'matricula',
            'schools_table' => $schoolsTable,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return list<array{escola: string, matriculas: int, lat: ?float, lng: ?float}>
     */
    private function buildSchoolsTable(array $markers): array
    {
        $rows = [];
        foreach ($markers as $m) {
            if (! isset($m['lat'], $m['lng'])) {
                continue;
            }
            $school = is_array($m['school'] ?? null) ? $m['school'] : [];
            $rows[] = [
                'escola' => (string) ($m['label'] ?? $school['nome'] ?? __('Unidade')),
                'matriculas' => max(0, (int) ($school['matriculas'] ?? 0)),
                'lat' => round((float) $m['lat'], 5),
                'lng' => round((float) $m['lng'], 5),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['matriculas'] <=> $a['matriculas']);

        return array_slice($rows, 0, 20);
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     */
    private function baseMapForMarkers(City $city, array $markers, int $mapWidth = 520, int $mapHeight = 292): ?string
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

        $tile = $this->mapResolver->fetchStaticMapAt($centerLat, $centerLng, $zoom, $mapWidth, $mapHeight);
        if ($tile !== null) {
            return $tile['data_uri'];
        }

        $maps = $this->mapResolver->resolve($city);

        return $maps['municipal']['data_uri'] ?? null;
    }
}
