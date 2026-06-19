<?php

namespace App\Http\Controllers;

use App\Services\Horizonte\HorizonteMapService;
use App\Support\Horizonte\HorizonteMapPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HorizonteController extends Controller
{
    public function __construct(
        private readonly HorizonteMapService $map,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canViewAdminDashboard(), 403);
        abort_unless((bool) config('horizonte.enabled', true), 404);

        return view('horizonte.index', [
            'mapDataUrl' => route('dashboard.horizonte.map-data'),
            'refYear' => (int) config('horizonte.reference_year', (int) date('Y') - 1),
            'legend' => HorizonteMapPresenter::legendItems(),
            'colors' => HorizonteMapPresenter::tierColors(),
        ]);
    }

    public function mapData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canViewAdminDashboard(), 403);
        abort_unless((bool) config('horizonte.enabled', true), 404);

        return response()->json($this->map->build());
    }
}
