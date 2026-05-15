<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSyncDomain;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\AdminSync\AdminSyncGeoPayloadBuilder;
use App\Services\AdminSync\AdminSyncQueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeoSyncController extends Controller
{
    public function __construct(
        private AdminSyncQueueService $syncQueue,
    ) {}

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
            'step' => 'required|string|in:ieducar,microdados,official,pipeline,probe',
            'city_id' => 'nullable|integer|exists:cities,id',
            'threshold' => 'nullable|numeric|min:0|max:50000',
        ]);

        $cityId = isset($validated['city_id']) ? (int) $validated['city_id'] : null;

        if ($validated['step'] === 'probe' && $cityId === null) {
            return redirect()
                ->route('admin.geo-sync.index')
                ->with('geo_sync_error', __('Para o diagnóstico (probe), selecione uma cidade — são usados os códigos INEP de school_unit_geos dessa cidade.'));
        }

        $payload = AdminSyncGeoPayloadBuilder::fromRequest($request, $validated['step']);

        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Geo,
            $validated['step'],
            $payload['title'],
            $payload,
            $cityId,
        );

        return redirect()
            ->route('admin.geo-sync.index')
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task),
            ]);
    }
}
