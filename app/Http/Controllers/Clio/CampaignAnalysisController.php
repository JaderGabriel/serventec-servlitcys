<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignAnalysisController extends Controller
{
    public function show(ClioCampaign $campaign, CampaignParseService $parser): View
    {
        $this->authorize('view', $campaign);

        $campaign->load([
            'inferences',
            'findings' => fn ($q) => $q->with('school')->latest('id')->limit(100),
            'schools',
        ]);
        $campaign->loadCount(['artifacts', 'schools', 'findings']);

        $coverage = $parser->coverage($campaign);
        $inferences = $campaign->inferences->keyBy('code');

        return view('clio.campaigns.analysis', [
            'campaign' => $campaign,
            'coverage' => $coverage,
            'inferences' => $inferences,
            'findings' => $campaign->findings,
        ]);
    }

    public function run(Request $request, ClioCampaign $campaign, CampaignAnalyzer $analyzer): RedirectResponse
    {
        $this->authorize('analyze', $campaign);

        $result = $analyzer->analyze($campaign);

        return redirect()
            ->route('clio.campaigns.analysis', $campaign)
            ->with('success', __('Análise Clio: :i inferências · :f achados.', [
                'i' => $result['inferences'],
                'f' => $result['findings'],
            ]));
    }

    public function school(ClioCampaign $campaign, string $inep, CampaignParseService $parser): View
    {
        $this->authorize('view', $campaign);

        $school = ClioCampaignSchool::query()
            ->where('campaign_id', $campaign->id)
            ->where('inep_code', $inep)
            ->with(['artifacts', 'campaign'])
            ->firstOrFail();

        $findings = $campaign->findings()
            ->where('school_id', $school->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $coverageRow = collect($parser->coverage($campaign)['schools'] ?? [])
            ->firstWhere('inep', $inep);

        return view('clio.campaigns.school', [
            'campaign' => $campaign,
            'school' => $school,
            'findings' => $findings,
            'coverageRow' => $coverageRow,
        ]);
    }
}
