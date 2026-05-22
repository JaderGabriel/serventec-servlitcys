<?php

namespace App\Support\Queue;

/**
 * Alinha retry_after das filas ao timeout dos jobs longos (admin-sync, PDF).
 */
final class LongJobQueueTiming
{
    public static function retryAfterSeconds(): int
    {
        $configured = (int) env('DB_QUEUE_RETRY_AFTER', 0);
        if ($configured > 0) {
            return $configured;
        }

        $admin = max(60, (int) config('ieducar.admin_sync.job_timeout', 3600));
        $pdf = max(120, (int) config('analytics.pdf_report.job_timeout', 900));

        return max($admin, $pdf) + 120;
    }
}
