<?php

namespace App\Support\Horizonte;

use App\Models\MunicipalTransferSnapshot;
use Carbon\CarbonInterface;

/**
 * Competência temporal do último repasse observado (meta mensal CKAN ou importação local).
 */
final class HorizonteFundebTransferTemporal
{
    /** @var list<string> */
    private const MONTH_SHORT_PT = [
        1 => 'jan',
        2 => 'fev',
        3 => 'mar',
        4 => 'abr',
        5 => 'mai',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'set',
        10 => 'out',
        11 => 'nov',
        12 => 'dez',
    ];

    /**
     * @param  list<MunicipalTransferSnapshot>  $rows
     * @return array{
     *     month: int,
     *     year: int,
     *     label: string,
     *     recorded_at: ?string,
     *     source: string
     * }|null
     */
    public static function lastRecorded(array $rows, int $filterYear): ?array
    {
        if ($rows === []) {
            return null;
        }

        $lastMonth = 0;
        $latestImported = null;

        foreach ($rows as $row) {
            if (! $row instanceof MunicipalTransferSnapshot) {
                continue;
            }

            $meta = is_array($row->meta) ? $row->meta : [];
            $mensal = self::mensalForYear($meta, $filterYear);
            foreach ($mensal as $month => $valor) {
                $m = (int) $month;
                if ($m < 1 || $m > 12 || ! is_numeric($valor) || (float) $valor <= 0) {
                    continue;
                }
                if ($m > $lastMonth) {
                    $lastMonth = $m;
                }
            }

            $importedAt = $row->imported_at;
            if ($importedAt instanceof CarbonInterface) {
                if ($latestImported === null || $importedAt->gt($latestImported)) {
                    $latestImported = $importedAt;
                }
            }
        }

        if ($lastMonth > 0) {
            return [
                'month' => $lastMonth,
                'year' => $filterYear,
                'label' => self::monthYearLabel($filterYear, $lastMonth),
                'recorded_at' => $latestImported?->toIso8601String(),
                'source' => 'mensal',
            ];
        }

        if ($latestImported !== null) {
            return [
                'month' => 0,
                'year' => $filterYear,
                'label' => $latestImported->timezone(config('app.timezone'))->format('d/m/Y'),
                'recorded_at' => $latestImported->toIso8601String(),
                'source' => 'imported_at',
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int, float>
     */
    private static function mensalForYear(array $meta, int $filterYear): array
    {
        $mensal = $meta['mensal'] ?? null;
        if (! is_array($mensal) || $mensal === []) {
            return [];
        }

        if (isset($mensal[$filterYear]) || isset($mensal[(string) $filterYear])) {
            $slice = $mensal[$filterYear] ?? $mensal[(string) $filterYear];

            return is_array($slice) ? self::normalizeMensalMap($slice) : [];
        }

        $first = reset($mensal);

        return is_array($first) ? [] : self::normalizeMensalMap($mensal);
    }

    /**
     * @param  array<int|string, mixed>  $map
     * @return array<int, float>
     */
    private static function normalizeMensalMap(array $map): array
    {
        $out = [];
        foreach ($map as $month => $valor) {
            if (! is_numeric($valor) || (float) $valor <= 0) {
                continue;
            }
            $m = (int) $month;
            if ($m >= 1 && $m <= 12) {
                $out[$m] = (float) $valor;
            }
        }

        return $out;
    }

    private static function monthYearLabel(int $year, int $month): string
    {
        $short = self::MONTH_SHORT_PT[$month] ?? (string) $month;

        return $short.'/'.$year;
    }
}
