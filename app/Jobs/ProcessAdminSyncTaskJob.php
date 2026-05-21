<?php

namespace App\Jobs;

use App\Enums\AdminSyncTaskStatus;
use App\Models\AdminSyncTask;
use App\Services\AdminSync\AdminSyncTaskRunner;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAdminSyncTaskJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public int $tries;

    public function __construct(
        public int $adminSyncTaskId,
    ) {
        $this->timeout = max(60, (int) config('ieducar.admin_sync.job_timeout', 3600));
        $this->tries = max(1, (int) config('ieducar.admin_sync.tries', 1));
        $this->onQueue((string) config('ieducar.admin_sync.queue', 'admin-sync'));
        $connection = config('ieducar.admin_sync.connection');
        if ($connection !== null && $connection !== '') {
            $this->onConnection((string) $connection);
        }
    }

    public function handle(AdminSyncTaskRunner $runner, NotificationDispatcher $notifications): void
    {
        $task = AdminSyncTask::query()->find($this->adminSyncTaskId);
        if ($task === null) {
            return;
        }

        if ($task->status === AdminSyncTaskStatus::Completed->value) {
            return;
        }

        $task->update([
            'status' => AdminSyncTaskStatus::Processing->value,
            'started_at' => $task->started_at ?? now(),
            'attempts' => $task->attempts + 1,
            'error_message' => null,
            'output_log' => $this->appendLogLine(
                (string) ($task->output_log ?? ''),
                'info',
                __('Estado: a processar na fila :queue…', ['queue' => (string) config('ieducar.admin_sync.queue', 'admin-sync')]),
            ),
        ]);

        try {
            $result = $runner->run($task);
            $task->refresh();
            $output = isset($result['output']) && is_string($result['output']) ? $result['output'] : null;
            $task->update([
                'status' => AdminSyncTaskStatus::Completed->value,
                'result' => $result,
                'completed_at' => now(),
                'output_log' => $output !== null && $output !== '' ? $output : $task->output_log,
            ]);
            $notifications->adminSyncFinished($task->fresh());
        } catch (Throwable $e) {
            $task->refresh();
            $task->update([
                'status' => AdminSyncTaskStatus::Failed->value,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'output_log' => $this->appendLogLine(
                    (string) ($task->output_log ?? ''),
                    'error',
                    __('Falha na fila: :msg', ['msg' => $e->getMessage()]),
                ),
            ]);

            throw $e;
        }
    }

    private function appendLogLine(string $existing, string $level, string $message): string
    {
        $line = '['.now()->format('H:i:s').'] ['.$level.'] '.$message;

        return $existing === '' ? $line : $existing."\n".$line;
    }

    public function failed(?Throwable $exception): void
    {
        $task = AdminSyncTask::query()->find($this->adminSyncTaskId);
        if ($task === null) {
            return;
        }

        if ($task->status !== AdminSyncTaskStatus::Completed->value) {
            $task->update([
                'status' => AdminSyncTaskStatus::Failed->value,
                'error_message' => $exception?->getMessage() ?? __('Falha desconhecida na fila.'),
                'completed_at' => $task->completed_at ?? now(),
            ]);
            app(NotificationDispatcher::class)->adminSyncFinished($task->fresh());
        }
    }
}
