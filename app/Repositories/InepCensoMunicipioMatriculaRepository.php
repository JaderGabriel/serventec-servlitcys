<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Carbon;

final class InepCensoMunicipioMatriculaRepository
{
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
        $ibgeNorm = FundebMunicipioReferenceRepository::normalizeIbge($ibge);
        if ($ibgeNorm === null || $ano < 2000) {
            return;
        }

        $now = $importedAt ?? now();

        $attributes = [
            'matriculas_total' => max(0, $matriculasTotal),
            'matriculas_municipal' => $this->nullablePositive($matriculasMunicipal),
            'matriculas_nao_municipal' => $this->nullablePositive($matriculasNaoMunicipal),
            'matriculas_regular' => $this->nullablePositive($matriculasRegular),
            'matriculas_eja' => $this->nullablePositive($matriculasEja),
            'matriculas_especial' => $this->nullablePositive($matriculasEspecial),
            'matriculas_complementar' => $this->nullablePositive($matriculasComplementar),
            'matriculas_infantil' => $this->nullablePositive($matriculasInfantil),
            'matriculas_fundamental_1' => $this->nullablePositive($matriculasFundamental1),
            'matriculas_fundamental_2' => $this->nullablePositive($matriculasFundamental2),
            'matriculas_medio' => $this->nullablePositive($matriculasMedio),
            'matriculas_profissional' => $this->nullablePositive($matriculasProfissional),
            'escolas_contagem' => max(0, $escolasContagem),
            'fonte' => $fonte,
            'imported_at' => $now,
        ];

        foreach ($dependenciaBreakdown as $column => $value) {
            if (! is_string($column)
                || (! str_ends_with($column, '_municipal') && ! str_ends_with($column, '_nao_municipal'))) {
                continue;
            }
            $attributes[$column] = $this->nullablePositive(is_numeric($value) ? (int) $value : null);
        }

        InepCensoMunicipioMatricula::query()->updateOrCreate(
            ['ibge_municipio' => $ibgeNorm, 'ano' => $ano],
            $attributes,
        );
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
