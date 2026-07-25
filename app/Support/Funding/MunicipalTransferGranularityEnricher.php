<?php

namespace App\Support\Funding;

use App\Services\Funding\TesouroTransferenciasCsvService;

/**
 * Garante meta com breakdown mensal (STN) ou lançamentos diários (BB) antes de gravar snapshots.
 */
final class MunicipalTransferGranularityEnricher
{
    public function __construct(
        private TesouroTransferenciasCsvService $tesouroCsv,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function enrichRows(array $rows, int $year): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->enrichRow($row, $year);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function enrichRow(array $row, int $year): array
    {
        $fonte = (string) ($row['fonte'] ?? '');
        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
        $importAno = (int) ($row['ano'] ?? $year);
        if ($importAno < 2000) {
            $importAno = $year;
        }

        if (in_array($fonte, ['tesouro_csv', 'sisweb_ckan'], true)) {
            $meta = $this->enrichTesouroMensal($meta, $importAno);
        }

        if ($fonte === 'portal_transparencia') {
            $meta = $this->finalizePortalMeta($meta);
        }

        if ($fonte === 'bb_extrato' && is_array($meta['lancamentos'] ?? null)) {
            $meta['granularity'] = 'day';
        } elseif ($this->hasMensalBreakdown($meta)) {
            $meta['granularity'] = 'month';
        }

        $row['meta'] = $meta;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function enrichTesouroMensal(array $meta, int $year): array
    {
        // Sempre reconciliar com o índice CKAN (meta antigo pode omitir meses novos).
        $mensal = $this->tesouroCsv->resolveMensalForSnapshotMeta($meta, $year);

        if ($mensal === []) {
            return $meta;
        }

        $meta['mensal'] = $mensal;
        $meta['meses_somados'] = count($mensal);
        $meta['repasses'] = $this->repassesFromMensal($mensal, $year);

        return $meta;
    }

    /**
     * @param  array<int, float>  $mensal
     * @return list<array{mes: int, ano: int, valor: float, granularity: string}>
     */
    private function repassesFromMensal(array $mensal, int $year): array
    {
        $out = [];
        foreach ($mensal as $month => $valor) {
            $out[] = [
                'mes' => (int) $month,
                'ano' => $year,
                'valor' => round((float) $valor, 2),
                'granularity' => 'month',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function hasMensalBreakdown(array $meta): bool
    {
        $mensal = $meta['mensal'] ?? null;

        return is_array($mensal) && $mensal !== [];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function finalizePortalMeta(array $meta): array
    {
        $repasses = is_array($meta['repasses'] ?? null) ? $meta['repasses'] : [];
        if ($repasses === []) {
            return $meta;
        }

        $hasDay = false;
        foreach ($repasses as $repasse) {
            if (! is_array($repasse)) {
                continue;
            }
            if (trim((string) ($repasse['data'] ?? '')) !== '' || ($repasse['granularity'] ?? '') === 'day') {
                $hasDay = true;
                break;
            }
        }

        $meta['granularity'] = $hasDay ? 'day' : 'month';

        return $meta;
    }
}
