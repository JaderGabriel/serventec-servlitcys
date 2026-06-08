<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Support\Fundeb\FundebMatrixCellPresentation;
use App\Support\Fundeb\FundebValueLexicon;
use Illuminate\Support\Collection;

class FundebMunicipioReferenceRepository
{
    /**
     * @return Collection<int, FundebMunicipioReference>
     */
    public function listForCity(City $city): Collection
    {
        $ibge = self::normalizeIbge($city->ibge_municipio);

        return FundebMunicipioReference::query()
            ->where(function ($q) use ($city, $ibge) {
                $q->where('city_id', $city->id);
                if ($ibge !== null) {
                    $q->orWhere('ibge_municipio', $ibge);
                }
            })
            ->orderByDesc('ano')
            ->get();
    }

    public function findForCityYear(City $city, int $ano): ?FundebMunicipioReference
    {
        $ibge = self::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return FundebMunicipioReference::query()
                ->where('city_id', $city->id)
                ->where('ano', $ano)
                ->first();
        }

        return FundebMunicipioReference::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $ano)
            ->first();
    }

    /**
     * @param  array{vaaf: float, vaat?: ?float, complementacao_vaar?: ?float, complementacao_vaat?: ?float, fonte?: string, notas?: ?string}  $data
     */
    public function upsert(City $city, int $ano, array $data): FundebMunicipioReference
    {
        $ibge = self::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            throw new \InvalidArgumentException(__('Cidade sem código IBGE de município (7 dígitos).'));
        }

        $keys = ['ibge_municipio' => $ibge, 'ano' => $ano];

        return FundebMunicipioReference::query()->updateOrCreate($keys, [
            'city_id' => $city->id,
            'vaaf' => (float) $data['vaaf'],
            'vaat' => isset($data['vaat']) ? (float) $data['vaat'] : null,
            'complementacao_vaar' => isset($data['complementacao_vaar']) ? (float) $data['complementacao_vaar'] : null,
            'fonte' => trim((string) ($data['fonte'] ?? 'api_fnde')) ?: 'api_fnde',
            'tipo_valor' => isset($data['tipo_valor']) ? trim((string) $data['tipo_valor']) : null,
            'receita_total' => isset($data['receita_total']) ? (float) $data['receita_total'] : null,
            'complementacao_vaaf' => isset($data['complementacao_vaaf']) ? (float) $data['complementacao_vaaf'] : null,
            'complementacao_vaat' => isset($data['complementacao_vaat']) ? (float) $data['complementacao_vaat'] : null,
            'matriculas_base' => isset($data['matriculas_base']) ? (int) $data['matriculas_base'] : null,
            'matriculas_fonte' => isset($data['matriculas_fonte']) ? trim((string) $data['matriculas_fonte']) : null,
            'url_portaria' => isset($data['url_portaria']) ? trim((string) $data['url_portaria']) : null,
            'meta' => isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : null,
            'notas' => isset($data['notas']) ? trim((string) $data['notas']) : null,
            'imported_at' => now(),
        ]);
    }

    public static function normalizeIbge(mixed $raw): ?string
    {
        $ibge = preg_replace('/\D/', '', (string) $raw);

        return strlen($ibge) === 7 ? $ibge : null;
    }

    /**
     * Remove referências do município para um ano (IBGE + city_id).
     */
    public function deleteForCityYear(City $city, int $ano): int
    {
        $ibge = self::normalizeIbge($city->ibge_municipio);

        return FundebMunicipioReference::query()
            ->where('ano', $ano)
            ->where(function ($q) use ($city, $ibge): void {
                $q->where('city_id', $city->id);
                if ($ibge !== null) {
                    $q->orWhere('ibge_municipio', $ibge);
                }
            })
            ->delete();
    }

    /**
     * @param  list<int>  $anos
     */
    public function deleteForBulk(?array $cityIds, array $anos): int
    {
        $anos = array_values(array_unique(array_map('intval', $anos)));
        if ($anos === []) {
            return 0;
        }

        $query = FundebMunicipioReference::query()->whereIn('ano', $anos);

        if ($cityIds !== null && $cityIds !== []) {
            $cityIds = array_values(array_unique(array_map('intval', $cityIds)));
            $ibges = [];
            foreach (City::query()->whereIn('id', $cityIds)->get(['id', 'ibge_municipio']) as $city) {
                $ibge = self::normalizeIbge($city->ibge_municipio);
                if ($ibge !== null) {
                    $ibges[] = $ibge;
                }
            }
            $query->where(function ($q) use ($cityIds, $ibges): void {
                $q->whereIn('city_id', $cityIds);
                if ($ibges !== []) {
                    $q->orWhereIn('ibge_municipio', $ibges);
                }
            });
        }

        return (int) $query->delete();
    }

    public function attachCityIdsFromIbge(): int
    {
        $updated = 0;
        $cities = City::query()
            ->whereNotNull('ibge_municipio')
            ->get(['id', 'ibge_municipio']);

        foreach ($cities as $city) {
            $ibge = self::normalizeIbge($city->ibge_municipio);
            if ($ibge === null) {
                continue;
            }
            $count = FundebMunicipioReference::query()
                ->where('ibge_municipio', $ibge)
                ->whereNull('city_id')
                ->update(['city_id' => $city->id]);
            $updated += $count;
        }

        return $updated;
    }

    /**
     * Ano de referência FUNDEB vigente e intervalo padrão (vigente + 2 anteriores).
     *
     * @return array{anchor: int, from: int, to: int}
     */
    public static function defaultMatrixYearRange(): array
    {
        $anchor = FundebOpenDataImportService::suggestedImportYear();
        $from = max(2000, $anchor - 2);

        return [
            'anchor' => $anchor,
            'from' => $from,
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
     * Matriz município × ano com VAAF, VAAT, complementação VAAR e classificação de fonte.
     *
     * @return array{
     *     year_from: int,
     *     year_to: int,
     *     anchor_year: int,
     *     years: list<int>,
     *     legend: list<array<string, mixed>>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function yearlyMatrix(int $yearFrom, int $yearTo): array
    {
        $range = self::normalizeMatrixYearRange($yearFrom, $yearTo);
        $yearFrom = $range['from'];
        $yearTo = $range['to'];
        $years = range($yearFrom, $yearTo);

        $cities = City::query()->orderBy('name')->get(['id', 'name', 'uf', 'ibge_municipio', 'is_active']);

        $refsByCity = [];
        $refsByIbge = [];

        foreach (FundebMunicipioReference::query()
            ->whereBetween('ano', [$yearFrom, $yearTo])
            ->get(['city_id', 'ibge_municipio', 'ano', 'vaaf', 'vaat', 'complementacao_vaar', 'fonte']) as $ref) {
            $ano = (int) $ref->ano;
            $payload = self::enrichCell(
                true,
                (float) $ref->vaaf,
                $ref->vaat !== null ? (float) $ref->vaat : null,
                $ref->complementacao_vaar !== null ? (float) $ref->complementacao_vaar : null,
                $ref->fonte !== null && trim((string) $ref->fonte) !== '' ? trim((string) $ref->fonte) : null,
            );
            if ($ref->city_id) {
                $refsByCity[(int) $ref->city_id][$ano] = $payload;
            }
            if ($ref->ibge_municipio) {
                $refsByIbge[(string) $ref->ibge_municipio][$ano] = $payload;
            }
        }

        $emptyCell = self::enrichCell(false, null, null, null, null);

        $rows = [];
        foreach ($cities as $city) {
            $ibge = self::normalizeIbge($city->ibge_municipio);
            $yearCells = [];
            foreach ($years as $ano) {
                $yearCells[$ano] = $refsByCity[(int) $city->id][$ano] ?? ($ibge !== null ? ($refsByIbge[$ibge][$ano] ?? $emptyCell) : $emptyCell);
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

        $yearSemantics = [];
        foreach ($years as $y) {
            $yearSemantics[$y] = [
                'phase' => FundebValueLexicon::exercisePhase((int) $y),
                'phase_label' => FundebValueLexicon::exercisePhaseLabel((int) $y),
                'phase_hint' => FundebValueLexicon::exercisePhaseHint((int) $y),
                'column_caption' => FundebValueLexicon::matrixColumnCaption((int) $y),
            ];
        }

        return [
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'anchor_year' => $defaults['anchor'],
            'years' => $years,
            'year_semantics' => $yearSemantics,
            'legend' => FundebMatrixCellPresentation::legendItems(),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *     has_reference: bool,
     *     vaaf: ?float,
     *     vaat: ?float,
     *     vaar: ?float,
     *     fonte: ?string,
     *     display_kind: string,
     *     display_label: string,
     *     display_short: string,
     *     display_title: string,
     *     cell_class: string,
     *     swatch_class: string,
     *     display_icon: string
     * }
     */
    private static function enrichCell(bool $hasReference, ?float $vaaf, ?float $vaat, ?float $vaar, ?string $fonte): array
    {
        $display = FundebMatrixCellPresentation::forFonte($fonte, $hasReference);
        $nature = FundebValueLexicon::valueNature($fonte, $hasReference);

        return [
            'has_reference' => $hasReference,
            'vaaf' => $vaaf,
            'vaat' => $vaat,
            'vaar' => $vaar,
            'fonte' => $fonte,
            'display_kind' => $display['kind'],
            'display_label' => $display['label'],
            'display_short' => $display['short'],
            'display_title' => $display['title'].($hasReference ? ' '.$nature['hint'] : ''),
            'value_nature_label' => $nature['label'],
            'cell_class' => $display['cell_class'],
            'swatch_class' => $display['swatch_class'],
            'display_icon' => $display['icon'],
        ];
    }
}
