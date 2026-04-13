<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarSchema;
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
     * @return array{rows: list<object>, error: ?string, chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}}
     */
    public function sample(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['rows' => [], 'error' => null, 'chart' => null];
        }

        try {
            return $this->cityData->run($city, function ($db) use ($city, $filters) {
                $table = IeducarSchema::resolveTable('matricula', $city);
                $mid = (string) config('ieducar.columns.matricula.id');
                $mturma = (string) config('ieducar.columns.matricula.turma');
                $mAtivo = (string) config('ieducar.columns.matricula.ativo');

                try {
                    $rows = $db->table($table)
                        ->select([
                            $mid.' as cod_matricula',
                            $mturma.' as ref_cod_turma',
                        ])
                        ->orderByDesc($mid)
                        ->limit(30)
                        ->get()
                        ->all();

                    $chart = $this->turmasComMaisMatriculas($db, $city, $filters);

                    return ['rows' => $rows, 'error' => null, 'chart' => $chart];
                } catch (QueryException $e) {
                    return [
                        'rows' => [],
                        'error' => __('Não foi possível listar matrículas. Ajuste config/ieducar.php (tabela e colunas).').' '.$e->getMessage(),
                        'chart' => null,
                    ];
                }
            });
        } catch (\Throwable $e) {
            return ['rows' => [], 'error' => $e->getMessage(), 'chart' => null];
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
            $mTurma = (string) config('ieducar.columns.matricula.turma');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $tId = (string) config('ieducar.columns.turma.id');
            $tName = (string) config('ieducar.columns.turma.name');
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $turno = (string) config('ieducar.columns.turma.turno');

            $q = $db->table($mat.' as m')
                ->join($turma.' as t', 'm.'.$mTurma, '=', 't.'.$tId)
                ->select('t.'.$tId.' as tid')
                ->selectRaw('MAX(t.'.$tName.') as tname')
                ->selectRaw('COUNT(*) as c');

            if ($mAtivo !== '') {
                $q->whereIn('m.'.$mAtivo, [1, '1', true, 't', 'true']);
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
