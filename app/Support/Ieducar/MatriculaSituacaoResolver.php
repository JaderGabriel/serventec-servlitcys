<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Resolve o campo de situação pedagógica da matrícula (códigos INEP 1–16).
 *
 * Preferência: {@see ref_cod_matricula_situacao} + tabela matricula_situacao (coluna «codigo»),
 * com fallback em «aprovado» na matrícula quando o catálogo não preencher.
 */
final class MatriculaSituacaoResolver
{
    /**
     * @return ?array{
     *   applyJoins: callable(Builder): void,
     *   chaveExpr: string,
     *   groupByExpr: string,
     *   campo_situacao: string
     * }
     */
    public static function resolveChaveAgrupamento(Connection $db, City $city, string $matAlias = 'm'): ?array
    {
        $mat = IeducarSchema::resolveTable('matricula', $city);

        $fkCol = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
            'ref_cod_matricula_situacao',
            'ref_cod_situacao_matricula',
        ]), $city);

        $aproCol = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
            (string) config('ieducar.columns.matricula_situacao.aprovado'),
            'aprovado',
            'situacao',
        ]), $city);

        $msTable = null;
        try {
            $msTable = IeducarSchema::resolveTable('matricula_situacao', $city);
        } catch (\InvalidArgumentException) {
            $msTable = null;
        }

        $msPk = null;
        $codigoCol = null;
        if ($fkCol !== null && $msTable !== null && IeducarColumnInspector::tableExists($db, $msTable, $city)) {
            $msPk = IeducarColumnInspector::firstExistingColumn($db, $msTable, array_filter([
                (string) config('ieducar.columns.matricula_situacao_catalog.id'),
                'cod_matricula_situacao',
                'id',
            ]), $city);
            $codigoCol = IeducarColumnInspector::firstExistingColumn($db, $msTable, array_filter([
                (string) config('ieducar.columns.matricula_situacao_catalog.codigo'),
                'codigo',
                'codigo_situacao',
                'cod_situacao',
            ]), $city);
        }

        $driver = $db->getDriverName();
        $grammar = $db->getQueryGrammar();
        $wrapM = fn (string $col) => $grammar->wrap($matAlias).'.'.$grammar->wrap($col);
        $wrapMs = fn (string $col) => $grammar->wrap('ms').'.'.$grammar->wrap($col);

        if ($fkCol !== null && $msTable !== null && $msPk !== null && $codigoCol !== null) {
            $applyJoins = static function (Builder $q) use ($matAlias, $msTable, $fkCol, $msPk): void {
                $q->leftJoin($msTable.' as ms', $matAlias.'.'.$fkCol, '=', 'ms.'.$msPk);
            };

            if ($aproCol !== null) {
                if ($driver === 'pgsql') {
                    $chaveExpr = 'COALESCE('.$wrapMs($codigoCol).'::text, CAST('.$wrapM($aproCol).' AS text))';
                    $groupByExpr = 'COALESCE('.$wrapMs($codigoCol).'::text, CAST('.$wrapM($aproCol).' AS text))';
                } else {
                    $chaveExpr = 'COALESCE(CAST('.$wrapMs($codigoCol).' AS CHAR), CAST('.$wrapM($aproCol).' AS CHAR))';
                    $groupByExpr = 'COALESCE(CAST('.$wrapMs($codigoCol).' AS CHAR), CAST('.$wrapM($aproCol).' AS CHAR))';
                }

                return [
                    'applyJoins' => $applyJoins,
                    'chaveExpr' => $chaveExpr,
                    'groupByExpr' => $groupByExpr,
                    'campo_situacao' => 'matricula_situacao.codigo ← '.$fkCol.' + matricula.'.$aproCol,
                ];
            }

            return [
                'applyJoins' => $applyJoins,
                'chaveExpr' => $driver === 'pgsql' ? $wrapMs($codigoCol).'::text' : 'CAST('.$wrapMs($codigoCol).' AS CHAR)',
                'groupByExpr' => $driver === 'pgsql' ? $wrapMs($codigoCol).'::text' : 'CAST('.$wrapMs($codigoCol).' AS CHAR)',
                'campo_situacao' => 'matricula_situacao.'.$codigoCol,
            ];
        }

        if ($aproCol !== null) {
            $applyJoins = static function (Builder $q): void {
            };

            $cast = $driver === 'pgsql' ? 'text' : 'CHAR';
            $chaveExpr = 'CAST('.$wrapM($aproCol).' AS '.$cast.')';
            $groupByExpr = 'CAST('.$wrapM($aproCol).' AS '.$cast.')';

            return [
                'applyJoins' => $applyJoins,
                'chaveExpr' => $chaveExpr,
                'groupByExpr' => $groupByExpr,
                'campo_situacao' => 'matricula.'.$aproCol,
            ];
        }

        return null;
    }
}
