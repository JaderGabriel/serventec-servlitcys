<?php

namespace App\Services\AdminSync;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Jobs\ProcessAdminSyncTaskJob;
use App\Models\AdminSyncTask;
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

        return $task->fresh(['city']);
    }

    public static function flashQueuedMessage(AdminSyncTask $task): string
    {
        return __('Tarefa #:id enfileirada («:label»). Acompanhe em :link.', [
            'id' => (string) $task->id,
            'label' => $task->label,
            'link' => route('admin.sync-queue.index'),
        ]);
    }
}
