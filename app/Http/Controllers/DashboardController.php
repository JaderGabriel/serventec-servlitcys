<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\User;
use App\Services\CityDataConnection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, CityDataConnection $cityData): View
    {
        $now = now();

        $stats = [
            'cities' => City::count(),
            'cities_active' => City::query()->active()->count(),
            'cities_this_month' => City::query()
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count(),
            'users' => User::count(),
        ];

        $recentCities = City::query()->active()->latest()->limit(8)->get();

        $citiesForFilter = City::query()->forAnalytics()->orderBy('name')->get();

        $selectedCity = null;
        $cityDataProbe = null;

        if ($request->filled('city_id')) {
            $selectedCity = City::query()
                ->forAnalytics()
                ->whereKey($request->integer('city_id'))
                ->first();

            if ($selectedCity) {
                $cityDataProbe = $cityData->probe($selectedCity);
            }
        }

        return view('dashboard', [
            'stats' => $stats,
            'recentCities' => $recentCities,
            'citiesForFilter' => $citiesForFilter,
            'selectedCity' => $selectedCity,
            'cityDataProbe' => $cityDataProbe,
            'selectedCityId' => $selectedCity?->getKey(),
        ]);
    }
}
