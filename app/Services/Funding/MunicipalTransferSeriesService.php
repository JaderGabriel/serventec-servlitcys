<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Dashboard\ChartPayload;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Séries históricas de repasse observado (municipal_transfer_snapshots).
 */
final class MunicipalTransferSeriesService
{
    public function __construct(
        private MunicipalTransferSnapshotRepository $snapshots,
    ) {}

    /**
     * @return array{
     *   available: bool,
     *   intro: string,
     *   total_ano: ?float,
     *   rows: list<array{programa_id: string, label: string, valor: float, valor_fmt: string, fonte: string}>,
     *   series: list<array{ano: int, valor: float}>,
     *   chart: ?array<string, mixed>,
     *   program_comparisons: list<array<string, mixed>>
     * }
     */
    public function build(City $city, int $year): array
    {
        $empty = [
            'available' => false,
            'intro' => __('Importe repasses (admin → sincronização financiamento) ou configure APIs Tesouro/Transparência.'),
            'total_ano' => null,
            'rows' => [],
            'series' => [],
            'chart' => null,
            'program_comparisons' => [],
        ];

        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return $empty;
        }

        $all = \App\Models\MunicipalTransferSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->orderBy('ano')
            ->get();

        if ($all->isEmpty()) {
            return $empty;
        }

        $records = $all->filter(static fn ($r): bool => (int) $r->ano === $year)->values()->all();
        if ($records === []) {
            $records = [$all->last()];
        }

        $rows = [];
        $total = 0.0;
        $byYear = [];

        foreach ($all as $r) {
            $y = (int) $r->ano;
            $byYear[$y] = ($byYear[$y] ?? 0.0) + (float) $r->valor;
        }

        foreach ($records as $r) {
            $valor = (float) $r->valor;
            $total += $valor;
            $rows[] = [
                'programa_id' => (string) $r->programa_id,
                'label' => (string) ($r->programa_label ?? $r->programa_id),
                'valor' => $valor,
                'valor_fmt' => DiscrepanciesFundingImpact::formatBrl($valor),
                'fonte' => (string) $r->fonte,
            ];
        }

        ksort($byYear);
        $series = [];
        foreach ($byYear as $ano => $valor) {
            $series[] = ['ano' => (int) $ano, 'valor' => round($valor, 2)];
        }

        $histYears = array_keys($byYear);
        $chart = null;
        if (count($histYears) >= 2) {
            $chart = ChartPayload::withValueFormatBrl(ChartPayload::bar(
                __('Repasse observado — evolução por exercício'),
                __('Valor (R$)'),
                array_map(static fn (int $a): string => (string) $a, $histYears),
                array_values($byYear),
            ));
        }

        return [
            'available' => true,
            'intro' => __(
                'Valores agregados de Tesouro Transparente e Portal da Transparência (import :data). Indicativo — confirme no portal oficial.',
                ['data' => $records[0]->imported_at?->format('d/m/Y H:i') ?? '—']
            ),
            'total_ano' => round($total, 2),
            'rows' => $rows,
            'series' => $series,
            'chart' => $chart,
            'program_comparisons' => [],
        ];
    }

    /**
     * @return list<array{ano: int, valor: float, programa_id: string}>
     */
    public function historicalTotals(City $city, string $programaId, int $years = 5): array
    {
        $from = (int) date('Y') - max(1, $years);

        return $this->snapshots->seriesByProgram($city, $programaId, $from);
    }
}
