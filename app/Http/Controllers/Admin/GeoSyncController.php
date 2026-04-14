<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class GeoSyncController extends Controller
{
    public function index(): View
    {
        $cities = City::query()->forAnalytics()->orderBy('name')->get(['id', 'name']);

        return view('admin.geo-sync.index', [
            'cities' => $cities,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'step' => 'required|string|in:ieducar,csv,official,pipeline,probe',
            'city_id' => 'nullable|integer|exists:cities,id',
            'threshold' => 'nullable|numeric|min:0|max:50000',
        ]);

        @set_time_limit(600);

        $cityId = isset($validated['city_id']) ? (int) $validated['city_id'] : null;

        $threshold = isset($validated['threshold']) && $validated['threshold'] !== null
            ? (float) $validated['threshold']
            : (float) config('ieducar.inep_geocoding.divergence_threshold_meters', 100);
        if ($threshold <= 0) {
            $threshold = (float) config('ieducar.inep_geocoding.divergence_threshold_meters', 100);
        }

        $ieducarOnlyMissing = $request->boolean('ieducar_only_missing');
        $officialOnlyMissing = $request->boolean('official_only_missing');
        $pipelineSkipIeducar = $request->boolean('pipeline_skip_ieducar');
        $pipelineWithCsv = $request->boolean('pipeline_with_csv');
        $dryRun = $request->boolean('dry_run');

        if ($validated['step'] === 'probe' && $cityId === null) {
            return redirect()
                ->route('admin.geo-sync.index')
                ->with('geo_sync_error', __('Para o diagnóstico (probe), selecione uma cidade — são usados os códigos INEP de school_unit_geos dessa cidade.'));
        }

        $exitCode = 1;
        $title = '';

        try {
            switch ($validated['step']) {
                case 'ieducar':
                    $title = __('i-Educar → school_unit_geos');
                    $args = ['--only-missing' => $ieducarOnlyMissing ? '1' : '0'];
                    if ($cityId !== null) {
                        $args['--city'] = (string) $cityId;
                    }
                    $exitCode = Artisan::call('app:sync-school-unit-geos', $args);
                    break;

                case 'csv':
                    $title = __('Import CSV de fallback INEP');
                    $exitCode = Artisan::call('app:import-inep-geo-fallback-csv', [
                        '--also-map-coords' => '0',
                        '--skip-if-missing' => '1',
                    ]);
                    break;

                case 'official':
                    $title = __('Coordenadas oficiais INEP + divergência');
                    $args = [
                        '--only-missing' => $officialOnlyMissing ? '1' : '0',
                        '--threshold' => (string) $threshold,
                        '--dry-run' => $dryRun ? '1' : '0',
                    ];
                    if ($cityId !== null) {
                        $args['--city'] = (string) $cityId;
                    }
                    $exitCode = Artisan::call('app:sync-school-unit-geos-official', $args);
                    break;

                case 'pipeline':
                    $title = __('Pipeline completo');
                    $args = [
                        '--skip-ieducar' => $pipelineSkipIeducar ? '1' : '0',
                        '--ieducar-only-missing' => $ieducarOnlyMissing ? '1' : '0',
                        '--official-only-missing' => $officialOnlyMissing ? '1' : '0',
                        '--threshold' => (string) $threshold,
                        '--dry-run' => $dryRun ? '1' : '0',
                        '--with-csv-import' => $pipelineWithCsv ? '1' : '0',
                        '--skip-csv-on-missing-file' => '1',
                    ];
                    if ($cityId !== null) {
                        $args['--city'] = (string) $cityId;
                    }
                    $exitCode = Artisan::call('app:sync-school-unit-geos-pipeline', $args);
                    break;

                case 'probe':
                    $title = __('Diagnóstico fontes INEP');
                    $args = [];
                    if ($cityId !== null) {
                        $args['--city'] = (string) $cityId;
                    }
                    $exitCode = Artisan::call('app:probe-inep-geo-fallbacks', $args);
                    break;

                default:
                    $exitCode = 1;
            }
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.geo-sync.index')
                ->with('geo_sync_error', $e->getMessage());
        }

        $output = Artisan::output();

        return redirect()
            ->route('admin.geo-sync.index')
            ->with('geo_sync_result', [
                'step' => $validated['step'],
                'title' => $title,
                'exit_code' => $exitCode,
                'output' => $output,
            ]);
    }
}
