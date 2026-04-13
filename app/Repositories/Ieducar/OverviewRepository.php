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

class OverviewRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   kpis: ?array{escolas: ?int, turmas: ?int, matriculas: ?int},
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>,
     *   filter_note: ?string,
     *   error: ?string
     * }
     */
    public function summary(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null];
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $kpis = [
                    'escolas' => $this->countEscolas($db, $city, $filters),
                    'turmas' => $this->countTurmas($db, $city, $filters),
                    'matriculas' => $this->countMatriculas($db, $city, $filters),
                ];

                $charts = [];
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

                foreach ([
                    fn () => MatriculaChartQueries::matriculasPorCursoTop($db, $city, $filters),
                    fn () => MatriculaChartQueries::matriculasPorEscolaTop($db, $city, $filters),
                    fn () => MatriculaChartQueries::turmasPorTurnoDistribuicao($db, $city, $filters),
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

                $note = $this->filterNote($filters);

                return [
                    'kpis' => $kpis,
                    'charts' => $charts,
                    'filter_note' => $note,
                    'error' => null,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'kpis' => null,
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

        if ($filters->escola_id || $filters->curso_id || $filters->turno_id) {
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
            if ($filters->escola_id) {
                $q->where($id, $filters->escola_id);
            }

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    private function countTurmas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $table = IeducarSchema::resolveTable('turma', $city);
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turno = (string) config('ieducar.columns.turma.turno');

            $q = $db->table($table);
            $yearVal = $filters->yearFilterValue();
            if ($yearVal !== null && $year !== '') {
                $q->where($year, $yearVal);
            }
            if ($filters->escola_id && $escola !== '') {
                $q->where($escola, $filters->escola_id);
            }
            if ($filters->curso_id && $curso !== '') {
                $q->where($curso, $filters->curso_id);
            }
            if ($filters->turno_id && $turno !== '') {
                $q->where($turno, $filters->turno_id);
            }

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    private function countMatriculas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mTurma = (string) config('ieducar.columns.matricula.turma');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');

            $yearVal = $filters->yearFilterValue();
            $needsTurma = $yearVal !== null
                || $filters->escola_id
                || $filters->curso_id
                || $filters->turno_id;

            if (! $needsTurma) {
                $q = $db->table($mat);
                MatriculaAtivoFilter::apply($q, $db, $mAtivo);

                return (int) $q->count();
            }

            $turma = IeducarSchema::resolveTable('turma', $city);
            $tId = (string) config('ieducar.columns.turma.id');
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
                    ->join($turma.' as t', 'mt.'.$mtTurma, '=', 't.'.$tId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
                if ($mtAtivo !== '') {
                    MatriculaAtivoFilter::apply($q, $db, 'mt.'.$mtAtivo);
                }
            } else {
                $q = $db->table($mat.' as m')->join($turma.' as t', 'm.'.$mTurma, '=', 't.'.$tId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);
            }

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

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }
}
