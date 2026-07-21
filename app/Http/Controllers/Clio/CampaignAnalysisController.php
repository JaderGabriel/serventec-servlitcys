<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\CampaignAnalyzer;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignAnalysisController extends Controller
{
    public function show(
        ClioCampaign $campaign,
        CampaignParseService $parser,
        CampaignAnalysisPresenter $presenter,
    ): View {
        $this->authorize('view', $campaign);

        $campaign->load([
            'inferences',
            'findings' => fn ($q) => $q->with('school')->latest('id')->limit(200),
            'schools',
        ]);
        $campaign->loadCount(['artifacts', 'schools', 'findings']);

        $coverage = $parser->coverage($campaign);
        $inferences = $campaign->inferences->keyBy('code');
        $dashboard = $presenter->present($campaign, $coverage, $inferences, $campaign->findings);

        return view('clio.campaigns.analysis', [
            'campaign' => $campaign,
            'coverage' => $coverage,
            'inferences' => $inferences,
            'findings' => $campaign->findings,
            'dashboard' => $dashboard,
        ]);
    }

    public function run(Request $request, ClioCampaign $campaign, CampaignAnalyzer $analyzer): RedirectResponse
    {
        $this->authorize('analyze', $campaign);

        $result = $analyzer->analyze($campaign);

        return redirect()
            ->route('clio.campaigns.analysis', $campaign)
            ->with('success', __('Análise concluída: :i indicadores · :f apontamentos.', [
                'i' => $result['inferences'],
                'f' => $result['findings'],
            ]));
    }

    public function school(
        ClioCampaign $campaign,
        string $inep,
        CampaignParseService $parser,
        CampaignAnalysisPresenter $presenter,
    ): View {
        $this->authorize('view', $campaign);

        $school = ClioCampaignSchool::query()
            ->where('campaign_id', $campaign->id)
            ->where('inep_code', $inep)
            ->with(['artifacts', 'campaign'])
            ->firstOrFail();

        $findings = $campaign->findings()
            ->where('school_id', $school->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->sortBy(fn ($f) => match ($f->severity) {
                'error' => 0,
                'warning' => 1,
                default => 2,
            })
            ->values();

        $coverage = $parser->coverage($campaign);
        $coverageRow = collect($coverage['schools'] ?? [])->firstWhere('inep', $inep);
        $dashboard = $presenter->presentSchool($school, is_array($coverageRow) ? $coverageRow : null, $findings);

        $siblings = $campaign->schools()
            ->orderBy('name')
            ->get(['id', 'inep_code', 'name']);
        $index = $siblings->search(fn ($s) => $s->id === $school->id);
        $prev = $index !== false && $index > 0 ? $siblings[$index - 1] : null;
        $next = $index !== false && $index < $siblings->count() - 1 ? $siblings[$index + 1] : null;

        return view('clio.campaigns.school', [
            'campaign' => $campaign,
            'school' => $school,
            'findings' => $findings,
            'coverageRow' => $coverageRow,
            'dashboard' => $dashboard,
            'prevSchool' => $prev,
            'nextSchool' => $next,
        ]);
    }
}
