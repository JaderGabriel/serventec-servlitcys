<?php

namespace App\Support\Horizonte;

/** Extrai indicadores fiscais municipais a partir de linhas RREO SICONFI. */
final class SiconfiRreoParser
{
    /**
     * @param  list<array<string, mixed>>  $annex01
     * @param  list<array<string, mixed>>  $annex02
     * @param  list<array<string, mixed>>  $annex06
     * @param  list<array<string, mixed>>  $annex14
     * @return array<string, mixed>
     */
    public static function parse(array $annex01, array $annex02, array $annex06, array $annex14): array
    {
        $receitaCorrente = self::value($annex01, 'RECEITAS CORRENTES (I)', 'Até o Bimestre')
            ?? self::value($annex01, 'RECEITAS CORRENTES (I)', 'DESPESAS LIQUIDADAS ATÉ O BIMESTRE (d)');

        $receitaTotal = self::value($annex01, 'RECEITAS (EXCETO INTRA-ORÇAMENTÁRIAS) (I)', 'Até o Bimestre')
            ?? self::value($annex01, 'RECEITAS REALIZADAS', 'Até o Bimestre', $annex14);

        $transferencias = self::sumAccounts($annex01, ['TRANSFERÊNCIAS CORRENTES', 'TRANSFERÊNCIAS DE CAPITAL'], 'Até o Bimestre');

        $despesaEducacao = self::value($annex02, 'Educação', 'DESPESAS LIQUIDADAS ATÉ O BIMESTRE (d)');
        $pctEducReceita = null;
        if ($despesaEducacao !== null && $receitaCorrente !== null && $receitaCorrente > 0) {
            $pctEducReceita = self::sanitizePct(round(100.0 * $despesaEducacao / $receitaCorrente, 3));
        }

        $pctMinimo = self::value($annex14, 'Mínimo Anual de <18% / 25%> das Receitas de Impostos na Manutenção e Desenvolvimento do Ensino', '% (d/total d)');
        $pctMinimo = self::sanitizePct($pctMinimo);

        $divida = self::value($annex06, 'DÍVIDA CONSOLIDADA (XXVIII)', 'Até o Bimestre');
        $caixa = self::value($annex06, 'Disponibilidade de Caixa', 'Até o Bimestre');
        $rap = self::value($annex06, '(-) Restos a Pagar Processados (XXX)', 'Até o Bimestre');

        $receitaPropria = null;
        $pctReceitaPropria = null;
        if ($receitaTotal !== null && $receitaTotal > 0) {
            $transf = $transferencias ?? 0.0;
            $receitaPropria = max(0.0, $receitaTotal - $transf);
            $pctReceitaPropria = self::sanitizePct(round(100.0 * $receitaPropria / $receitaTotal, 3));
        }

        $liquidityRatio = ($divida !== null && $divida > 0 && $caixa !== null)
            ? $caixa / $divida
            : null;

        return [
            'receita_corrente_liquida' => $receitaCorrente,
            'despesa_educacao_liquidada' => $despesaEducacao,
            'pct_educacao_receita_corrente' => $pctEducReceita,
            'pct_minimo_constitucional' => $pctMinimo,
            'divida_consolidada' => $divida,
            'disponibilidade_caixa' => $caixa,
            'restos_pagar_processados' => $rap,
            'restos_pagar_educacao' => null,
            'receita_propria' => $receitaPropria,
            'pct_receita_propria' => $pctReceitaPropria,
            'liquidity_ratio' => $liquidityRatio,
            'fiscal_capacity_score' => self::fiscalCapacityScore($pctEducReceita, $liquidityRatio, $divida, $receitaCorrente, $rap),
        ];
    }

    private static function fiscalCapacityScore(
        ?float $pctEduc,
        ?float $liquidityRatio,
        ?float $divida,
        ?float $receita,
        ?float $rap,
    ): int {
        $score = 50;

        if ($liquidityRatio !== null) {
            if ($liquidityRatio >= 0.35) {
                $score += 20;
            } elseif ($liquidityRatio >= 0.15) {
                $score += 8;
            } else {
                $score -= 18;
            }
        }

        if ($divida !== null && $receita !== null && $receita > 0) {
            $endiv = $divida / $receita;
            if ($endiv <= 1.2) {
                $score += 12;
            } elseif ($endiv <= 2.5) {
                $score += 0;
            } else {
                $score -= 20;
            }
        }

        if ($rap !== null && $receita !== null && $receita > 0) {
            $rapRatio = $rap / $receita;
            if ($rapRatio > 0.25) {
                $score -= 15;
            } elseif ($rapRatio > 0.12) {
                $score -= 6;
            }
        }

        if ($pctEduc !== null) {
            if ($pctEduc >= 25) {
                $score += 8;
            } elseif ($pctEduc >= 18) {
                $score += 4;
            } else {
                $score -= 10;
            }
        }

        return max(0, min(100, (int) round($score)));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function value(array $rows, string $contaNeedle, string $colunaNeedle, ?array $fallbackRows = null): ?float
    {
        $value = self::matchValue($rows, $contaNeedle, $colunaNeedle);
        if ($value !== null || $fallbackRows === null) {
            return $value;
        }

        return self::matchValue($fallbackRows, $contaNeedle, $colunaNeedle);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $contaNeedles
     */
    private static function sumAccounts(array $rows, array $contaNeedles, string $colunaNeedle): ?float
    {
        $sum = 0.0;
        $found = false;
        foreach ($contaNeedles as $needle) {
            $value = self::matchValue($rows, $needle, $colunaNeedle);
            if ($value !== null) {
                $sum += $value;
                $found = true;
            }
        }

        return $found ? $sum : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function matchValue(array $rows, string $contaNeedle, string $colunaNeedle): ?float
    {
        $contaNeedle = mb_strtoupper(trim($contaNeedle));
        $colunaNeedle = mb_strtoupper(trim($colunaNeedle));

        foreach ($rows as $row) {
            $conta = mb_strtoupper(trim((string) ($row['conta'] ?? '')));
            $coluna = mb_strtoupper(trim((string) ($row['coluna'] ?? '')));
            if (! str_contains($conta, $contaNeedle) && $conta !== $contaNeedle) {
                continue;
            }
            if ($coluna !== $colunaNeedle && ! str_contains($coluna, $colunaNeedle)) {
                continue;
            }
            if (isset($row['valor']) && is_numeric($row['valor'])) {
                return (float) $row['valor'];
            }
        }

        return null;
    }

    private static function sanitizePct(?float $value): ?float
    {
        if ($value === null || ! is_finite($value)) {
            return null;
        }

        if ($value < 0 || $value > 100) {
            return null;
        }

        return round($value, 3);
    }
}
