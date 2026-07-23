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

        $linkedCityIds = ClioCampaign::query()
            ->whereNotNull('city_id')
            ->distinct()
            ->pluck('city_id')
            ->all();

        $consultancyBase = City::query()->active()->withDataSetup();
        $hasConsultancySetup = (clone $consultancyBase)->exists();

        $consultancyCities = (clone $consultancyBase)
            ->when($linkedCityIds !== [], fn ($q) => $q->whereNotIn('id', $linkedCityIds))
            ->orderBy('name')
            ->get(['id', 'name', 'uf', 'ibge_municipio', 'clio_drive_url']);

        return view('clio.cities.create', [
            'consultancyCities' => $consultancyCities,
            'consultancyAllLinked' => $hasConsultancySetup && $consultancyCities->isEmpty(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('createCatalogCity', ClioCampaign::class);

        $request->merge([
            'setup_mode' => $request->input('setup_mode', 'catalog'),
        ]);

        $isConsultancy = $request->input('setup_mode') === 'consultancy';

        if ($isConsultancy) {
            $linkedCityIds = ClioCampaign::query()
                ->whereNotNull('city_id')
                ->distinct()
                ->pluck('city_id')
                ->all();

            $cityIdRules = ['required', 'integer', 'exists:cities,id'];
            if ($linkedCityIds !== []) {
                $cityIdRules[] = Rule::notIn($linkedCityIds);
            }

            $data = $request->validate([
                'setup_mode' => ['required', 'in:consultancy'],
                'city_id' => $cityIdRules,
                'clio_drive_url' => ['nullable', 'string', 'max:1024'],
            ], [
                'city_id.required' => __('Selecione um município da consultoria.'),
                'city_id.not_in' => __('Este município já está vinculado ao Clio.'),
            ]);

            $city = City::query()->active()->withDataSetup()->findOrFail((int) $data['city_id']);

            $driveUrl = filled($data['clio_drive_url'] ?? null) ? trim((string) $data['clio_drive_url']) : null;
            if ($driveUrl !== null && $driveUrl !== (string) ($city->clio_drive_url ?? '')) {
                $city->update(['clio_drive_url' => $driveUrl]);
            }

            return redirect()
                ->route('clio.campaigns.create', ['city_id' => $city->id])
                ->with('success', __('Município de consultoria selecionado. Crie a coleta Clio.'));
        }

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
            'setup_mode' => ['required', 'in:catalog'],
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
            'clio_drive_url' => ['nullable', 'string', 'max:1024'],
        ], [
            'name.unique' => __('Já existe uma cidade com este nome neste estado.'),
            'ibge_municipio.unique' => __('Este código IBGE já está associado a outra cidade.'),
        ]);

        $driveUrl = filled($data['clio_drive_url'] ?? null) ? trim((string) $data['clio_drive_url']) : null;

        $city = City::query()->create([
            'name' => $data['name'],
            'uf' => $data['uf'],
            'ibge_municipio' => $data['ibge_municipio'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'country' => 'Brasil',
            'db_driver' => City::DRIVER_MYSQL,
            'db_host' => null,
            'db_port' => null,
            'db_database' => null,
            'db_username' => null,
            'db_password' => '',
            'clio_drive_url' => $driveUrl,
            'is_active' => true,
        ]);

        return redirect()
            ->route('clio.campaigns.create', ['city_id' => $city->id])
            ->with('success', __('Município só coleta cadastrado. Crie a coleta Clio.'));
    }
}
