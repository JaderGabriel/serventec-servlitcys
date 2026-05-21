<?php

namespace App\Support\Fundeb;

use App\Models\City;

/**
 * Normalização e correspondência de códigos IBGE (7 dígitos) em registos heterogéneos.
 */
final class FundebIbgeMatcher
{
    public static function normalize(mixed $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 7) {
            return $digits;
        }

        if (strlen($digits) === 6) {
            return null;
        }

        if (strlen($digits) > 7) {
            return substr($digits, 0, 7);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function candidatesForCity(City $city): array
    {
        $primary = self::normalize($city->ibge_municipio);
        if ($primary === null) {
            return [];
        }

        $out = [$primary];
        $six = strlen($primary) === 7 ? substr($primary, 2) : null;
        if ($six !== null && $six !== '') {
            $out[] = $six;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function extractFromRecord(array $record): ?string
    {
        $keys = config('ieducar.fundeb.open_data.fields.ibge', []);
        if (! is_array($keys)) {
            $keys = [];
        }

        $normalized = [];
        foreach ($record as $key => $value) {
            $normalized[strtolower((string) $key)] = $value;
        }

        foreach ($keys as $key) {
            $k = strtolower((string) $key);
            if (! array_key_exists($k, $normalized)) {
                continue;
            }
            $ibge = self::extractDigitsFromValue($normalized[$k]);
            if ($ibge !== null) {
                return $ibge;
            }
        }

        foreach (['codigo_ibge', 'co_municipio', 'ibge', 'cod_municipio', 'codigo_municipio'] as $fallback) {
            if (! array_key_exists($fallback, $normalized)) {
                continue;
            }
            $ibge = self::extractDigitsFromValue($normalized[$fallback]);
            if ($ibge !== null) {
                return $ibge;
            }
        }

        return null;
    }

    public static function recordMatchesIbge(array $record, string $targetIbge): bool
    {
        $rowIbge = self::extractFromRecord($record);
        if ($rowIbge === null) {
            return false;
        }

        if ($rowIbge === $targetIbge) {
            return true;
        }

        if (strlen($targetIbge) === 7 && strlen($rowIbge) === 6) {
            return substr($targetIbge, 1, 6) === $rowIbge;
        }

        return false;
    }

    /**
     * Extrai 7 ou 6 dígitos de um valor de registo (CSV/CKAN).
     * normalize() continua a rejeitar 6 dígitos em formulários; aqui aceitamos legado.
     */
    private static function extractDigitsFromValue(mixed $raw): ?string
    {
        $seven = self::normalize($raw);
        if ($seven !== null) {
            return $seven;
        }

        $digits = preg_replace('/\D/', '', (string) $raw);
        if ($digits !== null && strlen($digits) === 6) {
            return $digits;
        }

        return null;
    }
}
