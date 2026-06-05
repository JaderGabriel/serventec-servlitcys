<?php

namespace App\Support\Funding;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Services\Funding\TesouroTransferenciasCsvService;
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
    /**
     * @param  list<MunicipalTransferSnapshot>  $rows
     * @param  list<MunicipalTransferSnapshot>  $hintRows  Snapshots brutos (incl. UF) para mensagem quando vazio.
     */
    public function build(array $rows, City $city, int $filterYear, float $expectedAnnual, array $hintRows = []): array
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
            $diagnosticRows = $hintRows !== [] ? $hintRows : $rows;

            return [
                'cycles' => [$this->emptyCycle($city, $filterYear, $diagnosticRows)],
                'consolidado' => $this->emptyConsolidado($expectedAnnual),
            ];
        }

        uksort($byFonte, static fn (string $a, string $b): int => FundebExtratoFontePriority::rank($a) <=> FundebExtratoFontePriority::rank($b));

        $cycles = [];

        foreach ($byFonte as $fonte => $fonteRows) {
            $cycles[] = $this->buildCycle($fonte, $fonteRows, $filterYear, $expectedAnnual);
        }

        return [
            'cycles' => $cycles,
            'consolidado' => $this->buildConsolidado($cycles, $expectedAnnual, $filterYear),
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

        $schedule = FundebPortariaExpectation::periodicSchedule($expectedAnnual, $filterYear, $fonteRows);
        $expectedMonthly = (float) ($schedule['monthly'] ?? 0);
        $byPeriod = $this->periodRowsFromBuckets($periodBuckets, $filterYear, $expectedMonthly);
        $cycleTotal = round(array_sum(array_map(static fn (array $p): float => (float) $p['credit'], $byPeriod)), 2);
        $expectedCycle = $this->hasAnnualBucket($periodBuckets)
            ? $expectedAnnual
            : (float) ($schedule['periodic_expected'] ?? ($expectedMonthly * max(1, (int) ($schedule['months_with_transfers'] ?? 1))));

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
                    'import_reference' => $credit['import_reference'] ?? null,
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
     * Conciliação: usa a fonte de referência (prioridade CKAN > BB > SISWEB) e só destaca divergências entre fontes.
     *
     * @param  list<array<string, mixed>>  $cycles
     * @return array<string, mixed>
     */
    private function buildConsolidado(array $cycles, float $expectedAnnual, int $filterYear): array
    {
        if ($cycles === []) {
            return $this->emptyConsolidado($expectedAnnual);
        }

        $reference = $this->pickReferenceCycle($cycles);
        $observedTotal = round((float) ($reference['cycle_total'] ?? 0), 2);
        $divergences = $this->sourceDivergences($cycles, $reference);
        $sourcesAligned = count($cycles) > 1 && $divergences === [];

        $byPeriod = is_array($reference['by_period'] ?? null) ? $reference['by_period'] : [];
        $yearRows = [];
        if ($observedTotal > 0) {
            $yearRows[] = [
                'year' => $filterYear,
                'year_label' => (string) $filterYear,
                'credit_fmt' => DiscrepanciesFundingImpact::formatBrl($observedTotal),
                'comparativo' => $this->comparativoBlock($observedTotal, $expectedAnnual),
            ];
        }

        return [
            'lines' => $this->buildReconciliationLines($reference, $divergences, $expectedAnnual, $sourcesAligned),
            'by_period' => $byPeriod,
            'by_year' => $yearRows,
            'total_fmt' => DiscrepanciesFundingImpact::formatBrl($observedTotal),
            'reference_fonte' => (string) ($reference['fonte'] ?? ''),
            'reference_fonte_label' => (string) ($reference['fonte_label'] ?? ''),
            'divergences' => $divergences,
            'sources_aligned' => $sourcesAligned,
            'comparativo' => $this->comparativoBlock($observedTotal, $expectedAnnual),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cycles
     * @return array<string, mixed>
     */
    private function pickReferenceCycle(array $cycles): array
    {
        $best = $cycles[0];
        $bestRank = FundebExtratoFontePriority::rank((string) ($best['fonte'] ?? ''));

        foreach ($cycles as $cycle) {
            $rank = FundebExtratoFontePriority::rank((string) ($cycle['fonte'] ?? ''));
            if ($rank < $bestRank) {
                $best = $cycle;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $cycles
     * @return list<array{fonte: string, fonte_label: string, total_fmt: string, delta_fmt: string, delta_sign: string}>
     */
    private function sourceDivergences(array $cycles, array $reference): array
    {
        $refTotal = (float) ($reference['cycle_total'] ?? 0);
        $refFonte = (string) ($reference['fonte'] ?? '');
        $out = [];

        foreach ($cycles as $cycle) {
            $fonte = (string) ($cycle['fonte'] ?? '');
            if ($fonte === '' || $fonte === $refFonte) {
                continue;
            }
            $total = (float) ($cycle['cycle_total'] ?? 0);
            $delta = MoneyMath::roundMoney($total - $refTotal);
            if (abs($delta) < 0.01) {
                continue;
            }
            $out[] = [
                'fonte' => $fonte,
                'fonte_label' => (string) ($cycle['fonte_label'] ?? $fonte),
                'total_fmt' => (string) ($cycle['cycle_total_fmt'] ?? DiscrepanciesFundingImpact::formatBrl($total)),
                'delta_fmt' => DiscrepanciesFundingImpact::formatBrl(abs($delta)),
                'delta_sign' => $delta >= 0 ? 'positive' : 'negative',
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{fonte: string, fonte_label: string, total_fmt: string, delta_fmt: string, delta_sign: string}>  $divergences
     * @return list<array<string, mixed>>
     */
    private function buildReconciliationLines(
        array $reference,
        array $divergences,
        float $expectedAnnual,
        bool $sourcesAligned,
    ): array {
        $lines = [];
        $refLabel = (string) ($reference['fonte_label'] ?? __('Fonte de referência'));
        $refTotal = (float) ($reference['cycle_total'] ?? 0);

        $lines[] = $this->lineEntry(
            'info',
            '—',
            __('Referência (:fonte): :valor', [
                'fonte' => $refLabel,
                'valor' => DiscrepanciesFundingImpact::formatBrl($refTotal),
            ]),
            null,
            null,
            $refTotal,
            'conciliacao',
        );

        if ($sourcesAligned) {
            $lines[] = $this->lineEntry(
                'info',
                '—',
                __('Demais fontes importadas coincidem com a referência (não são somadas).'),
                null,
                null,
                $refTotal,
                'conciliacao',
            );
        }

        foreach ($divergences as $divergence) {
            $sign = ($divergence['delta_sign'] ?? '') === 'negative' ? '−' : '+';
            $lines[] = $this->lineEntry(
                'info',
                '—',
                __(':fonte: :total — diferença vs. referência :delta', [
                    'fonte' => $divergence['fonte_label'],
                    'total' => $divergence['total_fmt'],
                    'delta' => $sign.$divergence['delta_fmt'],
                ]),
                null,
                null,
                $refTotal,
                'conciliacao',
            );
        }

        $expectedCmp = $this->comparativoBlock($refTotal, $expectedAnnual);
        $expSign = ($expectedCmp['delta_sign'] ?? '') === 'negative' ? '−' : '+';
        $lines[] = $this->lineEntry(
            'info',
            '—',
            __('Expectativa FUNDEB: :expected — diferença :delta', [
                'expected' => $expectedCmp['expected_fmt'],
                'delta' => $expSign.$expectedCmp['delta_fmt'],
            ]),
            null,
            null,
            $refTotal,
            'conciliacao',
            [
                'delta_pct' => $expectedCmp['delta_pct'],
            ],
        );

        return $lines;
    }

    /**
     * @return list<array{sort_key: string, date: string, year: int, month: int, valor: float, description: string, date_source?: string}>
     */
    private function extractCreditsFromRow(MunicipalTransferSnapshot $row, int $filterYear, string $descBase): array
    {
        $meta = $this->decodeMeta($row);
        $importReference = $this->importReferenceLabel($row);
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
                    'import_reference' => $importReference,
                ];
            }

            return $out;
        }

        foreach ($this->extractRepasseItemsFromMeta($meta, $filterYear, $descBase, $importReference) as $credit) {
            $out[] = $credit;
        }
        if ($out !== []) {
            return $out;
        }

        $mensal = $this->extractMensalFromMeta($meta, $filterYear);
        if ($mensal === [] && in_array((string) $row->fonte, ['tesouro_csv', 'sisweb_ckan'], true)) {
            $mensal = app(TesouroTransferenciasCsvService::class)->resolveMensalForSnapshotMeta($meta, $filterYear);
        }
        if ($mensal !== []) {
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
                    'import_reference' => $importReference,
                ];
            }

            return $out;
        }

        $valor = (float) $row->valor;
        if ($valor <= 0) {
            return [];
        }

        $eventDate = $this->resolveEventDateFromMeta($meta, $filterYear);
        $parsed = $eventDate !== null ? $this->parseSortKeyFromBrDate($eventDate) : null;
        $year = $parsed['year'] ?? $filterYear;
        $month = $parsed['month'] ?? 0;

        $out[] = [
            'sort_key' => $parsed['sort_key'] ?? sprintf('%04d-00-99', $filterYear),
            'date' => $eventDate ?? $this->repasseDateForMonth($filterYear, 12),
            'year' => $year,
            'month' => $month,
            'valor' => $valor,
            'description' => $descBase.' — '.__('Repasse :ano', ['ano' => (string) $filterYear]),
            'date_source' => $eventDate !== null ? 'repasse' : 'fim_ano',
            'import_reference' => $importReference,
        ];

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{sort_key: string, date: string, year: int, month: int, valor: float, description: string, date_source?: string, import_reference?: ?string}>
     */
    private function extractRepasseItemsFromMeta(array $meta, int $filterYear, string $descBase, ?string $importReference): array
    {
        $lists = [];
        foreach (['repasses', 'parcelas', 'transferencias', 'pagamentos'] as $key) {
            if (is_array($meta[$key] ?? null) && $meta[$key] !== []) {
                $lists[] = $meta[$key];
            }
        }

        $out = [];
        foreach ($lists as $items) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $valor = isset($item['valor']) && is_numeric($item['valor'])
                    ? (float) $item['valor']
                    : (isset($item['value']) && is_numeric($item['value']) ? (float) $item['value'] : 0.0);
                if ($valor <= 0) {
                    continue;
                }
                $date = trim((string) ($item['data'] ?? $item['date'] ?? $item['data_pagamento'] ?? $item['data_repasse'] ?? ''));
                $month = isset($item['mes']) && is_numeric($item['mes']) ? (int) $item['mes'] : 0;
                $year = isset($item['ano']) && is_numeric($item['ano']) ? (int) $item['ano'] : $filterYear;
                if ($date === '' && $month >= 1 && $month <= 12) {
                    $date = $this->repasseDateForMonth($year, $month);
                }
                if ($date === '') {
                    continue;
                }
                $parsed = $this->parseSortKeyFromBrDate($date);
                if ($parsed === null) {
                    continue;
                }
                if ($parsed['year'] !== $filterYear && $year !== $filterYear) {
                    continue;
                }
                $label = trim((string) ($item['historico'] ?? $item['description'] ?? $item['label'] ?? ''));
                $out[] = [
                    'sort_key' => $parsed['sort_key'],
                    'date' => $parsed['date'],
                    'year' => $parsed['year'],
                    'month' => $parsed['month'],
                    'valor' => $valor,
                    'description' => $descBase.($label !== '' ? ' — '.$label : ''),
                    'date_source' => 'repasse',
                    'import_reference' => $importReference,
                ];
            }
        }

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
    /**
     * @param  list<MunicipalTransferSnapshot>  $diagnosticRows
     */
    private function emptyCycle(City $city, int $ano, array $diagnosticRows = []): array
    {
        $description = $this->emptyCycleDescription($city, $ano, $diagnosticRows);

        return [
            'fonte' => '—',
            'fonte_label' => __('Sem dados'),
            'lines' => [[
                'line_type' => 'info',
                'date' => '—',
                'description' => $description,
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
     * @param  list<MunicipalTransferSnapshot>  $rows
     */
    private function emptyCycleDescription(City $city, int $ano, array $rows): string
    {
        if (
            FundebTransferScope::hasUfAggregatedFundebSnapshots($rows)
            && ! FundebTransferScope::hasMunicipalFundebSnapshots($rows)
        ) {
            return __('Há importação da publicação STN (total da UF), mas não repasses municipais para :city / :ano. A fila pode mostrar «carga concluída» só com esse total — reexecute Admin → Dados públicos → Repasses até aparecer CKAN/SISWEB (tesouro_csv ou sisweb_ckan) no log da tarefa.', [
                'city' => $city->name,
                'ano' => (string) $ano,
            ]);
        }

        return __('Sem repasses FUNDEB importados para :city / :ano. Use Admin → Dados públicos → Repasses.', [
            'city' => $city->name,
            'ano' => (string) $ano,
        ]);
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
    private function extractMensalFromMeta(array $meta, int $filterYear = 0): array
    {
        $mensal = $meta['mensal'] ?? null;
        if (! is_array($mensal) || $mensal === []) {
            return [];
        }

        if ($filterYear > 0 && (isset($mensal[$filterYear]) || isset($mensal[(string) $filterYear]))) {
            $slice = $mensal[$filterYear] ?? $mensal[(string) $filterYear];

            return $this->normalizeMensalMap(is_array($slice) ? $slice : []);
        }

        $firstKey = array_key_first($mensal);
        if (is_array($mensal[$firstKey] ?? null)) {
            return [];
        }

        return $this->normalizeMensalMap($mensal);
    }

    /**
     * @param  array<int|string, mixed>  $map
     * @return array<int, float>
     */
    private function normalizeMensalMap(array $map): array
    {
        $out = [];
        foreach ($map as $month => $valor) {
            if (! is_numeric($valor) || (float) $valor <= 0) {
                continue;
            }
            $m = (int) $month;
            if ($m >= 1 && $m <= 12) {
                $out[$m] = (float) $valor;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveEventDateFromMeta(array $meta, int $filterYear): ?string
    {
        foreach (['data_repasse', 'data_pagamento', 'data_transferencia', 'data', 'date'] as $key) {
            $raw = trim((string) ($meta[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            $parsed = $this->parseSortKeyFromBrDate($raw);

            return $parsed !== null ? $parsed['date'] : null;
        }

        $month = isset($meta['mes']) && is_numeric($meta['mes']) ? (int) $meta['mes'] : 0;
        if ($month >= 1 && $month <= 12) {
            return $this->repasseDateForMonth($filterYear, $month);
        }

        return null;
    }

    private function importReferenceLabel(MunicipalTransferSnapshot $row): ?string
    {
        return $row->imported_at?->format('d/m/Y H:i');
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
            'conciliacao' => __('Conciliação entre fontes'),
            default => $fonte,
        };
    }
}
