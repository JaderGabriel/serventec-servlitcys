<?php

namespace App\Support\Brazil;

/**
 * Deriva a UF a partir do prefixo numérico do código IBGE municipal (2 primeiros dígitos).
 */
final class IbgeUfFromCode
{
    /** @var array<string, string> */
    private const PREFIX_TO_UF = [
        '11' => 'RO', '12' => 'AC', '13' => 'AM', '14' => 'RR', '15' => 'PA',
        '16' => 'AP', '17' => 'TO', '21' => 'MA', '22' => 'PI', '23' => 'CE',
        '24' => 'RN', '25' => 'PB', '26' => 'PE', '27' => 'AL', '28' => 'SE',
        '29' => 'BA', '31' => 'MG', '32' => 'ES', '33' => 'RJ', '35' => 'SP',
        '41' => 'PR', '42' => 'SC', '43' => 'RS', '50' => 'MS', '51' => 'MT',
        '52' => 'GO', '53' => 'DF',
    ];

    public static function ufFromIbge(string $ibge): ?string
    {
        $digits = preg_replace('/\D/', '', $ibge);
        if ($digits === null || strlen($digits) < 2) {
            return null;
        }

        $prefix = substr(str_pad($digits, 7, '0', STR_PAD_LEFT), 0, 2);

        return self::PREFIX_TO_UF[$prefix] ?? null;
    }

    /** Prefixo IBGE (2 dígitos) para filtrar consultas SQL por UF. */
    public static function ibgePrefixForUf(string $uf): ?string
    {
        $uf = strtoupper(trim($uf));
        if ($uf === '') {
            return null;
        }

        foreach (self::PREFIX_TO_UF as $prefix => $code) {
            if ($code === $uf) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $ibgeCodes
     * @return list<string>
     */
    public static function ufsFromIbgeCodes(array $ibgeCodes): array
    {
        $ufs = [];
        foreach ($ibgeCodes as $ibge) {
            $uf = self::ufFromIbge((string) $ibge);
            if ($uf !== null) {
                $ufs[] = $uf;
            }
        }

        return array_values(array_unique($ufs));
    }
}
