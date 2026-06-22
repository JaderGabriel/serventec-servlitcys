<?php

namespace App\Support\Brazil;

/**
 * Chave normalizada município+UF para cruzar CSV Tesouro com catálogo IBGE.
 */
final class MunicipalityNomeUfKey
{
    public static function key(string $nome, string $uf): string
    {
        $nome = self::normalizeNome($nome);
        $uf = strtoupper(trim($uf));

        return $nome !== '' && $uf !== '' ? $nome.'|'.$uf : '';
    }

    public static function normalizeNome(string $nome): string
    {
        $nome = mb_strtolower(trim($nome));
        if ($nome === '') {
            return '';
        }
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        if (is_string($ascii) && $ascii !== '') {
            $nome = $ascii;
        }
        $nome = preg_replace('/[^a-z0-9\s]/', '', $nome) ?? $nome;

        return trim(preg_replace('/\s+/', ' ', $nome) ?? $nome);
    }
}
