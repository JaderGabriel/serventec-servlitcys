<?php

namespace App\Support\Funding;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Repositories\MunicipalTransferSnapshotRepository;
use Illuminate\Support\Str;

/**
 * Distingue repasses municipais de totais agregados por UF (ex.: publicação STN M_TOTAL).
 */
final class FundebTransferScope
{
    public static function isUfAggregated(MunicipalTransferSnapshot $row): bool
    {
        $meta = self::metaArray($row);

        if (($meta['agregacao'] ?? '') === 'uf') {
            return true;
        }

        // Publicação STN: folha M_TOTAL traz total por UF, não por município.
        return (string) $row->fonte === 'tesouro_publicacao';
    }

    /**
     * @param  list<MunicipalTransferSnapshot>  $rows
     * @return list<MunicipalTransferSnapshot>
     */
    public static function municipalSnapshotsOnly(array $rows): array
    {
        return array_values(array_filter($rows, static fn (MunicipalTransferSnapshot $r): bool => ! self::isUfAggregated($r)));
    }

    public static function cityYearSlug(City $city, int $year): string
    {
        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);
        $base = Str::slug(trim((string) $city->name).'-'.strtoupper(trim((string) ($city->uf ?? ''))));
        if ($ibge !== null) {
            $base .= '-'.$ibge;
        }

        return $base.'-'.$year;
    }

    /**
     * Linha de repasse pertence ao recorte FUNDEB da aba Tempo Real.
     *
     * @param  list<string>|null  $needles
     */
    public static function matchesFinanceRealtimeProgram(MunicipalTransferSnapshot $row, ?array $needles = null): bool
    {
        $needles = $needles ?? config('ieducar.finance_realtime.program_keywords', ['fundeb', 'fnde']);
        if (! is_array($needles) || $needles === []) {
            $needles = ['fundeb'];
        }

        $blob = mb_strtolower((string) $row->programa_id.' '.(string) $row->programa_label.' '.(string) $row->fonte);
        foreach ($needles as $needle) {
            if (str_contains($blob, mb_strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function metaArray(MunicipalTransferSnapshot $row): array
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
}
