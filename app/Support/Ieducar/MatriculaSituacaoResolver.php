<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Repositories\Ieducar\PerformanceRepository;
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
                $legacy = self::legacyAprovadoColumnToInepChaveSql($wrapM($aproCol), $driver);
                if ($driver === 'pgsql') {
                    $chaveExpr = 'COALESCE(NULLIF(TRIM('.$wrapMs($codigoCol).'::text), \'\'), '.$legacy.')';
                    $groupByExpr = $chaveExpr;
                } else {
                    $chaveExpr = 'COALESCE(NULLIF(TRIM(CAST('.$wrapMs($codigoCol).' AS CHAR)), \'\'), '.$legacy.')';
                    $groupByExpr = $chaveExpr;
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
            $applyJoins = static function (Builder $q): void {};

            $chaveExpr = self::legacyAprovadoColumnToInepChaveSql($wrapM($aproCol), $driver);
            $groupByExpr = $chaveExpr;

            return [
                'applyJoins' => $applyJoins,
                'chaveExpr' => $chaveExpr,
                'groupByExpr' => $groupByExpr,
                'campo_situacao' => 'matricula.'.$aproCol.' (mapeado para códigos INEP 2/3)',
            ];
        }

        return null;
    }

    /**
     * Coluna legada «aprovado» (boolean ou 0/1) não é o código INEP: «1» no texto é «em curso» (INEP 1).
     * Mapeia para 2 (aprovado) e 3 (reprovado) para alinhar com {@see PerformanceRepository}.
     */
    private static function legacyAprovadoColumnToInepChaveSql(string $qualifiedColumn, string $driver): string
    {
        $c = $qualifiedColumn;

        if ($driver === 'pgsql') {
            return 'CASE
                WHEN '.$c.' IS NULL THEN \'0\'
                WHEN TRIM(CAST('.$c.' AS text)) IN (\'1\',\'t\',\'true\',\'T\',\'yes\') THEN \'2\'
                WHEN TRIM(CAST('.$c.' AS text)) IN (\'0\',\'f\',\'false\',\'F\',\'no\') THEN \'3\'
                ELSE \'0\'
            END';
        }

        return 'CASE
            WHEN '.$c.' IS NULL THEN \'0\'
            WHEN TRIM(CAST('.$c.' AS CHAR)) IN (\'1\',\'t\',\'true\',\'T\',\'yes\') THEN \'2\'
            WHEN TRIM(CAST('.$c.' AS CHAR)) IN (\'0\',\'f\',\'false\',\'F\',\'no\') THEN \'3\'
            ELSE \'0\'
        END';
    }
}
