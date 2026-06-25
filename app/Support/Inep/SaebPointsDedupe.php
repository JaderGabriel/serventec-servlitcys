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
        $cityId = is_array($ids) && $ids !== [] ? (int) ($ids[0] ?? 0) : 0;

        // Escopo por cidade cadastrada (c<id>) ou, em cobertura nacional, por IBGE (m<ibge>).
        $scope = '';
        if ($cityId > 0) {
            $scope = 'c'.$cityId;
        } else {
            $ibge = preg_replace('/\D/', '', (string) ($p['municipio_ibge'] ?? '')) ?? '';
            if (strlen($ibge) === 7) {
                $scope = 'm'.$ibge;
            }
        }

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

        if ($year <= 0 || $disc === '' || $scope === '') {
            return null;
        }

        return $scope.'|'.$year.'|'.$disc.'|'.$etapa.'|'.$eid.'|'.$st;
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
