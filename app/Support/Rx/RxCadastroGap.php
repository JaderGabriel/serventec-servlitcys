<?php

namespace App\Support\Rx;

/**
 * Progresso e volumes em falta face à meta RX (turmas + matrículas; enturmação fora do total exibido).
 */
final class RxCadastroGap
{
    /**
     * @return array{
     *   falta_turmas: int,
     *   falta_matriculas: int,
     *   falta_enturmacoes: int,
     *   registros_restantes: int,
     *   progresso_cadastro_pct: ?float,
     *   progresso_turmas_pct: ?float,
     *   progresso_matriculas_pct: ?float,
     * }
     */
    public static function compute(
        int $metaTurmas,
        int $metaMatriculas,
        int $metaEnturmacoes,
        int $turmasVigente,
        int $matriculasVigente,
        int $enturmacoesVigente,
    ): array {
        $faltaTurmas = max(0, $metaTurmas - $turmasVigente);
        $faltaMat = max(0, $metaMatriculas - $matriculasVigente);
        $faltaEnt = max(0, $metaEnturmacoes - $enturmacoesVigente);

        $progTurmas = self::progressPct($turmasVigente, $metaTurmas);
        $progMat = self::progressPct($matriculasVigente, $metaMatriculas);
        $progEnt = self::progressPct($enturmacoesVigente, $metaEnturmacoes);

        // Evita «meta OK» só por turmas quando há matrículas vigentes sem meta definida (ex.: ref. histórica só com turmas).
        if ($matriculasVigente > 0 && $metaMatriculas <= 0) {
            $progMat = 0.0;
        }
        if ($turmasVigente > 0 && $metaTurmas <= 0) {
            $progTurmas = 0.0;
        }

        return [
            'falta_turmas' => $faltaTurmas,
            'falta_matriculas' => $faltaMat,
            'falta_enturmacoes' => $faltaEnt,
            'registros_restantes' => $faltaTurmas + $faltaMat,
            'progresso_cadastro_pct' => self::compositeProgressPct(
                $progTurmas,
                $progMat,
                $progEnt,
                $metaTurmas,
                $metaMatriculas,
                $metaEnturmacoes,
                $turmasVigente,
                $matriculasVigente,
            ),
            'progresso_turmas_pct' => $progTurmas,
            'progresso_matriculas_pct' => $progMat,
        ];
    }

    public static function progressPct(int $current, int $meta): ?float
    {
        if ($meta <= 0) {
            return null;
        }

        return round(100.0 * min($current, $meta) / $meta, 1);
    }

    /**
     * Progresso geral: menor percentual entre dimensões com meta > 0 (gargalo).
     */
    public static function compositeProgressPct(
        ?float $progTurmas,
        ?float $progMat,
        ?float $progEnt,
        int $metaTurmas,
        int $metaMatriculas,
        int $metaEnturmacoes,
        int $turmasVigente = 0,
        int $matriculasVigente = 0,
    ): ?float {
        $parts = [];
        if ($metaTurmas > 0 && $progTurmas !== null) {
            $parts[] = $progTurmas;
        } elseif ($turmasVigente > 0) {
            $parts[] = 0.0;
        }
        if ($metaMatriculas > 0 && $progMat !== null) {
            $parts[] = $progMat;
        } elseif ($matriculasVigente > 0) {
            $parts[] = 0.0;
        }
        if ($metaEnturmacoes > 0 && $progEnt !== null) {
            $parts[] = $progEnt;
        }

        if ($parts === []) {
            return null;
        }

        return round(min($parts), 1);
    }

    /**
     * Δ de matrículas vs ano imediato anterior (Y-1).
     *
     * @return array{delta: int, delta_pct: ?float, delta_sem_base: bool}
     */
    public static function matriculasDelta(int $matVigente, int $matAnterior): array
    {
        $delta = $matVigente - $matAnterior;

        return [
            'delta' => $delta,
            'delta_pct' => $matAnterior > 0 ? round(100.0 * $delta / $matAnterior, 1) : null,
            'delta_sem_base' => $matAnterior === 0 && $matVigente > 0,
        ];
    }
}
