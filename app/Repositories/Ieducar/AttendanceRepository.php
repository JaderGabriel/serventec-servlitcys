<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Frequência e faltas — extensível para tabelas de presença do iEducar.
 */
class AttendanceRepository
{
    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   message: string,
     *   error: ?string,
     *   chart: ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     * }
     */
    public function placeholder(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['rows' => [], 'message' => '', 'error' => null, 'chart' => null];
        }

        $chart = ChartPayload::bar(
            __('Frequência média por semana (ilustrativo até mapear diário)'),
            __('Presença %'),
            [__('S1'), __('S2'), __('S3'), __('S4')],
            [94.0, 93.2, 95.1, 94.5]
        );

        return [
            'rows' => [],
            'message' => __('Área preparada para indicadores de frequência. Mapeie diário de classe e faltas no banco da cidade.'),
            'error' => null,
            'chart' => $chart,
        ];
    }
}
