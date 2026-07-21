<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ClioCampaign::class);

        $year = $request->integer('year') ?: null;
        $defaultYear = (int) config('clio.layout_year_default', (int) date('Y'));

        $years = ClioCampaign::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->all();

        if ($years === []) {
            $years = [$defaultYear];
        }

        $filterYear = $year && in_array($year, $years, true) ? $year : ($years[0] ?? $defaultYear);

        $campaigns = ClioCampaign::query()
            ->with(['city', 'inferences' => fn ($q) => $q->where('code', 'INF-COE')])
            ->withCount([
                'artifacts',
                'schools',
                'findings as findings_error_count' => fn ($q) => $q->where('severity', ClioCampaignFinding::SEVERITY_ERROR),
            ])
            ->where('year', $filterYear)
            ->orderBy('municipality_name')
            ->paginate(40)
            ->withQueryString();

        $comparativo = [
            'year' => $filterYear,
            'total' => $campaigns->total(),
            'analyzed' => ClioCampaign::query()
                ->where('year', $filterYear)
                ->whereIn('status', [
                    ClioCampaign::STATUS_ANALYZED,
                    ClioCampaign::STATUS_CROSS_CHECKED,
                ])
                ->count(),
            'avg_triade' => null,
        ];

        $triades = ClioCampaign::query()
            ->where('year', $filterYear)
            ->with(['inferences' => fn ($q) => $q->where('code', 'INF-COE')])
            ->get()
            ->map(fn (ClioCampaign $c) => $c->triadeCoveragePct())
            ->filter(fn ($v) => $v !== null);

        if ($triades->isNotEmpty()) {
            $comparativo['avg_triade'] = round((float) $triades->avg(), 1);
        }

        return view('clio.campaigns.index', [
            'campaigns' => $campaigns,
            'years' => $years,
            'filterYear' => $filterYear,
            'comparativo' => $comparativo,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ClioCampaign::class);

        $cities = City::query()
            ->forClioCatalog()
            ->orderBy('name')
            ->get(['id', 'name', 'uf', 'ibge_municipio', 'db_host', 'db_database', 'db_username']);

        return view('clio.campaigns.create', [
            'cities' => $cities,
            'defaultYear' => (int) config('clio.layout_year_default', (int) date('Y')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ClioCampaign::class);

        $data = $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $city = City::query()->forClioCatalog()->findOrFail((int) $data['city_id']);

        $profile = $city->hasDataSetup()
            ? ClioCampaign::PROFILE_CONSULTANCY
            : ClioCampaign::PROFILE_ANALYSIS_ONLY;

        $campaign = ClioCampaign::query()->create([
            'city_id' => $city->id,
            'municipality_name' => $city->name,
            'uf' => (string) $city->uf,
            'ibge_municipio' => $city->ibge_municipio,
            'year' => (int) $data['year'],
            'stage' => ClioCampaign::STAGE_1,
            'profile' => $profile,
            'status' => ClioCampaign::STATUS_DRAFT,
            'source' => 'manual_upload',
            'meta' => filled($data['notes'] ?? null) ? ['notes' => $data['notes']] : null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('clio.campaigns.show', $campaign)
            ->with('success', __('Campanha Clio criada. Envie os relatórios da 1ª etapa.'));
    }

    public function show(ClioCampaign $campaign, \App\Services\Clio\Parse\CampaignParseService $parser): View
    {
        $this->authorize('view', $campaign);

        $campaign->load(['city', 'artifacts' => fn ($q) => $q->latest()->limit(50), 'schools']);
        $campaign->loadCount(['artifacts', 'schools']);

        return view('clio.campaigns.show', [
            'campaign' => $campaign,
            'coverage' => $parser->coverage($campaign),
        ]);
    }
}
