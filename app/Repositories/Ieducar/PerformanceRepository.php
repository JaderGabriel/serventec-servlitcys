<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Desempenho / avaliações — ligue aqui notas, médias e indicadores quando as tabelas estiverem mapeadas.
 */
class PerformanceRepository
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

        $chart = ChartPayload::line(
            __('Indicadores de desempenho (ilustrativo até mapear avaliações)'),
            __('Índice'),
            [__('Jan'), __('Fev'), __('Mar'), __('Abr'), __('Mai'), __('Jun')],
            [62, 64, 63, 68, 70, 72]
        );

        return [
            'rows' => [],
            'message' => __('Defina consultas de desempenho (notas, avaliações) após mapear as tabelas de avaliação do iEducar em config/ieducar.php ou em um serviço dedicado.'),
            'error' => null,
            'chart' => $chart,
        ];
    }
}
