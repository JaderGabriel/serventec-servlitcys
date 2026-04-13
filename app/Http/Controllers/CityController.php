<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Models\City;
use App\Services\CityDataConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CityController extends Controller
{
    /**
     * Estado da conexão com o banco da cidade (JSON para o indicador na listagem).
     */
    public function dbStatus(City $city, CityDataConnection $cityData): JsonResponse
    {
        $this->authorize('viewAny', City::class);

        return response()->json($cityData->connectionStatus($city));
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', City::class);

        $query = City::query()->orderBy('name');

        $search = $request->string('q')->trim()->toString();
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$like]);
        }

        $uf = $request->string('uf')->trim()->toString();
        $ufUpper = $uf !== '' ? strtoupper($uf) : '';
        if ($ufUpper !== '') {
            $query->where('uf', $ufUpper);
        }

        $dbDriver = $request->string('db_driver')->trim()->toString();
        if (in_array($dbDriver, [City::DRIVER_MYSQL, City::DRIVER_PGSQL], true)) {
            $query->where('db_driver', $dbDriver);
        } else {
            $dbDriver = '';
        }

        $cities = $query->paginate(15)->withQueryString();

        $ufs = City::query()->orderBy('uf')->distinct()->pluck('uf');
        $dbDrivers = City::query()->orderBy('db_driver')->distinct()->pluck('db_driver');

        return view('cities.index', [
            'cities' => $cities,
            'ufs' => $ufs,
            'dbDrivers' => $dbDrivers,
            'filters' => [
                'q' => $search,
                'uf' => $ufUpper,
                'db_driver' => $dbDriver,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', City::class);

        return view('cities.create');
    }

    public function store(StoreCityRequest $request): RedirectResponse
    {
        $this->authorize('create', City::class);

        $data = $request->validated();
        $data['country'] = $data['country'] ?? 'Brasil';
        if (! array_key_exists('db_password', $data) || $data['db_password'] === null) {
            $data['db_password'] = '';
        }

        City::create($data);

        return redirect()->route('cities.index')->with('success', __('Cidade cadastrada com sucesso.'));
    }

    public function edit(City $city): View
    {
        $this->authorize('update', $city);

        return view('cities.edit', ['city' => $city]);
    }

    public function update(UpdateCityRequest $request, City $city): RedirectResponse
    {
        $this->authorize('update', $city);

        $data = $request->validated();
        $data['country'] = $data['country'] ?? 'Brasil';
        if (empty($data['db_password'])) {
            unset($data['db_password']);
        }

        $city->update($data);

        return redirect()->route('cities.index')->with('success', __('Cidade atualizada com sucesso.'));
    }

    public function destroy(City $city): RedirectResponse
    {
        $this->authorize('delete', $city);

        $city->delete();

        return redirect()->route('cities.index')->with('success', __('Cidade removida.'));
    }
}
