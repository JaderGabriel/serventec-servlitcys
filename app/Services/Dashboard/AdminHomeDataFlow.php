<?php

namespace App\Services\Dashboard;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Série temporal e metadados do fluxo de dados no Início (admin).
 */
final class AdminHomeDataFlow
{
    private const DAYS = 7;

    /**
     * @return array{
     *     labels: list<string>,
     *     datasets: list<array{label: string, data: list<int>, color: string}>,
     *     totals: array<string, int>,
     *     period_label: string,
     *     has_pulse: bool
     * }
     */
    public function chartPayload(): array
    {
        $days = $this->dayBuckets();
        $keys = $days->map(fn (Carbon $d) => $d->toDateString())->all();
        $labels = $days->map(fn (Carbon $d) => $d->format('d/m'))->all();

        $http = $this->countsByDay($keys, $this->pulseHttpByDay($days->first(), $days->last()));
        $sync = $this->countsByDay($keys, $this->completedTasksByDay(
            AdminSyncTask::query()
                ->where('status', AdminSyncTaskStatus::Completed->value)
                ->where('completed_at', '>=', $days->first()),
            'completed_at',
        ));
        $pdf = $this->countsByDay($keys, $this->completedTasksByDay(
            AnalyticsReportExport::query()
                ->where('status', AnalyticsReportExportStatus::Completed->value)
                ->where('updated_at', '>=', $days->first()),
            'updated_at',
        ));
        $notify = $this->countsByDay($keys, $this->notificationsByDay($days->first()));

        $datasets = [
            [
                'label' => __('Pedidos HTTP'),
                'data' => $http,
                'color' => '#0d9488',
            ],
            [
                'label' => __('Sync concluídos'),
                'data' => $sync,
                'color' => '#6366f1',
            ],
            [
                'label' => __('PDF gerados'),
                'data' => $pdf,
                'color' => '#d97706',
            ],
            [
                'label' => __('Notificações'),
                'data' => $notify,
                'color' => '#64748b',
            ],
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'totals' => [
                'http' => array_sum($http),
                'sync' => array_sum($sync),
                'pdf' => array_sum($pdf),
                'notify' => array_sum($notify),
            ],
            'period_label' => __('Últimos :days dias', ['days' => self::DAYS]),
            'has_pulse' => Schema::hasTable('pulse_entries'),
        ];
    }

    /**
     * Nós do diagrama de fluxo (valores agregados do período).
     *
     * @param  array{http: int, sync: int, pdf: int, notify: int}  $totals
     * @return list<array{key: string, label: string, value: string, hint: string, tone: string}>
     */
    public function pipelineNodes(int $citiesReady, int $citiesActive, array $totals): array
    {
        return [
            [
                'key' => 'ieducar',
                'label' => __('Bases i-Educar'),
                'value' => number_format($citiesReady).' / '.number_format($citiesActive),
                'hint' => __('Municípios com ligação configurada'),
                'tone' => 'teal',
            ],
            [
                'key' => 'app',
                'label' => __('Aplicação'),
                'value' => number_format($totals['http'] ?? 0),
                'hint' => __('Pedidos HTTP no período (Pulse)'),
                'tone' => 'slate',
            ],
            [
                'key' => 'analytics',
                'label' => __('Consultoria'),
                'value' => '→',
                'hint' => __('Painel analítico por município'),
                'tone' => 'teal',
            ],
            [
                'key' => 'sync',
                'label' => __('Sincronização'),
                'value' => number_format($totals['sync'] ?? 0),
                'hint' => __('Jobs admin-sync concluídos'),
                'tone' => 'indigo',
            ],
            [
                'key' => 'pdf',
                'label' => __('Relatórios PDF'),
                'value' => number_format($totals['pdf'] ?? 0),
                'hint' => __('Exportações concluídas'),
                'tone' => 'amber',
            ],
            [
                'key' => 'notify',
                'label' => __('Notificações'),
                'value' => number_format($totals['notify'] ?? 0),
                'hint' => __('Alertas gerados no período'),
                'tone' => 'slate',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentNotifications(int $limit = 6): array
    {
        if (! Schema::hasTable('notifications')) {
            return [];
        }

        return DatabaseNotification::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $n) => NotificationPresenter::fromDatabaseNotification($n))
            ->all();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function dayBuckets(): Collection
    {
        $end = now()->startOfDay();

        return collect(range(self::DAYS - 1, 0))
            ->map(fn (int $offset) => $end->copy()->subDays($offset));
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, int>  $byDay
     * @return list<int>
     */
    private function countsByDay(array $keys, array $byDay): array
    {
        return array_map(fn (string $key) => (int) ($byDay[$key] ?? 0), $keys);
    }

    /**
     * @return array<string, int>
     */
    private function pulseHttpByDay(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('pulse_entries')) {
            return [];
        }

        $driver = DB::connection()->getDriverName();
        $fromTs = $from->copy()->startOfDay()->timestamp;
        $toTs = $to->copy()->endOfDay()->timestamp;

        $dayExpr = match ($driver) {
            'pgsql' => "to_char(to_timestamp(timestamp), 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', timestamp, 'unixepoch')",
            default => "DATE(FROM_UNIXTIME(timestamp))",
        };

        $rows = DB::table('pulse_entries')
            ->selectRaw("{$dayExpr} as day, SUM(value) as total")
            ->where('type', 'trafego_app')
            ->whereBetween('timestamp', [$fromTs, $toTs])
            ->groupByRaw($dayExpr)
            ->pluck('total', 'day');

        return $rows->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, int>
     */
    private function completedTasksByDay($query, string $dateColumn): array
    {
        $driver = DB::connection()->getDriverName();
        $dayExpr = match ($driver) {
            'pgsql' => "to_char({$dateColumn}, 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', {$dateColumn})",
            default => "DATE({$dateColumn})",
        };

        return $query
            ->selectRaw("{$dayExpr} as day, COUNT(*) as total")
            ->groupByRaw($dayExpr)
            ->pluck('total', 'day')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function notificationsByDay(Carbon $from): array
    {
        if (! Schema::hasTable('notifications')) {
            return [];
        }

        $driver = DB::connection()->getDriverName();
        $dayExpr = match ($driver) {
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            default => 'DATE(created_at)',
        };

        return DB::table('notifications')
            ->where('created_at', '>=', $from)
            ->selectRaw("{$dayExpr} as day, COUNT(*) as total")
            ->groupByRaw($dayExpr)
            ->pluck('total', 'day')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
