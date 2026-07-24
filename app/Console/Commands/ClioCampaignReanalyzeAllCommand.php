<?php

namespace App\Console\Commands;

use App\Jobs\ProcessClioCampaignAnalyzeJob;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('clio:campaign-reanalyze-all
                            {--year= : Filtrar pelo exercício da coleta}
                            {--skip-parse : Não executar interpretação pendente antes}
                            {--queue : Enfileirar reanálise (em vez de sincrona)}
                            {--dry-run : Só listar coletas que seriam reanalisadas}')]
#[Description('Clio — reanalisa todas as coletas (cidades) do Modo A e refresca o BI.')]
final class ClioCampaignReanalyzeAllCommand extends Command
{
    public function handle(CampaignAnalyzer $analyzer): int
    {
        if (! filter_var(config('clio.enabled', true), FILTER_VALIDATE_BOOL)) {
            $this->error(__('Clio está desativado (CLIO_ENABLED).'));

            return self::FAILURE;
        }

        $year = $this->option('year') !== null && $this->option('year') !== ''
            ? (int) $this->option('year')
            : null;
        $skipParse = (bool) $this->option('skip-parse');
        $queue = (bool) $this->option('queue');
        $dryRun = (bool) $this->option('dry-run');
        $parseFirst = ! $skipParse;

        $query = ClioCampaign::query()
            ->orderBy('year')
            ->orderBy('municipality_name')
            ->orderBy('id');

        if ($year !== null) {
            $query->where('year', $year);
        }

        $campaigns = $query->get(['id', 'uuid', 'municipality_name', 'uf', 'year', 'status']);
        $total = $campaigns->count();

        if ($total === 0) {
            $this->warn(__('Nenhuma coleta Clio encontrada.'));

            return self::SUCCESS;
        }

        $this->info(__('Clio reanálise: :n coleta(s):year · :mode', [
            'n' => $total,
            'year' => $year !== null ? ' · '.__('exercício :y', ['y' => $year]) : '',
            'mode' => $dryRun
                ? __('dry-run')
                : ($queue ? __('fila') : __('síncrono')),
        ]));

        if ($dryRun) {
            $rows = $campaigns->map(fn (ClioCampaign $c): array => [
                $c->municipality_name ?? '—',
                $c->uf ?? '—',
                (string) $c->year,
                $c->status,
                $c->uuid,
            ])->all();
            $this->table(
                [__('Município'), 'UF', __('Ano'), __('Status'), 'UUID'],
                $rows,
            );
            $this->info(__('Dry-run: :n coleta(s) seriam reanalisadas.', ['n' => $total]));

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;
        $queued = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($campaigns as $campaign) {
            $label = trim(($campaign->municipality_name ?? '—').' / '.($campaign->uf ?? '—').' '.$campaign->year);

            try {
                if ($queue) {
                    ProcessClioCampaignAnalyzeJob::dispatch($campaign->id, $parseFirst);
                    $queued++;
                    $ok++;
                } else {
                    $result = $analyzer->analyze($campaign, parseFirst: $parseFirst);
                    $ok++;
                    if ($this->output->isVerbose()) {
                        $this->newLine();
                        $this->line(__('  :label — :i inferências · :f achados', [
                            'label' => $label,
                            'i' => $result['inferences'],
                            'f' => $result['findings'],
                        ]));
                    }
                }
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error(__('Falha em :label (:uuid): :msg', [
                    'label' => $label,
                    'uuid' => $campaign->uuid,
                    'msg' => $e->getMessage(),
                ]));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($queue) {
            $this->info(__('Enfileiradas :q reanálise(s).', ['q' => $queued]));
        } else {
            $this->info(__('Reanalisadas :ok · falhas :fail', [
                'ok' => $ok,
                'fail' => $failed,
            ]));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
