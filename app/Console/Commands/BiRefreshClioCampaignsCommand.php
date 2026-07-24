<?php

namespace App\Console\Commands;

use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Bi\ClioBiRefreshService;
use Illuminate\Console\Command;

class BiRefreshClioCampaignsCommand extends Command
{
    protected $signature = 'bi:refresh-clio-campaigns
                            {uuid? : UUID da coleta (omitir com --all)}
                            {--all : Actualizar todas as coletas analisadas}
                            {--year= : Filtrar pelo exercício}';

    protected $description = 'Clio S7 — popula tabelas bi_clio_* (agregados sem PII) para Power BI / insights';

    public function handle(ClioBiRefreshService $bi): int
    {
        if (! filter_var(config('clio.enabled', true), FILTER_VALIDATE_BOOL)) {
            $this->error(__('Clio está desativado (CLIO_ENABLED).'));

            return self::FAILURE;
        }

        $uuid = $this->argument('uuid');
        $all = (bool) $this->option('all');
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;

        if (! $all && blank($uuid)) {
            $this->error(__('Informe o UUID da coleta ou use --all.'));

            return self::FAILURE;
        }

        if ($all) {
            $result = $bi->refreshAll($year);
            $this->info(__('BI Clio: :n coleta(s) actualizada(s).', ['n' => $result['refreshed']]));

            return self::SUCCESS;
        }

        $campaign = ClioCampaign::query()->where('uuid', (string) $uuid)->first();
        if ($campaign === null) {
            $this->error(__('Coleta não encontrada: :u', ['u' => $uuid]));

            return self::FAILURE;
        }

        $bi->refreshCampaign($campaign);
        $this->info(__('BI Clio actualizado para :m (:y).', [
            'm' => $campaign->municipality_name ?? $campaign->uuid,
            'y' => $campaign->year,
        ]));

        return self::SUCCESS;
    }
}
