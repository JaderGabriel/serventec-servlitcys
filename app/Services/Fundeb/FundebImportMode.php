<?php

namespace App\Services\Fundeb;

/**
 * Estratégia de persistência na importação FUNDEB (VAAF/VAAT).
 */
final class FundebImportMode
{
    /** Apaga referências do município/ano no âmbito e grava o que a API/CSV devolver. */
    public const REPLACE = 'replace';

    /** Mantém registos iguais; grava só quando o valor obtido difere do gravado (VAAF/VAAT/VAAR). */
    public const UPDATE = 'update';

    public static function normalize(mixed $value): string
    {
        $mode = is_string($value) ? trim($value) : '';

        return $mode === self::REPLACE ? self::REPLACE : self::UPDATE;
    }

    public static function isReplace(string $mode): bool
    {
        return self::normalize($mode) === self::REPLACE;
    }
}
