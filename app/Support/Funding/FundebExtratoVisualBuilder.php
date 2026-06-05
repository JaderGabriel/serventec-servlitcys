<?php

namespace App\Support\Funding;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Support\Finance\MoneyMath;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Extrato simulado tipo conta-corrente: data do repasse, subtotal mensal e saldo anual acumulado.
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
        foreach (FundebTransferScope::municipalSnapshotsOnly($rows) as $row) {
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
        $periodBuckets = [];
        $credits = [];

        foreach ($fonteRows as $row) {
            $desc = trim((string) ($row->programa_label ?: $row->programa_id));
            foreach ($this->extractCreditsFromRow($row, $filterYear, $desc) as $credit) {
                $credits[] = $credit;
                $month = (int) ($credit['month'] ?? 0);
                $year = (int) ($credit['year'] ?? $filterYear);
                $periodKey = $year.'-'.($month > 0 ? $month : 0);
                $periodBuckets[$periodKey] = ($periodBuckets[$periodKey] ?? 0.0) + (float) $credit['valor'];
            }
        }

        $expectedMonthly = $expectedAnnual > 0 ? $expectedAnnual / 12 : 0.0;
        $byPeriod = $this->periodRowsFromBuckets($periodBuckets, $filterYear, $expectedMonthly);
        $cycleTotal = round(array_sum(array_map(static fn (array $p): float => (float) $p['credit'], $byPeriod)), 2);
        $monthsWithData = count(array_filter($byPeriod, static fn (array $p): bool => (int) ($p['month'] ?? 0) > 0));
        $expectedCycle = $this->hasAnnualBucket($periodBuckets)
            ? $expectedAnnual
            : $expectedMonthly * max(1, $monthsWithData);

        return [
            'fonte' => $fonte,
            'fonte_label' => $this->fonteLabel($fonte),
            'lines' => $this->buildStatementLines($credits, $fonte),
            'by_period' => $byPeriod,
            'cycle_total' => $cycleTotal,
            'cycle_total_fmt' => DiscrepanciesFundingImpact::formatBrl($cycleTotal),
            'comparativo' => $this->comparativoBlock($cycleTotal, $expectedCycle),
        ];
    }

    /**
     * @param  list<array{sort_key: string, date: string, year: int, month: int, valor: float, description: string, date_source?: string}>  $credits
     * @return list<array<string, mixed>>
     */
    private function buildStatementLines(array $credits, string $fonte): array
    {
        if ($credits === []) {
            return [];
        }

        usort($credits, static fn (array $a, array $b): int => strcmp($a['sort_key'], $b['sort_key']));

        $lines = [
            $this->lineEntry('opening', '—', __('Saldo anterior'), null, null, 0.0, $fonte, [
                'date_note' => null,
            ]),
        ];

        $runningAnnual = 0.0;
        $currentMonthKey = null;
        $monthTotal = 0.0;
        $lastCreditDate = '—';

        $flushMonth = function () use (&$lines, &$monthTotal, &$runningAnnual, &$lastCreditDate, $fonte, &$currentMonthKey): void {
            if ($currentMonthKey === null || $monthTotal <= 0) {
                return;
            }
            [$year, $month] = array_map('intval', explode('-', $currentMonthKey, 2));
            $lines[] = $this->lineEntry(
                'month_total',
                $lastCreditDate,
                __('Total mensal :periodo', ['periodo' => self::monthPeriodLabelStatic($year, $month)]),
                $monthTotal,
                null,
                $runningAnnual,
                $fonte,
                ['month_total_fmt' => DiscrepanciesFundingImpact::formatBrl($monthTotal)],
            );
            $monthTotal = 0.0;
        };

        foreach ($credits as $credit) {
            $monthKey = $credit['year'].'-'.str_pad((string) max(0, (int) $credit['month']), 2, '0', STR_PAD_LEFT);

            if ($currentMonthKey !== null && $monthKey !== $currentMonthKey) {
                $flushMonth();
            }

            $currentMonthKey = $monthKey;
            $valor = round((float) $credit['valor'], 2);
            $runningAnnual = round($runningAnnual + $valor, 2);
            $monthTotal = round($monthTotal + $valor, 2);
            $lastCreditDate = (string) $credit['date'];

            $lines[] = $this->lineEntry(
                'credit',
                (string) $credit['date'],
                (string) $credit['description'],
                $valor,
                null,
                $runningAnnual,
                $fonte,
                [
                    'date_note' => $credit['date_source'] ?? null,
                ],
            );
        }

        $flushMonth();

        $annualTotal = $runningAnnual;
        $lines[] = $this->lineEntry(
            'year_total',
            $lastCreditDate !== '—' ? $lastCreditDate : '—',
            __('Total anual acumulado'),
            $annualTotal,
            null,
            $annualTotal,
            $fonte,
            ['year_total_fmt' => DiscrepanciesFundingImpact::formatBrl($annualTotal)],
        );

        return $lines;
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

        $consolidatedCredits = [];
        foreach ($consolidatedPeriods as $period) {
            $year = (int) $period['year'];
            $month = (int) $period['month'];
            $credit = round((float) $period['credit'], 2);
            if ($credit <= 0) {
                continue;
            }

            $byPeriod[] = $this->periodRow(
                $year,
                $month,
                $credit,
                $month === 0 ? $expectedAnnual : $expectedMonthly,
            );

            if ($month >= 1 && $month <= 12) {
                $consolidatedCredits[] = [
                    'sort_key' => sprintf('%04d-%02d-99', $year, $month),
                    'date' => $this->repasseDateForMonth($year, $month),
                    'year' => $year,
                    'month' => $month,
                    'valor' => $credit,
                    'description' => __('Repasse FUNDEB consolidado (todas as fontes) — :periodo', [
                        'periodo' => $this->monthName($month).'/'.$year,
                    ]),
                    'date_source' => 'fim_mes',
                ];
            } elseif ($month === 0) {
                $consolidatedCredits[] = [
                    'sort_key' => sprintf('%04d-00-99', $year),
                    'date' => '31/12/'.$year,
                    'year' => $year,
                    'month' => 0,
                    'valor' => $credit,
                    'description' => __('Repasse FUNDEB consolidado — :ano', ['ano' => $year]),
                    'date_source' => 'anual',
                ];
            }
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
            'lines' => $this->buildStatementLines($consolidatedCredits, 'consolidado'),
            'by_period' => $byPeriod,
            'by_year' => $yearRows,
            'total_fmt' => DiscrepanciesFundingImpact::formatBrl($observedTotal),
            'comparativo' => $this->comparativoBlock($observedTotal, $expectedAnnual),
        ];
    }

    /**
     * @return list<array{sort_key: string, date: string, year: int, month: int, valor: float, description: string, date_source?: string}>
     */
    private function extractCreditsFromRow(MunicipalTransferSnapshot $row, int $filterYear, string $descBase): array
    {
        $meta = $this->decodeMeta($row);
        $out = [];

        $lancamentos = $meta['lancamentos'] ?? null;
        if (is_array($lancamentos) && $lancamentos !== []) {
            foreach ($lancamentos as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $valor = isset($item['valor']) && is_numeric($item['valor']) ? (float) $item['valor'] : 0.0;
                if ($valor <= 0) {
                    continue;
                }
                $date = trim((string) ($item['data'] ?? $item['date'] ?? ''));
                if ($date === '') {
                    continue;
                }
                $parsed = $this->parseSortKeyFromBrDate($date);
                if ($parsed === null) {
                    continue;
                }
                $historico = trim((string) ($item['historico'] ?? $item['description'] ?? ''));
                $out[] = [
                    'sort_key' => $parsed['sort_key'],
                    'date' => $parsed['date'],
                    'year' => $parsed['year'],
                    'month' => $parsed['month'],
                    'valor' => $valor,
                    'description' => $descBase.($historico !== '' ? ' — '.$historico : ''),
                    'date_source' => 'extrato',
                ];
            }

            return $out;
        }

        $mensal = $this->extractMensalFromMeta($meta);
        if ($mensal !== []) {
            ksort($mensal);
            foreach ($mensal as $month => $valor) {
                $month = (int) $month;
                if ($month < 1 || $month > 12 || $valor <= 0) {
                    continue;
                }
                $date = $this->repasseDateForMonth($filterYear, $month);
                $out[] = [
                    'sort_key' => sprintf('%04d-%02d-99', $filterYear, $month),
                    'date' => $date,
                    'year' => $filterYear,
                    'month' => $month,
                    'valor' => $valor,
                    'description' => $descBase.' — '.__('Repasse ref. :mes/:ano', [
                        'mes' => $this->monthName($month),
                        'ano' => (string) $filterYear,
                    ]),
                    'date_source' => 'fim_mes',
                ];
            }

            return $out;
        }

        $valor = (float) $row->valor;
        if ($valor <= 0) {
            return [];
        }

        $date = $row->imported_at?->format('d/m/Y') ?? '—';
        $parsed = $date !== '—' ? $this->parseSortKeyFromBrDate($date) : null;
        $year = $parsed['year'] ?? $filterYear;
        $month = $parsed['month'] ?? 0;

        $out[] = [
            'sort_key' => $parsed['sort_key'] ?? sprintf('%04d-00-99', $filterYear),
            'date' => $date,
            'year' => $year,
            'month' => $month,
            'valor' => $valor,
            'description' => $descBase.' — '.__('Crédito único :ano', ['ano' => (string) $filterYear]),
            'date_source' => 'importacao',
        ];

        return $out;
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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function lineEntry(
        string $lineType,
        string $date,
        string $description,
        ?float $credit,
        ?float $debit,
        float $balanceAnnual,
        string $fonte,
        array $extra = [],
    ): array {
        return array_merge([
            'line_type' => $lineType,
            'date' => $date,
            'description' => $description,
            'credit' => $credit !== null && $credit > 0 ? DiscrepanciesFundingImpact::formatBrl($credit) : null,
            'debit' => $debit !== null && $debit > 0 ? DiscrepanciesFundingImpact::formatBrl($debit) : null,
            'balance' => DiscrepanciesFundingImpact::formatBrl($balanceAnnual),
            'balance_annual_fmt' => DiscrepanciesFundingImpact::formatBrl($balanceAnnual),
            'fonte' => $fonte,
            'valor_fmt' => $credit !== null ? DiscrepanciesFundingImpact::formatBrl($credit) : null,
            'is_subtotal' => in_array($lineType, ['month_total', 'year_total', 'opening'], true),
        ], $extra);
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
                'line_type' => 'info',
                'date' => '—',
                'description' => __('Sem repasses FUNDEB importados para :city / :ano. Use Admin → Dados públicos → Repasses.', [
                    'city' => $city->name,
                    'ano' => (string) $ano,
                ]),
                'credit' => null,
                'debit' => null,
                'balance' => null,
                'balance_annual_fmt' => null,
                'fonte' => '—',
                'valor_fmt' => '—',
                'is_subtotal' => false,
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
            'lines' => [],
            'by_period' => [],
            'by_year' => [],
            'total_fmt' => '—',
            'comparativo' => $this->comparativoBlock(0.0, $expectedAnnual),
        ];
    }

    private function isFundebRow(MunicipalTransferSnapshot $row): bool
    {
        return FundebTransferScope::matchesFinanceRealtimeProgram($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(MunicipalTransferSnapshot $row): array
    {
        $meta = $row->meta;
        if (is_array($meta)) {
            return $meta;
        }
        if (! is_string($meta) || $meta === '') {
            return [];
        }
        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, float>
     */
    private function extractMensalFromMeta(array $meta): array
    {
        $mensal = $meta['mensal'] ?? null;
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

    private function repasseDateForMonth(int $year, int $month): string
    {
        if ($month < 1 || $month > 12) {
            return '31/12/'.$year;
        }

        return sprintf('%02d/%02d/%04d', (int) date('t', mktime(0, 0, 0, $month, 1, $year)), $month, $year);
    }

    /**
     * @return array{sort_key: string, date: string, year: int, month: int}|null
     */
    private function parseSortKeyFromBrDate(string $brDate): ?array
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($brDate), $m) !== 1) {
            return null;
        }

        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = (int) $m[3];
        if ($month < 1 || $month > 12 || $year < 2000) {
            return null;
        }

        return [
            'sort_key' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'date' => sprintf('%02d/%02d/%04d', $day, $month, $year),
            'year' => $year,
            'month' => $month,
        ];
    }

    private function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month] ?? (string) $month;
    }

    private static function monthPeriodLabelStatic(int $year, int $month): string
    {
        $names = self::MONTH_NAMES;

        return ($names[$month] ?? (string) $month).'/'.$year;
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
            'consolidado' => __('Consolidado (todas as fontes)'),
            default => $fonte,
        };
    }
}
