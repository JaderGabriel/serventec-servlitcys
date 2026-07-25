<?php

namespace App\Support\Horizonte;

/**
 * Scores moderados HOR-08d–f — proxy SGE / timing de licitação (não dominam FUNDEB/fiscal/Canteiro).
 */
final class HorizonteProcurementMarketScorer
{
    /**
     * Proxy de presença de SGE / mercado de software educação (0–100).
     *
     * @param  array{
     *     sge_found?: bool,
     *     sge_status?: string,
     *     transparency_contratos_software?: int|null,
     *     licitacoes_software?: int,
     *     national_vendor_matched?: int
     * }  $signals
     */
    public static function proxySge(array $signals): int
    {
        $score = 0;

        $sgeFound = (bool) ($signals['sge_found'] ?? false);
        $sgeStatus = (string) ($signals['sge_status'] ?? '');
        if ($sgeFound && $sgeStatus === 'registry') {
            $score += 65;
        } elseif ($sgeFound && in_array($sgeStatus, ['market', 'market_national'], true)) {
            $score += 45;
        } elseif ($sgeFound && in_array($sgeStatus, ['catalog_pending', 'catalog_configured'], true)) {
            $score += 35;
        }

        $contratosSoft = max(0, (int) ($signals['transparency_contratos_software'] ?? 0));
        if ($contratosSoft > 0) {
            $score += min(35, 12 + $contratosSoft * 4);
        }

        $licSoft = max(0, (int) ($signals['licitacoes_software'] ?? 0));
        if ($licSoft > 0) {
            $score += min(30, 10 + $licSoft * 8);
        }

        // Sinal fraco (nacional / órgão): só reforça se já há outro sinal municipal.
        $national = max(0, (int) ($signals['national_vendor_matched'] ?? 0));
        if ($national > 0 && $score > 0) {
            $score += min(10, 3 + (int) floor($national / 5));
        }

        return max(0, min(100, $score));
    }

    /**
     * Timing comercial por editais/licitações recentes com IBGE municipal (0–100).
     */
    public static function timingLicitacao(int $licitacoes, int $licitacoesSoftware = 0): int
    {
        $licitacoes = max(0, $licitacoes);
        if ($licitacoes === 0) {
            return 0;
        }

        $base = match (true) {
            $licitacoes >= 7 => 85,
            $licitacoes >= 4 => 70,
            $licitacoes >= 2 => 55,
            default => 40,
        };

        if ($licitacoesSoftware > 0) {
            $base = min(100, $base + 15);
        }

        return $base;
    }
}
