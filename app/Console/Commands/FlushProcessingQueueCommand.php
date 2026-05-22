<?php

namespace App\Console\Commands;

use App\Services\AdminSync\ProcessingQueueFlushService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:flush-processing-queue
    {--confirm= : Slug obrigatório em production (ver config ieducar.admin_sync.flush_confirm_slug)}
    {--only-sync : Só sincronização admin (admin_sync_tasks + fila admin-sync)}
    {--only-pdf : Só exportações PDF analítico}
    {--include-failed : Remove também tarefas/exportações com estado «falhou»}
    {--include-completed : Remove também histórico «concluído» (destrutivo)}
    {--dry-run : Mostra contagens sem apagar}')]
#[Description('Esvazia as filas de processamento (sync admin + PDF) e jobs Laravel pendentes')]
class FlushProcessingQueueCommand extends Command
{
    public function handle(ProcessingQueueFlushService $flush): int
    {
        $onlySync = (bool) $this->option('only-sync');
        $onlyPdf = (bool) $this->option('only-pdf');
        $flushSync = ! $onlyPdf || $onlySync;
        $flushPdf = ! $onlySync || $onlyPdf;

        if ($onlySync && $onlyPdf) {
            $flushSync = true;
            $flushPdf = true;
        }

        if (! $flushSync && ! $flushPdf) {
            $flushSync = true;
            $flushPdf = true;
        }

        $requiredSlug = (string) config('ieducar.admin_sync.flush_confirm_slug', 'zerar-fila-processamento');
        $confirm = trim((string) $this->option('confirm'));

        $dryRun = (bool) $this->option('dry-run');

        if (app()->environment('production') && ! $dryRun) {
            if ($confirm === '' || ! hash_equals($requiredSlug, $confirm)) {
                $this->error(__('Em production é obrigatório passar o slug correcto:'));
                $this->line('  php artisan app:flush-processing-queue --confirm='.$requiredSlug);
                $this->line('  php artisan app:flush-processing-queue --dry-run');

                return self::FAILURE;
            }
        } elseif ($confirm === '' && ! $dryRun && ! $this->confirm(__('Confirma esvaziar a fila de processamento?'), false)) {
            $this->comment(__('Operação cancelada.'));

            return self::SUCCESS;
        }

        $includeFailed = (bool) $this->option('include-failed');
        $includeCompleted = (bool) $this->option('include-completed');

        if ($includeCompleted && ! $dryRun && $confirm === '' && ! app()->environment('production')) {
            if (! $this->confirm(__('Inclui registos concluídos — isto apaga histórico. Continuar?'), false)) {
                $this->comment(__('Operação cancelada.'));

                return self::SUCCESS;
            }
        }

        [$syncConn, $syncQueue] = $flush->syncQueueTarget();
        [$pdfConn, $pdfQueue] = $flush->pdfQueueTarget();

        $this->info($dryRun ? __('Simulação (dry-run) — nada será apagado') : __('A esvaziar filas de processamento…'));
        $this->newLine();

        if ($flushSync) {
            $this->line(__('Sincronização admin: :conn · :queue', ['conn' => $syncConn, 'queue' => $syncQueue]));
        }
        if ($flushPdf) {
            $this->line(__('PDF analítico: :conn · :queue', ['conn' => $pdfConn, 'queue' => $pdfQueue]));
        }
        $this->newLine();

        $stats = $flush->flush($flushSync, $flushPdf, $includeFailed, $includeCompleted, $dryRun);

        if ($flushSync) {
            $this->line(__('  Tarefas admin_sync: :n', ['n' => (string) $stats['sync_tasks']]));
            if (! $dryRun) {
                $this->line(__('  Jobs na fila: :s', [
                    's' => $stats['sync_jobs_cleared'] ? __('limpos') : __('não alterados (ligação sync ou fila indisponível)'),
                ]));
                $this->line(__('  failed_jobs (fila sync): :n', ['n' => (string) $stats['sync_failed_jobs']]));
            }
        }

        if ($flushPdf) {
            $this->line(__('  Exportações PDF: :n', ['n' => (string) $stats['pdf_exports']]));
            if (! $dryRun) {
                $this->line(__('  Jobs na fila: :s', [
                    's' => $stats['pdf_jobs_cleared'] ? __('limpos') : __('não alterados'),
                ]));
                $this->line(__('  failed_jobs (fila PDF): :n', ['n' => (string) $stats['pdf_failed_jobs']]));
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? __('Dry-run concluído. Repita com --confirm=:slug para executar em production.', ['slug' => $requiredSlug])
            : __('Fila de processamento esvaziada.'));

        if (! $dryRun && app()->environment('production')) {
            $this->comment(__('Reinicie workers após limpar: php artisan queue:restart'));
        }

        return self::SUCCESS;
    }
}
