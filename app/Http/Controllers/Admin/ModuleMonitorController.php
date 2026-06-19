<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ModuleMonitorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ModuleMonitorController extends Controller
{
    public function __construct(
        private readonly ModuleMonitorService $monitor,
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()?->isAdmin(), 403);

        $period = (string) $request->query('period', (string) config('module_monitor.default_period', '24h'));
        if (! isset(config('module_monitor.periods', [])[$period])) {
            $period = '24h';
        }

        $statusFilter = (string) $request->query('status', 'all');
        if (! in_array($statusFilter, ['all', 'healthy', 'warning', 'critical', 'unknown'], true)) {
            $statusFilter = 'all';
        }

        $report = $this->monitor->build($period);

        if ($statusFilter !== 'all') {
            $report['modules'] = array_values(array_filter(
                $report['modules'],
                static fn (array $module): bool => ($module['status'] ?? '') === $statusFilter,
            ));
        }

        return view('admin.module-monitor.index', [
            'report' => $report,
            'period' => $period,
            'statusFilter' => $statusFilter,
            'periods' => array_keys(config('module_monitor.periods', ['24h' => [], '7d' => []])),
        ]);
    }
}
