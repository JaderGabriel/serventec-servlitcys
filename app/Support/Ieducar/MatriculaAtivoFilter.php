<?php

namespace App\Support\Ieducar;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Filtro «matrícula ativa» compatível com PostgreSQL (boolean / smallint / char) e MySQL.
 */
final class MatriculaAtivoFilter
{
    public static function apply(Builder $query, Connection $db, string $columnRef): void
    {
        if ($columnRef === '') {
            return;
        }

        if ($db->getDriverName() === 'pgsql') {
            $col = self::wrapQualifiedColumn($db, $columnRef);
            // i-Educar usa frequentemente smallint (0/1) em «ativo»; em PostgreSQL IS TRUE exige boolean.
            $query->whereRaw(self::pgsqlActiveExpression($col));

            return;
        }

        $query->whereIn($columnRef, [1, '1', true, 't', 'true']);
    }

    private static function wrapQualifiedColumn(Connection $db, string $ref): string
    {
        $grammar = $db->getQueryGrammar();

        return implode('.', array_map(
            fn (string $s) => $grammar->wrap(trim($s)),
            explode('.', $ref)
        ));
    }

    /**
     * Condição «registro ativo» segura para PostgreSQL (boolean, smallint, char).
     *
     * @param  string  $wrappedColumn  Identificador já envolvido por wrap() (pode ser qualificado).
     */
    public static function pgsqlActiveExpression(string $wrappedColumn): string
    {
        return "(({$wrappedColumn})::text IN ('1','t','true','T') OR ({$wrappedColumn}) = 1)";
    }
}
