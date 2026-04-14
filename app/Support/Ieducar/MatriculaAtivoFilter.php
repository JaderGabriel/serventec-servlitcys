<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Filtro «matrícula ativa» compatível com PostgreSQL (boolean / smallint / char) e MySQL.
 *
 * Quando é passada a cidade e a config ieducar.matricula_indicadores.incluir_situacao_inep
 * está activa, matrículas com alias «m» também entram se existir situação INEP em curso
 * (ex. codigo=1 em matricula_situacao), alinhando bases onde «ativo» está indefinido.
 */
final class MatriculaAtivoFilter
{
    public static function apply(Builder $query, Connection $db, string $columnRef, ?City $city = null): void
    {
        if ($columnRef === '') {
            return;
        }

        $ativoCol = (string) config('ieducar.columns.matricula.ativo', 'ativo');
        if (
            $city !== null
            && filter_var(config('ieducar.matricula_indicadores.incluir_situacao_inep', true), FILTER_VALIDATE_BOOLEAN)
            && self::isMatriculaAliasAtivoColumn($columnRef, $ativoCol)
        ) {
            $catalog = self::resolveMatriculaSituacaoCatalog($db, $city);
            if ($catalog !== null) {
                self::applyMatriculaAtivoOrSituacaoInep($query, $columnRef, $catalog);

                return;
            }
        }

        self::applyLegacy($query, $db, $columnRef);
    }

    private static function isMatriculaAliasAtivoColumn(string $columnRef, string $ativoColumnName): bool
    {
        if ($ativoColumnName === '') {
            return false;
        }
        $parts = explode('.', $columnRef);
        if (count($parts) !== 2) {
            return false;
        }

        $a = trim($parts[0], '`"');
        $c = trim($parts[1], '`"');

        return $a === 'm' && $c === $ativoColumnName;
    }

    /**
     * @return ?array{fk: string, msTable: string, msPk: string, codigoCol: string}
     */
    private static function resolveMatriculaSituacaoCatalog(Connection $db, City $city): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $fkCol = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
            'ref_cod_matricula_situacao',
            'ref_cod_situacao_matricula',
        ]), $city);

        $msTable = null;
        try {
            $msTable = IeducarSchema::resolveTable('matricula_situacao', $city);
        } catch (\InvalidArgumentException) {
            $msTable = null;
        }

        if ($fkCol === null || $msTable === null || ! IeducarColumnInspector::tableExists($db, $msTable, $city)) {
            return null;
        }

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

        if ($msPk === null || $codigoCol === null) {
            return null;
        }

        return ['fk' => $fkCol, 'msTable' => $msTable, 'msPk' => $msPk, 'codigoCol' => $codigoCol];
    }

    /**
     * @param  array{fk: string, msTable: string, msPk: string, codigoCol: string}  $catalog
     */
    private static function applyMatriculaAtivoOrSituacaoInep(Builder $query, string $columnRef, array $catalog): void
    {
        $codes = config('ieducar.matricula_indicadores.situacao_inep_como_ativa');
        if (! is_array($codes) || $codes === []) {
            $codes = ['1'];
        }
        $codes = array_values(array_unique(array_map(static fn ($v) => trim((string) $v), $codes)));
        if ($codes === []) {
            $codes = ['1'];
        }

        $colWrapped = self::wrapQualifiedColumn($db, $columnRef);
        $grammar = $db->getQueryGrammar();
        $mFk = $grammar->wrap('m').'.'.$grammar->wrap($catalog['fk']);
        $msAlias = 'ms_matr_ind';
        $wrapMs = static fn (string $c) => $grammar->wrap($msAlias).'.'.$grammar->wrap($c);

        $driver = $db->getDriverName();

        $query->where(function (Builder $w) use ($driver, $columnRef, $colWrapped, $mFk, $catalog, $msAlias, $wrapMs, $codes): void {
            if ($driver === 'pgsql') {
                $w->whereRaw(self::pgsqlActiveExpression($colWrapped));
            } else {
                $w->whereIn($columnRef, [1, '1', true, 't', 'true']);
            }

            $w->orWhereExists(function (Builder $sub) use ($driver, $mFk, $catalog, $msAlias, $wrapMs, $codes): void {
                $sub->selectRaw('1')
                    ->from($catalog['msTable'].' as '.$msAlias)
                    ->whereColumn($mFk, $wrapMs($catalog['msPk']));

                $sub->where(function (Builder $c) use ($driver, $wrapMs, $codes, $catalog): void {
                    $cod = $wrapMs($catalog['codigoCol']);
                    foreach ($codes as $code) {
                        if ($driver === 'pgsql') {
                            $c->orWhereRaw('trim('.$cod.'::text) = ?', [$code]);
                        } else {
                            $c->orWhereRaw('TRIM(CAST('.$cod.' AS CHAR)) = ?', [$code]);
                        }
                    }
                });
            });
        });
    }

    private static function applyLegacy(Builder $query, Connection $db, string $columnRef): void
    {
        if ($db->getDriverName() === 'pgsql') {
            $col = self::wrapQualifiedColumn($db, $columnRef);
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
