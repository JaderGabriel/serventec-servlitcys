<?php

namespace App\Support\Horizonte;

/**
 * Config curada HOR-08d–f (órgãos SIAFI + fornecedores software).
 */
final class PortalProcurementConfig
{
    /**
     * @return list<array{codigo: string, sigla: string, nome: string}>
     */
    public static function orgaosSiafi(): array
    {
        $raw = config('horizonte.transparency.procurement.orgaos_siafi', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $codigo = preg_replace('/\D/', '', (string) ($row['codigo'] ?? '')) ?: '';
            if ($codigo === '') {
                continue;
            }
            $out[] = [
                'codigo' => $codigo,
                'sigla' => trim((string) ($row['sigla'] ?? '')),
                'nome' => trim((string) ($row['nome'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string> cnpj => label
     */
    public static function softwareVendors(): array
    {
        $raw = (string) config('horizonte.transparency.procurement.software_vendors_raw', '');
        if ($raw === '') {
            return [];
        }

        $map = [];
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $pair, 2));
            $cnpj = preg_replace('/\D/', '', $parts[0] ?? '') ?: '';
            if (strlen($cnpj) !== 14) {
                continue;
            }
            $map[$cnpj] = $parts[1] ?? $cnpj;
        }

        return $map;
    }

    public static function enabled(): bool
    {
        return filter_var(config('horizonte.transparency.procurement.enabled', true), FILTER_VALIDATE_BOOL);
    }
}
