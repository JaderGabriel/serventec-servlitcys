<?php

namespace App\Console\Commands;

use App\Jobs\ProcessClioCampaignAnalyzeJob;
use App\Jobs\ProcessClioCampaignIngestJob;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Ingest\CampaignIngestService;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clio:campaign-ingest {uuid : UUID da coleta Clio} {--path= : Arquivo, pasta ou ZIP a ingerir} {--disk= : Disco Laravel (opcional)} {--queue : Despachar job em vez de sincronizar} {--no-parse : Só classificar, sem interpretar CSV}')]
#[Description('Clio — ingere ZIP/pasta/arquivos, classifica e (por padrão) interpreta CSV.')]
final class ClioCampaignIngestCommand extends Command
{
    public function handle(CampaignIngestService $ingest, CampaignParseService $parser): int
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

        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? $path : null;
        $doParse = ! $this->option('no-parse');

        if ($this->option('queue')) {
            ProcessClioCampaignIngestJob::dispatch($campaign->id, $path, null, $doParse);
            $this->info(__('Job Clio despachado na fila :queue.', [
                'queue' => (string) config('clio.queue', 'clio'),
            ]));

            return self::SUCCESS;
        }

        $disk = $this->option('disk');
        if (is_string($disk) && $disk !== '') {
            config(['clio.disk' => $disk]);
        }

        if ($path !== null) {
            $result = $ingest->ingestFromPath($campaign, $path);
        } else {
            $result = $ingest->expandPendingZips($campaign);
        }

        $this->info(__('Clio ingestão: :stored salvo(s), :exp expandido(s) de ZIP, :dup duplicado(s), :ign ignorado(s).', [
            'stored' => $result['stored'],
            'exp' => $result['expanded'],
            'dup' => $result['duplicates'],
            'ign' => $result['ignored'],
        ]));

        if ($doParse) {
            $stats = $parser->parseCampaign($campaign->fresh() ?? $campaign);
            $this->info(__('Clio interpretação: :p processado(s) · ok=:ok · aviso=:w · falha=:f', [
                'p' => $stats['parsed'],
                'ok' => $stats['ok'],
                'w' => $stats['warning'],
                'f' => $stats['failed'],
            ]));
            $fresh = $campaign->fresh() ?? $campaign;
            $ok = (int) ($stats['ok'] ?? 0) + (int) ($stats['warning'] ?? 0);
            if ($ok > 0 && in_array($fresh->status, [
                ClioCampaign::STATUS_ANALYZED,
                ClioCampaign::STATUS_CROSS_CHECKED,
            ], true)) {
                ProcessClioCampaignAnalyzeJob::dispatch((int) $fresh->id, parseFirst: false);
                $this->info(__('Reanálise enfileirada (coleta já analisada).'));
            }
        }

        $campaign->refresh();
        $this->line(__('Estado: :s · arquivos: :n · escolas: :e', [
            's' => $campaign->statusLabel(),
            'n' => (string) $campaign->artifacts()->count(),
            'e' => (string) $campaign->schools()->count(),
        ]));

        return self::SUCCESS;
    }
}
