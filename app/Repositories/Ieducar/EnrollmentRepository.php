<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaChartQueries;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

class EnrollmentRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * Amostra de matrículas (últimas N linhas) + gráfico por turma (top).
     *
     * @return array{
     *   rows: list<object>,
     *   error: ?string,
     *   chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>},
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}>
     * }
     */
    public function sample(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['rows' => [], 'error' => null, 'chart' => null, 'charts' => []];
        }

        try {
            return $this->cityData->run($city, function ($db) use ($city, $filters) {
                $table = IeducarSchema::resolveTable('matricula', $city);
                $mid = (string) config('ieducar.columns.matricula.id');
                $mturma = (string) config('ieducar.columns.matricula.turma');
                $mAtivo = (string) config('ieducar.columns.matricula.ativo');

                try {
                    if (MatriculaTurmaJoin::usePivotTable($db, $city)) {
                        $mt = IeducarSchema::resolveTable('matricula_turma', $city);
                        $mtMat = (string) config('ieducar.columns.matricula_turma.matricula');
                        $mtTurma = (string) config('ieducar.columns.matricula_turma.turma');
                        $mtAtivo = (string) config('ieducar.columns.matricula_turma.ativo');
                        $q = $db->table($table.' as m')
                            ->join($mt.' as mt', 'm.'.$mid, '=', 'mt.'.$mtMat)
                            ->select([
                                'm.'.$mid.' as cod_matricula',
                                'mt.'.$mtTurma.' as ref_cod_turma',
                            ])
                            ->orderByDesc('m.'.$mid);
                        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                        if ($mtAtivo !== '') {
                            MatriculaAtivoFilter::apply($q, $db, 'mt.'.$mtAtivo);
                        }
                    } else {
                        $q = $db->table($table)
                            ->select([
                                $mid.' as cod_matricula',
                                $mturma.' as ref_cod_turma',
                            ])
                            ->orderByDesc($mid);
                        MatriculaAtivoFilter::apply($q, $db, $mAtivo);
                    }
                    $rows = $q->limit(30)->get()->all();

                    $charts = [];
                    $main = $this->turmasComMaisMatriculas($db, $city, $filters);
                    if ($main !== null) {
                        $charts[] = $main;
                    }
                    foreach ([
                        fn () => MatriculaChartQueries::matriculasPorCursoTop($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorSerieTop($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorTurno($db, $city, $filters),
                        fn () => MatriculaChartQueries::turmasPorTurnoDistribuicao($db, $city, $filters),
                    ] as $fn) {
                        try {
                            $c = $fn();
                            if ($c !== null) {
                                $charts[] = $c;
                            }
                        } catch (QueryException) {
                        }
                    }

                    return [
                        'rows' => $rows,
                        'error' => null,
                        'chart' => $charts[0] ?? null,
                        'charts' => $charts,
                    ];
                } catch (QueryException $e) {
                    return [
                        'rows' => [],
                        'error' => __('Não foi possível listar matrículas. Ajuste config/ieducar.php (tabela e colunas).').' '.$e->getMessage(),
                        'chart' => null,
                        'charts' => [],
                    ];
                }
            });
        } catch (\Throwable $e) {
            return ['rows' => [], 'error' => $e->getMessage(), 'chart' => null, 'charts' => []];
        }
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function turmasComMaisMatriculas(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $turma = IeducarSchema::resolveTable('turma', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mTurma = (string) config('ieducar.columns.matricula.turma');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $tId = (string) config('ieducar.columns.turma.id');
            $tName = (string) config('ieducar.columns.turma.name');
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turno = (string) config('ieducar.columns.turma.turno');

            $usePivot = MatriculaTurmaJoin::usePivotTable($db, $city);

            if ($usePivot) {
                $mt = IeducarSchema::resolveTable('matricula_turma', $city);
                $mtMat = (string) config('ieducar.columns.matricula_turma.matricula');
                $mtTurma = (string) config('ieducar.columns.matricula_turma.turma');
                $mtAtivo = (string) config('ieducar.columns.matricula_turma.ativo');
                $q = $db->table($mat.' as m')
                    ->join($mt.' as mt', 'm.'.$mId, '=', 'mt.'.$mtMat)
                    ->join($turma.' as t', 'mt.'.$mtTurma, '=', 't.'.$tId)
                    ->select('t.'.$tId.' as tid')
                    ->selectRaw('MAX(t.'.$tName.') as tname')
                    ->selectRaw('COUNT(*) as c');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                if ($mtAtivo !== '') {
                    MatriculaAtivoFilter::apply($q, $db, 'mt.'.$mtAtivo);
                }
            } else {
                $q = $db->table($mat.' as m')
                    ->join($turma.' as t', 'm.'.$mTurma, '=', 't.'.$tId)
                    ->select('t.'.$tId.' as tid')
                    ->selectRaw('MAX(t.'.$tName.') as tname')
                    ->selectRaw('COUNT(*) as c');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            }

            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $year !== '') {
                $q->where('t.'.$year, $yearVal);
            }
            if ($filters->escola_id && $escola !== '') {
                $q->where('t.'.$escola, $filters->escola_id);
            }
            if ($filters->curso_id && $curso !== '') {
                $q->where('t.'.$curso, $filters->curso_id);
            }
            if ($filters->turno_id && $turno !== '') {
                $q->where('t.'.$turno, $filters->turno_id);
            }

            $q->groupBy('t.'.$tId)
                ->orderByDesc('c')
                ->limit(12);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) (($row->tname ?? '') !== '' ? $row->tname : ('#'.$row->tid));
                $values[] = (int) ($row->c ?? 0);
            }

            return ChartPayload::bar(
                __('Matrículas por turma (top 12)'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }
}
