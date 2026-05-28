<?php

namespace App\Console\Commands;

use App\Enums\AdminSyncDomain;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Cadunico\CadunicoAutoSyncService;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use Illuminate\Console\Command;

class CadunicoAutoSyncCommand extends Command
{
    protected $signature = 'cadunico:auto-sync
        {--ano= : Ano de referência (omite para anos configurados)}
        {--queue : Enfileirar na fila admin-sync em vez de executar já}
        {--no-gap-fill : Não tentar API/CSV municipal para municípios em falta após import nacional}';

    protected $description = 'Sincronização automática CadÚnico (download URL → CSV nacional → lacunas por API)';

    public function handle(CadunicoAutoSyncService $autoSync, AdminSyncQueueService $queue): int
    {
        if (! filter_var(config('ieducar.cadunico.auto_sync.enabled', true), FILTER_VALIDATE_BOOL)) {
            $this->warn(__('CadÚnico auto-sync desactivado.'));

            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            $ano = $this->option('ano') !== null
                ? (int) $this->option('ano')
                : CadunicoOpenDataImportService::suggestedImportYear();

            $task = $queue->dispatch(
                AdminSyncDomain::Cadastro,
                'auto_sync',
                __('CadÚnico — sincronização automática (:ano)', ['ano' => (string) $ano]),
                [
                    'ano' => $ano,
                    'fill_gaps' => ! $this->option('no-gap-fill'),
                    'all_years' => $this->option('ano') === null,
                ],
                null,
            );

            $this->info(__('Tarefa #:id enfileirada.', ['id' => (string) $task->id]));

            return self::SUCCESS;
        }

        if ($this->option('ano') !== null) {
            $result = $autoSync->syncYear((int) $this->option('ano'), ! $this->option('no-gap-fill'));
            $this->report($result);

            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $result = $autoSync->syncAllConfiguredYears();
        foreach ($result['by_year'] ?? [] as $year => $yearResult) {
            if (! is_array($yearResult)) {
                continue;
            }
            $this->line(__('Ano :y', ['y' => (string) $year]));
            $this->report($yearResult);
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): void
    {
        $this->info((string) ($result['message'] ?? ''));
        foreach ($result['log'] ?? [] as $line) {
            $this->comment('  · '.$line);
        }
    }
}
