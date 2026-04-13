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
                ],
            ],
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
                    'tension' => 0.3,
                ],
            ],
        ];
    }
}
