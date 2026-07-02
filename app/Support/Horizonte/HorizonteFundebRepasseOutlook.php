<?php

namespace App\Support\Horizonte;

use App\Models\MunicipalTransferSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Funding\FinanceRealtimeYearEndOutlook;
use App\Support\Funding\FundebExtratoFontePriority;
use App\Support\Funding\FundebPortariaExpectation;
use App\Support\Funding\FundebTransferScope;
use Illuminate\Support\Facades\Schema;

/**
 * Repasse FUNDEB do ano civil corrente (observado × previsão) para o tooltip do mapa Horizonte.
 */
final class HorizonteFundebRepasseOutlook
{
    public static function currentYear(): int
    {
        return (int) date('Y');
    }

    /**
     * @param  array<string, array{
     *     complementacao_total: float,
     *     receita_total: ?float,
     *     matriculas_base: ?int,
     *     matriculas_fonte: ?string,
     *     vaaf: ?float,
     *     ano: int
     * }>  $fundebRefYearByIbge
     * @param  array<string, array{
     *     complementacao_total: float,
     *     receita_total: ?float,
     *     matriculas_base: ?int,
     *     matriculas_fonte: ?string,
     *     vaaf: ?float,
     *     ano: int
     * }>  $fundebCurrentYearByIbge
     * @param  array<string, array{matriculas_total: int, ano: int}>  $censoByIbge
     * @return array<string, array{
     *     ano: int,
     *     observed: ?float,
     *     expected: ?float,
     *     projected: ?float,
     *     balance: ?float,
     *     pct_done: ?float,
     *     months_with_transfers: int,
     *     last_transfer_month: ?int,
     *     last_transfer_label: ?string,
     *     last_recorded_at: ?string,
     *     outlook: string,
     *     outlook_label: string,
     *     outlook_detail: string,
     *     gap: ?float,
     *     gap_sign: string,
     *     portaria_receita: ?float,
     *     portaria_base_mat_vaaf: ?float,
     *     portaria_vaaf: ?float,
     *     portaria_matriculas: ?int,
     *     portaria_matriculas_fonte: ?string,
     *     portaria_adjustments: list<array{key: string, label: string, value: float, value_fmt: string}>,
     *     portaria_adjustments_note: ?string,
     *     expected_source: string
     * }>
     */
    public static function byIbge(
        int $refYear,
        ?string $ibgePrefix,
        array $fundebRefYearByIbge,
        array $fundebCurrentYearByIbge,
        array $censoByIbge,
    ): array {
        $currentYear = self::currentYear();
        if ($currentYear <= $refYear) {
            return [];
        }

        $observedByIbge = self::loadObservedFundeb($currentYear, $ibgePrefix);
        $ibgeCodes = array_unique(array_merge(
            array_keys($fundebCurrentYearByIbge),
            array_keys($fundebRefYearByIbge),
            array_keys($observedByIbge),
            array_keys($censoByIbge),
        ));

        $out = [];
        foreach ($ibgeCodes as $ibge) {
            $pack = self::buildForIbge(
                $ibge,
                $currentYear,
                $fundebCurrentYearByIbge[$ibge] ?? null,
                $fundebRefYearByIbge[$ibge] ?? null,
                $censoByIbge[$ibge] ?? null,
                $observedByIbge[$ibge] ?? null,
            );
            if ($pack !== null) {
                $out[$ibge] = $pack;
            }
        }

        return $out;
    }

