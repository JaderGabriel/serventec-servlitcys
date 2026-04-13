<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Desempenho / avaliações — ligue aqui notas, médias e indicadores quando as tabelas estiverem mapeadas.
 */
class PerformanceRepository
{
    /**
     * @return array{rows: list<array<string, mixed>>, message: string, error: ?string}
     */
    public function placeholder(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['rows' => [], 'message' => '', 'error' => null];
        }

        return [
            'rows' => [],
            'message' => __('Defina consultas de desempenho (notas, avaliações) após mapear as tabelas de avaliação do iEducar em config/ieducar.php ou em um serviço dedicado.'),
            'error' => null,
        ];
    }
}
