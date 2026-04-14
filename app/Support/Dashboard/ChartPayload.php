<?php

namespace App\Support\Dashboard;

/**
 * Estrutura uniforme para Chart.js (título, tipo, labels, datasets com cores).
 */
final class ChartPayload
{
    /**
     * @return list<string>
     */
    public static function palette(): array
    {
        $c = config('ieducar.chart_colors', []);
        if (! is_array($c) || $c === []) {
            return ['#6366f1', '#22c55e', '#f59e0b', '#ec4899', '#06b6d4', '#a855f7'];
        }

        return array_values(array_filter(array_map(static fn ($x) => is_string($x) ? trim($x) : '', $c)));
    }

    /**
     * @param  list<string|int|float>  $labels
     * @param  list<int|float>  $values
     * @return array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function bar(string $title, string $datasetLabel, array $labels, array $values): array
    {
        $colors = self::palette();
        $bg = [];
        foreach (array_values($values) as $i => $_) {
            $bg[] = $colors[$i % max(1, count($colors))];
        }

        return [
            'type' => 'bar',
            'title' => $title,
            'labels' => array_map(static fn ($l) => (string) $l, $labels),
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => array_values($values),
                    'backgroundColor' => $bg,
                    'borderColor' => $bg,
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'maxBarThickness' => 48,
                ],
            ],
            'options' => [],
        ];
    }

    /**
     * Barras horizontais (melhor para rótulos longos: cursos, escolas).
     *
     * @param  list<string|int|float>  $labels
     * @param  list<int|float>  $values
     * @return array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options: array<string, mixed>}
     */
    public static function barHorizontal(string $title, string $datasetLabel, array $labels, array $values): array
    {
        $payload = self::bar($title, $datasetLabel, $labels, $values);
        $payload['options'] = ['indexAxis' => 'y'];

        return $payload;
    }

    /**
     * Barras horizontais empilhadas: uma cor por série (ex.: curso), mesmas categorias no eixo Y.
     *
     * @param  list<string|int|float>  $labels
     * @param  list<array{label: string, data: list<int|float>}>  $series
     * @return array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options: array<string, mixed>}
     */
    public static function barHorizontalStacked(string $title, string $valueAxisLabel, array $labels, array $series): array
    {
        return self::barHorizontalMultiSeries($title, $valueAxisLabel, $labels, $series, true, 48);
    }

    /**
     * Barras horizontais agrupadas (multi-barras lado a lado por categoria no eixo Y).
     *
     * @param  list<string|int|float>  $labels
     * @param  list<array{label: string, data: list<int|float>}>  $series
     * @return array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options: array<string, mixed>}
     */
    public static function barHorizontalGrouped(string $title, string $valueAxisLabel, array $labels, array $series): array
    {
        return self::barHorizontalMultiSeries($title, $valueAxisLabel, $labels, $series, false, 22);
    }

