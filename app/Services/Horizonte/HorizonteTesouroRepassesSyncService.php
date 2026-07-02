<?php

namespace App\Services\Horizonte;

use App\Support\Horizonte\HorizonteTesouroRepassesSyncProgress;
use App\Support\Horizonte\HorizonteUfScope;

/**
 * Importação dedicada de repasses Tesouro CKAN (ano vigente e opcionalmente referência Horizonte).
 */
final class HorizonteTesouroRepassesSyncService
{
    public function __construct(
        private readonly HorizonteTesouroTransferSyncService $transferSync,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     message: string,
     *     complete?: bool,
     *     years?: list<int>,
     *     imported?: int,
     *     imported_by_year?: array<int, int>,
     *     ufs?: list<string>,
     *     remaining_ufs?: list<string>,
     *     dry_run?: bool
     * }
     */
    public function run(array $options = []): array
    {
        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);
        $currentYear = (int) date('Y');
        $yearOption = trim((string) ($options['year'] ?? ''));
        $targetYear = $yearOption !== '' && is_numeric($yearOption)
            ? max(2000, (int) $yearOption)
            : $currentYear;

        $withRef = (bool) ($options['with_ref'] ?? false);
        $refOnly = (bool) ($options['ref_only'] ?? false);

        $years = $refOnly
            ? [$refYear]
            : ($withRef
                ? array_values(array_unique([$refYear, $targetYear]))
                : [$targetYear]);
        sort($years);

        $scopedUf = HorizonteUfScope::normalize(isset($options['uf']) ? (string) $options['uf'] : null);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $ufsPerStep = max(1, (int) ($options['ufs_per_step'] ?? config('horizonte.tesouro_repasses_sync.ufs_per_step', 1)));

        if ((bool) ($options['reset'] ?? false)) {
            if ($scopedUf !== null) {
                HorizonteTesouroRepassesSyncProgress::unmarkDone([$scopedUf], $years);
            } else {
                HorizonteTesouroRepassesSyncProgress::reset($years);
            }
        }

        $ufs = $scopedUf !== null
            ? [$scopedUf]
            : array_slice(
                HorizonteTesouroRepassesSyncProgress::remainingUfs($years),
                0,
                $ufsPerStep,
            );

        if ($ufs === []) {
            return [
                'success' => true,
                'complete' => true,
                'message' => __('Repasses Tesouro: todas as UFs já importadas para :anos.', [
                    'anos' => implode(', ', array_map('strval', $years)),
                ]),
                'years' => $years,
                'imported' => 0,
                'ufs' => [],
                'remaining_ufs' => [],
            ];
        }

        if ($dryRun) {
            $preview = $this->transferSync->previewFundebImportYears($years, $ufs);

            return [
                'success' => true,
                'dry_run' => true,
                'complete' => $scopedUf !== null || HorizonteTesouroRepassesSyncProgress::isComplete($years),
                'message' => __('Dry-run: :ufs UF(s) · anos :anos · :n linha(s) estimada(s).', [
                    'ufs' => implode(', ', $ufs),
                    'anos' => implode(', ', array_map('strval', $years)),
                    'n' => (string) ($preview['row_estimate'] ?? 0),
                ]),
                'years' => $years,
                'ufs' => $ufs,
                'remaining_ufs' => $scopedUf !== null
                    ? []
                    : HorizonteTesouroRepassesSyncProgress::remainingUfs($years),
                'imported_by_year' => $preview['by_year'] ?? [],
            ];
        }

        $result = $this->transferSync->importFundebYearsForUfs($years, $ufs);
        $imported = (int) ($result['imported'] ?? 0);

        if ($imported > 0) {
            HorizonteTesouroRepassesSyncProgress::markDone($ufs, $years);
        }

        $remaining = $scopedUf !== null ? [] : HorizonteTesouroRepassesSyncProgress::remainingUfs($years);
        $complete = $scopedUf !== null || $remaining === [];

        return [
            'success' => $imported > 0 || ($result['skipped'] ?? false),
            'complete' => $complete,
            'message' => (string) ($result['message'] ?? ''),
            'years' => $years,
            'imported' => $imported,
            'imported_by_year' => is_array($result['imported_by_year'] ?? null)
                ? $result['imported_by_year']
                : [],
            'ufs' => $ufs,
            'remaining_ufs' => $remaining,
        ];
    }
}
