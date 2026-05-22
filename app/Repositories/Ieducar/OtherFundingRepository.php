<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Services\Funding\MunicipalFundingPublicSnapshotService;
use App\Services\Funding\MunicipalTransferSeriesService;
use App\Services\Funding\ProgramRepasseVsMatriculasService;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaChartQueries;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\QueryException;

/**
 * Relatórios sobre programas complementares ao FUNDEB (PNAE, PNATE, PDDE, etc.).
 */
class OtherFundingRepository
{
    public function __construct(
        private CityDataConnection $cityData,
        private MunicipalFundingPublicSnapshotService $publicSnapshot,
        private MunicipalTransferSeriesService $transferSeries,
        private ProgramRepasseVsMatriculasService $programRepasse,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildReport(?City $city, IeducarFilterState $filters): array
    {
        $yearLabel = $this->yearLabel($filters);
        $empty = [
            'year_label' => $yearLabel,
            'city_name' => $city?->name ?? '',
            'intro' => '',
            'footnote' => '',
            'programs' => [],
            'transport' => null,
            'total_matriculas' => null,
            'funding_pillars' => [],
            'chart_programas' => null,
            'public_municipal' => [],
            'error' => null,
        ];

        if ($city === null || ! $filters->hasYearSelected()) {
            $empty['intro'] = __('Selecione cidade e ano letivo para consultar demais financiamentos.');
            if ($city !== null) {
                $empty['public_municipal'] = $this->publicSnapshot->build($city, $filters);
            }

            return $empty;
        }

        $skeleton = $empty;
        $skeleton['city_name'] = (string) $city->name;

        try {
            $ano = $filters->hasYearSelected() && ! $filters->isAllSchoolYears()
                ? (int) $filters->ano_letivo
                : (int) date('Y');

            return $this->cityData->run($city, function ($db) use ($city, $filters, $yearLabel, $ano) {
                $totalMat = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);
                $programs = $this->buildPrograms($db, $city, $filters);
                $programs = $this->programRepasse->enrichPrograms($db, $city, $filters, $programs, $ano);
                $transport = $this->transportBlock($db, $city, $filters);
                $pillarComplement = array_values(array_filter(
                    DiscrepanciesFundingImpact::fundingPillars(),
                    static fn (array $p): bool => ($p['id'] ?? '') === 'pnae-transporte'
                ));
                $transferSeries = $this->transferSeries->build($city, $ano);

                return [
                    'year_label' => $yearLabel,
                    'city_name' => (string) $city->name,
                    'intro' => __(
                        'Programas federais e complementares de educação (transporte, alimentação, PDDE e correlatos) dependem de matrículas e escolas consistentes no Censo Escolar. Esta aba cruza o cadastro do i-Educar com referências FNDE e com o pilar «Programas complementares» das discrepâncias.'
                    ),
                    'footnote' => __(
                        'Valores de repasse não são calculados aqui. A seção «Consultas públicas» obtém prévias e relatórios por IBGE/ano; links oficiais (FNDE, Simec, Tesouro) estão na aba FUNDEB. Os indicadores de i-Educar mostram cobertura de campos quando existirem na base.'
                    ),
                    'programs' => $programs,
                    'transport' => $transport,
                    'total_matriculas' => $totalMat,
                    'funding_pillars' => $pillarComplement,
                    'chart_programas' => $this->chartProgramCoverage($programs),
                    'public_municipal' => $this->publicSnapshot->build($city, $filters),
                    'transfer_series' => $transferSeries,
                    'error' => null,
                ];
            });
        } catch (QueryException|\Throwable $e) {
            $skeleton['error'] = $e->getMessage();

            return $skeleton;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPrograms($db, City $city, IeducarFilterState $filters): array
    {
        $config = config('ieducar.other_funding.programs', []);
        if (! is_array($config)) {
            return [];
        }

        $mat = IeducarSchema::resolveTable('matricula', $city);
        $programs = [];

        foreach ($config as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (string) ($item['id'] ?? '');
            $columns = is_array($item['matricula_columns'] ?? null) ? $item['matricula_columns'] : [];
            $detected = [];
            $distributions = [];
            $status = 'neutral';
            $kpis = [];

            foreach ($columns as $colName) {
                $col = IeducarColumnInspector::firstExistingColumn($db, $mat, [(string) $colName], $city);
                if ($col === null) {
                    continue;
                }
                $detected[] = $col;
                $dist = $this->distributionForColumn($db, $city, $filters, $mat, $col);
                if ($dist !== null) {
                    $distributions[$col] = $dist;
                }
            }

            if ($detected === []) {
                $status = 'warning';
                $kpis[] = [
                    'label' => __('Campo no i-Educar'),
                    'value' => __('Não detectado'),
                    'tone' => 'amber',
                ];
            } else {
                $filled = 0;
                $total = 0;
                foreach ($distributions as $dist) {
                    foreach ($dist['rows'] ?? [] as $row) {
                        $total += (int) ($row['count'] ?? 0);
                        $val = strtolower(trim((string) ($row['value'] ?? '')));
                        if ($val !== '' && $val !== '0' && $val !== 'null' && $val !== 'não' && $val !== 'nao' && $val !== 'n') {
                            $filled += (int) ($row['count'] ?? 0);
                        }
                    }
                }
                if ($total > 0) {
                    $pct = round(100.0 * $filled / $total, 1);
                    $kpis[] = [
                        'label' => __('Preenchimento indicativo'),
                        'value' => $pct.'%',
                        'tone' => $pct >= 70 ? 'emerald' : ($pct >= 40 ? 'amber' : 'rose'),
                    ];
                    $status = $pct >= 70 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                }
                $kpis[] = [
                    'label' => __('Colunas utilizadas'),
                    'value' => implode(', ', $detected),
                    'tone' => 'indigo',
                ];
            }

            $programs[] = [
                'id' => $id,
                'titulo' => (string) ($item['titulo'] ?? $id),
                'descricao' => (string) ($item['descricao'] ?? ''),
                'fnde_url' => (string) ($item['fnde_url'] ?? ''),
                'status' => $status,
                'kpis' => $kpis,
                'distributions' => $distributions,
                'detected_columns' => $detected,
            ];
        }

        return $programs;
    }

    /**
     * @return ?array{col: string, rows: list<array{value: string, count: int}>}
     */
    private function distributionForColumn($db, City $city, IeducarFilterState $filters, string $mat, string $col): ?array
    {
        try {
            $q = $db->table($mat.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.(string) config('ieducar.columns.matricula.ativo'), $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $rows = $q->selectRaw('m.'.$col.' as tv')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('m.'.$col)
                ->orderByDesc('c')
                ->limit(10)
                ->get();

            $list = [];
            foreach ($rows as $r) {
                $a = (array) $r;
                $list[] = [
                    'value' => (string) ($a['tv'] ?? '—'),
                    'count' => (int) ($a['c'] ?? 0),
                ];
            }

            return ['col' => $col, 'rows' => $list];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return ?array{texto: string, linhas: list<string>, col: ?string}
     */
    private function transportBlock($db, City $city, IeducarFilterState $filters): ?array
    {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $col = IeducarColumnInspector::firstExistingColumn($db, $mat, [
            'transporte_escolar',
            'uso_transporte_escolar',
            'veiculo_transporte_escolar',
            'ref_cod_transporte_escolar',
        ], $city);

        if ($col === null) {
            return [
                'texto' => __('Transporte escolar: coluna não encontrada na matrícula desta base.'),
                'linhas' => [],
                'col' => null,
            ];
        }

        $dist = $this->distributionForColumn($db, $city, $filters, $mat, $col);
        $linhas = [];
        if ($dist !== null) {
            foreach ($dist['rows'] as $row) {
                $linhas[] = ($row['value'] ?? '—').': '.number_format((int) ($row['count'] ?? 0), 0, ',', '.');
            }
        }

        return [
            'texto' => __('Distribuição de matrículas ativas por «:col» (PNATE / transporte).', ['col' => $col]),
            'linhas' => $linhas,
            'col' => $col,
        ];
    }

    private function yearLabel(IeducarFilterState $filters): string
    {
        if (! $filters->hasYearSelected()) {
            return '';
        }
        if ($filters->isAllSchoolYears()) {
            return __('Todos os anos (consolidado no filtro)');
        }

        return __('Ano letivo :ano', ['ano' => $filters->ano_letivo]);
    }

    /**
     * @param  list<array<string, mixed>>  $programs
     * @return ?array<string, mixed>
     */
    private function chartProgramCoverage(array $programs): ?array
    {
        $withData = array_values(array_filter(
            $programs,
            static fn (array $p): bool => ($p['status'] ?? '') !== 'neutral' || count($p['detected_columns'] ?? []) > 0
        ));
        if ($withData === []) {
            return null;
        }

        $labels = [];
        $values = [];
        foreach ($withData as $p) {
            $pct = 0.0;
            foreach ($p['kpis'] ?? [] as $kpi) {
                if (($kpi['label'] ?? '') === __('Preenchimento indicativo')) {
                    $pct = (float) str_replace(['%', ','], ['', '.'], (string) ($kpi['value'] ?? '0'));
                }
            }
            $labels[] = mb_substr((string) ($p['titulo'] ?? ''), 0, 36);
            $values[] = $pct;
        }

        return ChartPayload::barHorizontal(
            __('Cobertura indicativa de campos por programa'),
            __('% preenchido'),
            $labels,
            $values
        );
    }
}
