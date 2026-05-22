<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Conexão turma → escola com PK canónica e colunas alternativas (cod_escola, id).
 */
final class EscolaTurmaJoin
{
    /**
     * @return array{qualified: string, idCol: string}|null
     */
    public static function pkSpec(Connection $db, City $city): ?array
    {
        $qualified = IeducarSchema::resolveTable('escola', $city);
        if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
            return null;
        }

        $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.escola.id'),
            'cod_escola',
            'id',
        ]), $city);

        if ($idCol === null) {
            return null;
        }

        return ['qualified' => $qualified, 'idCol' => $idCol];
    }

    /**
     * Colunas na tabela escola que podem receber o FK da turma (evita zeros quando o FK não é a PK configurada).
     *
     * @return list<string>
     */
    public static function pkMatchColumns(Connection $db, City $city, string $primaryIdCol): array
    {
        $qualified = IeducarSchema::resolveTable('escola', $city);
        $cols = [$primaryIdCol];
        foreach (['cod_escola', 'id'] as $candidate) {
            if ($candidate !== $primaryIdCol
                && IeducarColumnInspector::columnExists($db, $qualified, $candidate, $city)) {
                $cols[] = $candidate;
            }
        }

        return array_values(array_unique($cols));
    }

    /**
     * Junta turma (alias) à escola quando a coluna FK da turma está preenchida.
     *
     * @return array{qualified: string, idCol: string}|null spec da escola (PK para groupBy / whereIn)
     */
    public static function joinTurmaEscolaFk(
        Builder $q,
        Connection $db,
        City $city,
        string $turmaAlias,
        string $escolaAlias = 'e',
    ): ?array {
        $spec = self::pkSpec($db, $city);
        if ($spec === null) {
            return null;
        }

        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        if ($tc['escola'] === '') {
            return null;
        }

        $grammar = $db->getQueryGrammar();
        $turmaEscolaSql = $grammar->wrap($turmaAlias).'.'.$grammar->wrap($tc['escola']);
        $matchCols = self::pkMatchColumns($db, $city, $spec['idCol']);

        $q->join($spec['qualified'].' as '.$escolaAlias, function ($join) use ($db, $turmaEscolaSql, $escolaAlias, $grammar, $matchCols) {
            $join->where(function ($w) use ($db, $turmaEscolaSql, $escolaAlias, $grammar, $matchCols) {
                $first = true;
                foreach ($matchCols as $col) {
                    $eColSql = $grammar->wrap($escolaAlias).'.'.$grammar->wrap($col);
                    $clause = $db->getDriverName() === 'pgsql'
                        ? '('.$turmaEscolaSql.')::text = ('.$eColSql.')::text'
                        : 'CAST('.$turmaEscolaSql.' AS UNSIGNED) = CAST('.$eColSql.' AS UNSIGNED)';
                    if ($first) {
                        $w->whereRaw($clause);
                        $first = false;
                    } else {
                        $w->orWhereRaw($clause);
                    }
                }
            });
        });

        return $spec;
    }
}
