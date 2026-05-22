<?php

namespace App\Support\Analytics;

/**
 * Mapa de unidades escolares (mesma lógica do painel) para o PDF — escolas + ponto de abrangência das matrículas.
 */
final class AnalyticsReportSchoolUnitsMapSvg
{
    /**
     * @param  list<array<string, mixed>>  $markers
     * @return ?array{svg: string, data_uri: string, stats: array<string, int|float|string|null>}
     */
    public static function render(
        array $markers,
        ?string $baseMapDataUri = null,
        int $width = 680,
        int $height = 360,
        string $primary = '#0f766e',
        string $secondary = '#4338ca',
    ): ?array {
        $points = [];
        $totalMat = 0;
        foreach ($markers as $m) {
            if (! isset($m['lat'], $m['lng'])) {
                continue;
            }
            $school = is_array($m['school'] ?? null) ? $m['school'] : [];
            $mat = max(0, (int) ($school['matriculas'] ?? 0));
            $points[] = [
                'lat' => (float) $m['lat'],
                'lng' => (float) $m['lng'],
                'mat' => $mat,
                'label' => (string) ($m['label'] ?? ''),
            ];
            $totalMat += $mat;
        }

        if ($points === []) {
            return null;
        }

        $bounds = AnalyticsReportMapProjection::bounds(
            array_map(static fn (array $p): array => ['lat' => $p['lat'], 'lng' => $p['lng']], $points),
            0.14,
        );

        $covLat = 0.0;
        $covLng = 0.0;
        $weight = 0;
        foreach ($points as $p) {
            $w = max(1, $p['mat']);
            $covLat += $p['lat'] * $w;
            $covLng += $p['lng'] * $w;
            $weight += $w;
        }
        $covLat /= max(1, $weight);
        $covLng /= max(1, $weight);
        $cov = AnalyticsReportMapProjection::project($covLat, $covLng, $bounds, $width, $height, 28);

        $maxMat = max(1, max(array_column($points, 'mat')));

        $schoolDots = '';
        foreach ($points as $p) {
            $xy = AnalyticsReportMapProjection::project($p['lat'], $p['lng'], $bounds, $width, $height, 28);
            $r = 4 + (int) round(8 * sqrt($p['mat'] / $maxMat));
            $fill = $p['mat'] > 0 ? $primary : '#94a3b8';
            $schoolDots .= sprintf(
                '<circle cx="%.1f" cy="%.1f" r="%d" fill="%s" fill-opacity="0.88" stroke="#fff" stroke-width="1.2"/>',
                $xy['x'],
                $xy['y'],
                $r,
                $fill
            );
        }

        $baseImage = $baseMapDataUri !== null && $baseMapDataUri !== ''
            ? sprintf(
                '<image href="%s" x="0" y="0" width="%d" height="%d" opacity="0.92" preserveAspectRatio="xMidYMid slice"/>',
                htmlspecialchars($baseMapDataUri, ENT_QUOTES, 'UTF-8'),
                $width,
                $height
            )
            : sprintf('<rect width="100%%" height="100%%" fill="#f1f5f9"/>');

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            .'%s'
            .'<rect x="1" y="1" width="%d" height="%d" fill="none" stroke="#cbd5e1" stroke-width="1" rx="8"/>'
            .'%s'
            .'<circle cx="%.1f" cy="%.1f" r="14" fill="%s" fill-opacity="0.18" stroke="%s" stroke-width="2" stroke-dasharray="4 3"/>'
            .'<circle cx="%.1f" cy="%.1f" r="5" fill="%s" stroke="#fff" stroke-width="2"/>'
            .'<text x="28" y="22" font-size="11" font-weight="bold" fill="#0f172a">%s</text>'
            .'<text x="28" y="36" font-size="9" fill="#475569">%s</text>'
            .'<g transform="translate(%d,%d)">'
            .'<circle cx="8" cy="8" r="6" fill="%s"/>'
            .'<text x="20" y="11" font-size="8" fill="#334155">%s</text>'
            .'<circle cx="8" cy="26" r="4" fill="%s" fill-opacity="0.5"/>'
            .'<text x="20" y="29" font-size="8" fill="#334155">%s</text>'
            .'</g>'
            .'</svg>',
            $width,
            $height,
            $width,
            $height,
            $baseImage,
            $width - 2,
            $height - 2,
            $schoolDots,
            $cov['x'],
            $cov['y'],
            $secondary,
            $secondary,
            $cov['x'],
            $cov['y'],
            $secondary,
            __('Unidades escolares e abrangência das matrículas'),
            __('Ponto central = média ponderada pelo volume de matrículas no filtro; tamanho do círculo ∝ matrículas.'),
            $width - 168,
            $height - 52,
            $primary,
            __('Escola (matrículas no recorte)'),
            $secondary,
            __('Centro de abrangência')
        );

        return [
            'svg' => $svg,
            'data_uri' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            'stats' => [
                'schools' => count($points),
                'matriculas_total' => $totalMat,
                'coverage_lat' => $covLat,
                'coverage_lng' => $covLng,
            ],
        ];
    }
}
