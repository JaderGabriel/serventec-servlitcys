<?php

namespace App\Support\Scheduling;

use App\Enums\AnalyticsReportExportStatus;
use App\Models\AnalyticsReportExport;
use Illuminate\Support\Facades\Queue;

/**
 * Decide se a fila de PDF analítico precisa de worker (exportações pendentes ou jobs na fila).
 */
final class AnalyticsPdfScheduleGate
{
    public static function hasPendingWork(): bool
    {
        if (self::hasPendingExports()) {
            return true;
        }

        return self::queuedJobCount() > 0;
    }

    public static function hasPendingExports(): bool
    {
        return AnalyticsReportExport::query()
            ->where('status', AnalyticsReportExportStatus::Pending->value)
            ->exists();
    }

    public static function queuedJobCount(): int
    {
        $queue = (string) config('analytics.pdf_report.queue', 'default');
        $connection = config('analytics.pdf_report.connection') ?? config('queue.default');

        try {
            return (int) Queue::connection($connection)->size($queue);
        } catch (\Throwable) {
            return 0;
        }
    }
}