    /**
     * @param  list<string|int|float>  $labels
     * @param  list<array{label: string, data: list<int|float>}>  $series
     * @return array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options: array<string, mixed>}
     */
    private static function barHorizontalMultiSeries(string $title, string $valueAxisLabel, array $labels, array $series, bool $stacked, int $maxBarThickness): array
    {
        $labels = array_map(static fn ($l) => (string) $l, $labels);
        $n = count($labels);
        $colors = self::palette();
        $datasets = [];
        foreach (array_values($series) as $i => $s) {
            $c = $colors[$i % max(1, count($colors))];
            $raw = array_values(array_map(static fn ($v) => is_numeric($v) ? (float) $v : 0.0, $s['data'] ?? []));
            if (count($raw) < $n) {
                $raw = array_pad($raw, $n, 0.0);
            } elseif (count($raw) > $n) {
                $raw = array_slice($raw, 0, $n);
            }
            $datasets[] = [
                'label' => (string) ($s['label'] ?? ''),
                'data' => $raw,
                'backgroundColor' => $c,
                'borderColor' => $c,
                'borderWidth' => 1,
                'borderRadius' => 4,
                'borderSkipped' => false,
                'maxBarThickness' => $maxBarThickness,
            ];
        }

        $options = [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'stacked' => $stacked,
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => $valueAxisLabel,
                    ],
                ],
                'y' => [
                    'stacked' => $stacked,
                ],
            ],
        ];
        if (! $stacked) {
            $options['datasets'] = [
                'bar' => [
                    'categoryPercentage' => 0.72,
                    'barPercentage' => 0.72,
                ],
            ];
        }

        return [
            'type' => 'bar',
            'title' => $title,
            'labels' => $labels,
            'datasets' => $datasets,
            'options' => $options,
        ];
    }

    /**
     * @param  list<string|int|float>  $labels
     * @param  list<int|float>  $values
     * @return array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function doughnut(string $title, array $labels, array $values): array
    {
        $colors = self::palette();
        $bg = [];
        foreach (array_values($values) as $i => $_) {
            $bg[] = $colors[$i % max(1, count($colors))];
        }

        return [
            'type' => 'doughnut',
            'title' => $title,
            'labels' => array_map(static fn ($l) => (string) $l, $labels),
            'datasets' => [
                [
                    'label' => $title,
                    'data' => array_values($values),
                    'backgroundColor' => $bg,
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                    'hoverOffset' => 4,
                ],
            ],
        ];
    }

    /**
     * Medidor semicircular (0–100 %) para Chart.js (doughnut com rotação e arco).
     */
    public static function gaugePercent(string $title, float $percent): array
    {
        $colors = self::palette();
        $filled = $colors[0] ?? '#6366f1';
        $p = max(0.0, min(100.0, $percent));
        $rest = max(0.01, 100.0 - $p);

        return [
            'type' => 'doughnut',
            'title' => $title,
            'labels' => [__('Com registo'), __('Restante')],
            'datasets' => [
                [
                    'label' => $title,
                    'data' => [$p, $rest],
                    'backgroundColor' => [$filled, '#e5e7eb'],
                    'borderWidth' => 0,
                    'borderRadius' => 14,
                    'hoverOffset' => 5,
                ],
            ],
            'options' => [
                'rotation' => -90,
                'circumference' => 180,
                'cutout' => '72%',
                'spacing' => 2,
                'plugins' => [
                    'legend' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<string|int|float>  $labels
     * @param  list<int|float>  $values
     * @return array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function line(string $title, string $datasetLabel, array $labels, array $values): array
    {
        $colors = self::palette();

        return [
            'type' => 'line',
            'title' => $title,
            'labels' => array_map(static fn ($l) => (string) $l, $labels),
            'datasets' => [
                [
                    'label' => $datasetLabel,
                    'data' => array_values($values),
                    'borderColor' => $colors[0] ?? '#6366f1',
                    'backgroundColor' => ($colors[0] ?? '#6366f1').'33',
                    'fill' => true,
                    'tension' => 0.35,
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => $colors[0] ?? '#6366f1',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1,
                ],
            ],
        ];
    }

    /**
     * Várias séries (linhas) com paleta automática — ex.: raça/cor e NEE por escola.
     *
     * @param  list<array{label: string, data: list<int|float>}>  $series
     * @return array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}
     */
    public static function lineMulti(string $title, array $labels, array $series, array $extraOptions = []): array
    {
        $colors = self::palette();
        $datasets = [];
        foreach (array_values($series) as $i => $s) {
            $c = $colors[$i % max(1, count($colors))];
            $datasets[] = [
                'label' => (string) ($s['label'] ?? ''),
                'data' => array_values(array_map(static fn ($v) => is_numeric($v) ? (float) $v : 0.0, $s['data'] ?? [])),
                'borderColor' => $c,
                'backgroundColor' => $c.'22',
                'fill' => false,
                'tension' => 0.22,
                'borderWidth' => 2,
                'pointRadius' => 2,
                'pointHoverRadius' => 4,
                'pointBackgroundColor' => $c,
                'pointBorderColor' => '#ffffff',
                'pointBorderWidth' => 1,
            ];
        }

        return [
            'type' => 'line',
            'title' => $title,
            'labels' => array_map(static fn ($l) => (string) $l, $labels),
            'datasets' => $datasets,
            'options' => array_merge([
                'panelHeight' => 'lg',
                'scales' => [
                    'x' => [
                        'ticks' => [
                            'maxRotation' => 88,
                            'minRotation' => 45,
                            'autoSkip' => true,
                        ],
                    ],
                ],
            ], $extraOptions),
        ];
    }

    /**
     * Junta categorias excedentes num único rótulo (gráficos legíveis; totais conservados).
     *
     * @param  list<string|int|float>  $labels
     * @param  list<int|float>  $values
     * @return array{0: list<string>, 1: list<int|float>}
     */
    public static function capTailAsOutros(array $labels, array $values, int $maxCategories, string $outrosLabel = 'Outros'): array
    {
        $labels = array_values($labels);
        $values = array_values($values);
        $n = min(count($labels), count($values));
        if ($n === 0) {
            return [[], []];
        }
        $labels = array_slice($labels, 0, $n);
        $values = array_slice($values, 0, $n);
        if ($n <= $maxCategories) {
            return [array_map(static fn ($l) => (string) $l, $labels), $values];
        }
        $keep = max(1, $maxCategories - 1);
        $rest = 0;
        for ($i = $keep; $i < $n; $i++) {
            $rest += (int) $values[$i];
        }

        return [
            array_merge(array_map(static fn ($l) => (string) $l, array_slice($labels, 0, $keep)), [$outrosLabel]),
            array_merge(array_slice($values, 0, $keep), [$rest]),
        ];
    }
}
