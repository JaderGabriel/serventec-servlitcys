<?php

namespace App\Services\Cadunico;

use App\Models\InepCensoMunicipioMatricula;

/**
 * Desconta matrículas fora da rede municipal (Censo INEP) da lacuna CadÚnico.
 */
final class CadunicoCensoAjuste
{
    /**
     * @return array{
     *   aplicado: bool,
     *   cadunico_ajustado: int,
     *   gap_ajustado: ?int,
     *   nao_municipal_estimado: int,
     *   metodo: string,
     *   nota: ?string
     * }
     */
    public static function apply(
        int $cadTotal,
        int $baseRede,
        ?int $gapTotal,
        ?InepCensoMunicipioMatricula $censoRow,
    ): array {
        $none = [
            'aplicado' => false,
            'cadunico_ajustado' => $cadTotal,
            'gap_ajustado' => $gapTotal,
            'nao_municipal_estimado' => 0,
            'metodo' => 'none',
            'nota' => null,
        ];

        if ($gapTotal === null || $gapTotal <= 0 || $cadTotal <= 0 || $baseRede <= 0) {
            return $none;
        }

        $naoMunicipal = self::naoMunicipalEstimado($baseRede, $censoRow);
        if ($naoMunicipal <= 0) {
            return $none;
        }

        $gapAjustado = max(0, $gapTotal - $naoMunicipal);
        $cadAjustado = max($baseRede, $cadTotal - $naoMunicipal);

        $metodo = ($censoRow !== null && (int) ($censoRow->matriculas_nao_municipal ?? 0) > 0)
            ? 'censo_dependencia'
            : 'censo_proxy_total_menos_municipal';

        return [
            'aplicado' => true,
            'cadunico_ajustado' => $cadAjustado,
            'gap_ajustado' => $gapAjustado,
            'nao_municipal_estimado' => $naoMunicipal,
            'metodo' => $metodo,
            'nota' => __(
                'Lacuna descontada em :n matrícula(s) estimadas fora da rede municipal (Censo INEP), para não contar crianças já escolarizadas em rede estadual/privada/EJA.',
                ['n' => number_format($naoMunicipal, 0, ',', '.')],
            ),
        ];
    }

    public static function naoMunicipalEstimado(int $baseRede, ?InepCensoMunicipioMatricula $censoRow): int
    {
        if ($censoRow === null) {
            return 0;
        }

        $explicit = (int) ($censoRow->matriculas_nao_municipal ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        $total = (int) $censoRow->matriculas_total;
        if ($total <= $baseRede) {
            return 0;
        }

        return $total - $baseRede;
    }
}
