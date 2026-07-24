<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Carbon;

final class InepCensoMunicipioMatriculaRepository
{
    /**
     * MySQL limita placeholders por prepared statement (~65 535).
     * ~40 colunas × 200 linhas = 8 000 — margem segura no upsert nacional.
     */
    private const UPSERT_CHUNK_SIZE = 200;

    /**
     * @param  array<string, int|null>  $dependenciaBreakdown  Colunas *_municipal / *_nao_municipal por segmento e etapa
     */
    public function upsert(
        string $ibge,
        int $ano,
        int $matriculasTotal,
        int $escolasContagem = 0,
        string $fonte = 'inep_microdados',
        ?Carbon $importedAt = null,
        ?int $matriculasMunicipal = null,
        ?int $matriculasNaoMunicipal = null,
        ?int $matriculasRegular = null,
        ?int $matriculasEja = null,
        ?int $matriculasEspecial = null,
        ?int $matriculasComplementar = null,
        ?int $matriculasInfantil = null,
        ?int $matriculasFundamental1 = null,
        ?int $matriculasFundamental2 = null,
        ?int $matriculasMedio = null,
        ?int $matriculasProfissional = null,
        array $dependenciaBreakdown = [],
    ): void {
        $this->upsertBatch([[
            'ibge_municipio' => $ibge,
            'ano' => $ano,
            'matriculas_total' => $matriculasTotal,
            'escolas_contagem' => $escolasContagem,
            'fonte' => $fonte,
            'matriculas_municipal' => $matriculasMunicipal,
            'matriculas_nao_municipal' => $matriculasNaoMunicipal,
            'matriculas_regular' => $matriculasRegular,
            'matriculas_eja' => $matriculasEja,
            'matriculas_especial' => $matriculasEspecial,
            'matriculas_complementar' => $matriculasComplementar,
            'matriculas_infantil' => $matriculasInfantil,
            'matriculas_fundamental_1' => $matriculasFundamental1,
            'matriculas_fundamental_2' => $matriculasFundamental2,
            'matriculas_medio' => $matriculasMedio,
            'matriculas_profissional' => $matriculasProfissional,
            'dependencia_breakdown' => $dependenciaBreakdown,
        ]], $importedAt);
    }

