<?php

namespace App\Support\Horizonte;

use App\Models\FundebMunicipioReference;
use App\Models\MunicipalTransferSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Brazil\BrazilUfNames;
use App\Support\Brazil\IbgeUfFromCode;
use App\Support\Fundeb\FundebFndePortariaCatalog;
use App\Support\Funding\FundebTransferScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Agregados FUNDEB por UF para o cabeçalho regional do mapa Horizonte.
 */
final class HorizonteUfFundebInsights
{
    /**
     * @param  list<array<string, mixed>>  $regionalMarkers
     * @param  array<string, array<string, mixed>>|null  $nationalByUf
     * @return array<string, mixed>
     */
    public static function forRegional(
        string $uf,
        array $regionalMarkers,
        int $refYear,
        int $currentYear,
        ?array $nationalByUf = null,
    ): array {
        $uf = strtoupper(trim($uf));
        $snapshot = self::aggregateMarkers($regionalMarkers);
        $portaria = self::portariaMeta($uf, $refYear, $regionalMarkers);
        $realtime = self::aggregateRealtime($regionalMarkers, $currentYear, $refYear);
        $national = $nationalByUf !== null
            ? self::nationalComparison($uf, $snapshot, $realtime, $nationalByUf)
            : null;

        return [
            'uf' => $uf,
            'uf_name' => BrazilUfNames::name($uf),
            'reference_year' => $refYear,
            'current_year' => $currentYear,
            ...$snapshot,
            'portaria' => $portaria,
            'realtime' => $realtime,
            'national' => $national,
        ];
    }

