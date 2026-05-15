<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\User;
use App\Services\CityDataConnection;
use App\Support\Auth\UserCityAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, CityDataConnection $cityData): View
    {
        $user = $request->user();
        $now = now();

        $stats = [
            'cities' => $user?->isAdmin() ? City::count() : UserCityAccess::citiesQuery($user)->count(),
            'cities_active' => $user?->isAdmin()
                ? City::query()->active()->count()
                : UserCityAccess::citiesQuery($user)->count(),
            'cities_this_month' => $user?->isAdmin()
                ? City::query()
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->count()
                : 0,
            'users' => $user?->isAdmin() ? User::count() : 0,
        ];

        $recentCities = $user?->isAdmin()
            ? City::query()->active()->latest()->limit(8)->get()
            : UserCityAccess::citiesQuery($user)->limit(8)->get();

        $citiesForFilter = UserCityAccess::citiesQuery($user)->get();

        $selectedCity = null;
        $cityDataProbe = null;

        if ($request->filled('city_id') && $user?->canImportOrConfigure()) {
            $selectedCity = UserCityAccess::citiesQuery($user)
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
            'showAdminStats' => $user?->isAdmin() ?? false,
            'showCityProbe' => $user?->canImportOrConfigure() ?? false,
        ]);
    }
}
