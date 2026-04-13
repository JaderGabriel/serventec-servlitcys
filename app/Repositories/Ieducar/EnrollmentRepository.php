<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarColumnInspector;
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
     * Gráficos de matrículas (turmas por etapa/série, escola, série, turno, oferta, vagas).
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
                try {
                    $charts = [];
                    $main = $this->turmasPorSerieOrdenadasInep($db, $city, $filters);
                    if ($main !== null) {
                        $charts[] = $main;
                    }
                    foreach ([
                        fn () => MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorSerieTop($db, $city, $filters),
                        fn () => MatriculaChartQueries::matriculasPorTurno($db, $city, $filters),
                        fn () => MatriculaChartQueries::turmasPorTurnoDistribuicao($db, $city, $filters),
                        fn () => MatriculaChartQueries::vagasAbertasPorCurso($db, $city, $filters),
                        fn () => MatriculaChartQueries::vagasAbertasPorEscola($db, $city, $filters),
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
                        'rows' => [],
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
     * Matrículas por turma, ordenadas pela etapa/série (ordem INEP aproximada via coluna «serie» ou equivalente).
     */
    private function turmasPorSerieOrdenadasInep(Connection $db, City $city, IeducarFilterState $filters): ?array
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
            $refSerie = (string) config('ieducar.columns.turma.serie');

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

            $serieTable = IeducarSchema::resolveTable('serie', $city);
            $sId = (string) config('ieducar.columns.serie.id');
            $sName = (string) config('ieducar.columns.serie.name');

            $sortCol = null;
            if ($refSerie !== '' && IeducarColumnInspector::columnExists($db, $turma, $refSerie, $city)) {
                $sortCol = IeducarColumnInspector::firstExistingColumn($db, $serieTable, array_filter([
                    (string) config('ieducar.columns.serie.sort'),
                    'serie',
                    'etapa_educacenso',
                    'cod_serie',
                ]), $city);

                $q->leftJoin($serieTable.' as s', 't.'.$refSerie, '=', 's.'.$sId)
                    ->selectRaw('MAX(s.'.$sName.') as sname');
                if ($sortCol !== null && $sortCol !== '') {
                    $q->selectRaw('MAX(s.'.$sortCol.') as ssort');
                }
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

            $q->groupBy('t.'.$tId);

            $driver = $db->getDriverName();
            if ($sortCol !== null && $sortCol !== '') {
                if ($driver === 'pgsql') {
                    $q->orderByRaw('MAX(s.'.$sortCol.') ASC NULLS LAST');
                } else {
                    $q->orderByRaw('MAX(s.'.$sortCol.') ASC');
                }
            }
            $q->orderByRaw('MAX(t.'.$tName.') ASC')
                ->limit(24);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $tname = (string) ($row->tname ?? '');
                $sname = isset($row->sname) ? (string) $row->sname : '';
                $labels[] = $sname !== ''
                    ? $sname.' — '.$tname
                    : ($tname !== '' ? $tname : ('#'.$row->tid));
                $values[] = (int) ($row->c ?? 0);
            }

            return ChartPayload::barHorizontal(
                __('Matrículas por turma (ordenado por etapa/série)'),
                __('Alunos'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }
}