    /**
     * @param  list<array{
     *   ibge_municipio: string,
     *   ano: int,
     *   matriculas_total: int,
     *   escolas_contagem?: int,
     *   fonte?: string,
     *   matriculas_municipal?: ?int,
     *   matriculas_nao_municipal?: ?int,
     *   matriculas_regular?: ?int,
     *   matriculas_eja?: ?int,
     *   matriculas_especial?: ?int,
     *   matriculas_complementar?: ?int,
     *   matriculas_infantil?: ?int,
     *   matriculas_fundamental_1?: ?int,
     *   matriculas_fundamental_2?: ?int,
     *   matriculas_medio?: ?int,
     *   matriculas_profissional?: ?int,
     *   dependencia_breakdown?: array<string, int|null>
     * }>  $rows
     */
    public function upsertBatch(array $rows, ?Carbon $importedAt = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = $importedAt ?? now();
        $payload = [];
        $updateColumns = null;

        foreach ($rows as $row) {
            $ibgeNorm = FundebMunicipioReferenceRepository::normalizeIbge((string) ($row['ibge_municipio'] ?? ''));
            $ano = (int) ($row['ano'] ?? 0);
            if ($ibgeNorm === null || $ano < 2000) {
                continue;
            }

            $attributes = [
                'ibge_municipio' => $ibgeNorm,
                'ano' => $ano,
                'matriculas_total' => max(0, (int) ($row['matriculas_total'] ?? 0)),
                'matriculas_municipal' => $this->nullablePositive(isset($row['matriculas_municipal']) && is_numeric($row['matriculas_municipal']) ? (int) $row['matriculas_municipal'] : null),
                'matriculas_nao_municipal' => $this->nullablePositive(isset($row['matriculas_nao_municipal']) && is_numeric($row['matriculas_nao_municipal']) ? (int) $row['matriculas_nao_municipal'] : null),
                'matriculas_regular' => $this->nullablePositive(isset($row['matriculas_regular']) && is_numeric($row['matriculas_regular']) ? (int) $row['matriculas_regular'] : null),
                'matriculas_eja' => $this->nullablePositive(isset($row['matriculas_eja']) && is_numeric($row['matriculas_eja']) ? (int) $row['matriculas_eja'] : null),
                'matriculas_especial' => $this->nullablePositive(isset($row['matriculas_especial']) && is_numeric($row['matriculas_especial']) ? (int) $row['matriculas_especial'] : null),
                'matriculas_complementar' => $this->nullablePositive(isset($row['matriculas_complementar']) && is_numeric($row['matriculas_complementar']) ? (int) $row['matriculas_complementar'] : null),
                'matriculas_infantil' => $this->nullablePositive(isset($row['matriculas_infantil']) && is_numeric($row['matriculas_infantil']) ? (int) $row['matriculas_infantil'] : null),
                'matriculas_fundamental_1' => $this->nullablePositive(isset($row['matriculas_fundamental_1']) && is_numeric($row['matriculas_fundamental_1']) ? (int) $row['matriculas_fundamental_1'] : null),
                'matriculas_fundamental_2' => $this->nullablePositive(isset($row['matriculas_fundamental_2']) && is_numeric($row['matriculas_fundamental_2']) ? (int) $row['matriculas_fundamental_2'] : null),
                'matriculas_medio' => $this->nullablePositive(isset($row['matriculas_medio']) && is_numeric($row['matriculas_medio']) ? (int) $row['matriculas_medio'] : null),
                'matriculas_profissional' => $this->nullablePositive(isset($row['matriculas_profissional']) && is_numeric($row['matriculas_profissional']) ? (int) $row['matriculas_profissional'] : null),
                'escolas_contagem' => max(0, (int) ($row['escolas_contagem'] ?? 0)),
                'fonte' => (string) ($row['fonte'] ?? 'inep_microdados'),
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $breakdown = $row['dependencia_breakdown'] ?? [];
            if (is_array($breakdown)) {
                foreach ($breakdown as $column => $value) {
                    if (! is_string($column)
                        || (! str_ends_with($column, '_municipal') && ! str_ends_with($column, '_nao_municipal'))) {
                        continue;
                    }
                    $attributes[$column] = $this->nullablePositive(is_numeric($value) ? (int) $value : null);
                }
            }

            if ($updateColumns === null) {
                $updateColumns = array_values(array_diff(array_keys($attributes), ['ibge_municipio', 'ano', 'created_at']));
            }

            $payload[] = $attributes;
        }

        if ($payload === [] || $updateColumns === null) {
            return 0;
        }

        foreach (array_chunk($payload, self::UPSERT_CHUNK_SIZE) as $chunk) {
            // Alinha colunas entre linhas do chunk (breakdown opcional).
            $allKeys = [];
            foreach ($chunk as $item) {
                foreach (array_keys($item) as $key) {
                    $allKeys[$key] = true;
                }
            }
            $keys = array_keys($allKeys);
            $normalized = [];
            foreach ($chunk as $item) {
                $row = [];
                foreach ($keys as $key) {
                    $row[$key] = $item[$key] ?? null;
                }
                $normalized[] = $row;
            }
            $chunkUpdate = array_values(array_diff($keys, ['ibge_municipio', 'ano', 'created_at']));
            InepCensoMunicipioMatricula::query()->upsert($normalized, ['ibge_municipio', 'ano'], $chunkUpdate);
        }

        return count($payload);
    }

    public function findForCityYear(?City $city, int $year): ?InepCensoMunicipioMatricula
    {
        if ($city === null) {
            return null;
        }
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return null;
        }

        return InepCensoMunicipioMatricula::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $year)
            ->first();
    }

    private function nullablePositive(?int $value): ?int
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        return max(0, $value);
    }
}
