<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
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
            ->with([
                'city',
                'acompArtifact',
                'inferences' => fn ($rel) => $rel->where('code', 'INF-COE'),
                'schools' => fn ($rel) => $rel->with([
                    'artifacts' => fn ($artifacts) => $artifacts->select('id', 'school_id', 'kind'),
                ]),
            ])
            ->withCount([
                'artifacts',
                'artifacts as artifacts_ok_count' => fn ($rel) => $rel->whereIn('parse_status', [
                    ClioCampaignArtifact::PARSE_OK,
                    ClioCampaignArtifact::PARSE_WARNING,
                ]),
                'artifacts as artifacts_failed_count' => fn ($rel) => $rel->where(
                    'parse_status',
                    ClioCampaignArtifact::PARSE_FAILED,
                ),
                'artifacts as artifacts_pending_count' => fn ($rel) => $rel->where(
                    'parse_status',
                    ClioCampaignArtifact::PARSE_PENDING,
                ),
                'schools',
                'findings as findings_error_count' => fn ($rel) => $rel->where('severity', ClioCampaignFinding::SEVERITY_ERROR),
                'findings as findings_warning_count' => fn ($rel) => $rel->where('severity', ClioCampaignFinding::SEVERITY_WARNING),
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

        $yearBase = ClioCampaign::query()->where('year', $filterYear);

        $reportReadyCount = (clone $yearBase)
            ->whereIn('status', [
                ClioCampaign::STATUS_ANALYZED,
                ClioCampaign::STATUS_CROSS_CHECKED,
            ])
            ->count();

        $inProgressCount = (clone $yearBase)
            ->whereNotIn('status', [
                ClioCampaign::STATUS_ANALYZED,
                ClioCampaign::STATUS_CROSS_CHECKED,
            ])
            ->count();

        $yearErrors = (int) ClioCampaignFinding::query()
            ->where('severity', ClioCampaignFinding::SEVERITY_ERROR)
            ->whereIn('campaign_id', (clone $yearBase)->select('id'))
            ->count();

        $yearCampaignsForStats = (clone $yearBase)
            ->with(['inferences' => fn ($rel) => $rel->where('code', 'INF-COE')])
            ->withCount('schools')
            ->get();

        $yearSchools = (int) $yearCampaignsForStats->sum('schools_count');

        $triades = $yearCampaignsForStats
            ->map(fn (ClioCampaign $c) => $c->triadeCoveragePct())
            ->filter(fn ($v) => $v !== null);

        return view('clio.home', [
            'campaigns' => $campaigns,
            'years' => $years,
            'filterYear' => $filterYear,
            'search' => $q,
            'reportReadyCount' => $reportReadyCount,
            'inProgressCount' => $inProgressCount,
            'yearErrors' => $yearErrors,
            'yearSchools' => $yearSchools,
            'avgTriade' => $triades->isNotEmpty() ? round((float) $triades->avg(), 1) : null,
        ]);
    }
}
