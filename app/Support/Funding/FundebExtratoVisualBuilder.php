<?php

namespace App\Support\Funding;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Extrato simulado por ciclo (fonte), com lançamentos e resumo mensal/anual comparado à expectativa FUNDEB.
 */
final class FundebExtratoVisualBuilder
{
    /** @var array<int, string> */
    private const MONTH_NAMES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    /**
     * @param  list<MunicipalTransferSnapshot>  $rows
     * @return array{
     *   cycles: list<array<string, mixed>>,
     *   consolidado: array<string, mixed>
     * }
     */
    public function build(array $rows, City $city, int $filterYear, float $expectedAnnual): array
    {
        $byFonte = [];
        foreach ($rows as $row) {
            if (! $this->isFundebRow($row)) {
                continue;
            }
            $fonte = (string) $row->fonte;
            $byFonte[$fonte] ??= [];
            $byFonte[$fonte][] = $row;
        }

        if ($byFonte === []) {
            return [
                'cycles' => [$this->emptyCycle($city, $filterYear)],
                'consolidado' => $this->emptyConsolidado($expectedAnnual),
            ];
        }

        uksort($byFonte, static fn (string $a, string $b): int => FundebExtratoFontePriority::rank($a) <=> FundebExtratoFontePriority::rank($b));

        $cycles = [];
        $consolidatedPeriods = [];

        foreach ($byFonte as $fonte => $fonteRows) {
            $cycle = $this->buildCycle($fonte, $fonteRows, $filterYear, $expectedAnnual);
            $cycles[] = $cycle;
            foreach ($cycle['by_period'] as $period) {
                $key = $period['year'].'-'.str_pad((string) $period['month'], 2, '0', STR_PAD_LEFT);
                if (! isset($consolidatedPeriods[$key])) {
                    $consolidatedPeriods[$key] = [
                        'year' => $period['year'],
                        'month' => $period['month'],
                        'period_label' => $period['period_label'],
                        'credit' => 0.0,
                    ];
                }
                $consolidatedPeriods[$key]['credit'] += (float) $period['credit'];
            }
        }

        $observedTotal = 0.0;
        foreach ($cycles as $cycle) {
            $observedTotal += (float) ($cycle['cycle_total'] ?? 0);
        }

        return [
            'cycles' => $cycles,
            'consolidado' => $this->buildConsolidado($consolidatedPeriods, $observedTotal, $expectedAnnual, $filterYear),
        ];
    }

    /**
     * @param  list<MunicipalTransferSnapshot>  $fonteRows
     * @return array<string, mixed>
     */
    private function buildCycle(string $fonte, array $fonteRows, int $filterYear, float $expectedAnnual): array
    {
        $lines = [];
        $periodBuckets = [];
        $running = 0.0;
        $expectedMonthly = $expectedAnnual > 0 ? $expectedAnnual / 12 : 0.0;

        foreach ($fonteRows as $row) {
            $desc = trim((string) ($row->programa_label ?: $row->programa_id));
            $mensal = $this->extractMensal($row, $filterYear);

            if ($mensal !== []) {
                ksort($mensal);
                foreach ($mensal as $month => $valor) {
                    $month = (int) $month;
                    if ($month < 1 || $month > 12 || $valor <= 0) {
                        continue;
                    }
                    $running += $valor;
                    $periodKey = $filterYear.'-'.$month;
                    $periodBuckets[$periodKey] = ($periodBuckets[$periodKey] ?? 0.0) + $valor;

                    $lines[] = $this->lineEntry(
                        $this->periodDateLabel($filterYear, $month),
                        $desc.' — '.$this->monthName($month).'/'.$filterYear,
                        $valor,
                        $running,
                        $fonte,
                    );
                }

                continue;
            }

            $valor = (float) $row->valor;
            if ($valor <= 0) {
                continue;
            }
            $running += $valor;
            $periodKey = $filterYear.'-0';
            $periodBuckets[$periodKey] = ($periodBuckets[$periodKey] ?? 0.0) + $valor;

            $lines[] = $this->lineEntry(
                $row->imported_at?->format('d/m/Y') ?? '—',
                $desc.' — '.__('Anual :ano', ['ano' => $filterYear]),
                $valor,
                $running,
                $fonte,
            );
        }

        $byPeriod = $this->periodRowsFromBuckets($periodBuckets, $filterYear, $expectedMonthly);
        $cycleTotal = round(array_sum(array_map(static fn (array $p): float => (float) $p['credit'], $byPeriod)), 2);
        $monthsWithData = count(array_filter($byPeriod, static fn (array $p): bool => (int) ($p['month'] ?? 0) > 0));
        $expectedCycle = $this->hasAnnualBucket($periodBuckets)
            ? $expectedAnnual
            : $expectedMonthly * max(1, $monthsWithData);

        return [
            'fonte' => $fonte,
            'fonte_label' => $this->fonteLabel($fonte),
            'lines' => $lines,
            'by_period' => $byPeriod,
            'cycle_total' => $cycleTotal,
            'cycle_total_fmt' => DiscrepanciesFundingImpact::formatBrl($cycleTotal),
            'comparativo' => $this->comparativoBlock($cycleTotal, $expectedCycle),
        ];
    }

