<?php

namespace App\Support\Brazil;

/** Nomes oficiais dos estados brasileiros por sigla (UF). */
final class BrazilUfNames
{
    /** @var array<string, string> */
    private const NAMES = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];

    public static function name(string $uf): string
    {
        $uf = strtoupper(trim($uf));

        return self::NAMES[$uf] ?? $uf;
    }

    public static function label(string $uf): string
    {
        $uf = strtoupper(trim($uf));
        if ($uf === '') {
            return '';
        }
        $name = self::name($uf);

        return $name !== $uf ? $uf.' — '.$name : $uf;
    }

    /**
     * @return array<string, string> sigla → nome
     */
    public static function all(): array
    {
        return self::NAMES;
    }
}
