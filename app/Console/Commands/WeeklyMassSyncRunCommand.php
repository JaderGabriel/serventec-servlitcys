<?php

namespace App\Console\Commands;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Models\AdminSyncTask;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\AdminSync\WeeklyMassSyncOrchestrator;
use App\Support\AdminSync\WeeklyMassSyncCheckpoint;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('weekly-mass-sync:run
                            {--resume= : ID da tarefa falhada para retomar (checkpoint)}
                            {--sync : Executar na consola (sem fila; só para diagnóstico)}
                            {--force : Enfileirar mesmo com outra sincronização massiva pendente}')]
#[Description('Sincronização massiva semanal (geo, FUNDEB, repasses, SAEB) — enfileira ou retoma com checkpoint')]
class WeeklyMassSyncRunCommand extends Command
{
    public function handle(
        AdminSyncQueueService $syncQueue,
        WeeklyMassSyncOrchestrator $orchestrator,
    ): int {
        if (! filter_var(config('ieducar.weekly_mass_sync.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn(__('Sincronização massiva semanal desactivada (IEDUCAR_WEEKLY_MASS_SYNC_ENABLED=false).'));

            return self::SUCCESS;
        }

        $resumeId = $this->option('resume');
        if ($resumeId !== null && $resumeId !== '' && is_numeric($resumeId)) {
            return $this->resumeTask($syncQueue, (int) $resumeId);
        }

        if (! $this->option('force') && $this->hasActiveWeeklyTask()) {
            $this->warn(__('Já existe uma sincronização massiva pendente ou em processamento. Use --resume=ID para retomar uma falha ou --force para enfileirar outra.'));

            return self::FAILURE;
        }

        $payload = [
            'all_cities' => true,
            'trigger' => 'cli',
            'import_mode' => 'update',
            'saeb_auto_microdados' => true,
            'saeb_resolve_inep' => true,
        ];

        if ($this->option('sync')) {
            return $this->runSynchronously($orchestrator, $payload);
        }

        $task = $syncQueue->dispatch(
            AdminSyncDomain::System,
            WeeklyMassSyncCheckpoint::TASK_KEY,
            __('Sincronização massiva semanal'),
            $payload,
            null,
        );

        $this->info(__('Tarefa #:id enfileirada. Acompanhe em :url', [
            'id' => (string) $task->id,
            'url' => route('admin.sync-queue.index'),
        ]));
        $this->line(__('Timeout do job: :s s · Retomar: php artisan weekly-mass-sync:run --resume=:id', [
            's' => (string) config('ieducar.weekly_mass_sync.job_timeout', 14400),
            'id' => (string) $task->id,
        ]));

        return self::SUCCESS;
    }

    private function resumeTask(AdminSyncQueueService $syncQueue, int $taskId): int
    {
        $task = AdminSyncTask::query()->find($taskId);
        if ($task === null) {
            $this->error(__('Tarefa :id não encontrada.', ['id' => (string) $taskId]));

            return self::FAILURE;
        }

        if ($task->domain !== AdminSyncDomain::System->value
            || $task->task_key !== WeeklyMassSyncCheckpoint::TASK_KEY) {
            $this->error(__('A tarefa :id não é uma sincronização massiva semanal.', ['id' => (string) $taskId]));

            return self::FAILURE;
        }

        if ($task->status !== AdminSyncTaskStatus::Failed->value) {
            $this->error(__('A tarefa :id não está em estado «Falhou» (actual: :s).', [
                'id' => (string) $taskId,
                's' => (string) $task->status,
            ]));

            return self::FAILURE;
        }

        $syncQueue->resume($task);
        $this->info(__('Tarefa #:id reenfileirada — o checkpoint será respeitado.', ['id' => (string) $taskId]));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function runSynchronously(WeeklyMassSyncOrchestrator $orchestrator, array $payload): int
    {
        $task = AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::System->value,
            'task_key' => WeeklyMassSyncCheckpoint::TASK_KEY,
            'label' => __('Sincronização massiva semanal (consola)'),
            'status' => AdminSyncTaskStatus::Processing->value,
            'payload' => $payload,
            'started_at' => now(),
        ]);

        $this->warn(__('Modo --sync: execução directa (pode demorar horas).'));

        $progress = \App\Services\AdminSync\AdminSyncTaskProgress::forTask($task);
        $result = $orchestrator->run($task, $progress);

        $task->update([
            'status' => ($result['success'] ?? false)
                ? AdminSyncTaskStatus::Completed->value
                : AdminSyncTaskStatus::Failed->value,
            'result' => $result,
            'error_message' => ($result['success'] ?? false) ? null : ($result['message'] ?? ''),
            'completed_at' => now(),
        ]);

        if ($result['success'] ?? false) {
            $this->info((string) ($result['message'] ?? __('Concluído.')));

            return self::SUCCESS;
        }

        $this->error((string) ($result['message'] ?? __('Falhou.')));
        $this->line(__('Retomar: php artisan weekly-mass-sync:run --resume=:id', ['id' => (string) $task->id]));

        return self::FAILURE;
    }

    private function hasActiveWeeklyTask(): bool
    {
        return AdminSyncTask::query()
            ->where('domain', AdminSyncDomain::System->value)
            ->where('task_key', WeeklyMassSyncCheckpoint::TASK_KEY)
            ->whereIn('status', [
                AdminSyncTaskStatus::Pending->value,
                AdminSyncTaskStatus::Processing->value,
            ])
            ->exists();
    }
}
