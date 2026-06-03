<?php

namespace App\Repositories;

use App\Models\CadunicoMunicipioSnapshot;
use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use Illuminate\Support\Collection;

class CadunicoMunicipioSnapshotRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(string $ibge, int $ano, array $data): CadunicoMunicipioSnapshot
    {
        $keys = ['ibge_municipio' => $ibge, 'ano_referencia' => $ano];

        return CadunicoMunicipioSnapshot::query()->updateOrCreate($keys, array_merge($data, [
            'imported_at' => now(),
        ]));
    }

    public function findForCityYear(?City $city, int $year): ?CadunicoMunicipioSnapshot
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city?->ibge_municipio);
        if ($ibge === null) {
            return null;
        }

        $row = CadunicoMunicipioSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano_referencia', $year)
            ->first();

        if ($row !== null) {
            return $row;
        }

        return CadunicoMunicipioSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano_referencia', '<=', $year)
            ->orderByDesc('ano_referencia')
            ->first();
    }

    /**
     * @return Collection<int, CadunicoMunicipioSnapshot>
     */
    public function listForCity(City $city): Collection
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return collect();
        }

        return CadunicoMunicipioSnapshot::query()
            ->where('ibge_municipio', $ibge)
            ->orderByDesc('ano_referencia')
            ->get();
    }

    /**
     * @return array{anchor: int, from: int, to: int}
     */
    public static function defaultMatrixYearRange(): array
    {
        $anchor = CadunicoOpenDataImportService::suggestedImportYear();

        return [
            'anchor' => $anchor,
            'from' => max(2000, $anchor - 2),
            'to' => $anchor,
        ];
    }

    /**
     * @return array{from: int, to: int}
     */
    public static function normalizeMatrixYearRange(?int $from, ?int $to): array
    {
        $defaults = self::defaultMatrixYearRange();
        $maxYear = (int) date('Y') + 1;
        $to = $to ?? $defaults['to'];
        $from = $from ?? $defaults['from'];
        $to = max(2000, min($maxYear, (int) $to));
        $from = max(2000, min($maxYear, (int) $from));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Matriz município × ano com agregados Cecad importados.
     *
     * @return array{
     *     year_from: int,
     *     year_to: int,
     *     anchor_year: int,
     *     years: list<int>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function yearlyMatrix(int $yearFrom, int $yearTo): array
    {
        $range = self::normalizeMatrixYearRange($yearFrom, $yearTo);
        $yearFrom = $range['from'];
        $yearTo = $range['to'];
        $years = range($yearFrom, $yearTo);

        $snapshotsByIbge = [];
        foreach (CadunicoMunicipioSnapshot::query()
            ->whereBetween('ano_referencia', [$yearFrom, $yearTo])
            ->get() as $row) {
            $ibge = (string) $row->ibge_municipio;
            $ano = (int) $row->ano_referencia;
            $snapshotsByIbge[$ibge][$ano] = self::enrichCell($row);
        }

        $emptyCell = self::enrichCell(null);

        $rows = [];
        foreach (City::query()->forAnalytics()->orderBy('name')->get(['id', 'name', 'uf', 'ibge_municipio', 'is_active']) as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            $yearCells = [];
            foreach ($years as $ano) {
                $yearCells[$ano] = $ibge !== null
                    ? ($snapshotsByIbge[$ibge][$ano] ?? $emptyCell)
                    : $emptyCell;
            }

            $rows[] = [
                'city_id' => (int) $city->id,
                'name' => $city->name,
                'uf' => $city->uf,
                'ibge' => $ibge,
                'has_ibge' => $ibge !== null,
                'is_active' => (bool) $city->is_active,
                'years' => $yearCells,
            ];
        }

        $defaults = self::defaultMatrixYearRange();

        return [
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'anchor_year' => $defaults['anchor'],
            'years' => $years,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *     has_snapshot: bool,
     *     pop_escolar: ?int,
     *     pessoas: ?int,
     *     familias: ?int,
     *     fonte: ?string,
     *     imported_at: ?string,
     *     cell_class: string
     * }
     */
    private static function enrichCell(?CadunicoMunicipioSnapshot $row): array
    {
        if ($row === null) {
            return [
                'has_snapshot' => false,
                'pop_escolar' => null,
                'pessoas' => null,
                'familias' => null,
                'fonte' => null,
                'imported_at' => null,
                'cell_class' => '',
            ];
        }

        return [
            'has_snapshot' => true,
            'pop_escolar' => $row->totalCriancasEscolaridade(),
            'pessoas' => (int) $row->pessoas_cadastradas,
            'familias' => (int) $row->familias_cadastradas,
            'fonte' => $row->fonte,
            'imported_at' => $row->imported_at?->format('d/m/Y H:i'),
            'cell_class' => 'bg-emerald-50/80 dark:bg-emerald-950/30 text-emerald-950 dark:text-emerald-100',
        ];
    }
}
