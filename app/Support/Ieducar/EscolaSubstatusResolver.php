<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Ligação opcional escola → catálogo de «situação de funcionamento» / substatus (INEP / Educacenso).
 *
 * Em bases só com `escola.ativo`, o resolver devolve null e o painel mantém o binário ativa/inativa.
 */
final class EscolaSubstatusResolver
{
    /**
     * @return array{fk: string, catalog: string, id: string, name: string}|null
     */
    public static function resolveJoinSpec(Connection $db, City $city): ?array
    {
        $escolaT = IeducarSchema::resolveTable('escola', $city);
        if (! IeducarColumnInspector::tableExists($db, $escolaT, $city)) {
            return null;
        }

        $fkConfigured = trim((string) config('ieducar.columns.escola.substatus_fk', ''));
        $fk = $fkConfigured !== '' && IeducarColumnInspector::columnExists($db, $escolaT, $fkConfigured, $city)
            ? $fkConfigured
            : IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                'ref_cod_situacao_funcionamento',
                'ref_cod_escola_situacao_funcionamento',
                'ref_cod_situacao_funcionamento_escola',
                'situacao_funcionamento',
                'ref_cod_substatus_escola',
                'ref_cod_escola_situacao',
            ], $city);

        if ($fk === null || $fk === '') {
            return null;
        }

        $catalogTable = self::resolveCatalogTable($db, $city);
        if ($catalogTable === null) {
            return null;
        }

        $idConfigured = trim((string) config('ieducar.columns.escola_situacao_funcionamento.id', ''));
        $nameConfigured = trim((string) config('ieducar.columns.escola_situacao_funcionamento.name', ''));

        $idCol = IeducarColumnInspector::firstExistingColumn($db, $catalogTable, array_filter([
            $idConfigured !== '' ? $idConfigured : null,
            'cod_situacao_funcionamento',
            'cod_escola_situacao_funcionamento',
            'id',
            'codigo',
        ]), $city);

        $nameCol = IeducarColumnInspector::firstExistingColumn($db, $catalogTable, array_filter([
            $nameConfigured !== '' ? $nameConfigured : null,
            'nm_situacao',
            'nome',
            'descricao',
            'ds_situacao_funcionamento',
            'descricao_situacao',
        ]), $city);

        if ($idCol === null || $nameCol === null) {
            return null;
        }

        return [
            'fk' => $fk,
            'catalog' => $catalogTable,
            'id' => $idCol,
            'name' => $nameCol,
        ];
    }

    private static function resolveCatalogTable(Connection $db, City $city): ?string
    {
        $fromConfig = trim((string) config('ieducar.tables.escola_situacao_funcionamento', ''));
        if ($fromConfig !== '') {
            try {
                $t = IeducarSchema::resolveTable('escola_situacao_funcionamento', $city);
            } catch (\InvalidArgumentException) {
                return null;
            }
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, [
            'escola_situacao_funcionamento',
            'situacao_funcionamento',
            'situacao_funcionamento_escola',
            'escola_situacao',
        ], $city);
    }

    /**
     * @param  array{fk: string, catalog: string, id: string, name: string}  $spec
     */
    public static function applyLeftJoinCatalog(Builder $q, Connection $db, string $escolaAlias, string $catalogAlias, array $spec): void
    {
        $g = $db->getQueryGrammar();
        $lhs = $g->wrap($escolaAlias).'.'.$g->wrap($spec['fk']);
        $rhs = $g->wrap($catalogAlias).'.'.$g->wrap($spec['id']);
        $tableSql = $spec['catalog'].' as '.$catalogAlias;

        if ($db->getDriverName() === 'pgsql') {
            $q->leftJoin($tableSql, function ($join) use ($lhs, $rhs): void {
                $join->whereRaw('('.$lhs.')::text = ('.$rhs.')::text');
            });
        } else {
            $q->leftJoin($tableSql, function ($join) use ($lhs, $rhs): void {
                $join->whereRaw('CAST('.$lhs.' AS UNSIGNED) = CAST('.$rhs.' AS UNSIGNED)');
            });
        }
    }

    /**
     * Expressão SQL para rótulo do substatus (COALESCE + TRIM), para SELECT/GROUP BY alinhados.
     *
     * @param  array{fk: string, catalog: string, id: string, name: string}  $spec
     */
    public static function substatusLabelSql(Connection $db, string $catalogAlias, array $spec): string
    {
        $g = $db->getQueryGrammar();
        $ne = $g->wrap($catalogAlias).'.'.$g->wrap($spec['name']);
        $cast = $db->getDriverName() === 'pgsql' ? 'TEXT' : 'CHAR';
        $fb = $g->quoteString(__('Sem substatus'));

        return 'COALESCE(NULLIF(TRIM(CAST('.$ne.' AS '.$cast.')), ' . "''" . '), '.$fb.')';
    }
}
