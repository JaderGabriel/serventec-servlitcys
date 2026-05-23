<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\Dashboard\AdminHomeMapCadastroSnapshot;
use App\Services\Dashboard\CitySchoolYearsForMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardMunicipalityMapController extends Controller
{
    public function cadastroSnapshot(Request $request, AdminHomeMapCadastroSnapshot $snapshot): JsonResponse
    {
        if ($request->user() === null || ! $request->user()->canViewAdminDashboard()) {
            abort(403);
        }

        return response()->json($snapshot->forMap());
    }

    public function schoolYears(Request $request, City $city, CitySchoolYearsForMap $catalog): JsonResponse
    {
        if ($request->user() === null || ! $request->user()->canViewAdminDashboard()) {
            abort(403);
        }

        return response()->json([
            'city_id' => $city->id,
            'name' => $city->name,
            'uf' => $city->uf,
            'implemented_at' => $city->created_at?->format('d/m/Y'),
            'implemented_at_iso' => $city->created_at?->toIso8601String(),
            'is_active' => (bool) $city->is_active,
            'has_data_setup' => $city->hasDataSetup(),
            'school_years' => $catalog->forCity($city),
            'analytics_url' => route('dashboard.analytics', ['city_id' => $city->id]),
        ]);
    }
}
