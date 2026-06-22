<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteIbgeCentroidSyncService;
use App\Support\Horizonte\HorizonteIbgeCentroidSyncProgress;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteSyncIbgeCentroidsCommand extends Command
{
    protected $signature = 'horizonte:sync-ibge-centroids
                            {--reset : Reiniciar progresso e reordenar UFs do menor para o maior}
                            {--ufs-per-step= : UFs processadas por invocação (predefinição: 1)}
                            {--uf= : Processar apenas uma UF (ex.: RR)}
                            {--force : Rebuscar centroides mesmo quando já estão em cache}
                            {--dry-run : Listar municípios e estado do cache sem chamar a API individual}
                            {--delay= : Milissegundos entre pedidos à API IBGE (predefinição: config)}';

    protected $description = 'Sincroniza centroides IBGE de todos os municípios brasileiros para o mapa Horizonte (retomável, UFs menores primeiro)';

    public function handle(HorizonteIbgeCentroidSyncService $sync): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $ufRaw = trim((string) $this->option('uf'));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            $this->error(__('UF inválida: :uf', ['uf' => $ufRaw]));

            return self::FAILURE;
        }

        $verbose = $this->getOutput()->isVerbose();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(__('Horizonte — sincronização de centroides IBGE'));
        $this->renderProgressHeader();

        if ((bool) $this->option('reset')) {
            $this->warn(__('Progresso reiniciado — ordem: UFs com menos municípios primeiro.'));
        }

        $delayOption = $this->option('delay');
        $delayMs = $delayOption !== null && $delayOption !== ''
            ? max(0, (int) $delayOption)
            : (int) config('horizonte.ibge_centroid_sync.delay_ms', 120);

        $ufsPerStepOption = $this->option('ufs-per-step');
        $ufsPerStep = $ufsPerStepOption !== null && $ufsPerStepOption !== ''
            ? max(1, (int) $ufsPerStepOption)
            : (int) config('horizonte.ibge_centroid_sync.ufs_per_step', 1);

        $result = $sync->run([
            'reset' => (bool) $this->option('reset'),
            'uf' => $ufRaw !== '' ? $ufRaw : null,
            'ufs_per_step' => $ufsPerStep,
            'force' => (bool) $this->option('force'),
            'dry_run' => $dryRun,
            'delay_ms' => $delayMs,
            'verbose' => $verbose,
        ]);

        foreach ($result['steps'] as $step) {
            $this->renderStep($step, $verbose, $dryRun);
        }

        $this->newLine();
        if ($result['complete'] ?? false) {
            $this->info($result['message']);
        } elseif ($result['success'] ?? false) {
            $this->info($result['message']);
            if (! $dryRun && ($result['remaining_ufs'] ?? []) !== []) {
                $this->line(__('Retomar: php artisan horizonte:sync-ibge-centroids'));
            }
        } else {
            $this->warn($result['message']);
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function renderProgressHeader(): void
    {
        if (! HorizonteIbgeCentroidSyncProgress::hasStarted()) {
            $this->line(__('Nenhum progresso anterior — será criada fila por tamanho da UF.'));

            return;
        }

        $done = HorizonteIbgeCentroidSyncProgress::doneUfs();
        $remaining = HorizonteIbgeCentroidSyncProgress::remainingUfs();
        $this->line(__('Progresso: :done/:total UFs concluídas.', [
            'done' => (string) count($done),
            'total' => (string) count(HorizonteIbgeCentroidSyncProgress::ufOrder()),
        ]));

        if ($remaining !== []) {
            $this->line(__('Próxima(s) UF(s): :ufs', ['ufs' => implode(', ', array_slice($remaining, 0, 5))]));
        }
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function renderStep(array $step, bool $verbose, bool $dryRun): void
    {
        $uf = (string) ($step['uf'] ?? '');
        $total = (int) ($step['total'] ?? 0);
        $stats = is_array($step['stats'] ?? null) ? $step['stats'] : [];

        if ($total === 0) {
            $this->error((string) ($step['message'] ?? __('UF :uf sem dados.', ['uf' => $uf])));

            return;
        }

        $this->newLine();
        $this->info(__('[:uf] :total municípios', ['uf' => $uf, 'total' => (string) $total]));

        $lines = is_array($step['lines'] ?? null) ? $step['lines'] : [];
        foreach ($lines as $line) {
            if (! $verbose && ! in_array((string) ($line['status'] ?? ''), ['failed'], true)) {
                continue;
            }

            $this->renderMunicipalityLine($line);
        }

        if (! $verbose && ($stats['failed'] ?? 0) === 0) {
            $this->line(__('  … :total linhas (use -v para detalhe)', ['total' => (string) count($lines)]));
        }

        $summary = $dryRun
            ? __('  Resumo: :cached em cache · :pending pendentes', [
                'cached' => (string) ($stats['cached'] ?? 0),
                'pending' => (string) ($stats['pending'] ?? 0),
            ])
            : __('  Resumo: :fetched obtidos · :cached em cache · :failed falhas', [
                'fetched' => (string) ($stats['fetched'] ?? 0),
                'cached' => (string) ($stats['cached'] ?? 0),
                'failed' => (string) ($stats['failed'] ?? 0),
            ]);

        $this->line($summary);

        if (! $dryRun && isset($stats['catalog_size'])) {
            $approx = (int) ($stats['still_approximate'] ?? 0);
            $this->line(__('  Catálogo geo: :n municípios (:approx ainda aproximados)', [
                'n' => (string) ($stats['catalog_size'] ?? 0),
                'approx' => (string) $approx,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function renderMunicipalityLine(array $line): void
    {
        $status = (string) ($line['status'] ?? 'failed');
        $ibge = (string) ($line['ibge'] ?? '');
        $name = (string) ($line['name'] ?? '');
        $uf = (string) ($line['uf'] ?? '');

        if ($status === 'failed') {
            $this->error("  ✗ {$ibge} {$name}/{$uf}");

            return;
        }

        if ($status === 'pending') {
            $this->line("  ○ {$ibge} {$name}/{$uf} [pendente]");

            return;
        }

        $lat = isset($line['lat']) ? number_format((float) $line['lat'], 5, '.', '') : '—';
        $lng = isset($line['lng']) ? number_format((float) $line['lng'], 5, '.', '') : '—';
        $label = $status === 'cached' ? 'cache' : 'ibge';
        $this->line("  ✓ {$ibge} {$name}/{$uf} ({$lat}, {$lng}) [{$label}]");
    }
}
