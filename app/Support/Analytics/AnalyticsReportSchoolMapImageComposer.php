<?php

namespace App\Support\Analytics;

/**
 * Compõe mapa territorial em PNG para o PDF (DomPDF renderiza mal SVG com imagens embutidas).
 */
final class AnalyticsReportSchoolMapImageComposer
{
    /**
     * @param  list<array<string, mixed>>  $markers
     * @return ?array{data_uri: string, width: int, height: int, stats: array<string, int|float>}
     */
    public static function compose(
        array $markers,
        ?string $backdropPngDataUri,
        int $width = 720,
        int $height = 400,
        string $primary = '#0f766e',
        string $secondary = '#4338ca',
    ): ?array {
        $points = self::normalizePoints($markers);
        if ($points === []) {
            return null;
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        $bounds = AnalyticsReportMapProjection::bounds(
            array_map(static fn (array $p): array => ['lat' => $p['lat'], 'lng' => $p['lng']], $points),
            0.16,
        );

        $canvas = self::loadCanvas($backdropPngDataUri, $width, $height);
        if ($canvas === null) {
            return null;
        }

        $maxMat = max(1, max(array_column($points, 'mat')));

        foreach ($points as $p) {
            $xy = AnalyticsReportMapProjection::project($p['lat'], $p['lng'], $bounds, $width, $height, 36);
            $r = (int) max(5, min(18, 5 + round(10 * sqrt($p['mat'] / $maxMat))));
            $rgb = self::hexToRgb($p['mat'] > 0 ? $primary : '#94a3b8');
            $fill = imagecolorallocatealpha($canvas, $rgb[0], $rgb[1], $rgb[2], 18);
            $stroke = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledellipse($canvas, (int) $xy['x'], (int) $xy['y'], $r * 2, $r * 2, $fill);
            imageellipse($canvas, (int) $xy['x'], (int) $xy['y'], $r * 2, $r * 2, $stroke);
        }

        $cov = self::coverageCenter($points);
        $covXy = AnalyticsReportMapProjection::project($cov['lat'], $cov['lng'], $bounds, $width, $height, 36);
        $secRgb = self::hexToRgb($secondary);
        $covFill = imagecolorallocatealpha($canvas, $secRgb[0], $secRgb[1], $secRgb[2], 90);
        $covStroke = imagecolorallocate($canvas, $secRgb[0], $secRgb[1], $secRgb[2]);
        imagefilledellipse($canvas, (int) $covXy['x'], (int) $covXy['y'], 28, 28, $covFill);
        imageellipse($canvas, (int) $covXy['x'], (int) $covXy['y'], 28, 28, $covStroke);
        imagefilledellipse($canvas, (int) $covXy['x'], (int) $covXy['y'], 8, 8, $covStroke);

        self::drawLegendBar($canvas, $width, $height, $primary, $secondary, count($points));

        ob_start();
        imagepng($canvas, null, 6);
        imagedestroy($canvas);
        $binary = ob_get_clean();
        if ($binary === false || $binary === '') {
            return null;
        }

        $totalMat = array_sum(array_column($points, 'mat'));

        return [
            'data_uri' => 'data:image/png;base64,'.base64_encode($binary),
            'width' => $width,
            'height' => $height,
            'stats' => [
                'schools' => count($points),
                'matriculas_total' => $totalMat,
                'coverage_lat' => $cov['lat'],
                'coverage_lng' => $cov['lng'],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return list<array{lat: float, lng: float, mat: int, label: string}>
     */
    private static function normalizePoints(array $markers): array
    {
        $points = [];
        foreach ($markers as $m) {
            if (! isset($m['lat'], $m['lng'])) {
                continue;
            }
            $school = is_array($m['school'] ?? null) ? $m['school'] : [];
            $points[] = [
                'lat' => (float) $m['lat'],
                'lng' => (float) $m['lng'],
                'mat' => max(0, (int) ($school['matriculas'] ?? 0)),
                'label' => (string) ($m['label'] ?? $school['nome'] ?? ''),
            ];
        }

        return $points;
    }

    /**
     * @param  list<array{lat: float, lng: float, mat: int}>  $points
     * @return array{lat: float, lng: float}
     */
    private static function coverageCenter(array $points): array
    {
        $covLat = 0.0;
        $covLng = 0.0;
        $weight = 0;
        foreach ($points as $p) {
            $w = max(1, $p['mat']);
            $covLat += $p['lat'] * $w;
            $covLng += $p['lng'] * $w;
            $weight += $w;
        }
        $weight = max(1, $weight);

        return ['lat' => $covLat / $weight, 'lng' => $covLng / $weight];
    }

    private static function loadCanvas(?string $backdropPngDataUri, int $width, int $height): ?\GdImage
    {
        if ($backdropPngDataUri !== null && $backdropPngDataUri !== '') {
            $binary = self::decodeDataUri($backdropPngDataUri);
            if ($binary !== null) {
                $loaded = @imagecreatefromstring($binary);
                if ($loaded instanceof \GdImage) {
                    $resized = imagecreatetruecolor($width, $height);
                    imagecopyresampled($resized, $loaded, 0, 0, 0, 0, $width, $height, imagesx($loaded), imagesy($loaded));
                    imagedestroy($loaded);

                    return $resized;
                }
            }
        }

        $canvas = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($canvas, 241, 245, 249);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $bg);
        $grid = imagecolorallocate($canvas, 226, 232, 240);
        for ($x = 0; $x < $width; $x += 40) {
            imageline($canvas, $x, 0, $x, $height, $grid);
        }
        for ($y = 0; $y < $height; $y += 40) {
            imageline($canvas, 0, $y, $width, $y, $grid);
        }

        return $canvas;
    }

    private static function drawLegendBar(\GdImage $canvas, int $width, int $height, string $primary, string $secondary, int $schoolCount): void
    {
        $barH = 44;
        $y0 = $height - $barH;
        $overlay = imagecolorallocatealpha($canvas, 255, 255, 255, 25);
        imagefilledrectangle($canvas, 0, $y0, $width, $height, $overlay);
        $border = imagecolorallocate($canvas, 203, 213, 225);
        imageline($canvas, 0, $y0, $width, $y0, $border);

        $pRgb = self::hexToRgb($primary);
        $sRgb = self::hexToRgb($secondary);
        $dotP = imagecolorallocate($canvas, $pRgb[0], $pRgb[1], $pRgb[2]);
        $dotS = imagecolorallocate($canvas, $sRgb[0], $sRgb[1], $sRgb[2]);
        imagefilledellipse($canvas, 24, $y0 + 22, 12, 12, $dotP);
        imagefilledellipse($canvas, 24, $y0 + 36, 20, 20, $dotS);

        $textColor = imagecolorallocate($canvas, 15, 23, 42);
        $muted = imagecolorallocate($canvas, 71, 85, 105);
        imagestring($canvas, 3, 42, $y0 + 10, 'Escolas (tamanho ~ matriculas)', $textColor);
        imagestring($canvas, 2, 42, $y0 + 26, 'Centro de abrangencia (ponderado)', $muted);
        imagestring($canvas, 2, $width - 110, $y0 + 14, $schoolCount.' unidades', $textColor);
    }

    private static function decodeDataUri(string $uri): ?string
    {
        if (! str_contains($uri, 'base64,')) {
            return null;
        }
        $payload = substr($uri, (int) strpos($uri, 'base64,') + 7);
        $decoded = base64_decode($payload, true);

        return $decoded !== false ? $decoded : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
