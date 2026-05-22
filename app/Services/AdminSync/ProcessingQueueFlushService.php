<?php

namespace App\Services\AdminSync;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esvazia tarefas ativas da UI «Filas de processamento» e jobs Laravel associados.
 */
final class ProcessingQueueFlushService
{
    /**
     * @return array{
     *   sync_tasks: int,
     *   pdf_exports: int,
     *   sync_jobs_cleared: bool,
     *   pdf_jobs_cleared: bool,
     *   sync_failed_jobs: int,
     *   pdf_failed_jobs: int
     * }
     */
    public function flush(bool $sync, bool $pdf, bool $includeFailed, bool $includeCompleted, bool $dryRun): array
    {
        $result = [
            'sync_tasks' => 0,
            'pdf_exports' => 0,
            'sync_jobs_cleared' => false,
            'pdf_jobs_cleared' => false,
            'sync_failed_jobs' => 0,
            'pdf_failed_jobs' => 0,
        ];

        if ($sync) {
            $statuses = $this->activeStatuses($includeFailed, $includeCompleted);
            $result['sync_tasks'] = AdminSyncTask::query()->whereIn('status', $statuses)->count();

            if (! $dryRun) {
                AdminSyncTask::query()->whereIn('status', $statuses)->delete();
                [$connection, $queue] = $this->syncQueueTarget();
                $result['sync_jobs_cleared'] = $this->clearQueue($connection, $queue);
                $result['sync_failed_jobs'] = $this->pruneFailedJobs($queue);
            }
        }

        if ($pdf) {
            $statuses = $this->activeStatuses($includeFailed, $includeCompleted);
            $result['pdf_exports'] = AnalyticsReportExport::query()->whereIn('status', $statuses)->count();

            if (! $dryRun) {
                AnalyticsReportExport::query()->whereIn('status', $statuses)->delete();
                [$connection, $queue] = $this->pdfQueueTarget();
                $result['pdf_jobs_cleared'] = $this->clearQueue($connection, $queue);
                $result['pdf_failed_jobs'] = $this->pruneFailedJobs($queue);
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function activeStatuses(bool $includeFailed, bool $includeCompleted): array
    {
        $statuses = [
            AdminSyncTaskStatus::Pending->value,
            AdminSyncTaskStatus::Processing->value,
        ];

        if ($includeFailed) {
            $statuses[] = AdminSyncTaskStatus::Failed->value;
        }

        if ($includeCompleted) {
            $statuses[] = AdminSyncTaskStatus::Completed->value;
        }

        return $statuses;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function syncQueueTarget(): array
    {
        $connection = (string) (config('ieducar.admin_sync.connection') ?? config('queue.default', 'database'));
        $queue = (string) config('ieducar.admin_sync.queue', 'admin-sync');

        return [$connection, $queue];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function pdfQueueTarget(): array
    {
        $connection = (string) (config('analytics.pdf_report.connection') ?? config('queue.default', 'database'));
        $queue = (string) config('analytics.pdf_report.queue', 'default');

        return [$connection, $queue];
    }

    private function clearQueue(string $connection, string $queue): bool
    {
        if ($queue === '' || config("queue.connections.{$connection}") === null) {
            return false;
        }

        try {
            Artisan::call('queue:clear', [
                'connection' => $connection,
                '--queue' => $queue,
                '--force' => true,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function pruneFailedJobs(string $queue): int
    {
        if ($queue === '' || ! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->where('queue', $queue)->delete();
    }
}
