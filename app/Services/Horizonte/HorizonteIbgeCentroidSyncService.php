<?php

namespace App\Services\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteIbgeCentroidSyncProgress;
use App\Support\Horizonte\HorizonteMapCacheBuster;
use App\Support\Horizonte\HorizonteUfScope;

final class HorizonteIbgeCentroidSyncService
{
    public function __construct(
        private readonly IbgeMunicipalityCatalog $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     complete: bool,
     *     message: string,
     *     steps: list<array<string, mixed>>,
     *     done_ufs: int,
     *     total_ufs: int,
     *     remaining_ufs: list<string>
     * }
     */
    public function run(array $options = []): array
    {
        $reset = (bool) ($options['reset'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $singleUf = HorizonteUfScope::normalize($options['uf'] ?? null);

        if ($reset) {
            HorizonteIbgeCentroidSyncProgress::reset();
        }

        if ($singleUf === null && ! HorizonteIbgeCentroidSyncProgress::hasStarted()) {
            HorizonteIbgeCentroidSyncProgress::initialize();
        }

        if ($singleUf !== null) {
            $batch = [$singleUf];
        } else {
            $remaining = HorizonteIbgeCentroidSyncProgress::remainingUfs();
            if ($remaining === []) {
                return [
                    'success' => true,
                    'complete' => true,
                    'message' => __('Sincronização IBGE já concluída para todas as UFs.'),
                    'steps' => [],
                    'done_ufs' => count(IbgeMunicipalityCatalog::brazilianUfs()),
                    'total_ufs' => count(IbgeMunicipalityCatalog::brazilianUfs()),
                    'remaining_ufs' => [],
                ];
            }

            $ufsPerStep = max(1, (int) ($options['ufs_per_step'] ?? config('horizonte.ibge_centroid_sync.ufs_per_step', 1)));
            $batch = array_slice($remaining, 0, $ufsPerStep);
        }

        $steps = [];
        $anySuccess = false;

        foreach ($batch as $uf) {
            $step = $this->syncUf($uf, $options);
            $steps[] = $step;
            $anySuccess = $anySuccess || ($step['success'] ?? false);

            if (! $dryRun && $singleUf === null && ($step['success'] ?? false)) {
                HorizonteIbgeCentroidSyncProgress::markDone($uf);
            }
        }

        if (! $dryRun && $steps !== []) {
            HorizonteMapCacheBuster::bust();
        }

        $complete = $singleUf === null && HorizonteIbgeCentroidSyncProgress::isComplete();
        if ($complete) {
            HorizonteIbgeCentroidSyncProgress::reset();
        }

        $totalUfs = count(IbgeMunicipalityCatalog::brazilianUfs());
        $doneUfs = $complete ? $totalUfs : count(HorizonteIbgeCentroidSyncProgress::doneUfs());
        $remainingUfs = $singleUf === null ? HorizonteIbgeCentroidSyncProgress::remainingUfs() : [];

        return [
            'success' => $anySuccess || $dryRun,
            'complete' => $complete,
            'message' => $this->buildSummaryMessage($steps, $complete, $dryRun, $remainingUfs),
            'steps' => $steps,
            'done_ufs' => $doneUfs,
            'total_ufs' => $totalUfs,
            'remaining_ufs' => $remainingUfs,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function syncUf(string $uf, array $options): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $delayMs = max(0, (int) ($options['delay_ms'] ?? config('horizonte.ibge_centroid_sync.delay_ms', 120)));

        $list = $this->catalog->listMunicipalitiesForUf($uf);
        if ($list === []) {
            return [
                'uf' => $uf,
                'success' => false,
                'total' => 0,
                'message' => __('Não foi possível listar municípios da UF :uf (API IBGE indisponível).', ['uf' => $uf]),
                'stats' => ['cached' => 0, 'fetched' => 0, 'failed' => 0, 'pending' => 0],
                'lines' => [],
            ];
        }

        $stats = ['cached' => 0, 'fetched' => 0, 'failed' => 0, 'pending' => 0];
        $lines = [];
        $preCached = [];

        if (! $dryRun && ! $force) {
            foreach ($list as $municipality) {
                $ibge = (string) ($municipality['ibge'] ?? '');
                if ($this->catalog->hasCentroidCached($ibge)) {
                    $preCached[$ibge] = true;
                }
            }
        }

        $bulkCentroids = [];
        if (! $dryRun) {
            $malha = $this->catalog->syncCentroidsForUfFromMalha($uf, $force);
            $bulkCentroids = is_array($malha['centroids'] ?? null) ? $malha['centroids'] : [];
        }

        foreach ($list as $index => $municipality) {
            $ibge = (string) ($municipality['ibge'] ?? '');
            $name = (string) ($municipality['name'] ?? '');

            if ($dryRun) {
                $status = $this->catalog->hasCentroidCached($ibge) ? 'cached' : 'pending';
                $stats[$status]++;
                $lines[] = [
                    'ibge' => $ibge,
                    'name' => $name,
                    'uf' => $uf,
                    'status' => $status,
                ];

                continue;
            }

            if (! $this->catalog->hasCentroidCached($ibge)) {
                $result = $this->catalog->syncCentroidForIbge($ibge, true);
                if ($delayMs > 0 && ($result['status'] ?? '') === 'fetched' && $index < count($list) - 1) {
                    usleep($delayMs * 1000);
                }
            } else {
                $cached = \App\Support\Dashboard\AdminHomeMapCache::get('ibge_municipality_centroid:'.$ibge);
                $wasCached = isset($preCached[$ibge]) && ! $force;
                $result = [
                    'status' => $wasCached ? 'cached' : 'fetched',
                    'lat' => (float) ($cached['lat'] ?? 0),
                    'lng' => (float) ($cached['lng'] ?? 0),
                    'source' => isset($bulkCentroids[$ibge]) ? 'malha' : 'cache',
                ];
            }

            $status = (string) ($result['status'] ?? 'failed');
            if (! isset($stats[$status])) {
                $stats[$status] = 0;
            }
            $stats[$status]++;

            $lines[] = array_merge([
                'ibge' => $ibge,
                'name' => $name,
                'uf' => $uf,
            ], $result);
        }

        $catalogSize = 0;
        $stillApproximate = 0;

        if (! $dryRun && ($stats['failed'] ?? 0) === 0) {
            $this->catalog->invalidateUfCatalogCache($uf);
            $catalog = $this->catalog->municipalitiesForUf($uf, true);
            $catalogSize = count($catalog);
            $stillApproximate = count(array_filter(
                $catalog,
                static fn (array $meta): bool => ($meta['coord_source'] ?? '') === 'uf_spread',
            ));
        }

        return [
            'uf' => $uf,
            'success' => ($stats['failed'] ?? 0) === 0 && $list !== [],
            'total' => count($list),
            'message' => __('UF :uf — :total municípios processados.', [
                'uf' => $uf,
                'total' => (string) count($list),
            ]),
            'stats' => array_merge($stats, [
                'catalog_size' => $catalogSize,
                'still_approximate' => $stillApproximate,
                'malha_bulk' => count($bulkCentroids),
            ]),
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>  $remainingUfs
     */
    private function buildSummaryMessage(array $steps, bool $complete, bool $dryRun, array $remainingUfs): string
    {
        if ($dryRun) {
            return __('Simulação concluída — :n passo(s) listado(s).', ['n' => (string) count($steps)]);
        }

        if ($complete) {
            return __('Sincronização IBGE concluída — centroides reais em cache e mapa Horizonte atualizado.');
        }

        if ($remainingUfs !== []) {
            return __('Passo concluído — :done/:total UFs. Próximas: :next. Retome com o mesmo comando.', [
                'done' => (string) count(HorizonteIbgeCentroidSyncProgress::doneUfs()),
                'total' => (string) count(IbgeMunicipalityCatalog::brazilianUfs()),
                'next' => implode(', ', array_slice($remainingUfs, 0, 5)),
            ]);
        }

        return __('Sincronização IBGE processada.');
    }
}
