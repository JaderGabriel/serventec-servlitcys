<?php

namespace App\Support\Horizonte;

/**
 * Valor de repasse usado na dimensão transfer_dependency (CKAN → fallback FNDE).
 */
final class HorizonteTransferScoring
{
    /**
     * @param  array{total?: float, fundeb?: float}|null  $transfer
     * @param  array{complementacao_total?: float, receita_total?: float}|null  $fundeb
     */
    public static function resolveTotalForScoring(?array $transfer, ?array $fundeb): ?float
    {
        if ($transfer !== null) {
            $total = (float) ($transfer['total'] ?? 0);
            if ($total > 0) {
                return $total;
            }
            $fundebTransfer = (float) ($transfer['fundeb'] ?? 0);
            if ($fundebTransfer > 0) {
                return $fundebTransfer;
            }
        }

        if ($fundeb !== null) {
            $compl = (float) ($fundeb['complementacao_total'] ?? 0);
            if ($compl > 0) {
                return $compl;
            }
        }

        return null;
    }

    /**
     * @param  array{total?: float, fundeb?: float}|null  $transfer
     * @param  array{complementacao_total?: float, receita_total?: float}|null  $fundeb
     */
    public static function ratioForBenchmark(?array $transfer, ?array $fundeb): ?float
    {
        $amount = self::resolveTotalForScoring($transfer, $fundeb);
        if ($amount === null || $amount <= 0 || $fundeb === null) {
            return null;
        }

        $base = max(1.0, (float) ($fundeb['receita_total'] ?? 0), (float) ($fundeb['complementacao_total'] ?? 0));

        return $amount / $base;
    }
}
