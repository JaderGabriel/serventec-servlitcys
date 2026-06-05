<?php

namespace App\Services\Fundeb;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\MatriculaChartQueries;

/**
 * Matrículas por ano letivo (i-Educar) e Censo INEP agregado municipal.
 */
final class FundebMatriculasByYearService
{
    public function __construct(
        private CityDataConnection $cityData,
    ) {}

    /**
     * @param  list<int>  $years
     * @return array<int, array{
     *   ano: int,
     *   ieducar: int,
     *   censo: ?int,
     *   usado: int,
     *   fonte_usada: string,
     *   ieducar_erro: ?string,
     *   censo_ano_usado: ?int
     * }>
     */
    public function forCityYears(City $city, array $years): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        $useCensoFallback = (bool) config('ieducar.fundeb.open_data.vaaf_use_censo_matriculas_fallback', true);
        $lookback = max(0, min(5, (int) config('ieducar.fundeb.open_data.censo_matriculas_lookback_years', 3)));
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

            $censo = null;
            $censoAnoUsado = null;
            if ($ieducar <= 0 && $useCensoFallback) {
                $censoHit = $this->resolveCensoMatriculas($ibge, $ano, $lookback);
                $censo = $censoHit['matriculas'] ?? null;
                $censoAnoUsado = $censoHit['ano'] ?? null;
            }
            $usado = $ieducar > 0 ? $ieducar : ($censo !== null && $censo > 0 ? $censo : 0);
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
                'censo_ano_usado' => $censoAnoUsado !== $ano ? $censoAnoUsado : null,
            ];
        }

        return $out;
    }

    /**
     * Censo INEP por município — com lookback para exercícios FUNDEB já encerrados
     * (o microdados publica `nu_ano_censo`, em geral defasado 1–2 anos face ao exercício).
     *
     * @return array{matriculas: ?int, ano: ?int}
     */
    public function resolveCensoMatriculas(?string $ibge, int $requestedAno, ?int $lookbackYears = null): array
    {
        if ($ibge === null) {
            return ['matriculas' => null, 'ano' => null];
        }

        $lookback = $lookbackYears ?? max(0, min(5, (int) config('ieducar.fundeb.open_data.censo_matriculas_lookback_years', 3)));
        $years = [$requestedAno];
        for ($i = 1; $i <= $lookback; $i++) {
            $years[] = $requestedAno - $i;
        }
        $years = FundebOpenDataImportService::normalizeYearList($years);

        foreach ($years as $censoAno) {
            $mat = $this->censoMatriculasExact($ibge, $censoAno);
            if ($mat !== null && $mat > 0) {
                return ['matriculas' => $mat, 'ano' => $censoAno];
            }
        }

        return ['matriculas' => null, 'ano' => null];
    }

    private function censoMatriculasExact(?string $ibge, int $ano): ?int
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
