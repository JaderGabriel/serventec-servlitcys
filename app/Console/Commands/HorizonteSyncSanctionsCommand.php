<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizontePortalSanctionsSyncService;
use App\Support\Horizonte\PortalProcurementConfig;
use Illuminate\Console\Command;

class HorizonteSyncSanctionsCommand extends Command
{
    protected $signature = 'horizonte:sync-sanctions
                            {--max-pages= : Páginas por base/CNPJ (default config)}
                            {--dry-run : Simular sem gravar}';

    protected $description = 'Consulta CEIS/CNEP/CEPIM para CNPJs curados (HOR-08g — due diligence)';

    public function handle(HorizontePortalSanctionsSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $vendors = PortalProcurementConfig::softwareVendors();

        $this->info(__('Horizonte — Sanções Portal (CEIS / CNEP / CEPIM)'));
        $this->line(__('CNPJs curados: :n', ['n' => (string) count($vendors)]));

        if ($vendors === []) {
            $this->warn(__('Defina HORIZONTE_PROCUREMENT_SOFTWARE_VENDORS.'));

            return self::SUCCESS;
        }

        $options = ['dry_run' => $dryRun];
        if ($this->option('max-pages') !== null) {
            $options['max_pages'] = (int) $this->option('max-pages');
        }

        $result = $sync->sync($options);

        if ($result['skipped'] ?? false) {
            $this->warn((string) ($result['message'] ?? ''));

            return self::SUCCESS;
        }

        if (! ($result['success'] ?? false)) {
            $this->error((string) ($result['message'] ?? __('Falha no sync de sanções.')));

            return self::FAILURE;
        }

        $this->table(
            [__('Base'), __('Registos')],
            [
                ['CEIS', (int) ($result['by_fonte']['ceis'] ?? 0)],
                ['CNEP', (int) ($result['by_fonte']['cnep'] ?? 0)],
                ['CEPIM', (int) ($result['by_fonte']['cepim'] ?? 0)],
            ],
        );

        if ($dryRun) {
            $this->comment((string) $result['message']);
            $this->comment(__('Dry-run: nenhuma alteração. Remova --dry-run para gravar.'));
        } else {
            $this->info((string) $result['message']);
        }

        $this->comment(__('Sanção ≠ tipo de SGE — use só como filtro de risco comercial.'));

        return self::SUCCESS;
    }
}
