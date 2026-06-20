<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteDataBundleService;
use Illuminate\Console\Command;

class HorizonteExportDataBundleCommand extends Command
{
    protected $signature = 'horizonte:export-data-bundle
                            {--output= : Caminho do ficheiro .zip (default storage/app/horizonte/bundles/)}
                            {--skip-fundeb : Omitir FUNDEB}
                            {--skip-censo : Omitir Censo}
                            {--skip-saeb : Omitir SAEB municipal}
                            {--skip-ibge : Omitir cache IBGE}
                            {--skip-sge : Omitir registo SGE}
                            {--skip-cadunico : Omitir CadÚnico}
                            {--skip-demography : Omitir SIDRA demografia}
                            {--skip-transfers : Omitir repasses Tesouro}';

    protected $description = 'Exporta dados Horizonte (FUNDEB, Censo, SAEB, CadÚnico, SIDRA, repasses, cache IBGE, SGE) para ZIP — transferência local → produção';

    public function handle(HorizonteDataBundleService $bundle): int
    {
        $this->info(__('Horizonte — exportação de pacote de dados'));

        $sections = [
            'fundeb' => ! $this->option('skip-fundeb'),
            'censo' => ! $this->option('skip-censo'),
            'saeb' => ! $this->option('skip-saeb'),
            'cadunico' => ! $this->option('skip-cadunico'),
            'demography' => ! $this->option('skip-demography'),
            'transfers' => ! $this->option('skip-transfers'),
            'ibge_cache' => ! $this->option('skip-ibge'),
            'sge_registry' => ! $this->option('skip-sge'),
        ];

        $output = trim((string) $this->option('output'));
        $result = $bundle->export($sections, $output !== '' ? $output : null);

        if (! ($result['success'] ?? false)) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        foreach ($result['manifest']['counts'] ?? [] as $key => $count) {
            $this->line(sprintf('  · %s: %s', $key, (string) $count));
        }
        $this->newLine();
        $this->line(__('Cópia rápida: storage/app/horizonte/bundles/latest.zip'));
        $this->line(__('Produção: scp o ZIP e execute horizonte:import-data-bundle {ficheiro}'));

        return self::SUCCESS;
    }
}
