<?php

namespace App\Support\Ieducar;

/**
 * Linhas planas para exportação CSV de discrepâncias (por escola ou agregado por rotina).
 */
final class DiscrepanciesCsvRowsBuilder
{
    /**
     * @param  array<string, mixed>  $snapshot  Resultado de DiscrepanciesRepository::snapshot()
     * @return list<array{
     *   check_id: string,
     *   check_titulo: string,
     *   escola_id: string,
     *   escola: string,
     *   total: int,
     *   tipos_recurso: string,
     *   perda_estimada: float,
     *   ganho_potencial: float,
     *   sugestao_correcao: string,
     *   agregado: bool
     * }>
     */
    public static function fromSnapshot(array $snapshot): array
    {
        $checks = is_array($snapshot['checks'] ?? null) ? $snapshot['checks'] : [];
        $catalog = DiscrepanciesCheckCatalog::definitions();
        $rows = [];

        foreach ($checks as $check) {
            $id = (string) ($check['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $meta = $catalog[$id] ?? [];
            $titulo = (string) ($check['title'] ?? $meta['title'] ?? $id);
            $sugestao = (string) ($meta['correction'] ?? $check['correction'] ?? '');
            $schoolRows = is_array($check['school_rows'] ?? null) ? $check['school_rows'] : [];
            $totalCheck = (int) ($check['total'] ?? 0);
            $perdaTotal = (float) ($check['perda_estimada_anual'] ?? 0);
            $ganhoTotal = (float) ($check['ganho_potencial_anual'] ?? 0);

            if ($schoolRows === [] && $totalCheck > 0) {
                $rows[] = [
                    'check_id' => $id,
                    'check_titulo' => $titulo,
                    'escola_id' => '',
                    'escola' => __('Total na rede (sem detalhe por escola)'),
                    'total' => $totalCheck,
                    'tipos_recurso' => '',
                    'perda_estimada' => $perdaTotal,
                    'ganho_potencial' => $ganhoTotal,
                    'sugestao_correcao' => $sugestao,
                    'agregado' => true,
                ];

                continue;
            }

            $unitPerda = $totalCheck > 0 ? $perdaTotal / $totalCheck : 0.0;

            foreach ($schoolRows as $row) {
                $cnt = (int) ($row['total'] ?? 0);
                $rows[] = [
                    'check_id' => $id,
                    'check_titulo' => $titulo,
                    'escola_id' => (string) ($row['escola_id'] ?? ''),
                    'escola' => (string) ($row['escola'] ?? '—'),
                    'total' => $cnt,
                    'tipos_recurso' => (string) ($row['tipos_recurso'] ?? ''),
                    'perda_estimada' => round($unitPerda * $cnt, 2),
                    'ganho_potencial' => (float) ($row['ganho_potencial_anual'] ?? round(($ganhoTotal / max(1, $totalCheck)) * $cnt, 2)),
                    'sugestao_correcao' => $sugestao,
                    'agregado' => false,
                ];
            }
        }

        return $rows;
    }
}
