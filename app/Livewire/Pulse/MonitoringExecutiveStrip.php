<?php

namespace App\Livewire\Pulse;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Support\Pulse\PulseDatabaseMetricsAggregator;
use App\Support\Pulse\PulseOperationMetricsAggregator;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * Faixa de KPIs para decisão rápida (municípios, filas, erros, latência).
 */
#[Lazy]
class MonitoringExecutiveStrip extends Card
{
    public function render(): Renderable
    {
        [$pulse, $timePulse, $runAtPulse] = $this->remember(function () {
            $exceptions = 0;
            foreach ($this->aggregate('exception', 'count', 'count', 'desc', 50) as $r) {
                $exceptions += (int) ($r->count ?? 0);
            }

            $slowRequests = 0;
            $maxSlowMs = null;
            foreach ($this->aggregate('slow_request', ['count', 'max'], 'count', 'desc', 100) as $r) {
                $slowRequests += (int) ($r->count ?? 0);
                $m = isset($r->max) ? (int) $r->max : null;
                if ($m !== null) {
                    $maxSlowMs = $maxSlowMs === null ? $m : max($maxSlowMs, $m);
                }
            }

            $globalRequests = 0;
            foreach ($this->aggregate('trafego_app', 'count', null, 'desc', 5) as $r) {
                $globalRequests += (int) ($r->count ?? 0);
            }

            $dbMetrics = PulseDatabaseMetricsAggregator::summarize(
                fn (string $type, array|string $aggregate, ?string $orderBy, string $direction, int $limit) => $this->aggregate($type, $aggregate, $orderBy, $direction, $limit)
            );

            $systemSlow = (int) ($dbMetrics['system']['slow_count'] ?? 0);
            $muniSlow = 0;
            $muniWorstMs = null;
            foreach ($dbMetrics['municipal_by_city'] as $row) {
                $muniSlow += (int) ($row['slow_count'] ?? 0);
                $candidate = max(
                    (int) ($row['slow_max_ms'] ?? 0),
                    (int) ($row['run_max_ms'] ?? 0),
                );
                if ($candidate > 0) {
                    $muniWorstMs = $muniWorstMs === null ? $candidate : max($muniWorstMs, $candidate);
                }
            }

            $opMetrics = PulseOperationMetricsAggregator::summarize(
                fn (string $type, array|string $aggregate, ?string $orderBy, string $direction, int $limit) => $this->aggregate($type, $aggregate, $orderBy, $direction, $limit)
            );
            $slowOperations = 0;
            $maxOpMs = null;
            foreach ($opMetrics['slow_operations'] as $row) {
                $slowOperations += (int) ($row['count'] ?? 0);
                $m = (int) ($row['max_ms'] ?? 0);
                if ($m > 0) {
                    $maxOpMs = $maxOpMs === null ? $m : max($maxOpMs, $m);
                }
            }

            return [
                'exceptions' => $exceptions,
                'slow_requests' => $slowRequests,
                'max_slow_ms' => $maxSlowMs,
                'global_requests' => $globalRequests,
                'system_slow_queries' => $systemSlow,
                'municipal_slow_queries' => $muniSlow,
                'municipal_worst_ms' => $muniWorstMs,
                'slow_operations' => $slowOperations,
                'max_operation_ms' => $maxOpMs,
            ];
        }, 'pulse-kpi');

        [$ops, $timeOps, $runAtOps] = $this->remember(function () {
            $active = City::query()->active()->count();
            $ready = City::query()->active()->get()->filter(fn (City $c) => $c->hasDataSetup())->count();
            $pgsql = City::query()->active()->where('db_driver', City::DRIVER_PGSQL)->count();
            $mysql = City::query()->active()->where('db_driver', City::DRIVER_MYSQL)->count();

            $syncPending = AdminSyncTask::query()
                ->whereIn('status', [AdminSyncTaskStatus::Pending->value, AdminSyncTaskStatus::Processing->value])
                ->count();
            $syncFailed = AdminSyncTask::query()
                ->where('status', AdminSyncTaskStatus::Failed->value)
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $pdfPending = AnalyticsReportExport::query()
                ->whereIn('status', [
                    AnalyticsReportExportStatus::Pending->value,
                    AnalyticsReportExportStatus::Processing->value,
                ])
                ->count();

            return compact('active', 'ready', 'pgsql', 'mysql', 'syncPending', 'syncFailed', 'pdfPending');
        }, 'ops');

        return View::make('livewire.pulse.monitoring-executive-strip', [
            'pulse' => $pulse,
            'ops' => $ops,
            'time' => max($timePulse, $timeOps),
            'runAt' => $runAtOps,
            'period' => $this->periodForHumans(),
        ]);
    }
}