    /**
     * @param  array<string, float>  $periodBuckets
     * @return list<array<string, mixed>>
     */
    private function periodRowsFromBuckets(array $periodBuckets, int $defaultYear, float $expectedMonthly): array
    {
        $rows = [];
        ksort($periodBuckets);

        foreach ($periodBuckets as $key => $credit) {
            [$yearStr, $monthStr] = array_pad(explode('-', $key, 2), 2, '0');
            $year = (int) $yearStr;
            $month = (int) $monthStr;
            if ($year < 2000) {
                $year = $defaultYear;
            }

            $expected = $month === 0 ? ($expectedMonthly * 12) : $expectedMonthly;
            $rows[] = $this->periodRow($year, $month, $credit, $expected);
        }

        return $rows;
    }

    /**
     * @param  array<string, array{year: int, month: int, period_label: string, credit: float}>  $consolidatedPeriods
     * @return array<string, mixed>
     */
    private function buildConsolidado(array $consolidatedPeriods, float $observedTotal, float $expectedAnnual, int $filterYear): array
    {
        $expectedMonthly = $expectedAnnual > 0 ? $expectedAnnual / 12 : 0.0;
        $byPeriod = [];
        ksort($consolidatedPeriods);

        foreach ($consolidatedPeriods as $period) {
            $byPeriod[] = $this->periodRow(
                (int) $period['year'],
                (int) $period['month'],
                (float) $period['credit'],
                (int) $period['month'] === 0 ? $expectedAnnual : $expectedMonthly,
            );
        }

        $byYear = [];
        foreach ($byPeriod as $period) {
            $y = (int) $period['year'];
            $byYear[$y] = ($byYear[$y] ?? 0.0) + (float) $period['credit'];
        }

        $yearRows = [];
        foreach ($byYear as $year => $total) {
            $expectedYear = $year === $filterYear ? $expectedAnnual : 0.0;
            $yearRows[] = [
                'year' => $year,
                'year_label' => (string) $year,
                'credit_fmt' => DiscrepanciesFundingImpact::formatBrl($total),
                'comparativo' => $expectedYear > 0
                    ? $this->comparativoBlock($total, $expectedYear)
                    : null,
            ];
        }

        return [
            'by_period' => $byPeriod,
            'by_year' => $yearRows,
            'total_fmt' => DiscrepanciesFundingImpact::formatBrl($observedTotal),
            'comparativo' => $this->comparativoBlock($observedTotal, $expectedAnnual),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodRow(int $year, int $month, float $credit, float $expected): array
    {
        $credit = round($credit, 2);
        $cmp = $this->comparativoBlock($credit, $expected);

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => $month === 0
                ? __('Anual :ano', ['ano' => $year])
                : $this->monthName($month).'/'.$year,
            'credit' => $credit,
            'credit_fmt' => DiscrepanciesFundingImpact::formatBrl($credit),
            'expected_fmt' => $expected > 0 ? DiscrepanciesFundingImpact::formatBrl($expected) : '—',
            'comparativo' => $cmp,
        ];
    }

    /**
     * @return array{observed_fmt: string, expected_fmt: string, delta_fmt: string, delta_sign: string, delta_pct: ?float}
     */
    private function comparativoBlock(float $observed, float $expected): array
    {
        $delta = MoneyMath::roundMoney($observed - $expected);
        $deltaPct = $expected > 0 ? round(($delta / $expected) * 100, 1) : null;

        return [
            'observed_fmt' => DiscrepanciesFundingImpact::formatBrl($observed),
            'expected_fmt' => $expected > 0 ? DiscrepanciesFundingImpact::formatBrl($expected) : '—',
            'delta_fmt' => DiscrepanciesFundingImpact::formatBrl(abs($delta)),
            'delta_sign' => $delta >= 0 ? 'positive' : 'negative',
            'delta_pct' => $deltaPct,
        ];
    }

    /**
     * @return array{date: string, description: string, credit: ?string, debit: ?string, balance: ?string, fonte: string, valor_fmt: string}
     */
    private function lineEntry(string $date, string $description, float $valor, float $running, string $fonte): array
    {
        return [
            'date' => $date,
            'description' => $description,
            'credit' => $valor > 0 ? DiscrepanciesFundingImpact::formatBrl($valor) : null,
            'debit' => $valor < 0 ? DiscrepanciesFundingImpact::formatBrl(abs($valor)) : null,
            'balance' => DiscrepanciesFundingImpact::formatBrl($running),
            'fonte' => $fonte,
            'valor_fmt' => DiscrepanciesFundingImpact::formatBrl($valor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCycle(City $city, int $ano): array
    {
        return [
            'fonte' => '—',
            'fonte_label' => __('Sem dados'),
            'lines' => [[
                'date' => '—',
                'description' => __('Sem repasses FUNDEB importados para :city / :ano. Use Admin → Dados públicos → Repasses.', [
                    'city' => $city->name,
                    'ano' => (string) $ano,
                ]),
                'credit' => null,
                'debit' => null,
                'balance' => null,
                'fonte' => '—',
                'valor_fmt' => '—',
            ]],
            'by_period' => [],
            'cycle_total' => 0.0,
            'cycle_total_fmt' => '—',
            'comparativo' => $this->comparativoBlock(0.0, 0.0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyConsolidado(float $expectedAnnual): array
    {
        return [
            'by_period' => [],
            'by_year' => [],
            'total_fmt' => '—',
            'comparativo' => $this->comparativoBlock(0.0, $expectedAnnual),
        ];
    }

    private function isFundebRow(MunicipalTransferSnapshot $row): bool
    {
        $needles = config('ieducar.finance_realtime.program_keywords', ['fundeb', 'fnde']);
        if (! is_array($needles)) {
            $needles = ['fundeb'];
        }
        $blob = mb_strtolower((string) $row->programa_id.' '.(string) $row->programa_label.' '.(string) $row->fonte);
        foreach ($needles as $n) {
            if (str_contains($blob, mb_strtolower((string) $n))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, float>
     */
    private function extractMensal(MunicipalTransferSnapshot $row, int $filterYear): array
    {
        $meta = $row->meta;
        if (! is_string($meta) || $meta === '') {
            return [];
        }
        $decoded = json_decode($meta, true);
        if (! is_array($decoded)) {
            return [];
        }
        $mensal = $decoded['mensal'] ?? null;
        if (! is_array($mensal)) {
            return [];
        }

        $out = [];
        foreach ($mensal as $month => $valor) {
            if (! is_numeric($valor) || (float) $valor <= 0) {
                continue;
            }
            $out[(int) $month] = (float) $valor;
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $periodBuckets
     */
    private function hasAnnualBucket(array $periodBuckets): bool
    {
        foreach (array_keys($periodBuckets) as $key) {
            if (str_ends_with((string) $key, '-0')) {
                return true;
            }
        }

        return false;
    }

    private function periodDateLabel(int $year, int $month): string
    {
        return sprintf('01/%02d/%d', $month, $year);
    }

    private function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month] ?? (string) $month;
    }

    private function fonteLabel(string $fonte): string
    {
        return match ($fonte) {
            'tesouro_csv' => __('Tesouro CKAN (municipal)'),
            'tesouro_publicacao' => __('Tesouro Transparente — publicação'),
            'sisweb_ckan' => __('SISWEB — espelho CKAN'),
            'sisweb_export' => __('SISWEB — export'),
            'bb_extrato' => __('Extrato BB'),
            'tesouro' => __('Tesouro CKAN (datastore)'),
            'portal_transparencia' => __('Portal da Transparência'),
            default => $fonte,
        };
    }
}
