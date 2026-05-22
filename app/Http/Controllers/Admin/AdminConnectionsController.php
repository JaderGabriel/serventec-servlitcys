<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Auth\UserCityAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Painel operacional: estatísticas da aplicação e teste de conexão i-Educar por município.
 */
class AdminConnectionsController extends Controller
{
    public function index(Request $request, CityDataConnection $cityData): View
    {
        $user = $request->user();
        $now = now();

        $stats = [
            'cities' => City::count(),
            'cities_active' => City::query()->active()->count(),
            'cities_this_month' => City::query()
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count(),
            'users' => \App\Models\User::count(),
        ];

        $recentCities = City::query()->active()->latest()->limit(8)->get();
        $citiesForFilter = UserCityAccess::citiesQuery($user)->get();

        $selectedCity = null;
        $cityDataProbe = null;

        if ($request->filled('city_id')) {
            $selectedCity = UserCityAccess::citiesQuery($user)
                ->whereKey($request->integer('city_id'))
                ->first();

            if ($selectedCity) {
                $cityDataProbe = $cityData->probe($selectedCity);
            }
        }

        return view('admin.connections', [
            'stats' => $stats,
            'recentCities' => $recentCities,
            'citiesForFilter' => $citiesForFilter,
            'selectedCity' => $selectedCity,
            'cityDataProbe' => $cityDataProbe,
            'selectedCityId' => $selectedCity?->getKey(),
        ]);
    }
}
