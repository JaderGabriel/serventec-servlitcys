<?php

namespace App\Support\Ieducar;

/**
 * Estimativa indicativa de perda / ganho potencial (FUNDEB, VAAR, Censo) por tipo de discrepância.
 *
 * Valores são referências configuráveis (VAAF municipal médio × peso por eixo), não substituem
 * cálculo oficial do FNDE ou prestação de contas no Simec.
 */
final class DiscrepanciesFundingImpact
{
    /**
     * @return array{
     *   perda_anual: float,
     *   ganho_potencial_anual: float,
     *   valor_unitario: float,
     *   peso: float,
     *   formula: string,
     *   aviso: string
     * }
     */
    public static function estimate(string $checkId, int $occurrences): array
    {
        $occurrences = max(0, $occurrences);
        $vaa = (float) config('ieducar.discrepancies.vaa_referencia_anual', 4500);
        $pesos = config('ieducar.discrepancies.peso_por_check', []);
        $peso = 1.0;
        if (is_array($pesos) && isset($pesos[$checkId])) {
            $peso = max(0.0, (float) $pesos[$checkId]);
        }

        $valorUnit = $vaa * $peso;
        $perda = $occurrences * $valorUnit;
        $ganho = $perda;

        return [
            'perda_anual' => $perda,
            'ganho_potencial_anual' => $ganho,
            'valor_unitario' => $valorUnit,
            'peso' => $peso,
            'formula' => __(
                ':n ocorrência(s) × R$ :unit (referência VAAF :vaa × peso :p para «:id»).',
                [
                    'n' => number_format($occurrences, 0, ',', '.'),
                    'unit' => self::formatBrl($valorUnit),
                    'vaa' => self::formatBrl($vaa),
                    'p' => number_format($peso, 2, ',', '.'),
                    'id' => $checkId,
                ]
            ),
            'aviso' => (string) config('ieducar.discrepancies.aviso_financeiro', ''),
        ];
    }

    public static function formatBrl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    /**
     * @return list<array{id: string, titulo: string, descricao: string}>
     */
    public static function fundingPillars(): array
    {
        $pillars = config('ieducar.discrepancies.funding_pillars', []);

        return is_array($pillars) ? array_values(array_filter($pillars, is_array(...))) : [];
    }
}
