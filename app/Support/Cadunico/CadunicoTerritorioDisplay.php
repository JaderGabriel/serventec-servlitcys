<?php

namespace App\Support\Cadunico;

use App\Models\CadunicoTerritorioSnapshot;
use Illuminate\Support\Collection;

/**
 * Rótulos de território (bairro/setor) — evita homónimos e expõe código IBGE.
 */
final class CadunicoTerritorioDisplay
{
    public static function normalizeCodigo(string $codigo): string
    {
        $digits = preg_replace('/\D/', '', $codigo) ?? '';

        return $digits === '' ? trim($codigo) : $digits;
    }

    /**
     * Sufixo legível do código (últimos 4 dígitos do setor/bairro).
     */
    public static function codigoSuffix(string $codigo): string
    {
        $norm = self::normalizeCodigo($codigo);
        if (strlen($norm) >= 4) {
            return substr($norm, -4);
        }

        return $norm !== '' ? $norm : $codigo;
    }

    /**
     * Nome para UI: distingue setores no mesmo bairro e homónimos.
     */
    public static function label(string $codigo, string $nome, string $tipo = 'bairro', bool $forceDistinct = false): string
    {
        $nome = trim($nome);
        $tipo = trim($tipo) !== '' ? trim($tipo) : 'bairro';
        $suffix = self::codigoSuffix($codigo);

        if ($tipo === 'setor' || $forceDistinct) {
            if ($nome !== '') {
                return __(':nome · setor :s', ['nome' => $nome, 's' => $suffix]);
            }

            return __('Setor censitário :s', ['s' => $suffix]);
        }

        if ($nome !== '') {
            return $nome;
        }

        return __('Território :s', ['s' => $suffix]);
    }

    /**
     * @param  Collection<int, CadunicoTerritorioSnapshot>  $rows
     * @return array<string, string> territorio_codigo => rótulo
     */
    public static function labelsForRows(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $nameCounts = [];
        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string) $row->territorio_nome));
            if ($key === '') {
                continue;
            }
            $nameCounts[$key] = ($nameCounts[$key] ?? 0) + 1;
        }

        $out = [];
        foreach ($rows as $row) {
            $codigo = (string) $row->territorio_codigo;
            $nome = trim((string) $row->territorio_nome);
            $tipo = trim((string) ($row->territorio_tipo ?? 'bairro'));
            $duplicateName = $nome !== '' && ($nameCounts[mb_strtolower($nome)] ?? 0) > 1;

            $out[$codigo] = self::label($codigo, $nome, $tipo, $tipo === 'setor' || $duplicateName);
        }

        return $out;
    }
}
