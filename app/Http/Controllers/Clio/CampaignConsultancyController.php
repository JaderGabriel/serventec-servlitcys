<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Services\CityDataConnection;
use App\Services\Clio\CrossCheck\IeducarGapAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignConsultancyController extends Controller
{
    public function editLink(ClioCampaign $campaign): View
    {
        $this->authorize('linkConsultancy', $campaign);

        $campaign->load('city');

        return view('clio.campaigns.link-ieducar', [
            'campaign' => $campaign,
            'city' => $campaign->city,
        ]);
    }

    public function storeLink(Request $request, ClioCampaign $campaign, CityDataConnection $cityData): RedirectResponse
    {
        $this->authorize('linkConsultancy', $campaign);

        $city = $campaign->city;
        abort_if($city === null, 404);

        $data = $request->validate([
            'db_driver' => ['required', 'in:mysql,pgsql'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'ieducar_schema' => ['nullable', 'string', 'max:64'],
            'ieducar_app_url' => ['nullable', 'url', 'max:512'],
        ]);

        $driver = $data['db_driver'];
        $update = [
            'db_driver' => $driver,
            'db_host' => $data['db_host'],
            'db_port' => $data['db_port'] ?? ($driver === City::DRIVER_PGSQL ? 5432 : 3306),
            'db_database' => $data['db_database'],
            'db_username' => $data['db_username'],
            'ieducar_schema' => filled($data['ieducar_schema'] ?? null) ? $data['ieducar_schema'] : null,
            'ieducar_app_url' => filled($data['ieducar_app_url'] ?? null) ? trim((string) $data['ieducar_app_url']) : null,
        ];

        if (array_key_exists('db_password', $data) && $data['db_password'] !== null && $data['db_password'] !== '') {
            $update['db_password'] = $data['db_password'];
        }

        $city->update($update);
        $city->refresh();

        $status = $cityData->connectionStatus($city);
        if ($status['status'] === 'error') {
            return redirect()
                ->route('clio.campaigns.link', $campaign)
                ->with('warning', __('Credenciais gravadas, mas o teste de conexão falhou: :m', [
                    'm' => $status['message'] ?? __('erro desconhecido'),
                ]));
        }

        $campaign->update(['profile' => ClioCampaign::PROFILE_CONSULTANCY]);

        return redirect()
            ->route('clio.campaigns.show', $campaign)
            ->with('success', __('i-Educar vinculado. Perfil da campanha: Consultoria. Teste de conexão: :s.', [
                's' => $status['status'],
            ]));
    }

    public function crossCheck(ClioCampaign $campaign, IeducarGapAnalyzer $analyzer): View
    {
        $this->authorize('view', $campaign);

        $campaign->load([
            'city',
            'inferences' => fn ($q) => $q->where('code', 'INF-GAP'),
            'findings' => fn ($q) => $q->where('code', 'like', 'CLIO-GAP-%')->latest('id')->limit(100),
        ]);

        return view('clio.campaigns.cross-check', [
            'campaign' => $campaign,
            'gap' => $campaign->inferences->firstWhere('code', 'INF-GAP'),
            'findings' => $campaign->findings,
            'canRun' => $campaign->city?->hasDataSetup() ?? false,
        ]);
    }

    public function runCrossCheck(ClioCampaign $campaign, IeducarGapAnalyzer $analyzer): RedirectResponse
    {
        $this->authorize('analyze', $campaign);

        $result = $analyzer->analyze($campaign);

        if (! $result['ok']) {
            return redirect()
                ->route('clio.campaigns.cross-check', $campaign)
                ->with('warning', $result['message'] ?? __('Cruzamento falhou.'));
        }

        return redirect()
            ->route('clio.campaigns.cross-check', $campaign)
            ->with('success', __('INF-GAP: :m em ambos · :c só Clio · :i só i-Educar.', [
                'm' => $result['matched'],
                'c' => $result['only_in_clio'],
                'i' => $result['only_in_ieducar'],
            ]));
    }
}
