<?php

namespace App\Http\Controllers;

use App\Services\Horizonte\HorizonteMapService;
use App\Support\Brazil\BrazilUfNames;
use App\Support\Horizonte\HorizonteMapPresenter;
use App\Support\Horizonte\HorizonteUfScope;
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
        abort_unless($user !== null && $user->canViewHorizonte(), 403);
        abort_unless((bool) config('horizonte.enabled', true), 404);

        return view('horizonte.index', [
            'mapDataUrl' => route('dashboard.horizonte.map-data'),
            'refYear' => (int) config('horizonte.reference_year', (int) date('Y') - 1),
            'legend' => HorizonteMapPresenter::legendItems(),
            'colors' => HorizonteMapPresenter::tierColors(),
            'methodology' => HorizonteMapPresenter::methodologyUi(),
            'defaultViewFilter' => HorizonteMapPresenter::defaultViewFilter(),
            'canRefreshData' => $user->canImportOrConfigure(),
            'canManageSge' => $user->canImportOrConfigure() && filter_var(config('horizonte.sge.enabled', true), FILTER_VALIDATE_BOOLEAN),
            'sgeShowUrl' => route('admin.horizonte.sge.show', ['ibge' => '__IBGE__']),
            'sgeRegistryUrl' => route('admin.horizonte.sge.upsert', ['ibge' => '__IBGE__']),
            'initialUf' => HorizonteUfScope::normalize($request->query('uf')) ?? '',
            'ufNames' => BrazilUfNames::all(),
        ]);
    }

    public function mapData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canViewHorizonte(), 403);
        abort_unless((bool) config('horizonte.enabled', true), 404);

        $uf = HorizonteUfScope::normalize($request->query('uf'));
        $scope = (string) $request->query('scope', $uf !== null ? 'regional' : 'overview');
        if (! in_array($scope, ['overview', 'regional'], true)) {
            $scope = 'overview';
        }
        if ($scope === 'regional' && $uf === null) {
            $scope = 'overview';
        }

        return response()->json($this->map->buildForRequest($scope, $uf));
    }
}
