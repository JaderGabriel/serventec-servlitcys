<?php

namespace App\Support\Analytics;

/**
 * Converte payloads Chart.js (bar / bar horizontal) em SVG para DomPDF.
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
     * @param  list<string|int|float>  $labels
     * @param  list<float>  $values
     * @param  list<string>  $colors
     */
    private static function vertical(string $title, array $labels, array $values, array $colors, int $width, int $height): string
    {
        $max = max(1.0, max($values));
        $padT = 36;
        $padB = 48;
        $padL = 40;
        $padR = 16;
        $plotH = $height - $padT - $padB;
        $plotW = $width - $padL - $padR;
        $n = count($values);
        $barW = max(8, (int) floor($plotW / max(1, $n * 1.6)));

        $bars = '';
        foreach ($values as $i => $v) {
            $h = (int) round(($v / $max) * $plotH);
            $x = $padL + (int) round($i * ($plotW / max(1, $n)) + ($plotW / $n - $barW) / 2);
            $y = $padT + $plotH - $h;
            $fill = $colors[$i % count($colors)];
            $bars .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s" rx="3"/>', $x, $y, $barW, $h, $fill);
            $label = htmlspecialchars(mb_substr((string) ($labels[$i] ?? ''), 0, 14), ENT_QUOTES, 'UTF-8');
            $bars .= sprintf('<text x="%d" y="%d" font-size="8" fill="#475569" text-anchor="middle">%s</text>', $x + (int) ($barW / 2), $height - 8, $label);
        }

        return self::wrap($width, $height, $title, $bars);
    }

    /**
     * @param  list<string|int|float>  $labels
     * @param  list<float>  $values
     * @param  list<string>  $colors
     */
    private static function horizontal(string $title, array $labels, array $values, array $colors, int $width, int $height): string
    {
        $max = max(1.0, max($values));
        $padT = 36;
        $padL = 120;
        $padR = 24;
        $rowH = max(14, (int) floor(($height - $padT - 12) / max(1, count($values))));
        $plotW = $width - $padL - $padR;

        $bars = '';
        foreach ($values as $i => $v) {
            $w = (int) round(($v / $max) * $plotW);
            $y = $padT + $i * $rowH + 4;
            $fill = $colors[$i % count($colors)];
            $bars .= sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s" rx="2"/>', $padL, $y, $w, $rowH - 6, $fill);
            $label = htmlspecialchars(mb_substr((string) ($labels[$i] ?? ''), 0, 22), ENT_QUOTES, 'UTF-8');
            $bars .= sprintf('<text x="%d" y="%d" font-size="8" fill="#334155" text-anchor="end">%s</text>', $padL - 6, $y + ($rowH / 2) + 3, $label);
            $bars .= sprintf('<text x="%d" y="%d" font-size="8" fill="#0f172a">%s</text>', $padL + $w + 4, $y + ($rowH / 2) + 3, self::formatValue($v));
        }

        $h = max($height, $padT + count($values) * $rowH + 16);

        return self::wrap($width, $h, $title, $bars);
    }

    private static function wrap(int $width, int $height, string $title, string $body): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect width="100%%" height="100%%" fill="#f8fafc" rx="6"/><text x="12" y="20" font-size="11" font-weight="bold" fill="#0f766e">%s</text>%s</svg>',
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
