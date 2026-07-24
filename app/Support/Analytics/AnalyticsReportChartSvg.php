<?php

namespace App\Support\Analytics;

/**
 * Converte payloads Chart.js (bar / bar horizontal / line multi) em SVG para DomPDF.
 */
final class AnalyticsReportChartSvg
{
    /**
     * @param  array<string, mixed>|null  $chart
     */
    public static function render(?array $chart, int $width = 520, int $height = 220): ?string
    {
        if ($chart === null || ! is_array($chart['labels'] ?? null) || ! is_array($chart['datasets'] ?? null)) {
            return null;
        }

        if (($chart['type'] ?? '') === 'line') {
            return self::lineMulti($chart, $width, max($height, 248));
        }

        $labels = array_values($chart['labels']);
        $dataset = $chart['datasets'][0] ?? null;
        if (! is_array($dataset) || ! is_array($dataset['data'] ?? null)) {
            return null;
        }

        $values = array_map(static fn ($v): float => (float) $v, array_values($dataset['data']));
        if ($values === []) {
            return null;
        }

        $horizontal = ($chart['options']['indexAxis'] ?? '') === 'y';
        $colors = self::colors($dataset, count($values));
        $title = htmlspecialchars((string) ($chart['title'] ?? ''), ENT_QUOTES, 'UTF-8');

        return $horizontal
            ? self::horizontal($title, $labels, $values, $colors, $width, $height)
            : self::vertical($title, $labels, $values, $colors, $width, $height);
    }

    /**
     * Data URI segura para DomPDF (linha multi → PNG; demais → SVG embutido).
     *
     * @param  array<string, mixed>|null  $chart
     */
    public static function renderDataUri(?array $chart, int $width = 520, int $height = 220): ?string
    {
        if ($chart === null || ! is_array($chart['labels'] ?? null) || ! is_array($chart['datasets'] ?? null)) {
            return null;
        }

        if (($chart['type'] ?? '') === 'line') {
            return self::lineMultiPngDataUri($chart, $width, max($height, 248));
        }

        $svg = self::render($chart, $width, $height);

        return $svg !== null ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }

