<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Frequência e faltas — extensível para tabelas de presença do iEducar.
 */
class AttendanceRepository
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
            'message' => __('Área preparada para indicadores de frequência. Mapeie diário de classe e faltas no banco da cidade.'),
            'error' => null,
        ];
    }
}
