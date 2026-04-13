<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarSchema;
use Illuminate\Database\QueryException;

class EnrollmentRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * Amostra de matrículas (últimas N linhas da tabela configurada).
     *
     * @return array{rows: list<object>, error: ?string}
     */
    public function sample(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['rows' => [], 'error' => null];
        }

        try {
            return $this->cityData->run($city, function ($db) {
                $table = IeducarSchema::resolveTable('matricula');
                $mid = config('ieducar.columns.matricula.id');
                $mturma = config('ieducar.columns.matricula.turma');

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

                    return ['rows' => $rows, 'error' => null];
                } catch (QueryException $e) {
                    return [
                        'rows' => [],
                        'error' => __('Não foi possível listar matrículas. Ajuste config/ieducar.php (tabela e colunas).').' '.$e->getMessage(),
                    ];
                }
            });
        } catch (\Throwable $e) {
            return ['rows' => [], 'error' => $e->getMessage()];
        }
    }
}
