<?php

namespace App\Support\Product;

/**
 * Convenção de tags de release: YYYYMMDD[-letra]-Codename (entidade mitológica).
 *
 * Codenames: greco-romano (padrão), nórdico ou asteca — ver docs/HISTORICO_VERSOES.md § convenção.
 *
 * Numeração do produto MAJOR.VERSÃO.MINOR — ver docs/HISTORICO_VERSOES.md:
 *   major (1.º) · versão/marco (2.º) · minor (3.º)
 *
 * Quando há mais de uma release no mesmo dia civil, acrescenta-se uma letra
 * minúscula em sequência (a, b, c…) imediatamente após a data, antes do codename.
 */
final class ProductReleaseTag
{
    private const TAG_PATTERN = '/^(\d{8})([a-z])?-(.+)$/i';

    private const DOC_BASENAME_PATTERN = '/^RELEASE_(\d{8})([a-z])?_(.+)$/i';

    /**
     * @return array{date: string, suffix: string, codename: string, sort_key: string}|null
     */
    public static function parse(string $tag): ?array
    {
        $tag = trim($tag);
        if ($tag === '' || ! preg_match(self::TAG_PATTERN, $tag, $matches)) {
            return null;
        }

        $date = $matches[1];
        $suffix = strtolower($matches[2] ?? '');
        $codename = $matches[3];

        return [
            'date' => $date,
            'suffix' => $suffix,
            'codename' => $codename,
            'sort_key' => $date.$suffix,
        ];
    }

    /**
     * @return array{date: string, suffix: string, codename: string, sort_key: string}|null
     */
    public static function parseDocBasename(string $basename): ?array
    {
        $basename = trim($basename);
        if ($basename === '' || ! preg_match(self::DOC_BASENAME_PATTERN, $basename, $matches)) {
            return null;
        }

        $date = $matches[1];
        $suffix = strtolower($matches[2] ?? '');

        return [
            'date' => $date,
            'suffix' => $suffix,
            'codename' => str_replace('_', ' ', $matches[3]),
            'sort_key' => $date.$suffix,
        ];
    }

    public static function isValid(string $tag): bool
    {
        return self::parse($tag) !== null;
    }

    /**
     * Caminho relativo docs/RELEASE_*.md para uma tag válida.
     */
    public static function releaseDocPath(string $tag): ?string
    {
        $parsed = self::parse($tag);
        if ($parsed === null) {
            return null;
        }

        $slug = strtoupper(str_replace([' ', '-'], '_', $parsed['codename']));

        return 'docs/RELEASE_'.$parsed['date'].$parsed['suffix'].'_'.$slug.'.md';
    }

    /**
     * Chave lexicográfica para ordenar releases (mais recente = maior).
     * Releases sem sufixo no mesmo dia ordenam antes das com letra (ex.: 20260607 < 20260607a).
     */
    public static function sortKeyFromDocBasename(string $basename): string
    {
        $parsed = self::parseDocBasename($basename);

        return $parsed['sort_key'] ?? '00000000';
    }

    /**
     * Próxima letra para uma data quando já existem releases nesse dia.
     * Devolve string vazia se ainda não houver nenhuma release na data.
     *
     * @param  list<string>  $existingSortKeys  chaves já usadas (YYYYMMDD ou YYYYMMDDa…)
     */
    public static function nextSuffixForDate(string $dateYmd, array $existingSortKeys): string
    {
        $prefix = preg_replace('/\D/', '', $dateYmd);
        if ($prefix === null || strlen($prefix) !== 8) {
            return '';
        }

        $used = [];
        foreach ($existingSortKeys as $key) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }
            $tail = substr($key, 8);
            if ($tail === '') {
                $used[''] = true;
            } elseif (preg_match('/^[a-z]$/', $tail)) {
                $used[$tail] = true;
            }
        }

        if ($used === []) {
            return '';
        }

        if (! isset($used[''])) {
            return 'a';
        }

        for ($ord = ord('a'); $ord <= ord('z'); $ord++) {
            $letter = chr($ord);
            if (! isset($used[$letter])) {
                return $letter;
            }
        }

        return 'z';
    }

    /**
     * Monta tag a partir de data, codename e sufixo opcional.
     */
    public static function format(string $dateYmd, string $codename, string $suffix = ''): string
    {
        $suffix = strtolower(trim($suffix));

        return $dateYmd.$suffix.'-'.$codename;
    }
}
