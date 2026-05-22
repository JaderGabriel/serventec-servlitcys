<?php

namespace App\Support\Rx;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\Connection;

/**
 * Meta de cadastro RX: ano de referência com dados (busca para trás se Y-1 zerado)
 * e acréscimo de +X% por cada salto de ano em relação ao ano imediato anterior.
 */
final class RxBaselineResolver
{
    /**
     * @return array{
     *   turmas: int,
     *   matriculas: int,
     *   enturmacoes: int,
     *   ano: int,
     *   referencia_ano: int,
     *   referencia_turmas: int,
     *   referencia_matriculas: int,
     *   referencia_enturmacoes: int,
     *   saltos: int,
     *   fator_meta: float,
     *   acrescimo_pct: float,
     *   encontrou_referencia: bool
     * }
     */
    public static function resolve(
        Connection $db,
        City $city,
        int $vigenteYear,
        ?int $maxLookback = null,
        ?float $pctPerSalto = null,
    ): array {
        $maxLookback = $maxLookback ?? (int) config('rx.meta_lookback_years', 10);
        $pctPerSalto = $pctPerSalto ?? (float) config('rx.meta_pct_per_salto', 5.0);
        $pctPerSalto = max(0.0, $pctPerSalto);

        $empty = [
            'turmas' => 0,
            'matriculas' => 0,
            'enturmacoes' => 0,
            'ano' => 0,
            'referencia_ano' => 0,
            'referencia_turmas' => 0,
            'referencia_matriculas' => 0,
            'referencia_enturmacoes' => 0,
            'saltos' => 0,
            'fator_meta' => 1.0,
            'acrescimo_pct' => 0.0,
            'encontrou_referencia' => false,
        ];

        if ($vigenteYear <= 1) {
            return $empty;
        }

        $immediatePrev = $vigenteYear - 1;
        $minYear = max(1, $vigenteYear - $maxLookback);

        $referenceYear = null;
        $rawTurmas = 0;
        $rawMatriculas = 0;
        $rawEnturmacoes = 0;

        for ($y = $immediatePrev; $y >= $minYear; $y--) {
            try {
                $filters = new IeducarFilterState((string) $y, null, null, null);
                $mat = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0;
                $turmas = IeducarWorkActivityQueries::countTurmasForYear($db, $city, $filters);
                $ent = IeducarWorkActivityQueries::countEnturmacoesForYear($db, $city, $filters);

                if ($mat > 0 || $turmas > 0) {
                    $referenceYear = $y;
                    $rawMatriculas = $mat;
                    $rawTurmas = $turmas;
                    $rawEnturmacoes = $ent;

                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($referenceYear === null) {
            return $empty;
        }

        $saltos = max(0, $immediatePrev - $referenceYear);
        $fator = self::multiplierForSaltos($saltos, $pctPerSalto);
        $acrescimoPct = round(($fator - 1.0) * 100.0, 2);

        return [
            'turmas' => (int) round($rawTurmas * $fator),
            'matriculas' => (int) round($rawMatriculas * $fator),
            'enturmacoes' => (int) round($rawEnturmacoes * $fator),
            'ano' => $referenceYear,
            'referencia_ano' => $referenceYear,
            'referencia_turmas' => $rawTurmas,
            'referencia_matriculas' => $rawMatriculas,
            'referencia_enturmacoes' => $rawEnturmacoes,
            'saltos' => $saltos,
            'fator_meta' => $fator,
            'acrescimo_pct' => $acrescimoPct,
            'encontrou_referencia' => true,
        ];
    }

    public static function multiplierForSaltos(int $saltos, float $pctPerSalto = 5.0): float
    {
        if ($saltos <= 0) {
            return 1.0;
        }

        return (float) pow(1.0 + max(0.0, $pctPerSalto) / 100.0, $saltos);
    }
}