    /**
     * @param  array{
     *     complementacao_total: float,
     *     receita_total: ?float,
     *     matriculas_base: ?int,
     *     matriculas_fonte: ?string,
     *     vaaf: ?float,
     *     ano: int
     * }|null  $fundebCurrent
     * @param  array{
     *     complementacao_total: float,
     *     receita_total: ?float,
     *     matriculas_base: ?int,
     *     matriculas_fonte: ?string,
     *     vaaf: ?float,
     *     ano: int
     * }|null  $fundebRef
     * @param  array{matriculas_total: int, ano: int}|null  $censo
     * @param  array{observed: float, rows: list<MunicipalTransferSnapshot>}|null  $observedPack
     * @return array{
     *     ano: int,
     *     observed: ?float,
     *     expected: ?float,
     *     projected: ?float,
     *     balance: ?float,
     *     pct_done: ?float,
     *     months_with_transfers: int,
     *     last_transfer_month: ?int,
     *     last_transfer_label: ?string,
     *     last_recorded_at: ?string,
     *     outlook: string,
     *     outlook_label: string,
     *     outlook_detail: string,
     *     gap: ?float,
     *     gap_sign: string,
     *     portaria_receita: ?float,
     *     portaria_base_mat_vaaf: ?float,
     *     portaria_vaaf: ?float,
     *     portaria_matriculas: ?int,
     *     portaria_matriculas_fonte: ?string,
     *     portaria_adjustments: list<array{key: string, label: string, value: float, value_fmt: string}>,
     *     portaria_adjustments_note: ?string,
     *     expected_source: string
     * }|null
     */
    private static function buildForIbge(
        string $ibge,
        int $currentYear,
        ?array $fundebCurrent,
        ?array $fundebRef,
        ?array $censo,
        ?array $observedPack,
    ): ?array {
        $matriculasPortaria = (int) ($fundebCurrent['matriculas_base'] ?? 0);
        $matriculasFonte = isset($fundebCurrent['matriculas_fonte'])
            ? (string) $fundebCurrent['matriculas_fonte']
            : null;
        if ($matriculasPortaria <= 0 && $fundebRef !== null) {
            $matriculasPortaria = (int) ($fundebRef['matriculas_base'] ?? 0);
            if ($matriculasFonte === null || trim($matriculasFonte) === '') {
                $matriculasFonte = isset($fundebRef['matriculas_fonte'])
                    ? (string) $fundebRef['matriculas_fonte']
                    : null;
            }
        }

        $matriculas = $matriculasPortaria;
        if ($matriculas <= 0) {
            $matriculas = (int) ($censo['matriculas_total'] ?? 0);
        }
        if ($matriculas <= 0 && $fundebRef !== null) {
            $matriculas = (int) ($fundebRef['matriculas_base'] ?? 0);
        }

        $vaaf = (float) ($fundebCurrent['vaaf'] ?? 0);
        if ($vaaf <= 0 && $fundebRef !== null) {
            $vaaf = (float) ($fundebRef['vaaf'] ?? 0);
        }

        $reference = $fundebCurrent ?? $fundebRef;
        $expectation = FundebPortariaExpectation::buildAnnual($matriculas, $vaaf, $reference);
        $expected = (float) ($expectation['annual'] ?? 0);
        $observed = $observedPack !== null ? max(0.0, (float) ($observedPack['observed'] ?? 0)) : 0.0;
        $rows = $observedPack['rows'] ?? [];

        if ($expected <= 0 && $observed <= 0) {
            return null;
        }

        $periodic = FundebPortariaExpectation::periodicSchedule($expected, $currentYear, $rows);
        $outlook = FinanceRealtimeYearEndOutlook::build($expected, $observed, $currentYear, $periodic);
        $temporal = HorizonteFundebTransferTemporal::lastRecorded($rows, $currentYear);

        $pctDone = $expected > 0
            ? round(min(100.0, ($observed / $expected) * 100.0), 1)
            : null;

        $baseMatVaaf = (float) ($expectation['base_mat_vaaf'] ?? 0);
        $receitaPortaria = $expectation['receita_portaria'] ?? null;
        $complTotal = $expectation['complementacao_total'] ?? null;
        $portariaTotal = (float) ($expectation['portaria_total_previsto'] ?? $expected);
        $gap = (float) ($outlook['gap_until_december'] ?? 0);

        return [
            'ano' => $currentYear,
            'observed' => $observed > 0 ? $observed : null,
            'expected' => $expected > 0 ? $expected : null,
            'projected' => (float) ($outlook['projected_repass_until_december'] ?? 0) > 0
                ? (float) $outlook['projected_repass_until_december']
                : null,
            'balance' => (float) ($outlook['balance_to_repass'] ?? 0) > 0
                ? (float) $outlook['balance_to_repass']
                : null,
            'pct_done' => $pctDone,
            'months_with_transfers' => (int) ($outlook['months_with_transfers'] ?? 0),
            'last_transfer_month' => $temporal !== null && ($temporal['month'] ?? 0) > 0
                ? (int) $temporal['month']
                : null,
            'last_transfer_label' => $temporal['label'] ?? null,
            'last_recorded_at' => $temporal['recorded_at'] ?? null,
            'outlook' => (string) ($outlook['outlook'] ?? 'unknown'),
            'outlook_label' => (string) ($outlook['outlook_label'] ?? ''),
            'outlook_detail' => (string) ($outlook['outlook_detail'] ?? ''),
            'gap' => $gap !== 0.0 ? $gap : null,
            'gap_sign' => (string) ($outlook['gap_sign'] ?? 'neutral'),
            'portaria_receita' => is_numeric($receitaPortaria) && (float) $receitaPortaria > 0
                ? (float) $receitaPortaria
                : null,
            'portaria_complementacao_total' => is_numeric($complTotal) && (float) $complTotal > 0
                ? (float) $complTotal
                : null,
            'portaria_total_previsto' => $portariaTotal > 0 ? $portariaTotal : null,
            'portaria_base_mat_vaaf' => $baseMatVaaf > 0 ? $baseMatVaaf : null,
            'portaria_vaaf' => $vaaf > 0 ? $vaaf : null,
            'portaria_matriculas' => $matriculasPortaria > 0 ? $matriculasPortaria : null,
            'portaria_matriculas_fonte' => is_string($matriculasFonte) && trim($matriculasFonte) !== ''
                ? trim($matriculasFonte)
                : null,
            'portaria_adjustments' => $expectation['adjustments'] ?? [],
            'portaria_adjustments_note' => $expectation['adjustments_note'] ?? null,
            'expected_source' => (string) ($expectation['source'] ?? 'matricula_vaaf'),
        ];
    }

    /**
     * @return array<string, array{observed: float, rows: list<MunicipalTransferSnapshot>}>
     */
    private static function loadObservedFundeb(int $year, ?string $ibgePrefix): array
    {
        if (! Schema::hasTable('municipal_transfer_snapshots')) {
            return [];
        }

        $query = MunicipalTransferSnapshot::query()->forYear($year);
        if ($ibgePrefix !== null && $ibgePrefix !== '') {
            $query->where('ibge_municipio', 'like', $ibgePrefix.'%');
        }

        /** @var array<string, list<MunicipalTransferSnapshot>> $byIbge */
        $byIbge = [];
        foreach ($query->get() as $row) {
            if (! $row instanceof MunicipalTransferSnapshot) {
                continue;
            }
            if (! FundebTransferScope::matchesFinanceRealtimeProgram($row)) {
                continue;
            }
            if (FundebTransferScope::isUfAggregated($row)) {
                continue;
            }
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $row->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $byIbge[$ibge][] = $row;
        }

        $out = [];
        foreach ($byIbge as $ibge => $rows) {
            $primary = FundebExtratoFontePriority::pickPrimaryFundebRows($rows);
            $observed = round(array_sum(array_map(static fn ($r) => (float) $r->valor, $primary)), 2);
            $out[$ibge] = [
                'observed' => $observed,
                'rows' => $primary,
            ];
        }

        return $out;
    }
}
