<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', ClioCampaign::class);

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

        $requestedYear = $request->integer('year') ?: null;
        $filterYear = $requestedYear && in_array($requestedYear, $years, true)
            ? $requestedYear
            : ($years[0] ?? $defaultYear);

        $q = trim((string) $request->input('q', ''));

        $campaignsQuery = ClioCampaign::query()
            ->with(['city', 'inferences' => fn ($rel) => $rel->where('code', 'INF-COE')])
            ->withCount([
                'artifacts',
                'schools',
                'findings as findings_error_count' => fn ($rel) => $rel->where('severity', ClioCampaignFinding::SEVERITY_ERROR),
            ])
            ->where('year', $filterYear)
            ->orderBy('municipality_name');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $campaignsQuery->where(function ($builder) use ($like, $q) {
                $builder->where('municipality_name', 'like', $like)
                    ->orWhere('uf', 'like', $like)
                    ->orWhere('ibge_municipio', 'like', $like);
                if (ctype_digit($q)) {
                    $builder->orWhere('city_id', (int) $q);
                }
            });
        }

        $campaigns = $campaignsQuery
            ->paginate(24)
            ->withQueryString();

        $reportReadyCount = ClioCampaign::query()
            ->where('year', $filterYear)
            ->whereIn('status', [
                ClioCampaign::STATUS_ANALYZED,
                ClioCampaign::STATUS_CROSS_CHECKED,
            ])
            ->count();

        $triades = ClioCampaign::query()
            ->where('year', $filterYear)
            ->with(['inferences' => fn ($rel) => $rel->where('code', 'INF-COE')])
            ->get()
            ->map(fn (ClioCampaign $c) => $c->triadeCoveragePct())
            ->filter(fn ($v) => $v !== null);

        $campaignCityIds = ClioCampaign::query()
            ->where('year', $filterYear)
            ->whereNotNull('city_id')
            ->pluck('city_id')
            ->all();

        $citiesWithoutCampaign = City::query()
            ->forClioCatalog()
            ->when($campaignCityIds !== [], fn ($builder) => $builder->whereNotIn('id', $campaignCityIds))
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'uf', 'ibge_municipio', 'db_host', 'db_database', 'db_username']);

        return view('clio.home', [
            'campaigns' => $campaigns,
            'years' => $years,
            'filterYear' => $filterYear,
            'search' => $q,
            'reportReadyCount' => $reportReadyCount,
            'avgTriade' => $triades->isNotEmpty() ? round((float) $triades->avg(), 1) : null,
            'citiesWithoutCampaign' => $citiesWithoutCampaign,
            'citiesWithoutCampaignTotal' => City::query()
                ->forClioCatalog()
                ->when($campaignCityIds !== [], fn ($builder) => $builder->whereNotIn('id', $campaignCityIds))
                ->count(),
        ]);
    }
}
