<?php

namespace App\Console\Commands;

use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clio:campaign-analyze {uuid : UUID da coleta} {--skip-parse : Não executar interpretação pendente antes}')]
#[Description('Clio — gera inferências INF-* e achados; marca a coleta como analisada.')]
final class ClioCampaignAnalyzeCommand extends Command
{
    public function handle(CampaignAnalyzer $analyzer): int
    {
        if (! filter_var(config('clio.enabled', true), FILTER_VALIDATE_BOOL)) {
            $this->error(__('Clio está desativado (CLIO_ENABLED).'));

            return self::FAILURE;
        }

        $uuid = (string) $this->argument('uuid');
        $campaign = ClioCampaign::query()->where('uuid', $uuid)->first();
        if ($campaign === null) {
            $this->error(__('Coleta Clio não encontrada: :uuid', ['uuid' => $uuid]));

            return self::FAILURE;
        }

        $result = $analyzer->analyze($campaign, parseFirst: ! $this->option('skip-parse'));

        $this->info(__('Clio análise: :i inferências · :f achados · estado :s', [
            'i' => $result['inferences'],
            'f' => $result['findings'],
            's' => $campaign->fresh()?->statusLabel() ?? '—',
        ]));

        $cov = $result['coverage'];
        $this->line(__('Tríade: :c/:t (:p%)', [
            'c' => $cov['schools_triade_complete'] ?? 0,
            't' => $cov['schools_total'] ?? 0,
            'p' => $cov['triade_coverage_pct'] ?? 0,
        ]));

        return self::SUCCESS;
    }
}
