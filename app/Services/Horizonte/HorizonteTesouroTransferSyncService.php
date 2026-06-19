<?php

namespace App\Services\Horizonte;

use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Services\Funding\TesouroTransferenciasCsvService;
use App\Support\Brazil\IbgeMunicipalityCatalog;
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
        $rows = $this->tesouroCsv->nationalFundebRowsForYear($refYear, $timeout, $options['uf'] ?? null, $this->ibgeCatalog);

        if ($rows === []) {
            return [
                'success' => false,
                'message' => __('Repasses Tesouro: nenhuma linha FUNDEB encontrada no índice CKAN.'),
                'imported' => 0,
            ];
        }

        $scoped = HorizonteUfScope::normalize($options['uf'] ?? null);
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
                ? __('Repasses Tesouro: :n município(s) actualizados (FUNDEB CKAN).', ['n' => (string) $imported])
                : __('Repasses Tesouro: nenhum registo gravado.'),
            'imported' => $imported,
        ];
    }

    /**
     * @return array<string, array{total: float, programas: int}>
     */
    public static function aggregateByIbge(int $year): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('municipal_transfer_snapshots')) {
            return [];
        }

        $rows = \App\Models\MunicipalTransferSnapshot::query()
            ->where('ano', $year)
            ->get(['ibge_municipio', 'valor']);

        $out = [];
        foreach ($rows as $row) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            if (! isset($out[$ibge])) {
                $out[$ibge] = ['total' => 0.0, 'programas' => 0];
            }
            $out[$ibge]['total'] += (float) $row->valor;
            $out[$ibge]['programas']++;
        }

        return $out;
    }
}
