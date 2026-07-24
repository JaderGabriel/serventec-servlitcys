<?php

namespace App\Http\Controllers\Clio;

use App\Http\Controllers\Controller;
use App\Models\Bi\BiClioCampaign;
use App\Models\Bi\BiClioInsight;
use App\Models\Clio\ClioCampaign;
use App\Services\Clio\Bi\ClioBiRefreshService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CampaignInsightsController extends Controller
{
    public function show(ClioCampaign $campaign): View
    {
        $this->authorize('view', $campaign);

        $bi = BiClioCampaign::query()->where('campaign_id', $campaign->id)->first();
        $insights = BiClioInsight::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return view('clio.campaigns.insights', [
            'campaign' => $campaign->load('city'),
            'bi' => $bi,
            'insights' => $insights,
            'analyzed' => in_array($campaign->status, [
                ClioCampaign::STATUS_ANALYZED,
                ClioCampaign::STATUS_CROSS_CHECKED,
            ], true),
        ]);
    }

    public function refresh(ClioCampaign $campaign, ClioBiRefreshService $bi): RedirectResponse
    {
        $this->authorize('analyze', $campaign);

        if (! in_array($campaign->status, [
            ClioCampaign::STATUS_ANALYZED,
            ClioCampaign::STATUS_CROSS_CHECKED,
        ], true)) {
            return back()->with('error', __('Analise a coleta antes de actualizar o dataset BI.'));
        }

        $bi->refreshCampaign($campaign);

        return back()->with('success', __('Dataset BI e insights actualizados.'));
    }
}