    /**
     * Métricas FUNDEB por UF para tooltip do mapa nacional (rank, total, % federal).
     *
     * @param  array<string, array<string, mixed>>  $nationalByUf
     * @return array<string, array<string, mixed>>
     */
    public static function overviewFundebMetrics(array $nationalByUf): array
    {
        if ($nationalByUf === []) {
            return [];
        }

        $receitaRows = [];
        $nationalReceita = 0.0;
        $nationalCompl = 0.0;

        foreach ($nationalByUf as $code => $row) {
            $receita = (float) ($row['receita_portaria_total'] ?? 0);
            $compl = (float) ($row['complementacao_total'] ?? 0);
            if ($receita > 0) {
                $receitaRows[] = ['uf' => strtoupper($code), 'value' => $receita];
                $nationalReceita += $receita;
            }
            if ($compl > 0) {
                $nationalCompl += $compl;
            }
        }

        usort($receitaRows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        $rankByUf = [];
        foreach ($receitaRows as $i => $row) {
            $rankByUf[$row['uf']] = $i + 1;
        }

        $totalUfs = count($receitaRows);
        $out = [];

        foreach ($nationalByUf as $code => $row) {
            $uf = strtoupper(trim((string) $code));
            if ($uf === '') {
                continue;
            }

            $receita = (float) ($row['receita_portaria_total'] ?? 0);
            $compl = (float) ($row['complementacao_total'] ?? 0);
            $total = $receita + $compl;

            if ($receita <= 0 && $compl <= 0) {
                continue;
            }

            $out[$uf] = [
                'exercise_year' => is_numeric($row['exercise_year'] ?? null)
                    ? (int) $row['exercise_year']
                    : null,
                'receita_total' => $receita > 0 ? round($receita, 2) : null,
                'complementacao_total' => $compl > 0 ? round($compl, 2) : null,
                'total_previsto' => $total > 0 ? round($total, 2) : null,
                'rank_receita' => $rankByUf[$uf] ?? null,
                'total_ufs' => $totalUfs,
                'share_receita_pct' => $nationalReceita > 0 && $receita > 0
                    ? round(($receita / $nationalReceita) * 100.0, 2)
                    : null,
                'pct_federal' => $total > 0 && $compl > 0
                    ? round(($compl / $total) * 100.0, 1)
                    : null,
                'share_complementacao_pct' => $nationalCompl > 0 && $compl > 0
                    ? round(($compl / $nationalCompl) * 100.0, 2)
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return array<string, array<string, mixed>>
     */
    public static function aggregateNationalByUf(array $markers, int $refYear, int $currentYear): array
    {
        $byUf = [];
        foreach ($markers as $marker) {
            $uf = strtoupper(trim((string) ($marker['uf'] ?? '')));
            if ($uf === '') {
                continue;
            }
            if (! isset($byUf[$uf])) {
                $byUf[$uf] = [];
            }
            $byUf[$uf][] = $marker;
        }

        $out = [];
        foreach ($byUf as $uf => $ufMarkers) {
            $snapshot = self::aggregateMarkers($ufMarkers);
            $realtime = self::aggregateRealtime($ufMarkers, $currentYear, $refYear);
            $out[$uf] = array_merge($snapshot, ['realtime' => $realtime]);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return array<string, mixed>
     */
    private static function aggregateMarkers(array $markers): array
    {
        $total = count($markers);
        $withFundeb = 0;
        $withRealtime = 0;
        $receitaTotal = 0.0;
        $complementacaoTotal = 0.0;
        $matriculas = 0;
        $exerciseYears = [];

        foreach ($markers as $marker) {
            if (! ($marker['has_fundeb'] ?? false)) {
                continue;
            }
            $withFundeb++;

            $receita = $marker['fundeb_receita_total'] ?? null;
            if (is_numeric($receita) && (float) $receita > 0) {
                $receitaTotal += (float) $receita;
            }

            $compl = $marker['complementacao_fundeb'] ?? null;
            if (is_numeric($compl) && (float) $compl > 0) {
                $complementacaoTotal += (float) $compl;
            }

            $mat = $marker['fundeb_matriculas_base'] ?? null;
            if (is_numeric($mat) && (int) $mat > 0) {
                $matriculas += (int) $mat;
            }

            $ano = (int) ($marker['fundeb_ano'] ?? 0);
            if ($ano > 0) {
                $exerciseYears[$ano] = ($exerciseYears[$ano] ?? 0) + 1;
            }

            if (($marker['fundeb_realtime_observed'] ?? null) !== null
                || ($marker['fundeb_realtime_expected'] ?? null) !== null) {
                $withRealtime++;
            }
        }

        arsort($exerciseYears);
        $exerciseYear = $exerciseYears !== [] ? (int) array_key_first($exerciseYears) : null;

        return [
            'municipalities_total' => $total,
            'municipalities_with_fundeb' => $withFundeb,
            'municipalities_with_realtime' => $withRealtime,
            'receita_portaria_total' => $receitaTotal > 0 ? round($receitaTotal, 2) : null,
            'complementacao_total' => $complementacaoTotal > 0 ? round($complementacaoTotal, 2) : null,
            'matriculas_fundeb' => $matriculas > 0 ? $matriculas : null,
            'exercise_year' => $exerciseYear,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return array<string, mixed>
     */
    private static function aggregateRealtime(array $markers, int $currentYear, int $refYear): array
    {
        if ($currentYear <= $refYear) {
            return [
                'available' => false,
                'ano' => $currentYear,
            ];
        }

        $observed = 0.0;
        $expected = 0.0;
        $balance = 0.0;
        $withData = 0;
        $lastRecordedAt = null;
        $lastTransferLabel = null;

        foreach ($markers as $marker) {
            $obs = $marker['fundeb_realtime_observed'] ?? null;
            $exp = $marker['fundeb_realtime_expected'] ?? null;
            if (! is_numeric($obs) && ! is_numeric($exp)) {
                continue;
            }
            $withData++;
            if (is_numeric($obs) && (float) $obs > 0) {
                $observed += (float) $obs;
            }
            if (is_numeric($exp) && (float) $exp > 0) {
                $expected += (float) $exp;
            }
            $bal = $marker['fundeb_realtime_balance'] ?? null;
            if (is_numeric($bal) && (float) $bal > 0) {
                $balance += (float) $bal;
            }

            $recordedAt = $marker['fundeb_realtime_last_recorded_at'] ?? null;
            if (is_string($recordedAt) && $recordedAt !== '') {
                $ts = strtotime($recordedAt);
                if ($ts !== false && ($lastRecordedAt === null || $ts > strtotime($lastRecordedAt))) {
                    $lastRecordedAt = $recordedAt;
                    $lastTransferLabel = $marker['fundeb_realtime_last_transfer_label'] ?? null;
                }
            }
        }

        if ($withData === 0) {
            return [
                'available' => false,
                'ano' => $currentYear,
            ];
        }

        $pctDone = $expected > 0
            ? round(min(100.0, ($observed / $expected) * 100.0), 1)
            : null;

        return [
            'available' => true,
            'ano' => $currentYear,
            'observed_total' => $observed > 0 ? round($observed, 2) : null,
            'expected_total' => $expected > 0 ? round($expected, 2) : null,
            'balance_total' => $balance > 0 ? round($balance, 2) : null,
            'pct_done' => $pctDone,
            'municipalities_with_data' => $withData,
            'last_transfer_label' => is_string($lastTransferLabel) && $lastTransferLabel !== ''
                ? $lastTransferLabel
                : null,
            'last_recorded_at' => $lastRecordedAt,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return array<string, mixed>
     */
    private static function portariaMeta(string $uf, int $refYear, array $markers): array
    {
        $exerciseYear = $refYear;
        foreach ($markers as $marker) {
            $ano = (int) ($marker['fundeb_ano'] ?? 0);
            if ($ano > 0) {
                $exerciseYear = $ano;
                break;
            }
        }

        $catalog = FundebFndePortariaCatalog::activePublication($exerciseYear);
        $label = is_array($catalog) ? trim((string) ($catalog['label'] ?? '')) : '';
        if ($label === '') {
            $label = __('Portaria FNDE :ano', ['ano' => (string) $exerciseYear]);
        }

        $url = FundebFndePortariaCatalog::receitaCsvUrl($exerciseYear);
        $listingUrl = is_array($catalog) ? ($catalog['listing_url'] ?? null) : null;
        $pubYear = is_array($catalog) ? ($catalog['exercicio'] ?? $exerciseYear) : $exerciseYear;

        $importedAt = self::latestFundebImportAt($uf);
        $transferImportedAt = self::latestTransferImportAt($uf, HorizonteFundebRepasseOutlook::currentYear());

        return [
            'exercise_year' => $exerciseYear,
            'publication_label' => $label,
            'publication_year' => is_numeric($pubYear) ? (int) $pubYear : $exerciseYear,
            'url' => $url,
            'listing_url' => is_string($listingUrl) && $listingUrl !== '' ? $listingUrl : null,
            'fundeb_imported_at' => $importedAt,
            'fundeb_imported_label' => self::formatImportedLabel($importedAt),
            'transfer_imported_at' => $transferImportedAt,
            'transfer_imported_label' => self::formatImportedLabel($transferImportedAt),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $realtime
     * @param  array<string, array<string, mixed>>  $nationalByUf
     * @return array<string, mixed>
     */
    private static function nationalComparison(
        string $uf,
        array $snapshot,
        array $realtime,
        array $nationalByUf,
    ): array {
        $ufs = array_keys($nationalByUf);
        $totalUfs = count($ufs);

        $receitaRows = [];
        $pctRows = [];
        $nationalReceita = 0.0;
        $nationalObserved = 0.0;
        $nationalExpected = 0.0;

        foreach ($nationalByUf as $code => $row) {
            $receita = (float) ($row['receita_portaria_total'] ?? 0);
            if ($receita > 0) {
                $receitaRows[] = ['uf' => $code, 'value' => $receita];
                $nationalReceita += $receita;
            }

            $rt = is_array($row['realtime'] ?? null) ? $row['realtime'] : [];
            $obs = (float) ($rt['observed_total'] ?? 0);
            $exp = (float) ($rt['expected_total'] ?? 0);
            if ($exp > 0) {
                $pctRows[] = [
                    'uf' => $code,
                    'value' => min(100.0, ($obs / $exp) * 100.0),
                ];
                $nationalObserved += $obs;
                $nationalExpected += $exp;
            }
        }

        usort($receitaRows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        usort($pctRows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        $rankReceita = self::rankPosition($receitaRows, $uf);
        $rankPct = self::rankPosition($pctRows, $uf);

        $ufReceita = (float) ($snapshot['receita_portaria_total'] ?? 0);
        $shareReceita = $nationalReceita > 0 && $ufReceita > 0
            ? round(($ufReceita / $nationalReceita) * 100.0, 2)
            : null;

        $nationalAvgPct = $nationalExpected > 0
            ? round(min(100.0, ($nationalObserved / $nationalExpected) * 100.0), 1)
            : null;

        $ufPct = is_numeric($realtime['pct_done'] ?? null) ? (float) $realtime['pct_done'] : null;
        $deltaPct = ($ufPct !== null && $nationalAvgPct !== null)
            ? round($ufPct - $nationalAvgPct, 1)
            : null;

        return [
            'total_ufs' => $totalUfs,
            'rank_receita' => $rankReceita,
            'rank_pct_done' => $rankPct,
            'share_receita_pct' => $shareReceita,
            'national_avg_pct_done' => $nationalAvgPct,
            'delta_pct_vs_national' => $deltaPct,
        ];
    }

    /**
     * @param  list<array{uf: string, value: float}>  $rows
     */
    private static function rankPosition(array $rows, string $uf): ?int
    {
        foreach ($rows as $i => $row) {
            if (strtoupper($row['uf']) === $uf) {
                return $i + 1;
            }
        }

        return null;
    }

    private static function latestFundebImportAt(string $uf): ?string
    {
        if (! self::schemaHasTable('fundeb_municipio_references')) {
            return null;
        }

        $prefix = IbgeUfFromCode::ibgePrefixForUf($uf);
        if ($prefix === null) {
            return null;
        }

        try {
            $max = FundebMunicipioReference::query()
                ->where('ibge_municipio', 'like', $prefix.'%')
                ->max('imported_at');
        } catch (\Throwable) {
            return null;
        }

        return self::isoTimestamp($max);
    }

    private static function latestTransferImportAt(string $uf, int $year): ?string
    {
        if (! self::schemaHasTable('municipal_transfer_snapshots')) {
            return null;
        }

        $prefix = IbgeUfFromCode::ibgePrefixForUf($uf);
        if ($prefix === null) {
            return null;
        }

        $max = null;
        try {
            $query = MunicipalTransferSnapshot::query()
                ->forYear($year)
                ->where('ibge_municipio', 'like', $prefix.'%');

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
                $at = $row->imported_at;
                if ($at === null) {
                    continue;
                }
                $iso = $at->toIso8601String();
                if ($max === null || strtotime($iso) > strtotime($max)) {
                    $max = $iso;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return $max;
    }

    private static function isoTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private static function formatImportedLabel(?string $iso): ?string
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        $ts = strtotime($iso);
        if ($ts === false) {
            return null;
        }

        return date('d/m/Y H:i', $ts);
    }

    private static function schemaHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
