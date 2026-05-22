<?php

namespace App\Services\AdminSync;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Jobs\ProcessAdminSyncTaskJob;
use App\Models\AdminSyncTask;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Auth;

final class AdminSyncQueueService
{
    public function dispatch(
        AdminSyncDomain $domain,
        string $taskKey,
        string $label,
        array $payload = [],
        ?int $cityId = null,
        ?int $userId = null,
    ): AdminSyncTask {
        $payload = AdminSyncTaskCitiesResolver::enrichPayload($payload, $cityId, $domain, $taskKey);

        $task = AdminSyncTask::query()->create([
            'domain' => $domain->value,
            'task_key' => $taskKey,
            'label' => $label,
            'city_id' => $cityId,
            'queued_by' => $userId ?? Auth::id(),
            'status' => AdminSyncTaskStatus::Pending->value,
            'payload' => $payload,
        ]);

        $connection = config('ieducar.admin_sync.connection');
        $queue = (string) config('ieducar.admin_sync.queue', 'admin-sync');

        $pending = ProcessAdminSyncTaskJob::dispatch($task->id)->onQueue($queue);
        if ($connection !== null && $connection !== '') {
            $pending->onConnection((string) $connection);
        }

        $task = $task->fresh(['city', 'queuedBy']);

        app(NotificationDispatcher::class)->adminSyncQueued($task);

        return $task;
    }

    public static function flashQueuedMessage(AdminSyncTask $task): string
    {
        return __('Tarefa #:id enfileirada («:label»). Acompanhe em :link.', [
            'id' => (string) $task->id,
            'label' => $task->label,
            'link' => route('admin.sync-queue.index'),
        ]);
    }

    /**
     * Reenfileira tarefa falhada ou interrompida; tarefas geo multi-município retomam do checkpoint.
     */
    public function resume(AdminSyncTask $task, ?int $userId = null): AdminSyncTask
    {
        if ($task->status !== AdminSyncTaskStatus::Failed->value) {
            throw new \InvalidArgumentException(__('Só é possível retomar tarefas com estado «Falhou».'));
        }

        $task->update([
            'status' => AdminSyncTaskStatus::Pending->value,
            'error_message' => null,
            'completed_at' => null,
            'result' => null,
            'queued_by' => $userId ?? Auth::id() ?? $task->queued_by,
        ]);

        $connection = config('ieducar.admin_sync.connection');
        $queue = (string) config('ieducar.admin_sync.queue', 'admin-sync');

        $pending = ProcessAdminSyncTaskJob::dispatch($task->id)->onQueue($queue);
        if ($connection !== null && $connection !== '') {
            $pending->onConnection((string) $connection);
        }

        return $task->fresh(['city', 'queuedBy']);
    }
}
