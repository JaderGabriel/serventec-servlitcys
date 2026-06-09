<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Carbon;

final class InepCensoMunicipioMatriculaRepository
{
    public function upsert(
        string $ibge,
        int $ano,
        int $matriculasTotal,
        int $escolasContagem = 0,
        string $fonte = 'inep_microdados',
        ?\Illuminate\Support\Carbon $importedAt = null,
        ?int $matriculasMunicipal = null,
        ?int $matriculasNaoMunicipal = null,
    ): void {
        $ibgeNorm = FundebMunicipioReferenceRepository::normalizeIbge($ibge);
        if ($ibgeNorm === null || $ano < 2000) {
            return;
        }

        $now = $importedAt ?? now();

        InepCensoMunicipioMatricula::query()->updateOrCreate(
            ['ibge_municipio' => $ibgeNorm, 'ano' => $ano],
            [
                'matriculas_total' => max(0, $matriculasTotal),
                'matriculas_municipal' => $matriculasMunicipal !== null ? max(0, $matriculasMunicipal) : null,
                'matriculas_nao_municipal' => $matriculasNaoMunicipal !== null ? max(0, $matriculasNaoMunicipal) : null,
                'escolas_contagem' => max(0, $escolasContagem),
                'fonte' => $fonte,
                'imported_at' => $now,
            ],
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
}
