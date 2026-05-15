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

    /**
     * @param  list<array<string, mixed>>  $pillars
     * @param  list<array<string, mixed>>  $dimensions
     * @return list<array<string, mixed>>
     */
    public static function pillarsWithMunicipioSummary(
        array $pillars,
        array $dimensions,
        string $cityName,
        string $yearLabel
    ): array {
        $pillarChecks = [
            'fundeb-base' => ['sem_raca', 'sem_sexo', 'sem_data_nascimento', 'matricula_duplicada', 'matricula_situacao_invalida', 'distorcao_idade_serie'],
            'vaar-inclusao' => ['nee_sem_aee', 'aee_sem_nee', 'nee_subnotificacao', 'sem_raca', 'sem_sexo'],
            'vaar-indicadores' => ['escola_sem_inep', 'escola_inativa_matricula', 'distorcao_idade_serie'],
            'pnae-transporte' => ['escola_sem_geo', 'matricula_duplicada', 'matricula_situacao_invalida'],
        ];

        $checksById = [];
        foreach ($dimensions as $c) {
            if (! is_array($c)) {
                continue;
            }
            $checksById[(string) ($c['id'] ?? '')] = $c;
        }

        $out = [];
        foreach ($pillars as $pillar) {
            if (! is_array($pillar)) {
                continue;
            }
            $pid = (string) ($pillar['id'] ?? '');
            $linked = $pillarChecks[$pid] ?? [];
            $tipos = 0;
            $ocorrencias = 0;
            $ganho = 0.0;
            foreach ($linked as $checkId) {
                $c = $checksById[$checkId] ?? null;
                if ($c === null || ! ($c['has_issue'] ?? $c['detected'] ?? false)) {
                    continue;
                }
                $tipos++;
                $ocorrencias += (int) ($c['total'] ?? 0);
                $ganho += (float) ($c['ganho_potencial_anual'] ?? 0);
            }

            $status = $tipos === 0 ? 'ok' : ($tipos >= 2 || $ocorrencias >= 50 ? 'danger' : 'warning');
            $texto = $tipos === 0
                ? __('Nenhuma pendência detectada neste eixo para o filtro actual.')
                : __(':city — :year: :tipos tipo(s) de problema, :n ocorrência(s), ganho potencial indicativo :ganho.', [
                    'city' => $cityName,
                    'year' => $yearLabel !== '' ? $yearLabel : __('filtro actual'),
                    'tipos' => number_format($tipos),
                    'n' => number_format($ocorrencias),
                    'ganho' => self::formatBrl($ganho),
                ]);

            $out[] = array_merge($pillar, [
                'municipio_resumo' => [
                    'texto' => $texto,
                    'status' => $status,
                    'tipos_afetados' => $tipos,
                    'ocorrencias' => $ocorrencias,
                    'ganho_potencial' => $ganho,
                ],
            ]);
        }

        return $out;
    }
}
