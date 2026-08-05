<?php

namespace App\Services\Clio\Home;

use App\Models\Clio\ClioCampaign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Agrupa coletas do mesmo município num único card da home Clio.
 */
final class ClioHomeMunicipalityCards
{
    /**
     * @param  Collection<int, ClioCampaign>  $campaigns
     * @return Collection<int, array{
     *   key: string,
     *   municipality_name: string,
     *   uf: string,
     *   ibge: string,
     *   campaigns: Collection<int, ClioCampaign>,
     *   selected: ClioCampaign,
     *   alpine: array<string, mixed>
     * }>
     */
    public function group(Collection $campaigns): Collection
    {
        return $campaigns
            ->groupBy(fn (ClioCampaign $c): string => $this->groupKey($c))
            ->map(function (Collection $group): array {
                $sorted = $group
                    ->sort(function (ClioCampaign $a, ClioCampaign $b): int {
                        $refA = $a->reference_date?->timestamp ?? 0;
                        $refB = $b->reference_date?->timestamp ?? 0;
                        if ($refA !== $refB) {
                            return $refB <=> $refA;
                        }

                        return ($b->created_at?->timestamp ?? 0) <=> ($a->created_at?->timestamp ?? 0);
                    })
                    ->values();

                /** @var ClioCampaign $selected */
                $selected = $sorted->first();

                return [
                    'key' => $this->groupKey($selected),
                    'municipality_name' => (string) $selected->municipality_name,
                    'uf' => (string) $selected->uf,
                    'ibge' => (string) ($selected->ibge_municipio ?: ''),
                    'campaigns' => $sorted,
                    'selected' => $selected,
                    'alpine' => $this->alpinePayload($sorted, $selected),
                ];
            })
            ->sortBy(fn (array $card): string => mb_strtolower($card['municipality_name']).'|'.$card['uf'], SORT_NATURAL)
            ->values();
    }

    public function groupKey(ClioCampaign $campaign): string
    {
        if ($campaign->city_id) {
            return 'city:'.$campaign->city_id;
        }

        $ibge = preg_replace('/\D+/', '', (string) ($campaign->ibge_municipio ?? '')) ?: '';
        if ($ibge !== '') {
            return 'ibge:'.$ibge;
        }

        return 'name:'.mb_strtolower(trim((string) $campaign->municipality_name)).'|'.mb_strtoupper(trim((string) $campaign->uf));
    }

    /**
     * @param  Collection<int, ClioCampaign>  $campaigns
     * @return array<string, mixed>
     */
    private function alpinePayload(Collection $campaigns, ClioCampaign $selected): array
    {
        $collections = $campaigns->map(function (ClioCampaign $campaign): array {
            $scope = $campaign->schoolScopeStats();
            $triade = $scope['triade_pct'];
            $files = $campaign->fileProcessingSummary();
            $ready = $campaign->hasReportReady();
            $errorCount = (int) ($campaign->findings_error_count ?? 0);
            $warningCount = (int) ($campaign->findings_warning_count ?? 0);

            $refLabel = $campaign->reference_date
                ? $campaign->reference_date->format('d/m/Y')
                : '—';
            $coletaLabel = $campaign->created_at
                ? $campaign->created_at->timezone(config('app.timezone'))->format('d/m/Y')
                : '—';

            return [
                'id' => (string) $campaign->uuid,
                'label' => __('Ref. :ref · Coleta :c', ['ref' => $refLabel, 'c' => $coletaLabel]),
                'ref_label' => $refLabel,
                'coleta_label' => $coletaLabel,
                'status_label' => $campaign->statusLabel(),
                'ready' => $ready,
                'rail_tone' => $this->railTone($campaign, $errorCount),
                'error_count' => $errorCount,
                'warning_count' => $warningCount,
                'triade' => $triade,
                'triade_label' => $triade !== null
                    ? number_format((float) $triade, 1, ',', '.').'%'
                    : '—',
                'triade_width' => $triade !== null ? min(100, max(0, (float) $triade)) : 0,
                'meter_tone' => $this->meterTone($triade),
                'schools_active' => (int) ($scope['active'] ?? 0),
                'schools_other' => (int) ($scope['other'] ?? 0),
                'artifacts_count' => (int) ($campaign->artifacts_count ?? 0),
                'ref_short' => $campaign->reference_date
                    ? $campaign->reference_date->format('d/m')
                    : '—',
                'show_url' => route('clio.campaigns.show', $campaign),
                'report_url' => $campaign->primaryReportUrl(),
                'insights_url' => route('clio.campaigns.insights', $campaign),
                'series_url' => route('clio.campaigns.enrollment-series', $campaign),
                'can_export' => Gate::allows('export', $campaign),
                'export_pdf' => route('clio.campaigns.export.pdf', $campaign),
                'export_gestor' => route('clio.campaigns.export.pdf-gestor', $campaign),
                'export_final' => route('clio.campaigns.export.pdf-final', $campaign),
                'export_mapa' => route('clio.campaigns.export.pdf-mapa-coleta', $campaign),
                'export_xlsx' => route('clio.campaigns.export.xlsx', $campaign),
                'export_xlsx_filtros' => route('clio.campaigns.export.xlsx-filtros', $campaign),
                'analysis_only' => $campaign->isAnalysisOnly(),
                'files' => $files,
            ];
        })->values()->all();

        return [
            'selectedId' => (string) $selected->uuid,
            'collections' => $collections,
            'seriesUrl' => route('clio.campaigns.enrollment-series', $selected),
        ];
    }

    private function railTone(ClioCampaign $campaign, int $errorCount): string
    {
        if ($errorCount > 0) {
            return 'error';
        }
        if ($campaign->hasReportReady()) {
            return 'ready';
        }
        if ($campaign->status === ClioCampaign::STATUS_PARSED) {
            return 'parsed';
        }

        return 'progress';
    }

    private function meterTone(mixed $pct): string
    {
        $value = (float) ($pct ?? 0);
        if ($value >= 80) {
            return 'good';
        }
        if ($value >= 40) {
            return 'mid';
        }

        return 'bad';
    }
}
