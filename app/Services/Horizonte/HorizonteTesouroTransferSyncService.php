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
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, skipped?: bool}
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
        $yearsToTry = $this->tesouroCsv->fundebYearsToTry($refYear, $timeout, $scopedUf);
        $rows = [];
        $usedYear = $refYear;
        $partialYear = false;

        foreach ($yearsToTry as $year) {
            $rows = $this->tesouroCsv->nationalFundebRowsForYear($year, $timeout, $scopedUf, $this->ibgeCatalog);
            if ($rows !== []) {
                $usedYear = $year;
                $partialYear = $year === (int) date('Y');
                break;
            }
        }

        if ($rows === []) {
            $diagYear = $usedYear;
            $diagnosis = $this->tesouroCsv->diagnoseNationalFundeb(
                $diagYear,
                $timeout,
                $scopedUf,
                $this->ibgeCatalog,
            );

            return [
                'success' => false,
                'message' => $diagnosis['message'],
                'imported' => 0,
            ];
        }

        $scoped = $scopedUf;
        if ($scoped !== null) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => HorizonteUfScope::ibgeBelongsToScope((string) ($row['ibge_municipio'] ?? ''), $scoped),
            ));
        }

        $imported = $this->snapshots->upsertBatch(null, $rows);

        return [
            'success' => $imported > 0,
            'message' => $imported > 0
                ? ($partialYear
                    ? __('Repasses Tesouro: :n município(s) actualizados (FUNDEB CKAN, ano :ano — parcial/YTD).', [
                        'n' => (string) $imported,
                        'ano' => (string) $usedYear,
                    ])
                    : __('Repasses Tesouro: :n município(s) actualizados (FUNDEB CKAN, ano :ano).', [
                        'n' => (string) $imported,
                        'ano' => (string) $usedYear,
                    ]))
                : __('Repasses Tesouro: nenhum registo gravado.'),
            'imported' => $imported,
        ];
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
