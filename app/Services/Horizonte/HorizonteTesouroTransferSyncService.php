<?php

namespace App\Services\Horizonte;

use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Services\Funding\TesouroTransferenciasCsvService;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteTransferBreakdown;
use App\Support\Horizonte\HorizonteUfScope;

/**
 * Sincroniza repasses Tesouro (CKAN CSV) para cobertura nacional no Horizonte.
 */
final class HorizonteTesouroTransferSyncService
{
    public function __construct(
        private readonly TesouroTransferenciasCsvService $tesouroCsv,
        private readonly MunicipalTransferSnapshotRepository $snapshots,
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
    ) {}

    /**
     * Importação nacional usada pelo feed Horizonte — ano de referência + ano vigente quando distintos.
     *
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, skipped?: bool, imported_by_year?: array<int, int>}
     */
    public function syncNationalFundeb(int $refYear, array $options = []): array
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        if (! (bool) ($cfg['csv_enabled'] ?? true)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('Repasses Tesouro: CSV CKAN desactivado.'),
            ];
        }

        $timeout = max(10, (int) ($cfg['timeout'] ?? 25));
        $scopedUf = HorizonteUfScope::normalize(isset($options['uf']) ? (string) $options['uf'] : null);
        $years = $this->resolveFundebImportYears($refYear, $timeout, $scopedUf);

        $ufs = $scopedUf !== null ? [$scopedUf] : null;

        return $this->importFundebYearsForUfs($years, $ufs, $timeout);
    }

    /**
     * @param  list<int>  $years
     * @param  list<string>|null  $ufs
     * @return array{success: bool, message: string, imported?: int, imported_by_year?: array<int, int>, skipped?: bool}
     */
    public function importFundebYearsForUfs(array $years, ?array $ufs = null, ?int $timeout = null): array
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        if (! (bool) ($cfg['csv_enabled'] ?? true)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('Repasses Tesouro: CSV CKAN desactivado.'),
            ];
        }

        $timeout = max(10, $timeout ?? (int) ($cfg['timeout'] ?? 25));

        $normalizedYears = array_values(array_unique(array_filter(array_map('intval', $years), static fn (int $y): bool => $y >= 2000)));
        if ($normalizedYears === []) {
            return [
                'success' => false,
                'message' => __('Repasses Tesouro: nenhum ano válido para importar.'),
                'imported' => 0,
            ];
        }

        $targetUfs = null;
        if ($ufs !== null) {
            $targetUfs = array_values(array_filter(array_map(
                static fn (string $uf): ?string => HorizonteUfScope::normalize($uf),
                $ufs,
            )));
        }

        $importedByYear = [];
        $totalImported = 0;
        $partialYears = [];

        foreach ($normalizedYears as $year) {
            $yearImported = 0;
            $ufList = $targetUfs ?? [null];

            foreach ($ufList as $uf) {
                $rows = $this->tesouroCsv->nationalFundebRowsForYear(
                    $year,
                    $timeout,
                    $uf,
                    $this->ibgeCatalog,
                );
                if ($rows === []) {
                    continue;
                }

                $yearImported += $this->snapshots->upsertBatch(null, $rows);
            }

            if ($yearImported > 0) {
                $importedByYear[$year] = $yearImported;
                $totalImported += $yearImported;
                if ($year === (int) date('Y')) {
                    $partialYears[] = $year;
                }
            }
        }

        if ($totalImported === 0) {
            $diagYear = $normalizedYears[0];
            $diagnosis = $this->tesouroCsv->diagnoseNationalFundeb(
                $diagYear,
                $timeout,
                $targetUfs[0] ?? null,
                $this->ibgeCatalog,
            );

            return [
                'success' => false,
                'message' => $diagnosis['message'],
                'imported' => 0,
                'imported_by_year' => [],
            ];
        }

        $yearLabels = [];
        foreach ($importedByYear as $year => $count) {
            $suffix = in_array($year, $partialYears, true)
                ? __(' (parcial/YTD)')
                : '';
            $yearLabels[] = __(':ano: :n', ['ano' => (string) $year, 'n' => (string) $count]).$suffix;
        }

        return [
            'success' => true,
            'message' => __('Repasses Tesouro: :total município(s) atualizados — :detalhe.', [
                'total' => (string) $totalImported,
                'detalhe' => implode(' · ', $yearLabels),
            ]),
            'imported' => $totalImported,
            'imported_by_year' => $importedByYear,
        ];
    }

    /**
     * @param  list<int>  $years
     * @param  list<string>  $ufs
     * @return array{row_estimate: int, by_year: array<int, int>}
     */
    public function previewFundebImportYears(array $years, array $ufs): array
    {
        $cfg = config('ieducar.other_funding.public_queries.tesouro_ckan', []);
        $timeout = max(10, (int) ($cfg['timeout'] ?? 25));
        $byYear = [];
        $estimate = 0;

        foreach (array_values(array_unique(array_map('intval', $years))) as $year) {
            if ($year < 2000) {
                continue;
            }
            $yearCount = 0;
            foreach ($ufs as $uf) {
                $scoped = HorizonteUfScope::normalize($uf);
                $rows = $this->tesouroCsv->nationalFundebRowsForYear(
                    $year,
                    $timeout,
                    $scoped,
                    $this->ibgeCatalog,
                );
                $yearCount += count($rows);
            }
            if ($yearCount > 0) {
                $byYear[$year] = $yearCount;
                $estimate += $yearCount;
            }
        }

        return [
            'row_estimate' => $estimate,
            'by_year' => $byYear,
        ];
    }

    /**
     * Anos a importar no feed: referência Horizonte + exercício civil corrente (quando existem no CSV).
     *
     * @return list<int>
     */
    public function resolveFundebImportYears(int $refYear, int $timeout, ?string $ufFilter = null): array
    {
        $currentYear = (int) date('Y');
        $available = array_fill_keys(
            $this->tesouroCsv->availableFundebYears($timeout, $ufFilter),
            true,
        );

        $ordered = [];
        foreach ([$refYear, $currentYear] as $year) {
            if ($year >= 2000 && isset($available[$year]) && ! in_array($year, $ordered, true)) {
                $ordered[] = $year;
            }
        }

        if ($ordered !== []) {
            return $ordered;
        }

        foreach ($this->tesouroCsv->fundebYearsToTry($refYear, $timeout, $ufFilter) as $year) {
            return [$year];
        }

        return array_values(array_unique(array_filter([$refYear, $currentYear], static fn (int $y): bool => $y >= 2000)));
    }

    /**
     * @return array<string, array{
     *     total: float,
     *     programas: int,
     *     ano: int,
     *     fundeb: float,
     *     educacao: float,
     *     pct_fundeb: ?float,
     *     pct_educacao: ?float
     * }>
     */
    public static function aggregateByIbge(int $year, ?string $ibgePrefix = null): array
    {
        return HorizonteTransferBreakdown::aggregateByIbge($year, $ibgePrefix);
    }
}
