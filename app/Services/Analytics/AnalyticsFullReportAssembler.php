<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Repositories\Ieducar\AttendanceRepository;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Repositories\Ieducar\EnrollmentRepository;
use App\Repositories\Ieducar\FundebRepository;
use App\Repositories\Ieducar\InclusionRepository;
use App\Repositories\Ieducar\MunicipalityHealthRepository;
use App\Repositories\Ieducar\NetworkRepository;
use App\Repositories\Ieducar\OtherFundingRepository;
use App\Repositories\Ieducar\OverviewRepository;
use App\Repositories\Ieducar\PerformanceRepository;
use App\Repositories\Ieducar\WorkDoneRepository;
use App\Support\Analytics\AnalyticsReportChartSvg;
use App\Support\Analytics\AnalyticsReportComparatives;
use App\Support\Analytics\AnalyticsReportCoverPresentation;
use App\Support\Analytics\PdfBrandAssets;
use App\Support\Dashboard\IeducarFilterState;

final class AnalyticsFullReportAssembler
{
    public function __construct(
        private MunicipalityHealthRepository $health,
        private OverviewRepository $overview,
        private DiscrepanciesRepository $discrepancies,
        private FundebRepository $fundeb,
        private OtherFundingRepository $otherFunding,
        private WorkDoneRepository $workDone,
        private EnrollmentRepository $enrollment,
        private PerformanceRepository $performance,
        private InclusionRepository $inclusion,
        private NetworkRepository $network,
        private AttendanceRepository $attendance,
        private AnalyticsReportCoverBuilder $coverBuilder,
        private AnalyticsReportComparatives $comparatives,
        private AnalyticsReportSchoolMapBuilder $schoolMapBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assemble(City $city, IeducarFilterState $filters): array
    {
        $yearLabel = $this->yearLabel($filters);

        $overview = $this->overview->summary($city, $filters);
        $enrollment = $this->enrollment->sample($city, $filters);
        $performance = $this->performance->snapshot($city, $filters);
        $attendance = $this->attendance->snapshot($city, $filters);
        $inclusion = $this->inclusion->snapshot($city, $filters);
        $network = $this->network->snapshot($city, $filters);
        $disc = $this->discrepancies->snapshot($city, $filters);
        $fundeb = $this->fundeb->buildReport($city, $filters, $overview, $enrollment, $performance, $attendance, $inclusion, $network, $disc);
        $other = $this->otherFunding->buildReport($city, $filters);
        $work = $this->workDone->buildReport($city, $filters);
        $health = $this->health->snapshot($city, $filters);

        $cover = AnalyticsReportCoverPresentation::enrich(
            $this->coverBuilder->build($city, $yearLabel, $filters),
            $city,
            $filters,
            $health,
            $overview,
            $disc,
        );

        $charts = $this->collectCharts($health, $disc, $fundeb, $other, $work, $overview, $performance, $enrollment);
        $comparativeData = $this->comparatives->build($city, $filters, $fundeb, $health);
        $schoolMap = $this->schoolMapBuilder->build($city, $filters);

        return [
            'generated_at' => now()->format('d/m/Y H:i'),
            'cover' => $cover,
            'year_label' => $yearLabel,
            'city' => [
                'name' => $city->name,
                'uf' => $city->uf,
                'ibge' => $cover['ibge'],
            ],
            'health' => $health,
            'overview' => $overview,
            'discrepancies' => $disc,
            'fundeb' => $fundeb,
            'other_funding' => $other,
            'work_done' => $work,
            'performance' => $performance,
            'inclusion' => $inclusion,
            'network' => $network,
            'enrollment' => $enrollment,
            'attendance' => $attendance,
            'comparatives' => $comparativeData,
            'year_comparison' => $comparativeData['year_comparison_enriched'],
            'municipal_vs_state' => $comparativeData['municipal_vs_state_enriched'],
            'charts' => $charts,
            'school_units_map' => $schoolMap,
            'brand' => PdfBrandAssets::enrich(config('analytics.pdf_report.brand', [])),
            'colors' => config('analytics.pdf_report.colors', []),
        ];
    }

    /**
     * @return list<array{title: string, section: string, svg: string}>
     */
    private function collectCharts(
        array $health,
        array $disc,
        array $fundeb,
        array $other,
        array $work,
        array $overview,
        array $performance,
        array $enrollment,
    ): array {
        $candidates = [
            ['Serventec', $health['chart_pendencias'] ?? null],
            ['Discrepâncias', $disc['chart_financeiro'] ?? $disc['chart_resumo'] ?? null],
            ['FUNDEB', data_get($fundeb, 'resource_projection.chart')],
            ['Financiamentos', $other['chart_programas'] ?? null],
            ['Censo', $work['chart_censo'] ?? $work['chart_periods'] ?? null],
            ['Visão geral', self::firstChart($overview['charts'] ?? null)],
            ['Desempenho SAEB', self::firstChart($performance['saeb_series']['charts'] ?? null)],
            ['Matrículas', self::firstChart($enrollment['charts'] ?? null)],
        ];

        $out = [];
        foreach ($candidates as [$section, $chart]) {
            if (! is_array($chart)) {
                continue;
            }
            $svg = AnalyticsReportChartSvg::render($chart);
            if ($svg === null) {
                continue;
            }
            $out[] = [
                'section' => $section,
                'title' => (string) ($chart['title'] ?? $section),
                'svg' => $svg,
            ];
            if (count($out) >= 12) {
                break;
            }
        }

        $saebExtra = is_array($performance['saeb_series']['extra_charts'] ?? null)
            ? $performance['saeb_series']['extra_charts']
            : [];
        foreach (array_slice($saebExtra, 0, 2) as $chart) {
            $svg = AnalyticsReportChartSvg::render(is_array($chart) ? $chart : null);
            if ($svg !== null) {
                $out[] = [
                    'section' => 'Desempenho SAEB',
                    'title' => (string) ($chart['title'] ?? 'SAEB'),
                    'svg' => $svg,
                ];
            }
        }

        return $out;
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function firstChart(mixed $charts): ?array
    {
        if (! is_array($charts) || $charts === []) {
            return null;
        }

        $first = reset($charts);

        return is_array($first) ? $first : null;
    }

    private function yearLabel(IeducarFilterState $filters): string
    {
        if (! $filters->hasYearSelected()) {
            return '';
        }
        if ($filters->isAllSchoolYears()) {
            return __('Todos os anos letivos');
        }

        return __('Ano letivo :ano', ['ano' => $filters->ano_letivo]);
    }
}
