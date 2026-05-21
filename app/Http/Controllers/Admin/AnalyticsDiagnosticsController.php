<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\Analytics\AnalyticsDiagnosticsRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsDiagnosticsController extends Controller
{
    public function __invoke(Request $request, AnalyticsDiagnosticsRunner $runner): View|JsonResponse
    {
        $report = $runner->run($request);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($report, $report['summary']['ok'] ? 200 : 422);
        }

        return view('admin.analytics-diagnostics', [
            'report' => $report,
            'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'uf']),
            'query' => [
                'city_id' => $request->integer('city_id'),
                'ano_letivo' => $request->input('ano_letivo', '2024'),
                'skip_slow' => $request->boolean('skip_slow'),
            ],
        ]);
    }
}
