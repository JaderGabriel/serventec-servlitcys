<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarCompatibilityProbe;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IeducarCompatibilityController extends Controller
{
    public function __construct(
        private CityDataConnection $cityData,
    ) {}

    public function index(Request $request): View
    {
        $cities = City::query()->orderBy('name')->get();
        $cityId = (int) $request->input('city_id', 0);
        $city = $cityId > 0 ? $cities->firstWhere('id', $cityId) : $cities->first();

        $report = null;
        $error = null;

        if ($city !== null) {
            $filters = IeducarFilterState::fromRequest($request);
            if (! $filters->hasYearSelected()) {
                $filters = new IeducarFilterState(ano_letivo: 'all', escola_id: $filters->escola_id, curso_id: $filters->curso_id, turno_id: $filters->turno_id);
            }

            try {
                $report = $this->cityData->run($city, function ($db) use ($city, $filters) {
                    return IeducarCompatibilityProbe::report($db, $city, $filters);
                });
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.ieducar-compatibility.index', [
            'cities' => $cities,
            'city' => $city,
            'report' => $report,
            'error' => $error,
            'filters' => $filters ?? null,
        ]);
    }
}
