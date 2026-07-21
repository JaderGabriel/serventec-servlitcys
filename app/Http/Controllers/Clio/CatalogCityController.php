<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Clio\ClioCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogCityController extends Controller
{
    public function create(): View
    {
        $this->authorize('createCatalogCity', ClioCampaign::class);

        return view('clio.cities.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('createCatalogCity', ClioCampaign::class);

        $request->merge([
            'uf' => strtoupper((string) $request->input('uf')),
            'is_active' => true,
        ]);

        if ($request->filled('ibge_municipio')) {
            $ibge = preg_replace('/\D/', '', (string) $request->input('ibge_municipio'));
            $request->merge([
                'ibge_municipio' => ($ibge !== null && strlen($ibge) === 7) ? $ibge : null,
            ]);
        }

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cities', 'name')->where(fn ($q) => $q->where('uf', (string) $request->input('uf'))),
            ],
            'uf' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'ibge_municipio' => [
                'nullable',
                'string',
                'size:7',
                'regex:/^[0-9]{7}$/',
                Rule::unique('cities', 'ibge_municipio'),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_whatsapp' => ['nullable', 'string', 'max:32'],
            'contact_email' => ['nullable', 'string', 'max:255', 'email'],
        ], [
            'name.unique' => __('Já existe uma cidade com este nome neste estado.'),
            'ibge_municipio.unique' => __('Este código IBGE já está associado a outra cidade.'),
        ]);

        $city = City::query()->create([
            ...$data,
            'country' => 'Brasil',
            'db_driver' => City::DRIVER_MYSQL,
            'db_host' => null,
            'db_port' => null,
            'db_database' => null,
            'db_username' => null,
            'db_password' => '',
            'is_active' => true,
        ]);

        return redirect()
            ->route('clio.campaigns.create', ['city_id' => $city->id])
            ->with('success', __('Município ficha leve cadastrado. Crie a campanha Clio.'));
    }
}
