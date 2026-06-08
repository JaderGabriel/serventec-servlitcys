<?php

namespace App\Support\Rx;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

/**
 * Ritmo recente de cadastro (turmas + matrículas) para mini-gráfico no RX.
 */
final class RxCadastroPulse
{
    public const SERIES_HOURS = 72;

    public const BUCKET_HOURS = 2;

    /** @var list<int> */
    public const WINDOW_HOURS = [24, 48, 72];

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'available' => false,
            'matricula_date_col' => null,
            'turma_date_col' => null,
            'windows' => self::emptyWindows(),
            'series' => [],
            'series_max' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $matCtx = IeducarWorkActivityQueries::matriculaActivityContext($db, $city);
        $turCtx = IeducarWorkActivityQueries::turmaActivityContext($db, $city);

        $matDateExpr = ($matCtx['available'] ?? false) ? ($matCtx['date_expr'] ?? null) : null;
        $matDateCol = ($matCtx['available'] ?? false) ? ($matCtx['date_col'] ?? null) : null;
        $turDateCol = ($turCtx['available'] ?? false) ? ($turCtx['date_col'] ?? null) : null;

        if ($matDateExpr === null && $turDateCol === null) {
            return self::empty();
        }

        $matWindows = $matDateExpr !== null
            ? IeducarWorkActivityQueries::matriculaWindowCounts(
                $db,
                $city,
                $filters,
                (string) $matDateExpr,
                $matCtx['user_col'] ?? null,
                self::WINDOW_HOURS,
            )
            : array_fill_keys(array_map('strval', self::WINDOW_HOURS), 0);

        $turWindows = $turDateCol !== null
            ? IeducarWorkActivityQueries::turmaWindowCounts(
                $db,
                $city,
                $filters,
                (string) $turDateCol,
                self::WINDOW_HOURS,
            )
            : array_fill_keys(array_map('strval', self::WINDOW_HOURS), 0);

        $matBuckets = $matDateExpr !== null
            ? IeducarWorkActivityQueries::matriculaHourlyBuckets(
                $db,
                $city,
                $filters,
                (string) $matDateExpr,
                $matCtx['user_col'] ?? null,
                self::SERIES_HOURS,
                self::BUCKET_HOURS,
            )
            : [];

        $turBuckets = $turDateCol !== null
            ? IeducarWorkActivityQueries::turmaHourlyBuckets(
                $db,
                $city,
                $filters,
                (string) $turDateCol,
                self::SERIES_HOURS,
                self::BUCKET_HOURS,
            )
            : [];

        $series = self::mergeSeries($matBuckets, $turBuckets, self::SERIES_HOURS, self::BUCKET_HOURS);
        $seriesMax = 0;
        foreach ($series as $point) {
            $seriesMax = max($seriesMax, (int) ($point['total'] ?? 0));
        }

        $windows = [];
        foreach (self::WINDOW_HOURS as $hours) {
            $key = (string) $hours;
            $windows[] = [
                'hours' => $hours,
                'turmas' => (int) ($turWindows[$key] ?? 0),
                'matriculas' => (int) ($matWindows[$key] ?? 0),
                'total' => (int) ($turWindows[$key] ?? 0) + (int) ($matWindows[$key] ?? 0),
            ];
        }

        $hasSignal = $seriesMax > 0 || array_sum(array_column($windows, 'total')) > 0;

        return [
            'available' => $hasSignal || $matDateExpr !== null || $turDateCol !== null,
            'matricula_date_col' => $matDateCol,
            'turma_date_col' => $turDateCol,
            'windows' => $windows,
            'series' => $series,
            'series_max' => $seriesMax,
        ];
    }

    /**
     * @param  array<string, int>  $matBuckets
     * @param  array<string, int>  $turBuckets
     * @return list<array{index: int, label: string, at: string, turmas: int, matriculas: int, total: int}>
     */
    public static function mergeSeries(
        array $matBuckets,
        array $turBuckets,
        int $seriesHours = self::SERIES_HOURS,
        int $bucketHours = self::BUCKET_HOURS,
    ): array {
        $bucketHours = max(1, $bucketHours);
        $bucketCount = (int) ceil($seriesHours / $bucketHours);
        $anchor = Carbon::now()->startOfHour();
        $start = $anchor->copy()->subHours($seriesHours);

        $series = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            $bucketStart = $start->copy()->addHours($i * $bucketHours);
            $turmas = 0;
            $matriculas = 0;
            for ($h = 0; $h < $bucketHours; $h++) {
                $key = $bucketStart->copy()->addHours($h)->format('Y-m-d H:00:00');
                $turmas += (int) ($turBuckets[$key] ?? 0);
                $matriculas += (int) ($matBuckets[$key] ?? 0);
            }
            $bucketEnd = $bucketStart->copy()->addHours($bucketHours)->subMinute();

            $series[] = [
                'index' => $i,
                'label' => $bucketStart->format('d/m H:i').' – '.$bucketEnd->format('H:i'),
                'at' => $bucketStart->toIso8601String(),
                'turmas' => $turmas,
                'matriculas' => $matriculas,
                'total' => $turmas + $matriculas,
            ];
        }

        return $series;
    }

    /**
     * @return list<array{hours: int, turmas: int, matriculas: int, total: int}>
     */
    private static function emptyWindows(): array
    {
        $windows = [];
        foreach (self::WINDOW_HOURS as $hours) {
            $windows[] = [
                'hours' => $hours,
                'turmas' => 0,
                'matriculas' => 0,
                'total' => 0,
            ];
        }

        return $windows;
    }
}
