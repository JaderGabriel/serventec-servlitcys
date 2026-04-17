<?php

namespace App\Support\Inep;

/**
 * Chave estável para fundir linhas CSV/JSON (mesma lógica que SaebCsvPedagogicalImportService).
 */
final class SaebPointsDedupe
{
    /**
     * @param  array<string, mixed>  $p
     */
    public static function fromRaw(array $p): ?string
    {
        $ids = $p['city_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return null;
        }
        $cityId = (int) ($ids[0] ?? 0);
        $year = 0;
        if (isset($p['ano'])) {
            $year = (int) $p['ano'];
        } elseif (isset($p['year'])) {
            $year = (int) $p['year'];
        }
        $disc = strtolower((string) ($p['disciplina'] ?? $p['disc'] ?? ''));
        $etapa = strtolower((string) ($p['etapa'] ?? ''));
        $st = strtolower((string) ($p['status'] ?? 'final'));
        $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : 0;

        if ($year <= 0 || $disc === '' || $cityId <= 0) {
            return null;
        }

        return $cityId.'|'.$year.'|'.$disc.'|'.$etapa.'|'.$eid.'|'.$st;
    }

    /**
     * Garante chave única quando a assinatura clássica não aplica.
     *
     * @param  array<string, mixed>  $p
     */
    public static function ensureKey(array $p): string
    {
        if (($k = self::fromRaw($p)) !== null) {
            return $k;
        }

        $json = json_encode($p, JSON_UNESCAPED_UNICODE);

        return 'h:'.hash('sha256', $json !== false ? $json : '');
    }
}
