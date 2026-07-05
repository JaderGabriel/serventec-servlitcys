<?php

namespace App\Support\Horizonte;

/** Tendência SAEB/IDEB a partir de séries anuais LP/MAT. */
final class HorizonteSaebTrend
{
    public const TREND_UP = 'up';

    public const TREND_DOWN = 'down';

    public const TREND_STABLE = 'stable';

    public const TREND_UNKNOWN = 'unknown';

    /**
     * @param  list<array{year: int, value: float}>  $lpSeries
     * @param  list<array{year: int, value: float}>  $matSeries
     * @return array{
     *     trend: string,
     *     trend_label: string,
     *     delta_lp: ?float,
     *     delta_mat: ?float,
     *     lp_series: list<array{year: int, value: float}>,
     *     mat_series: list<array{year: int, value: float}>
     * }
     */
    public static function analyze(array $lpSeries, array $matSeries, int $maxPoints = 4): array
    {
        $lp = self::normalizeSeries($lpSeries, $maxPoints);
        $mat = self::normalizeSeries($matSeries, $maxPoints);
        $combined = array_merge(array_column($lp, 'value'), array_column($mat, 'value'));
        $deltaLp = self::delta($lp);
        $deltaMat = self::delta($mat);
        $delta = null;
        if ($deltaLp !== null && $deltaMat !== null) {
            $delta = ($deltaLp + $deltaMat) / 2;
        } elseif ($deltaLp !== null) {
            $delta = $deltaLp;
        } elseif ($deltaMat !== null) {
            $delta = $deltaMat;
        }

        $trend = self::TREND_UNKNOWN;
        if ($delta !== null) {
            if ($delta >= 3.0) {
                $trend = self::TREND_UP;
            } elseif ($delta <= -3.0) {
                $trend = self::TREND_DOWN;
            } else {
                $trend = self::TREND_STABLE;
            }
        }

        return [
            'trend' => $trend,
            'trend_label' => self::label($trend),
            'delta_lp' => $deltaLp,
            'delta_mat' => $deltaMat,
            'learning_trajectory_score' => self::score($trend, $combined),
            'lp_series' => $lp,
            'mat_series' => $mat,
        ];
    }

    /**
     * @param  list<float>  $values
     */
    public static function score(string $trend, array $values): int
    {
        $base = 45;
        if ($trend === self::TREND_DOWN) {
            $base = 78;
        } elseif ($trend === self::TREND_UP) {
            $base = 28;
        } elseif ($trend === self::TREND_STABLE) {
            $base = 48;
        }

        $filtered = array_values(array_filter($values, static fn ($v) => is_finite((float) $v)));
        if ($filtered !== []) {
            $avg = array_sum($filtered) / count($filtered);
            if ($avg <= 200) {
                $base = min(100, $base + 10);
            }
        }

        return max(0, min(100, (int) round($base)));
    }

    public static function label(string $trend): string
    {
        return match ($trend) {
            self::TREND_UP => __('Em recuperação'),
            self::TREND_DOWN => __('Em queda'),
            self::TREND_STABLE => __('Estável'),
            default => __('Sem série'),
        };
    }

    /**
     * @param  list<array{year: int, value: float}>  $series
     * @return list<array{year: int, value: float}>
     */
    private static function normalizeSeries(array $series, int $maxPoints): array
    {
        usort($series, static fn (array $a, array $b): int => ($b['year'] <=> $a['year']));
        $out = [];
        foreach ($series as $row) {
            if (! isset($row['year'], $row['value']) || ! is_finite((float) $row['value'])) {
                continue;
            }
            $year = (int) $row['year'];
            foreach ($out as $existing) {
                if ($existing['year'] === $year) {
                    continue 2;
                }
            }
            $out[] = ['year' => $year, 'value' => (float) $row['value']];
            if (count($out) >= $maxPoints) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{year: int, value: float}>  $series
     */
    private static function delta(array $series): ?float
    {
        if (count($series) < 2) {
            return null;
        }

        $newest = $series[0]['value'];
        $oldest = $series[count($series) - 1]['value'];

        return round($newest - $oldest, 2);
    }
}