    /**
     * DomPDF costuma ignorar &lt;polyline&gt;/SVG de linha — PNG via GD.
     *
     * @param  array<string, mixed>  $chart
     */
    public static function lineMultiPngDataUri(array $chart, int $width = 520, int $height = 248): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $parsed = self::parseLineSeries($chart);
        if ($parsed === null) {
            return null;
        }
        ['labels' => $labels, 'series' => $series, 'max' => $max, 'title' => $title] = $parsed;

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return null;
        }
        imagesavealpha($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        $ink = imagecolorallocate($img, 15, 23, 42);
        $muted = imagecolorallocate($img, 100, 116, 139);
        $grid = imagecolorallocate($img, 203, 213, 225);
        $border = imagecolorallocate($img, 148, 163, 184);
        imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $white);
        imagerectangle($img, 0, 0, $width - 1, $height - 1, $border);

        $font = self::dejavuFontPath(false);
        $fontBold = self::dejavuFontPath(true);
        $padT = 36;
        $padB = 58;
        $padL = 52;
        $padR = 16;
        $plotH = $height - $padT - $padB;
        $plotW = $width - $padL - $padR;
        $n = count($labels);

        if ($fontBold !== null) {
            imagettftext($img, 11, 0, 14, 22, $ink, $fontBold, self::gdTruncate($title, 62));
        } else {
            imagestring($img, 3, 14, 8, self::gdTruncate($title, 60), $ink);
        }

        for ($i = 0; $i <= 4; $i++) {
            $gy = $padT + (int) round($i * ($plotH / 4));
            imageline($img, $padL, $gy, $padL + $plotW, $gy, $grid);
            $tick = self::formatValue($max * (1 - $i / 4));
            if ($font !== null) {
                $box = imagettfbbox(7, 0, $font, $tick);
                $tw = is_array($box) ? (int) abs($box[2] - $box[0]) : 20;
                imagettftext($img, 7, 0, $padL - 6 - $tw, $gy + 3, $muted, $font, $tick);
            } else {
                imagestring($img, 1, max(2, $padL - 36), $gy - 4, $tick, $muted);
            }
        }

        $colorIds = [];
        foreach ($series as $si => $s) {
            $rgb = self::hexToRgb($s['color']);
            $colorIds[$si] = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        }

        foreach ($series as $si => $s) {
            $prev = null;
            foreach ($s['values'] as $i => $v) {
                $x = $padL + (int) round($n === 1 ? $plotW / 2 : $i * ($plotW / max(1, $n - 1)));
                $y = $padT + $plotH - (int) round(($v / $max) * $plotH);
                if ($prev !== null) {
                    imageline($img, $prev['x'], $prev['y'], $x, $y, $colorIds[$si]);
                    imageline($img, $prev['x'], $prev['y'] + 1, $x, $y + 1, $colorIds[$si]);
                }
                imagefilledellipse($img, $x, $y, 6, 6, $colorIds[$si]);
                imageellipse($img, $x, $y, 6, 6, $white);
                $prev = ['x' => $x, 'y' => $y];
            }
        }

        foreach ($labels as $i => $label) {
            $x = $padL + (int) round($n === 1 ? $plotW / 2 : $i * ($plotW / max(1, $n - 1)));
            $txt = self::gdTruncate((string) $label, 6);
            if ($fontBold !== null) {
                $box = imagettfbbox(8, 0, $fontBold, $txt);
                $tw = is_array($box) ? (int) abs($box[2] - $box[0]) : 20;
                imagettftext($img, 8, 0, $x - (int) ($tw / 2), $height - 28, $ink, $fontBold, $txt);
            } else {
                imagestring($img, 2, $x - 12, $height - 36, $txt, $ink);
            }
        }

        $legendX = $padL;
        $legendY = $height - 12;
        foreach ($series as $si => $s) {
            $name = self::gdTruncate($s['label'], 16);
            imagefilledrectangle($img, $legendX, $legendY - 7, $legendX + 8, $legendY + 1, $colorIds[$si]);
            if ($font !== null) {
                imagettftext($img, 7.5, 0, $legendX + 11, $legendY, $ink, $font, $name);
                $box = imagettfbbox(7.5, 0, $font, $name);
                $tw = is_array($box) ? (int) abs($box[2] - $box[0]) : 40;
                $legendX += 11 + $tw + 14;
            } else {
                imagestring($img, 1, $legendX + 11, $legendY - 6, $name, $ink);
                $legendX += 11 + (strlen($name) * 5) + 14;
            }
            if ($legendX > $width - 70) {
                break;
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        if ($png === false || $png === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array{labels: list<string>, series: list<array{label: string, values: list<float>, color: string}>, max: float, title: string}|null
     */
    private static function parseLineSeries(array $chart): ?array
    {
        $labels = array_values(array_map(static fn ($l): string => (string) $l, $chart['labels'] ?? []));
        $datasets = array_values(array_filter(
            $chart['datasets'] ?? [],
            static fn ($ds): bool => is_array($ds) && is_array($ds['data'] ?? null),
        ));
        if ($labels === [] || $datasets === []) {
            return null;
        }

        $series = [];
        $max = 0.0;
        $palette = self::linePalette();
        foreach ($datasets as $i => $ds) {
            $values = [];
            foreach (array_values($ds['data']) as $v) {
                $n = ($v === null || $v === '') ? 0.0 : (float) $v;
                $values[] = $n;
                if ($n > $max) {
                    $max = $n;
                }
            }
            if (count($values) !== count($labels)) {
                $values = array_pad(array_slice($values, 0, count($labels)), count($labels), 0.0);
            }
            $color = is_string($ds['borderColor'] ?? null)
                ? (string) $ds['borderColor']
                : (string) ($palette[$i % max(1, count($palette))] ?? '#0f766e');
            $series[] = [
                'label' => (string) ($ds['label'] ?? ''),
                'values' => $values,
                'color' => $color,
            ];
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'max' => max(1.0, $max),
            'title' => (string) ($chart['title'] ?? ''),
        ];
    }

    private static function dejavuFontPath(bool $bold): ?string
    {
        $candidates = $bold
            ? [
                base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ]
            : [
                base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            ];
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) < 6) {
            return [15, 118, 110];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function gdTruncate(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, max(1, $max - 1)).'…';
    }

    /**
     * @param  array<string, mixed>  $chart
     */
    private static function lineMulti(array $chart, int $width, int $height): ?string
    {
        $parsed = self::parseLineSeries($chart);
        if ($parsed === null) {
            return null;
        }
        ['labels' => $labels, 'series' => $series, 'max' => $max, 'title' => $titleRaw] = $parsed;
        $title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
        $padT = 36;
        $padB = 58;
        $padL = 48;
        $padR = 14;
        $plotH = $height - $padT - $padB;
        $plotW = $width - $padL - $padR;
        $n = count($labels);

        $grid = self::horizontalGrid($padL, $padT, $plotW, $plotH, 4);
        for ($i = 0; $i <= 4; $i++) {
            $gy = $padT + (int) round($i * ($plotH / 4));
            $tick = $max * (1 - $i / 4);
            $grid .= sprintf(
                '<text x="%d" y="%d" font-size="7" fill="#64748b" text-anchor="end">%s</text>',
                $padL - 4,
                $gy + 3,
                self::formatValue($tick)
            );
        }

        $body = $grid;
        $pointsBySeries = [];
        foreach ($series as $s) {
            $points = [];
            foreach ($s['values'] as $i => $v) {
                $x = $padL + (int) round($n === 1 ? $plotW / 2 : $i * ($plotW / max(1, $n - 1)));
                $y = $padT + $plotH - (int) round(($v / $max) * $plotH);
                $points[] = ['x' => $x, 'y' => $y];
            }
            $pointsBySeries[] = $points;
            $body .= sprintf(
                '<polyline fill="none" stroke="%s" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="%s"/>',
                htmlspecialchars($s['color'], ENT_QUOTES, 'UTF-8'),
                implode(' ', array_map(static fn (array $p): string => $p['x'].','.$p['y'], $points))
            );
        }
        foreach ($series as $si => $s) {
            foreach ($pointsBySeries[$si] as $p) {
                $body .= sprintf(
                    '<circle cx="%d" cy="%d" r="2.5" fill="%s" stroke="#ffffff" stroke-width="0.8"/>',
                    $p['x'],
                    $p['y'],
                    htmlspecialchars($s['color'], ENT_QUOTES, 'UTF-8')
                );
            }
        }

        foreach ($labels as $i => $label) {
            $x = $padL + (int) round($n === 1 ? $plotW / 2 : $i * ($plotW / max(1, $n - 1)));
            $body .= sprintf(
                '<text x="%d" y="%d" font-size="8" font-weight="bold" fill="#1e293b" text-anchor="middle">%s</text>',
                $x,
                $height - 28,
                htmlspecialchars(mb_substr($label, 0, 6), ENT_QUOTES, 'UTF-8')
            );
        }

        $legendX = $padL;
        $legendY = $height - 10;
        foreach ($series as $s) {
            $name = htmlspecialchars(mb_substr($s['label'], 0, 18), ENT_QUOTES, 'UTF-8');
            $swatch = htmlspecialchars($s['color'], ENT_QUOTES, 'UTF-8');
            $body .= sprintf('<rect x="%d" y="%d" width="8" height="8" fill="%s" rx="1"/>', $legendX, $legendY - 7, $swatch);
            $body .= sprintf('<text x="%d" y="%d" font-size="7.5" fill="#334155">%s</text>', $legendX + 11, $legendY, $name);
            $legendX += 11 + (int) (mb_strlen($s['label']) * 4.2) + 14;
            if ($legendX > $width - 80) {
                break;
            }
        }

        return self::wrap($width, $height, $title, $body);
    }

    /**
     * @return list<string>
     */
    private static function linePalette(): array
    {
        $palette = config('analytics.pdf_report.colors.chart', []);
        if (is_array($palette) && $palette !== []) {
            return array_values(array_map(static fn ($c): string => (string) $c, $palette));
        }

        return ['#0f766e', '#4338ca', '#0369a1', '#b45309', '#be123c'];
    }

    /**
     * @param  list<string|int|float>  $labels
     * @param  list<float>  $values
     * @param  list<string>  $colors
     */
    private static function vertical(string $title, array $labels, array $values, array $colors, int $width, int $height): string
    {
        $max = max(1.0, max($values));
        $padT = 40;
        $padB = 52;
        $padL = 44;
        $padR = 16;
        $plotH = $height - $padT - $padB;
        $plotW = $width - $padL - $padR;
        $n = count($values);
        $barW = max(10, (int) floor($plotW / max(1, $n * 1.6)));

        $grid = self::horizontalGrid($padL, $padT, $plotW, $plotH, 4);

        $bars = '';
        foreach ($values as $i => $v) {
            $h = (int) round(($v / $max) * $plotH);
            $x = $padL + (int) round($i * ($plotW / max(1, $n)) + ($plotW / $n - $barW) / 2);
            $y = $padT + $plotH - $h;
            $fill = $colors[$i % count($colors)];
            $bars .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s" stroke="#0f172a" stroke-width="0.4" rx="2"/>', $x, $y, $barW, $h, $fill);
            if ($h > 14) {
                $bars .= sprintf(
                    '<text x="%d" y="%d" font-size="7.5" font-weight="bold" fill="#0f172a" text-anchor="middle">%s</text>',
                    $x + (int) ($barW / 2),
                    $y - 3,
                    self::formatValue($v)
                );
            }
            $label = htmlspecialchars(mb_substr((string) ($labels[$i] ?? ''), 0, 14), ENT_QUOTES, 'UTF-8');
            $bars .= sprintf('<text x="%d" y="%d" font-size="8" font-weight="bold" fill="#1e293b" text-anchor="middle">%s</text>', $x + (int) ($barW / 2), $height - 10, $label);
        }

        return self::wrap($width, $height, $title, $grid.$bars);
    }

    /**
     * @param  list<string|int|float>  $labels
     * @param  list<float>  $values
     * @param  list<string>  $colors
     */
    private static function horizontal(string $title, array $labels, array $values, array $colors, int $width, int $height): string
    {
        $max = max(1.0, max($values));
        $padT = 40;
        $padL = 128;
        $padR = 28;
        $rowH = max(18, (int) floor(($height - $padT - 12) / max(1, count($values))));
        $plotW = $width - $padL - $padR;
        $maxBars = 14;
        if (count($values) > $maxBars) {
            $labels = array_slice($labels, 0, $maxBars);
            $values = array_slice($values, 0, $maxBars);
            $colors = array_slice($colors, 0, $maxBars);
            $rowH = max(18, (int) floor(($height - $padT - 12) / max(1, count($values))));
        }

        $bars = '';
        foreach ($values as $i => $v) {
            $w = (int) round(($v / $max) * $plotW);
            $y = $padT + $i * $rowH + 4;
            $fill = $colors[$i % count($colors)];
            $bars .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s" stroke="#0f172a" stroke-width="0.4" rx="2"/>', $padL, $y, $w, $rowH - 6, $fill);
            $label = htmlspecialchars(mb_substr((string) ($labels[$i] ?? ''), 0, 22), ENT_QUOTES, 'UTF-8');
            $bars .= sprintf('<text x="%d" y="%d" font-size="8" font-weight="bold" fill="#0f172a" text-anchor="end">%s</text>', $padL - 6, $y + ($rowH / 2) + 3, $label);
            $bars .= sprintf('<text x="%d" y="%d" font-size="8.5" font-weight="bold" fill="#0f172a">%s</text>', $padL + $w + 6, $y + ($rowH / 2) + 3, self::formatValue($v));
        }

        $h = max($height, $padT + count($values) * $rowH + 16);

        return self::wrap($width, $h, $title, $bars);
    }

    private static function horizontalGrid(int $x, int $y, int $w, int $h, int $lines): string
    {
        $out = '';
        for ($i = 0; $i <= $lines; $i++) {
            $gy = $y + (int) round($i * ($h / $lines));
            $out .= sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#cbd5e1" stroke-width="1"/>', $x, $gy, $x + $w, $gy);
        }

        return $out;
    }

    private static function wrap(int $width, int $height, string $title, string $body): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            .'<rect width="100%%" height="100%%" fill="#ffffff" stroke="#94a3b8" stroke-width="1" rx="6"/>'
            .'<text x="14" y="22" font-size="11" font-weight="bold" fill="#0f172a">%s</text>'
            .'%s</svg>',
            $width,
            $height,
            $width,
            $height,
            $title,
            $body
        );
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<string>
     */
    private static function colors(array $dataset, int $count): array
    {
        $palette = config('analytics.pdf_report.colors.chart', []);
        if (! is_array($palette) || $palette === []) {
            $palette = ['#0f766e', '#4338ca', '#0369a1', '#b45309', '#be123c'];
        }
        $bg = $dataset['backgroundColor'] ?? null;
        if (is_array($bg) && count($bg) >= $count) {
            return array_map(static fn ($c) => is_string($c) ? $c : (string) ($palette[0] ?? '#0f766e'), array_slice($bg, 0, $count));
        }

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = (string) ($palette[$i % count($palette)] ?? '#0f766e');
        }

        return $out;
    }

    private static function formatValue(float $v): string
    {
        if (abs($v - round($v)) < 0.01) {
            return number_format((int) round($v), 0, ',', '.');
        }

        return number_format($v, 1, ',', '.');
    }
}
