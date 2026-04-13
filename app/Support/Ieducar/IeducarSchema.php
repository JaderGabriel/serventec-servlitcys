<?php

namespace App\Support\Ieducar;

use App\Models\City;

/**
 * Resolve nomes de tabelas iEducar na base do município (MySQL ou PostgreSQL).
 *
 * Compatível com iEducar 2.x (ex. 2.11) em PostgreSQL: vários schemas (pmieducar,
 * cadastro, …). Tabelas já qualificadas em config (cadastro.pessoa) não são alteradas.
 *
 * Ordem do schema efetivo: tabela já qualificada (schema.tabela) > campo da cidade
 * (ieducar_schema) > IEDUCAR_SCHEMA > para pgsql sem nada acima, pgsql_default_schema
 * (pmieducar por defeito).
 */
final class IeducarSchema
{
    public static function resolveTable(string $logicalKey, ?City $city = null): string
    {
        $table = config("ieducar.tables.{$logicalKey}");
        if ($table === null || $table === '') {
            throw new \InvalidArgumentException("ieducar.tables.{$logicalKey} não está definido.");
        }

        $table = (string) $table;

        /*
         * MySQL/MariaDB: nomes «schema.tabela» do Portabilis (ex.: cadastro.turno) não são válidos
         * como database.tabela na mesma conexão — usa-se o nome curto (turno) na base da cidade.
         */
        if ($city !== null && $city->effectiveIeducarDriver() === City::DRIVER_MYSQL && str_contains($table, '.')) {
            $override = config("ieducar.tables_mysql.{$logicalKey}");
            if (is_string($override) && trim($override) !== '') {
                $table = $override;
            } else {
                $table = self::mysqlShortTableName($logicalKey, $table);
            }
        }

        if (str_contains($table, '.')) {
            return $table;
        }

        $schema = self::effectiveSchema($city);

        if ($schema !== '') {
            return $schema.'.'.$table;
        }

        return $table;
    }

    /**
     * Schema PostgreSQL a aplicar antes do nome da tabela (vazio = MySQL ou public sem prefixo).
     */
    public static function effectiveSchema(?City $city): string
    {
        $global = trim((string) config('ieducar.schema', ''));
        $fromCity = trim((string) ($city?->ieducar_schema ?? ''));

        if ($fromCity !== '') {
            return $fromCity;
        }

        if ($global !== '') {
            return $global;
        }

        if ($city !== null && $city->effectiveIeducarDriver() === City::DRIVER_PGSQL) {
            $fallback = trim((string) config('ieducar.pgsql_default_schema', ''));

            return $fallback !== '' ? $fallback : 'pmieducar';
        }

        return '';
    }

    /**
     * Tabelas candidatas para turnos (PostgreSQL: cadastro, schema da cidade, public, extras no .env).
     *
     * @return list<string>
     */
    public static function turnoTableCandidates(?City $city): array
    {
        $primary = self::resolveTable('turno', $city);
        $out = [$primary];

        if ($city !== null && $city->effectiveIeducarDriver() === City::DRIVER_MYSQL) {
            return array_values(array_unique(array_filter($out)));
        }

        $extra = trim((string) config('ieducar.tables.turno_fallbacks', ''));
        if ($extra !== '') {
            foreach (preg_split('/\s*,\s*/', $extra) as $t) {
                if ($t !== '' && ! in_array($t, $out, true)) {
                    $out[] = $t;
                }
            }
        }

        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.turno';
        foreach ([$cad, 'public.turno'] as $t) {
            if (! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        $schema = self::effectiveSchema($city);
        if ($schema !== '') {
            $st = $schema.'.turno';
            if (! in_array($st, $out, true)) {
                $out[] = $st;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Tabelas candidatas para raça/cor (PostgreSQL: cadastro, schema da cidade, public, extras no .env).
     *
     * @return list<string>
     */
    public static function racaTableCandidates(?City $city): array
    {
        $primary = self::resolveTable('raca', $city);
        $out = [$primary];

        if ($city !== null && $city->effectiveIeducarDriver() === City::DRIVER_MYSQL) {
            return array_values(array_unique(array_filter($out)));
        }

        $extra = trim((string) config('ieducar.tables.raca_fallbacks', ''));
        if ($extra !== '') {
            foreach (preg_split('/\s*,\s*/', $extra) as $t) {
                if ($t !== '' && ! in_array($t, $out, true)) {
                    $out[] = $t;
                }
            }
        }

        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.raca';
        foreach ([$cad, 'public.raca'] as $t) {
            if (! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        $schema = self::effectiveSchema($city);
        if ($schema !== '') {
            $st = $schema.'.raca';
            if (! in_array($st, $out, true)) {
                $out[] = $st;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Nome de tabela curto para MySQL quando só existe config «cadastro.xxx» (Portabilis).
     */
    private static function mysqlShortTableName(string $logicalKey, string $qualified): string
    {
        $short = substr($qualified, (int) strrpos($qualified, '.') + 1);

        return match ($logicalKey) {
            'turno' => $short !== '' ? $short : 'turno',
            'pessoa' => $short !== '' ? $short : 'pessoa',
            'raca' => $short !== '' ? $short : 'raca',
            'aluno_deficiencia' => $short !== '' ? $short : 'aluno_deficiencia',
            'deficiencia' => $short !== '' ? $short : 'deficiencia',
            'matricula_situacao' => $short !== '' ? $short : 'matricula_situacao',
            default => $short,
        };
    }
}
