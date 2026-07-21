<?php

namespace App\Services\Clio\Rx;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\User;
use App\Policies\PlatformFeaturePolicy;

/**
 * Bloco RX (T13): ranking multi-coleta do exercício vigente.
 */
final class ClioRxBlockService
{
    /**
     * @return array{enabled: bool, year: int, campaigns_count: int, rows: list<array<string, mixed>>}|null
     */
    public function forUser(User $user, ?int $year = null): ?array
    {
        if (! app(PlatformFeaturePolicy::class)->viewClio($user)) {
            return null;
        }

        $year ??= (int) config('clio.layout_year_default', (int) date('Y'));

        $campaigns = ClioCampaign::query()
            ->where('year', $year)
            ->with([
                'inferences' => fn ($q) => $q->where('code', 'INF-COE'),
            ])
            ->withCount([
                'schools',
                'findings as findings_error_count' => fn ($q) => $q->where('severity', ClioCampaignFinding::SEVERITY_ERROR),
                'findings as findings_warning_count' => fn ($q) => $q->where('severity', ClioCampaignFinding::SEVERITY_WARNING),
            ])
            ->orderBy('municipality_name')
            ->get();

        $rows = [];
        foreach ($campaigns as $campaign) {
            $triade = $campaign->triadeCoveragePct() ?? 0.0;

            $rows[] = [
                'uuid' => $campaign->uuid,
                'municipality' => $campaign->municipality_name,
                'uf' => $campaign->uf,
                'profile' => $campaign->profile,
                'profile_label' => $campaign->profileLabel(),
                'status' => $campaign->status,
                'status_label' => $campaign->statusLabel(),
                'schools' => (int) $campaign->schools_count,
                'triade_pct' => $triade,
                'errors' => (int) $campaign->findings_error_count,
                'warnings' => (int) $campaign->findings_warning_count,
                'url' => route('clio.campaigns.show', $campaign),
                'analysis_url' => route('clio.campaigns.analysis', $campaign),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $errCmp = ((int) $b['errors']) <=> ((int) $a['errors']);
            if ($errCmp !== 0) {
                return $errCmp;
            }
            $triCmp = ((float) $a['triade_pct']) <=> ((float) $b['triade_pct']);
            if ($triCmp !== 0) {
                return $triCmp;
            }

            return strcasecmp((string) $a['municipality'], (string) $b['municipality']);
        });

        return [
            'enabled' => true,
            'year' => $year,
            'campaigns_count' => count($rows),
            'rows' => $rows,
        ];
    }
}
