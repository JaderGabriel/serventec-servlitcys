<?php

namespace App\Support\Analytics;

/**
 * Silhueta caricata do município (sem escolas) para a capa do PDF.
 */
final class AnalyticsReportMunicipalityOutlineSvg
{
    /**
     * @return array{svg: string, data_uri: string}
     */
    public static function render(
        string $cityName,
        string $uf,
        float $centerLat,
        float $centerLng,
        int $width = 680,
        int $height = 300,
        string $primary = '#0f766e',
        string $fill = '#99f6e4',
    ): array {
        $seed = crc32(mb_strtolower(trim($cityName.'|'.$uf), 'UTF-8'));
        $cx = $width / 2;
        $cy = $height / 2 - 8;
        $rx = (int) round($width * 0.34);
        $ry = (int) round($height * 0.38);
        $n = 28;
        $pts = [];

        for ($i = 0; $i < $n; $i++) {
            $angle = (2 * M_PI * $i) / $n;
            $noise = 0.78 + (self::hash01($seed, $i) * 0.42);
            $x = $cx + cos($angle) * $rx * $noise;
            $y = $cy + sin($angle) * $ry * (0.85 + self::hash01($seed, $i + 17) * 0.25);
            $pts[] = sprintf('%.1f,%.1f', $x, $y);
        }

        $path = 'M'.implode(' L', $pts).' Z';
        $title = htmlspecialchars(trim($cityName.($uf !== '' ? ' — '.$uf : '')), ENT_QUOTES, 'UTF-8');
        $coordsLabel = htmlspecialchars(
            sprintf('%.4f°, %.4f°', $centerLat, $centerLng),
            ENT_QUOTES,
            'UTF-8'
        );

        $grid = '';
        for ($g = 40; $g < $width; $g += 40) {
            $grid .= sprintf('<line x1="%d" y1="18" x2="%d" y2="%d" stroke="#e2e8f0" stroke-width="1"/>', $g, $g, $height - 36);
        }
        for ($g = 36; $g < $height - 28; $g += 36) {
            $grid .= sprintf('<line x1="24" y1="%d" x2="%d" y2="%d" stroke="#e2e8f0" stroke-width="1"/>', $g, $width - 24, $g);
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            .'<rect width="100%%" height="100%%" fill="#f8fafc"/>'
            .'%s'
            .'<path d="%s" fill="%s" fill-opacity="0.55" stroke="%s" stroke-width="3" stroke-linejoin="round"/>'
            .'<circle cx="%.1f" cy="%.1f" r="5" fill="%s" stroke="#fff" stroke-width="2"/>'
            .'<text x="%d" y="%d" text-anchor="middle" font-size="13" font-weight="bold" fill="#0f172a">%s</text>'
            .'<text x="%d" y="%d" text-anchor="middle" font-size="9" fill="#475569">%s · %s</text>'
            .'<text x="%d" y="14" font-size="8" fill="#64748b">%s</text>'
            .'</svg>',
            $width,
            $height,
            $width,
            $height,
            $grid,
            $path,
            $fill,
            $primary,
            $cx,
            $cy,
            $primary,
            (int) $cx,
            $height - 14,
            $title,
            (int) $cx,
            $height - 2,
            __('Recorte municipal ilustrativo'),
            __('sem unidades escolares'),
            (int) ($width - 24),
            __('Norte')
        );

        $dataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);

        return ['svg' => $svg, 'data_uri' => $dataUri];
    }

    private static function hash01(int $seed, int $i): float
    {
        $v = ($seed ^ ($i * 2654435761)) & 0x7FFFFFFF;

        return ($v % 1000) / 1000;
    }
}
