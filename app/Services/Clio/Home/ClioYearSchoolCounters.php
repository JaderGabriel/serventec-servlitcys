<?php

namespace App\Services\Clio\Home;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use Illuminate\Support\Collection;

/**
 * Contadores de escolas no exercício Clio — só arquivo geral, INEP único entre coletas.
 */
final class ClioYearSchoolCounters
{
    /**
     * Escolas ativas (arquivo geral + aptas) distintas por INEP no exercício.
     * Várias coletas do mesmo município não somam a mesma escola duas vezes.
     *
     * @param  Collection<int, ClioCampaign>|null  $campaignsWithSchools
     */
    public function uniqueActiveSchoolsForYear(int $year, ?Collection $campaignsWithSchools = null): int
    {
        if ($campaignsWithSchools !== null) {
            return $this->countUniqueEligible($campaignsWithSchools);
        }

        $campaignIds = ClioCampaign::query()
            ->where('year', $year)
            ->pluck('id');

        if ($campaignIds->isEmpty()) {
            return 0;
        }

        $schools = ClioCampaignSchool::query()
            ->whereIn('campaign_id', $campaignIds)
            ->get(['inep_code', 'functioning_status', 'dependency', 'meta']);

        return $this->countUniqueEligibleSchools($schools);
    }

    /**
     * @param  Collection<int, ClioCampaign>  $campaigns
     */
    private function countUniqueEligible(Collection $campaigns): int
    {
        $seen = [];

        foreach ($campaigns as $campaign) {
            if (! $campaign->relationLoaded('schools')) {
                continue;
            }

            foreach ($campaign->schools as $school) {
                $inep = preg_replace('/\D+/', '', (string) $school->inep_code) ?: '';
                if ($inep === '' || isset($seen[$inep])) {
                    continue;
                }

                $meta = is_array($school->meta) ? $school->meta : [];
                if (! CampaignAnalysisPresenter::isOperationallyEligible(
                    $school->functioning_status,
                    $school->dependency,
                    $meta,
                )) {
                    continue;
                }

                $seen[$inep] = true;
            }
        }

        return count($seen);
    }

    /**
     * @param  Collection<int, ClioCampaignSchool>  $schools
     */
    private function countUniqueEligibleSchools(Collection $schools): int
    {
        $seen = [];

        foreach ($schools as $school) {
            $inep = preg_replace('/\D+/', '', (string) $school->inep_code) ?: '';
            if ($inep === '' || isset($seen[$inep])) {
                continue;
            }

            $meta = is_array($school->meta) ? $school->meta : [];
            if (! CampaignAnalysisPresenter::isOperationallyEligible(
                $school->functioning_status,
                $school->dependency,
                $meta,
            )) {
                continue;
            }

            $seen[$inep] = true;
        }

        return count($seen);
    }
}
