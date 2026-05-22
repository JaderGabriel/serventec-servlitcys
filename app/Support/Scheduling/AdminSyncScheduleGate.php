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

    public static function hasPendingWeeklyMassSync(): bool
    {
        return AdminSyncTask::query()
            ->where('domain', 'system')
            ->where('task_key', 'weekly_mass_sync')
            ->whereIn('status', [
                AdminSyncTaskStatus::Pending->value,
                AdminSyncTaskStatus::Processing->value,
            ])
            ->exists();
    }

    /**
     * Tempo máximo do worker admin-sync:work quando há sincronização massiva na fila.
     */
    public static function workerMaxSeconds(int $defaultMax): int
    {
        if (! self::hasPendingWeeklyMassSync()) {
            return $defaultMax;
        }

        return max(
            $defaultMax,
            max(3600, (int) config('ieducar.weekly_mass_sync.worker_max_seconds', 14400)),
        );
    }

    public static function workerJobTimeout(int $defaultTimeout): int
    {
        if (! self::hasPendingWeeklyMassSync()) {
            return $defaultTimeout;
        }

        return max(
            $defaultTimeout,
            max(3600, (int) config('ieducar.weekly_mass_sync.job_timeout', 14400)),
        );
    }
}
