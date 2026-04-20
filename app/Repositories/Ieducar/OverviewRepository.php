<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\EscolaSubstatusResolver;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\InclusionDashboardQueries;
use App\Support\Ieducar\MatriculaChartQueries;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

class OverviewRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   kpis: ?array{escolas: ?int, turmas: ?int, matriculas: ?int},
     *   kpi_details: ?array{
     *     escolas?: ?array{
     *       ativas: ?int,
     *       inativas: ?int,
     *       por_substatus?: ?list<array{label: string, total: int}>
     *     },
     *     turmas_por_curso?: ?list<array{curso: string, turmas: int}>
     *   },
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>,
     *   filter_note: ?string,
     *   error: ?string
     * }
     */
    public function summary(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['kpis' => null, 'kpi_details' => null, 'charts' => [], 'filter_note' => null, 'error' => null];
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $kpis = [
                    'escolas' => $this->countEscolas($db, $city, $filters),
                    'turmas' => $this->countTurmas($db, $city, $filters),
                    'matriculas' => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters),
                ];

                $escolasKpi = $this->countEscolasAtivasInativas($db, $city, $filters);
                if ($escolasKpi !== null) {
                    $porSub = $this->escolasPorSubstatus($db, $city, $filters);
                    if ($porSub !== null) {
                        $escolasKpi['por_substatus'] = $porSub;
                    }
                }
                $kpiDetails = [
                    'escolas' => $escolasKpi,
                    'turmas_por_curso' => $this->turmasPorCurso($db, $city, $filters),
                ];

                $charts = [];

                try {
                    $neeOverview = InclusionDashboardQueries::chartNeeResumoVisaoGeral($db, $city, $filters);
                    if ($neeOverview !== null) {
                        $charts[] = $neeOverview;
                    }
                } catch (QueryException|\Throwable) {
                }

                try {
                    $redeOverview = MatriculaChartQueries::chartRedeOfertaResumoVisaoGeral($db, $city, $filters);
                    if ($redeOverview !== null) {
                        $charts[] = $redeOverview;
                    }
                } catch (QueryException|\Throwable) {
                }

                if ($kpis['escolas'] !== null || $kpis['turmas'] !== null || $kpis['matriculas'] !== null) {
                    $charts[] = ChartPayload::bar(
                        __('Totais (visão geral)'),
                        __('Quantidade'),
                        [__('Escolas'), __('Turmas'), __('Matrículas')],
                        [
                            (float) ($kpis['escolas'] ?? 0),
                            (float) ($kpis['turmas'] ?? 0),
                            (float) ($kpis['matriculas'] ?? 0),
                        ]
                    );
                }

                try {
                    $evo = MatriculaChartQueries::chartEvolucaoMatriculasPorAno($db, $city, $filters);
                    if ($evo !== null) {
                        if ($charts === []) {
                            $charts[] = $evo;
                        } else {
                            array_splice($charts, 1, 0, [$evo]);
                        }
                    }
                } catch (QueryException) {
                }

                foreach ([
                    fn () => MatriculaChartQueries::matriculasPorCursoTop($db, $city, $filters),
                    fn () => MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters),
                ] as $fn) {
                    try {
                        $c = $fn();
                        if ($c !== null) {
                            $charts[] = $c;
                        }
                    } catch (QueryException) {
                        // Ignorar gráficos opcionais quando a base não tiver as tabelas esperadas.
                    }
                }

                try {
                    $porTurno = MatriculaChartQueries::matriculasPorTurno($db, $city, $filters);
                    if ($porTurno !== null) {
                        $charts[] = $porTurno;
                    }
                } catch (QueryException) {
                }

                $note = $this->filterNote($filters);

                return [
                    'kpis' => $kpis,
                    'kpi_details' => $kpiDetails,
                    'charts' => $charts,
                    'filter_note' => $note,
                    'error' => null,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'kpis' => null,
                'kpi_details' => null,
                'charts' => [],
                'filter_note' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function filterNote(IeducarFilterState $filters): ?string
    {
        if (! $filters->hasYearSelected()) {
            return null;
        }

        if ($filters->escola_id !== null || $filters->curso_id !== null || $filters->turno_id !== null) {
            return __('Os totais acima aplicam também escola, tipo/segmento e turno quando existirem na turma.');
        }

        return null;
    }

    private function countEscolas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $table = IeducarSchema::resolveTable('escola', $city);
            $id = (string) config('ieducar.columns.escola.id');
            $q = $db->table($table);
            if ($filters->escola_id !== null) {
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $id, $filters->escola_id);
            }

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return ?array{ativas: ?int, inativas: ?int}
     */
    private function countEscolasAtivasInativas(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $table = IeducarSchema::resolveTable('escola', $city);
            $id = (string) config('ieducar.columns.escola.id');
            $activeCol = (string) config('ieducar.columns.escola.active', 'ativo');
            if ($activeCol === '') {
                return null;
            }

            $q = $db->table($table)->select([$activeCol]);
            if ($filters->escola_id !== null) {
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $id, $filters->escola_id);
            }

            $rows = $q->get();
            $ativas = 0;
            $inativas = 0;
            foreach ($rows as $r) {
                $v = $r->{$activeCol} ?? null;
                $b = $this->normalizeBool($v);
                if ($b === true) {
                    $ativas++;
                } elseif ($b === false) {
                    $inativas++;
                }
            }

            return ['ativas' => $ativas, 'inativas' => $inativas];
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Contagens por rótulo do catálogo de situação de funcionamento (substatus), quando a base o expõe.
     *
     * @return ?list<array{label: string, total: int}>
     */
    private function escolasPorSubstatus(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $spec = EscolaSubstatusResolver::resolveJoinSpec($db, $city);
        if ($spec === null) {
            return null;
        }

        try {
            $table = IeducarSchema::resolveTable('escola', $city);
            $id = (string) config('ieducar.columns.escola.id');
            $eAlias = 'e';
            $sAlias = 'ssub';
            $q = $db->table($table.' as '.$eAlias);
            EscolaSubstatusResolver::applyLeftJoinCatalog($q, $db, $eAlias, $sAlias, $spec);
            if ($filters->escola_id !== null) {
                MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, $eAlias, $id, $filters->escola_id);
            }

            $expr = EscolaSubstatusResolver::substatusLabelSql($db, $sAlias, $spec);
            $rows = $q->selectRaw($expr.' as sublabel')
                ->selectRaw('COUNT(*) as c')
                ->groupByRaw($expr)
                ->orderByDesc('c')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'label' => (string) ($row->sublabel ?? ''),
                    'total' => (int) ($row->c ?? 0),
                ];
            }

            return $out;
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    private function countTurmas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $table = IeducarSchema::resolveTable('turma', $city);
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);

            $q = $db->table($table);
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $tc['year'] !== '') {
                $q->where($tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, '', $tc['turno'], $filters->turno_id);

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return ?list<array{curso: string, turmas: int}>
     */
    private function turmasPorCurso(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $turmaTable = IeducarSchema::resolveTable('turma', $city);
            $cursoTable = IeducarSchema::resolveTable('curso', $city);
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['curso'] === '') {
                return null;
            }

            $cursoId = (string) config('ieducar.columns.curso.id');
            $cursoName = (string) config('ieducar.columns.curso.name');
            if ($cursoId === '' || $cursoName === '') {
                return null;
            }

            $q = $db->table($turmaTable.' as t')
                ->leftJoin($cursoTable.' as c', function ($join) use ($db, $tc, $cursoId) {
                    $g = $db->getQueryGrammar();
                    $lhs = $g->wrap('t').'.'.$g->wrap($tc['curso']);
                    $rhs = $g->wrap('c').'.'.$g->wrap($cursoId);
                    if ($db->getDriverName() === 'pgsql') {
                        $join->whereRaw('('.$lhs.')::text = ('.$rhs.')::text');
                    } else {
                        $join->whereRaw('CAST('.$lhs.' AS UNSIGNED) = CAST('.$rhs.' AS UNSIGNED)');
                    }
                });

            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $tc['year'] !== '') {
                $q->where('t.'.$tc['year'], $yearVal);
            }
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't.', $tc['escola'], $filters->escola_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't.', $tc['curso'], $filters->curso_id);
            MatriculaTurmaJoin::whereTurmaColumnEqualsFilterId($q, $db, 't.', $tc['turno'], $filters->turno_id);

            $rows = $q->selectRaw('COALESCE(c.'.$cursoName.", '—') as curso, COUNT(*) as turmas")
                ->groupBy('curso')
                ->orderByDesc('turmas')
                ->limit(12)
                ->get();

            return $rows->map(fn ($r) => [
                'curso' => (string) ($r->curso ?? '—'),
                'turmas' => (int) ($r->turmas ?? 0),
            ])->all();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    private function normalizeBool(mixed $v): ?bool
    {
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v)) {
            return $v !== 0;
        }
        $s = strtolower(trim((string) $v));
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['1', 't', 'true', 'y', 'yes', 'sim'], true)) {
            return true;
        }
        if (in_array($s, ['0', 'f', 'false', 'n', 'no', 'nao', 'não'], true)) {
            return false;
        }

        return null;
    }
}
