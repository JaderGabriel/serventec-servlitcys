<?php

namespace App\Services\Fundeb;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Ieducar\IeducarCityDataService;
use App\Support\Ieducar\IeducarFilterState;
use App\Support\Ieducar\MatriculaChartQueries;

/**
 * Matrículas por ano letivo (i-Educar) e Censo INEP agregado municipal.
 */
final class FundebMatriculasByYearService
{
    public function __construct(
        private IeducarCityDataService $cityData,
    ) {}

    /**
     * @param  list<int>  $years
     * @return array<int, array{
     *   ano: int,
     *   ieducar: int,
     *   censo: ?int,
     *   usado: int,
     *   fonte_usada: string,
     *   ieducar_erro: ?string
     * }>
     */
    public function forCityYears(City $city, array $years): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $useCensoFallback = (bool) config('ieducar.fundeb.open_data.vaaf_use_censo_matriculas_fallback', true);
        $out = [];

        foreach ($years as $year) {
            $ano = (int) $year;
            if ($ano < 2000) {
                continue;
            }
            $ieducar = 0;
            $erro = null;
            try {
                $filters = new IeducarFilterState((string) $ano, null, null, null);
                $ieducar = (int) $this->cityData->run(
                    $city,
                    static fn ($db) => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0,
                );
            } catch (\Throwable $e) {
                $erro = $e->getMessage();
            }

            $censo = $this->censoMatriculas($ibge, $ano);
            $usado = $ieducar > 0 ? $ieducar : ($useCensoFallback && $censo !== null && $censo > 0 ? $censo : 0);
            $fonte = match (true) {
                $ieducar > 0 => 'ieducar',
                $usado > 0 && $censo !== null => 'censo_inep',
                default => 'indisponivel',
            };

            $out[$ano] = [
                'ano' => $ano,
                'ieducar' => $ieducar,
                'censo' => $censo,
                'usado' => $usado,
                'fonte_usada' => $fonte,
                'ieducar_erro' => $erro,
            ];
        }

        return $out;
    }

    private function censoMatriculas(?string $ibge, int $ano): ?int
    {
        if ($ibge === null) {
            return null;
        }

        $row = InepCensoMunicipioMatricula::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $ano)
            ->first();

        if ($row === null || (int) $row->matriculas_total <= 0) {
            return null;
        }

        return (int) $row->matriculas_total;
    }
}
