<?php

namespace App\Support\Horizonte;

/** Enriquecimento CadÚnico — crianças fora da escola (estimativa). */
final class HorizonteCadunicoEnrichment
{
    /**
     * @return array<string, mixed>
     */
    public static function analyze(
        ?int $cadunicoEscolar,
        ?int $matriculasCenso,
        ?int $sidraPop417,
        ?int $criancas0a17 = null,
    ): array
    {
        $denominator = $cadunicoEscolar ?? $sidraPop417;
        if ($denominator === null || $denominator <= 0) {
            return [
                'available' => false,
                'criancas_fora_escola' => null,
                'pct_fora_escola' => null,
                'inclusion_gap_score' => 35,
            ];
        }

        $matriculas = max(0, (int) ($matriculasCenso ?? 0));
        $fora = max(0, $denominator - $matriculas);
        $pct = round(100.0 * $fora / $denominator, 1);
        $score = max(0, min(100, (int) round(min(100.0, $pct * 1.35))));

        return [
            'available' => true,
            'cadunico_escolar' => $cadunicoEscolar,
            'matriculas_censo' => $matriculas > 0 ? $matriculas : null,
            'criancas_fora_escola' => $fora,
            'pct_fora_escola' => $pct,
            'inclusion_gap_score' => $score,
            'nota' => __('Estimativa: população escolar CadÚnico/SIDRA menos matrículas Censo no território.'),
        ];
    }
}
