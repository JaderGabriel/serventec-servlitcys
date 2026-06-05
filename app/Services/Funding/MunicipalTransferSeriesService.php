<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Dashboard\ChartPayload;
use App\Support\Funding\FundebExtratoFontePriority;
use App\Support\Funding\FundebTransferScope;
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
     *   total_ano_note: ?string,
     *   deduplicated: bool,
     *   rows: list<array{programa_id: string, label: string, valor: float, valor_fmt: string, fonte: string, fontes_ignoradas: int}>,
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
            'total_ano_note' => null,
            'deduplicated' => true,
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
            ->get()
            ->all();

        if ($all === []) {
            return $empty;
        }

        $yearRows = array_values(array_filter(
            $all,
            static fn ($r): bool => (int) $r->ano === $year,
        ));
        if ($yearRows === []) {
            $yearRows = [end($all)];
            $year = (int) $yearRows[0]->ano;
        }

        $primaryForYear = FundebExtratoFontePriority::pickPrimaryPerProgram($yearRows);
        $rows = [];
        $total = 0.0;

        foreach ($primaryForYear as $r) {
            $valor = (float) $r->valor;
            $total += $valor;
            $ignored = $this->countAlternateSources($yearRows, (string) $r->programa_id, (string) $r->fonte);
            $rows[] = [
                'programa_id' => (string) $r->programa_id,
                'label' => (string) ($r->programa_label ?? $r->programa_id),
                'valor' => $valor,
                'valor_fmt' => DiscrepanciesFundingImpact::formatBrl($valor),
                'fonte' => (string) $r->fonte,
                'fontes_ignoradas' => $ignored,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));

        $byYear = FundebExtratoFontePriority::totalsByYearDeduped($all);
        $series = [];
        foreach ($byYear as $ano => $valor) {
            $series[] = ['ano' => (int) $ano, 'valor' => (float) $valor];
        }

        $histYears = array_keys($byYear);
        $chart = null;
        if (count($histYears) >= 2) {
            $chart = ChartPayload::withValueFormatBrl(ChartPayload::bar(
                __('Repasse observado — evolução por exercício (deduplicado)'),
                __('Valor (R$)'),
                array_map(static fn (int $a): string => (string) $a, $histYears),
                array_values($byYear),
            ));
        }

        $rawSum = array_sum(array_map(
            static fn ($r): float => FundebTransferScope::isUfAggregated($r) ? 0.0 : (float) $r->valor,
            $yearRows,
        ));
        $hadDuplicates = round($rawSum, 2) > round($total, 2) + 0.01;

        return [
            'available' => true,
            'intro' => __(
                'Valores por programa com uma fonte prioritária (evita somar CKAN + SISWEB + BB do mesmo repasse). FUNDEB detalhado: aba Finanças → Tempo Real.'
            ),
            'total_ano' => round($total, 2),
            'total_ano_note' => $hadDuplicates
                ? __('Total deduplicado — não some com VAAF nem com a aba Tempo Real.')
                : __('Soma de programas distintos no exercício — não confundir com VAAF (valor por aluno).'),
            'deduplicated' => true,
            'rows' => $rows,
            'series' => $series,
            'chart' => $chart,
            'program_comparisons' => [],
        ];
    }

    /**
     * @param  list<\App\Models\MunicipalTransferSnapshot>  $yearRows
     */
    private function countAlternateSources(array $yearRows, string $programaId, string $chosenFonte): int
    {
        $n = 0;
        foreach ($yearRows as $row) {
            if (FundebTransferScope::isUfAggregated($row)) {
                continue;
            }
            if ((string) $row->programa_id !== $programaId) {
                continue;
            }
            if ((string) $row->fonte !== $chosenFonte) {
                $n++;
            }
        }

        return $n;
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
