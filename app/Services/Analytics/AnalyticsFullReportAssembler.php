<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Models\SaebIndicatorPoint;
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
use App\Services\CityDataConnection;
use App\Support\Analytics\AnalyticsReportChartSvg;
use App\Support\Analytics\PdfBrandAssets;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Support\Facades\DB;

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
        private CityDataConnection $cityData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assemble(City $city, IeducarFilterState $filters): array
    {
        $yearLabel = $this->yearLabel($filters);
        $cover = $this->coverBuilder->build($city, $yearLabel, $filters);

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

        $charts = $this->collectCharts($health, $disc, $fundeb, $other, $work, $overview, $performance, $enrollment);

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
            'year_comparison' => $this->yearComparison($city, $filters),
            'municipal_vs_state' => $this->municipalVsState($city, $filters),
            'charts' => $charts,
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
     * @return list<array{ano: string, matriculas: ?int, label: string}>
     */
    private function yearComparison(City $city, IeducarFilterState $filters): array
    {
        if (! $filters->hasYearSelected() || $filters->isAllSchoolYears()) {
            return [];
        }

        $current = (int) $filters->ano_letivo;
        $years = array_values(array_unique([$current, $current - 1, $current - 2]));
        sort($years);
        $rows = [];

        foreach ($years as $year) {
            if ($year < 2000) {
                continue;
            }
            $f = new IeducarFilterState(
                ano_letivo: (string) $year,
                escola_id: $filters->escola_id,
                curso_id: $filters->curso_id,
                turno_id: $filters->turno_id,
            );
            $mat = null;
            try {
                $mat = $this->cityData->run($city, fn ($db) => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $f));
            } catch (\Throwable) {
                $mat = null;
            }
            $ov = $this->overview->summary($city, $f);
            $rows[] = [
                'ano' => (string) $year,
                'matriculas' => $mat ?? (isset($ov['kpis']['matriculas']) ? (int) $ov['kpis']['matriculas'] : null),
                'label' => $year === $current ? __('Ano filtrado') : __('Comparativo'),
            ];
        }

        return $rows;
    }

    /**
     * @return array{available: bool, rows: list<array<string, string>>, note: ?string}
     */
    private function municipalVsState(City $city, IeducarFilterState $filters): array
    {
        $ibge = filled($city->ibge_municipio) ? str_pad(preg_replace('/\D/', '', (string) $city->ibge_municipio), 7, '0', STR_PAD_LEFT) : null;
        if ($ibge === null) {
            return [
                'available' => false,
                'rows' => [],
                'note' => __('Código IBGE do município não configurado na cidade.'),
            ];
        }

        $maxYear = $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
            ? (int) $filters->ano_letivo
            : (int) date('Y');

        $munPoints = SaebIndicatorPoint::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', '<=', $maxYear)
            ->whereIn('disciplina', ['lp', 'mat', 'Língua Portuguesa', 'Matemática'])
            ->orderByDesc('ano')
            ->limit(20)
            ->get();

        $uf = strtoupper(trim((string) ($city->uf ?? '')));
        $stateQuery = SaebIndicatorPoint::query()
            ->where('ano', '<=', $maxYear)
            ->whereNull('city_id')
            ->orderByDesc('ano')
            ->limit(40);
        if ($uf !== '' && DB::connection()->getDriverName() === 'pgsql') {
            $stateQuery->whereRaw('raw_point::text ilike ?', ['%'.$uf.'%']);
        }
        $statePoints = $stateQuery->get();

        $rows = [];
        foreach (['lp' => 'Língua Portuguesa', 'mat' => 'Matemática'] as $disc => $label) {
            $m = $munPoints->first(fn ($p) => in_array((string) $p->disciplina, [$disc, $label], true));
            $s = $statePoints->first(fn ($p) => in_array((string) $p->disciplina, [$disc, $label], true)
                && (str_contains(strtolower((string) json_encode($p->raw_point)), 'uf')
                    || str_contains(strtolower((string) json_encode($p->raw_point)), 'estado')));
            if ($m === null && $s === null) {
                continue;
            }
            $rows[] = [
                'disciplina' => $label,
                'ano_municipio' => $m !== null ? (string) $m->ano : '—',
                'valor_municipio' => $m !== null && $m->valor !== null ? number_format((float) $m->valor, 1, ',', '.') : '—',
                'ano_estado' => $s !== null ? (string) $s->ano : '—',
                'valor_estado' => $s !== null && $s->valor !== null ? number_format((float) $s->valor, 1, ',', '.') : '—',
            ];
        }

        return [
            'available' => $rows !== [],
            'rows' => $rows,
            'note' => $rows === []
                ? __('Importe séries SAEB municipais e estaduais em Admin → Sincronizações → Pedagógicas para preencher este quadro.')
                : __('Valores da tabela saeb_indicator_points (rede municipal × referência estadual quando disponível na importação).'),
        ];
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
