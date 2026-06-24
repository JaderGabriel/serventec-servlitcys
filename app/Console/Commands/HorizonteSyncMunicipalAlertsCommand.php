<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteMunicipalAlertsSyncService;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteSyncMunicipalAlertsCommand extends Command
{
    protected $signature = 'horizonte:sync-municipal-alerts
                            {--uf= : Restringir merge a uma UF (ex.: BA)}
                            {--skip-fnde : Ignorar lista FNDE VAAT inabilitados (PDF)}
                            {--dry-run : Simular importação sem gravar cache}
                            {--reset : Limpar cache antes de sincronizar}';

    protected $description = 'Importa alertas oficiais MEC/FNDE (VAAT inabilitados, registo manual) para o modal municipal Horizonte';

    public function handle(HorizonteMunicipalAlertsSyncService $sync): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $ufRaw = trim((string) $this->option('uf'));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            $this->error(__('UF inválida: :uf — use sigla de estado (ex.: BA).', ['uf' => $ufRaw]));

            return self::FAILURE;
        }

        if ((bool) $this->option('reset')) {
            \App\Support\Horizonte\HorizonteMunicipalAlertsCache::forget();
            $this->warn(__('Cache de alertas municipais reiniciado.'));
        }

        $this->info(__('Horizonte — alertas MEC/FNDE (portarias / VAAT / registo manual)'));
        if ($ufRaw !== '') {
            $this->line(__('Âmbito: UF :uf', ['uf' => (string) HorizonteUfScope::normalize($ufRaw)]));
        }

        $result = $sync->sync([
            'uf' => $ufRaw !== '' ? HorizonteUfScope::normalize($ufRaw) : null,
            'skip_fnde' => (bool) $this->option('skip-fnde'),
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        foreach ($result['warnings'] ?? [] as $warning) {
            $this->warn((string) $warning);
        }

        if ($result['skipped'] ?? false) {
            $this->warn((string) ($result['message'] ?? ''));
        } else {
            $this->info((string) ($result['message'] ?? ''));
        }

        if (($result['sources'] ?? []) !== []) {
            $this->line(__('Fontes: :sources', [
                'sources' => implode(', ', array_map('strval', $result['sources'])),
            ]));
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
