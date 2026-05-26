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

        $report = $this->monitor->build($period);

        return view('admin.module-monitor.index', [
            'report' => $report,
            'period' => $period,
            'periods' => array_keys(config('module_monitor.periods', ['24h' => [], '7d' => []])),
        ]);
    }
}
