<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Brazil\IbgeUfFromCode;

/** Restringe o abastecimento Horizonte a uma UF (estado). */
final class HorizonteUfScope
{
    public static function normalize(?string $uf): ?string
    {
        $uf = strtoupper(trim((string) $uf));

        return in_array($uf, IbgeMunicipalityCatalog::brazilianUfs(), true) ? $uf : null;
    }

    public static function isActive(?string $uf): bool
    {
        return self::normalize($uf) !== null;
    }

    public static function ibgeBelongsToScope(string $ibge, ?string $uf): bool
    {
        $scoped = self::normalize($uf);
        if ($scoped === null) {
            return true;
        }

        return IbgeUfFromCode::ufFromIbge($ibge) === $scoped;
    }

    /**
     * @return list<string>|null  null = nacional (sem filtro)
     */
    public static function ibgeCodesForUf(?string $uf, IbgeMunicipalityCatalog $catalog): ?array
    {
        $scoped = self::normalize($uf);
        if ($scoped === null) {
            return null;
        }

        $index = $catalog->municipalitiesForUf($scoped);

        return array_values(array_keys($index));
    }

    /**
     * @return array<string, true>|null  null = todos os municípios
     */
    public static function allowedIbgeMap(?string $uf, IbgeMunicipalityCatalog $catalog): ?array
    {
        $codes = self::ibgeCodesForUf($uf, $catalog);
        if ($codes === null) {
            return null;
        }

        $map = [];
        foreach ($codes as $ibge) {
            $map[$ibge] = true;
        }

        return $map;
    }
}
