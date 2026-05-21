<?php

namespace App\Support\Scheduling;

use App\Enums\AdminSyncTaskStatus;
use App\Models\AdminSyncTask;
use Illuminate\Support\Facades\Queue;

/**
 * Decide se a fila admin-sync precisa de worker (tarefas ou jobs pendentes).
 */
final class AdminSyncScheduleGate
{
    public static function hasPendingWork(): bool
    {
        if (self::hasPendingTasks()) {
            return true;
        }

        return self::queuedJobCount() > 0;
    }

    public static function hasPendingTasks(): bool
    {
        return AdminSyncTask::query()
            ->whereIn('status', [
                AdminSyncTaskStatus::Pending->value,
                AdminSyncTaskStatus::Processing->value,
            ])
            ->exists();
    }

    public static function queuedJobCount(): int
    {
        $queue = (string) config('ieducar.admin_sync.queue', 'admin-sync');
        $connection = config('ieducar.admin_sync.connection') ?? config('queue.default');

        try {
            return (int) Queue::connection($connection)->size($queue);
        } catch (\Throwable) {
            return 0;
        }
    }
}
