<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Services\CityDataConnection;
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

    public function store(Request $request, CityDataConnection $cityData): RedirectResponse
    {
        $this->authorize('createCatalogCity', ClioCampaign::class);

        $request->merge([
            'uf' => strtoupper((string) $request->input('uf')),
            'is_active' => true,
            'setup_mode' => $request->input('setup_mode', 'catalog'),
        ]);

        if ($request->filled('ibge_municipio')) {
            $ibge = preg_replace('/\D/', '', (string) $request->input('ibge_municipio'));
            $request->merge([
                'ibge_municipio' => ($ibge !== null && strlen($ibge) === 7) ? $ibge : null,
            ]);
        }

        $isConsultancy = $request->input('setup_mode') === 'consultancy';

        $data = $request->validate([
            'setup_mode' => ['required', 'in:catalog,consultancy'],
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
            'db_driver' => [Rule::requiredIf($isConsultancy), 'nullable', 'in:mysql,pgsql'],
            'db_host' => [Rule::requiredIf($isConsultancy), 'nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => [Rule::requiredIf($isConsultancy), 'nullable', 'string', 'max:255'],
            'db_username' => [Rule::requiredIf($isConsultancy), 'nullable', 'string', 'max:255'],
            'db_password' => [Rule::requiredIf($isConsultancy), 'nullable', 'string', 'max:255'],
            'ieducar_schema' => ['nullable', 'string', 'max:64'],
            'ieducar_app_url' => ['nullable', 'url', 'max:512'],
            'clio_drive_url' => ['nullable', 'string', 'max:1024'],
        ], [
            'name.unique' => __('Já existe uma cidade com este nome neste estado.'),
            'ibge_municipio.unique' => __('Este código IBGE já está associado a outra cidade.'),
            'db_password.required' => __('Informe a senha da base i-Educar.'),
        ]);

        $driveUrl = filled($data['clio_drive_url'] ?? null) ? trim((string) $data['clio_drive_url']) : null;

        if ($isConsultancy) {
            $driver = $data['db_driver'];
            $city = City::query()->create([
                'name' => $data['name'],
                'uf' => $data['uf'],
                'ibge_municipio' => $data['ibge_municipio'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'country' => 'Brasil',
                'db_driver' => $driver,
                'db_host' => $data['db_host'],
                'db_port' => $data['db_port'] ?? ($driver === City::DRIVER_PGSQL ? 5432 : 3306),
                'db_database' => $data['db_database'],
                'db_username' => $data['db_username'],
                'db_password' => $data['db_password'],
                'ieducar_schema' => filled($data['ieducar_schema'] ?? null) ? $data['ieducar_schema'] : null,
                'ieducar_app_url' => filled($data['ieducar_app_url'] ?? null) ? trim((string) $data['ieducar_app_url']) : null,
                'clio_drive_url' => $driveUrl,
                'is_active' => true,
            ]);

            $status = $cityData->connectionStatus($city);
            $redirect = redirect()->route('clio.campaigns.create', ['city_id' => $city->id]);

            if ($status['status'] === 'error') {
                return $redirect->with('warning', __('Município de consultoria cadastrado, mas o teste de conexão falhou: :m. Ajuste as credenciais depois.', [
                    'm' => $status['message'] ?? __('erro desconhecido'),
                ]));
            }

            return $redirect->with('success', __('Município de consultoria cadastrado (conexão :s). Crie a coleta Clio.', [
                's' => $status['status'],
            ]));
        }

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
