<?php

namespace App\Services\Dashboard;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Models\User;
use App\Support\Pulse\PulseOperationRecorder;
use App\Support\Rx\RxFundebPortariaChart;

final class AdminHomeMetrics
{
    public function __construct(
        private readonly AdminSystemFlowStatus $systemFlow,
        private readonly AdminHomeMunicipalityMap $municipalityMap,
    ) {}

    /**
     * @return array{
     *     stats: array{cities: int, cities_active: int, cities_ready: int, cities_this_month: int, users: int, users_active: int},
     *     ops: array{sync_pending: int, sync_failed_24h: int, pdf_pending: int, pgsql: int, mysql: int},
     *     map_markers: list<array<string, mixed>>,
     *     map_summary: array<string, mixed>,
     *     fundeb_portaria: array<string, mixed>
     * }
     */
    public function gather(): array
    {
        return PulseOperationRecorder::measure('admin:home:gather', fn (): array => $this->gatherMetrics());
    }

    /**
     * @return array{
     *     stats: array{cities: int, cities_active: int, cities_ready: int, cities_this_month: int, users: int, users_active: int},
     *     ops: array{sync_pending: int, sync_failed_24h: int, pdf_pending: int, pgsql: int, mysql: int},
     *     map_markers: list<array<string, mixed>>,
     *     map_summary: array<string, mixed>,
     *     fundeb_portaria: array<string, mixed>
     * }
     */
    private function gatherMetrics(): array
    {
        $now = now();

        $activeCities = City::query()->active()->get();
        $ready = $activeCities->filter(fn (City $c) => $c->hasDataSetup())->count();

        $stats = [
            'cities' => City::count(),
            'cities_active' => $activeCities->count(),
            'cities_ready' => $ready,
            'cities_this_month' => City::query()
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count(),
            'users' => User::count(),
            'users_active' => User::query()->where('is_active', true)->count(),
        ];

        $ops = [
            'sync_pending' => AdminSyncTask::query()
                ->whereIn('status', [AdminSyncTaskStatus::Pending->value, AdminSyncTaskStatus::Processing->value])
                ->count(),
            'sync_failed_24h' => AdminSyncTask::query()
                ->where('status', AdminSyncTaskStatus::Failed->value)
                ->where('created_at', '>=', $now->copy()->subDay())
                ->count(),
            'pdf_pending' => AnalyticsReportExport::query()
                ->whereIn('status', [
                    AnalyticsReportExportStatus::Pending->value,
                    AnalyticsReportExportStatus::Processing->value,
                ])
                ->count(),
            'pgsql' => City::query()->active()->where('db_driver', City::DRIVER_PGSQL)->count(),
            'mysql' => City::query()->active()->where('db_driver', City::DRIVER_MYSQL)->count(),
        ];

        $vigenteYear = (int) config('rx.vigente_year', (int) date('Y'));

        return [
            'stats' => $stats,
            'ops' => $ops,
            'system_flow' => $this->systemFlow->diagram($ready, $activeCities->count()),
            'map_markers' => $this->municipalityMap->markers(),
            'map_summary' => $this->municipalityMap->summary(),
            'fundeb_portaria' => RxFundebPortariaChart::buildForCities($activeCities, $vigenteYear),
        ];
    }
}
