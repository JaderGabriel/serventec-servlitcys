<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\MatriculaAtivoFilter;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Indicadores de inclusão e diversidade (raça/cor, etc.) a partir de cadastro + matrícula.
 */
class InclusionRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>,
     *   notes: list<string>,
     *   error: ?string
     * }
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['charts' => [], 'notes' => [], 'error' => null];
        }

        $charts = [];
        $notes = [];

        try {
            $this->cityData->run($city, function (Connection $db) use ($city, $filters, &$charts, &$notes) {
                $customRaca = config('ieducar.sql.inclusion_raca');
                if (is_string($customRaca) && trim($customRaca) !== '') {
                    $racaChart = $this->chartFromRawSql($db, trim($customRaca), __('Matrículas por raça/cor (SQL personalizado)'));
                    if ($racaChart !== null) {
                        $charts[] = $racaChart;
                    } else {
                        $notes[] = __('A consulta personalizada de inclusão (raça) não devolveu linhas válidas.');
                    }
                } else {
                    $racaChart = $this->raceDistributionChart($db, $city, $filters);
                    if ($racaChart !== null) {
                        $charts[] = $racaChart;
                    } else {
                        $notes[] = __(
                            'Não foi possível montar o gráfico de raça/cor automaticamente. Verifique tabelas aluno, pessoa e raca em config/ieducar.php ou defina IEDUCAR_SQL_INCLUSION_RACA.'
                        );
                    }
                }

                $customExtra = config('ieducar.sql.inclusion_extra');
                if (is_string($customExtra) && trim($customExtra) !== '') {
                    $extra = $this->chartFromRawSql($db, trim($customExtra), __('Indicador complementar (SQL personalizado)'));
                    if ($extra !== null) {
                        $charts[] = $extra;
                    }
                } else {
                    $charts[] = ChartPayload::bar(
                        __('Exemplo: indicador complementar'),
                        __('Valor ilustrativo'),
                        [__('Série A'), __('Série B'), __('Série C')],
                        [40, 35, 25]
                    );
                    $notes[] = __(
                        'O segundo gráfico é ilustrativo até definir IEDUCAR_SQL_INCLUSION_EXTRA (ex.: necessidades educacionais específicas, público-alvo).'
                    );
                }
            });
        } catch (\Throwable $e) {
            return [
                'charts' => [],
                'notes' => [],
                'error' => $e->getMessage(),
            ];
        }

        return ['charts' => $charts, 'notes' => $notes, 'error' => null];
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function raceDistributionChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);
            $raca = IeducarSchema::resolveTable('raca', $city);

            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');
            $aPessoa = (string) config('ieducar.columns.aluno.pessoa');
            $pId = (string) config('ieducar.columns.pessoa.id');
            $pRaca = (string) config('ieducar.columns.pessoa.raca');
            $rId = (string) config('ieducar.columns.raca.id');
            $rName = (string) config('ieducar.columns.raca.name');

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId)
                ->leftJoin($raca.' as r', 'p.'.$pRaca, '=', 'r.'.$rId)
                ->selectRaw('r.'.$rId.' as rid')
                ->selectRaw('r.'.$rName.' as rname')
                ->selectRaw('COUNT(*) as c')
                ->groupBy('r.'.$rId)
                ->groupBy('r.'.$rName);

            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo);

            $this->applyMatriculaTurmaFilters($q, $city, $filters);

            $rows = $q->orderByDesc('c')->limit(16)->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $nm = $row->rname ?? null;
                $labels[] = $nm !== null && (string) $nm !== '' ? (string) $nm : __('Não informado');
                $values[] = (int) ($row->c ?? 0);
            }

            return ChartPayload::doughnut(__('Matrículas por raça/cor (cadastro)'), $labels, $values);
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    private function applyMatriculaTurmaFilters(Builder $q, City $city, IeducarFilterState $filters): void
    {
        $yearVal = $filters->yearFilterValue();
        $needsTurma = $yearVal !== null
            || $filters->escola_id !== null
            || $filters->curso_id !== null
            || $filters->turno_id !== null;

        if (! $needsTurma) {
            return;
        }

        $turma = IeducarSchema::resolveTable('turma', $city);
        $mTurma = (string) config('ieducar.columns.matricula.turma');
        $tId = (string) config('ieducar.columns.turma.id');
        $year = (string) config('ieducar.columns.turma.year');
        $escola = (string) config('ieducar.columns.turma.escola');
        $curso = (string) config('ieducar.columns.turma.curso');
        $turno = (string) config('ieducar.columns.turma.turno');

        $q->join($turma.' as t_filter', 'm.'.$mTurma, '=', 't_filter.'.$tId);

        if ($yearVal !== null && $year !== '') {
            $q->where('t_filter.'.$year, $yearVal);
        }
        if ($filters->escola_id !== null && $escola !== '') {
            $q->where('t_filter.'.$escola, $filters->escola_id);
        }
        if ($filters->curso_id !== null && $curso !== '') {
            $q->where('t_filter.'.$curso, $filters->curso_id);
        }
        if ($filters->turno_id !== null && $turno !== '') {
            $q->where('t_filter.'.$turno, $filters->turno_id);
        }
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function chartFromRawSql(Connection $db, string $sql, string $title): ?array
    {
        try {
            $rows = $db->select($this->appendLimit($sql, 32));
            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $vals = array_values($arr);
                if (count($vals) < 2) {
                    continue;
                }
                $labels[] = (string) $vals[0];
                $values[] = (float) $vals[1];
            }
            if ($labels === []) {
                return null;
            }

            return ChartPayload::doughnut($title, $labels, $values);
        } catch (QueryException) {
            return null;
        }
    }

    private function appendLimit(string $sql, int $max): string
    {
        $sql = trim($sql);
        if ($sql === '' || preg_match('/\blimit\s+\d+\s*$/i', $sql)) {
            return $sql;
        }

        return $sql.' LIMIT '.max(1, $max);
    }
}
