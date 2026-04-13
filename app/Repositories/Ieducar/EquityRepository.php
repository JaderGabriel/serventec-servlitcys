<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Equidade: distribuição por sexo e por etapa/série (comparar volumes entre grupos).
 */
class EquityRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>}>,
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
                $sex = MatriculaChartQueries::matriculasPorSexo($db, $city, $filters);
                if ($sex !== null) {
                    $charts[] = $sex;
                } else {
                    $notes[] = __(
                        'Gráfico por sexo indisponível: confirme cadastro.pessoa (colunas sexo / tipo_sexo / idsexo, etc.), IEDUCAR_TABLE_PESSOA / IEDUCAR_TABLE_ALUNO e as colunas IEDUCAR_COL_ALUNO_PESSOA e IEDUCAR_COL_PESSOA_ID (idpes).'
                    );
                }

                foreach ([
                    fn () => MatriculaChartQueries::matriculasPorSerieTop($db, $city, $filters),
                ] as $fn) {
                    try {
                        $c = $fn();
                        if ($c !== null) {
                            $charts[] = $c;
                        }
                    } catch (QueryException) {
                    }
                }
            });
        } catch (\Throwable $e) {
            return ['charts' => [], 'notes' => [], 'error' => $e->getMessage()];
        }

        return ['charts' => $charts, 'notes' => $notes, 'error' => null];
    }
}
