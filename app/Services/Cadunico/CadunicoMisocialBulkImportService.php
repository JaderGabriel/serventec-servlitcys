<?php

namespace App\Services\Cadunico;

/**
 * Importação nacional CadÚnico via SAGI/Misocial (MDS) para vários anos de referência.
 */
final class CadunicoMisocialBulkImportService
{
    public function __construct(
        private CadunicoSagiMisocialClient $misocial,
    ) {}

    /**
     * @return list<int>
     */
    public static function defaultYears(): array
    {
        $from = max(2000, (int) config('ieducar.cadunico.misocial.historical_from_year', 2020));
        $to = (int) date('Y');

        return self::yearsInRange($from, $to);
    }

    /**
     * @return list<int>
     */
    public static function yearsInRange(int $from, int $to): array
    {
        $from = max(2000, min(2100, $from));
        $to = max(2000, min(2100, $to));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $years = [];
        for ($y = $from; $y <= $to; $y++) {
            $years[] = $y;
        }

        return $years;
    }

    /**
     * @return list<int>
     */
    public static function parseYearsOption(?string $yearsCsv, ?int $from, ?int $to): array
    {
        $csv = trim((string) $yearsCsv);
        if ($csv !== '') {
            $parsed = [];
            foreach (preg_split('/\s*,\s*/', $csv) ?: [] as $part) {
                if (is_numeric($part)) {
                    $parsed[] = (int) $part;
                }
            }

            return array_values(array_unique(array_filter($parsed, fn (int $y) => $y >= 2000 && $y <= 2100)));
        }

        if ($from !== null || $to !== null) {
            return self::yearsInRange(
                $from ?? (int) config('ieducar.cadunico.misocial.historical_from_year', 2020),
                $to ?? (int) date('Y'),
            );
        }

        return self::defaultYears();
    }

    /**
     * @param  list<int>  $years
     * @param  callable(int, int, int): void|null  $onYearStart  (yearIndex, year, totalYears)
     * @return array{ok: bool, message: string, total_imported: int, per_year: array<int, array{success: bool, imported: int, month: string, message: string}>}
     */
    public function importYears(array $years, ?callable $onYearStart = null): array
    {
        if (! CadunicoSagiMisocialClient::enabled()) {
            return [
                'ok' => false,
                'message' => __('Misocial/SAGI desactivado (IEDUCAR_CADUNICO_MISOGIAL_ENABLED).'),
                'total_imported' => 0,
                'per_year' => [],
            ];
        }

        if ($years === []) {
            return [
                'ok' => false,
                'message' => __('Nenhum ano indicado. Use --from=2020 --to=2026 ou --years=2020,2021,...'),
                'total_imported' => 0,
                'per_year' => [],
            ];
        }

        sort($years);
        $perYear = [];
        $totalImported = 0;
        $anySuccess = false;
        $totalYears = count($years);

        foreach ($years as $index => $ano) {
            if ($onYearStart !== null) {
                $onYearStart($index + 1, $ano, $totalYears);
            }

            $result = $this->misocial->importYear($ano);
            $imported = (int) ($result['imported'] ?? 0);
            $totalImported += $imported;
            $success = (bool) ($result['success'] ?? false);
            $anySuccess = $anySuccess || $success;

            $perYear[$ano] = [
                'success' => $success,
                'imported' => $imported,
                'month' => (string) ($result['month'] ?? ''),
                'message' => (string) ($result['message'] ?? ''),
            ];
        }

        return [
            'ok' => $anySuccess,
            'message' => $anySuccess
                ? __('Misocial: :n registo(s) em :y ano(s).', ['n' => (string) $totalImported, 'y' => (string) count($years)])
                : __('Misocial: nenhum registo importado nos anos pedidos.'),
            'total_imported' => $totalImported,
            'per_year' => $perYear,
        ];
    }
}
