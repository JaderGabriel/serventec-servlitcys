<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Ligação matrícula ↔ turma: coluna direta em matricula (ref_cod_turma) ou pivô pmieducar.matricula_turma.
 */
final class MatriculaTurmaJoin
{
    /** @var array<string, bool> */
    private static array $pivotCache = [];

    /** @var array<string, array{year: string, escola: string, curso: string, turno: string, serie: string}> */
    private static array $turmaFilterColumnsCache = [];

    /**
     * Usar tabela matricula_turma quando matricula não tiver a coluna configurada (ex.: ref_cod_turma).
     */
    public static function usePivotTable(Connection $db, City $city): bool
    {
        $key = $city->getKey().'_'.spl_object_id($db).'_'.(string) config('ieducar.columns.matricula.turma');
        if (array_key_exists($key, self::$pivotCache)) {
            return self::$pivotCache[$key];
        }

        $matTable = IeducarSchema::resolveTable('matricula', $city);
        $directCol = (string) config('ieducar.columns.matricula.turma');
        if (IeducarColumnInspector::columnExists($db, $matTable, $directCol, $city)) {
            return self::$pivotCache[$key] = false;
        }

        $pivotTable = IeducarSchema::resolveTable('matricula_turma', $city);
        $cMat = (string) config('ieducar.columns.matricula_turma.matricula');
        $cTurma = (string) config('ieducar.columns.matricula_turma.turma');
        $out = IeducarColumnInspector::columnExists($db, $pivotTable, $cMat, $city)
            && IeducarColumnInspector::columnExists($db, $pivotTable, $cTurma, $city);

        return self::$pivotCache[$key] = $out;
    }

    /**
     * Junta sempre matricula (alias m) à turma (aliases mt_filter + t_filter como no iEducar).
     */
    public static function joinMatriculaToTurma(Builder $q, Connection $db, City $city, string $matAlias = 'm'): void
    {
        $turma = IeducarSchema::resolveTable('turma', $city);
        $mId = (string) config('ieducar.columns.matricula.id');
        $mTurma = (string) config('ieducar.columns.matricula.turma');
        $tId = (string) config('ieducar.columns.turma.id');

        if (self::usePivotTable($db, $city)) {
            $mt = IeducarSchema::resolveTable('matricula_turma', $city);
            $mtMat = (string) config('ieducar.columns.matricula_turma.matricula');
            $mtTurma = (string) config('ieducar.columns.matricula_turma.turma');
            $q->join($mt.' as mt_filter', $matAlias.'.'.$mId, '=', 'mt_filter.'.$mtMat)
                ->join($turma.' as t_filter', 'mt_filter.'.$mtTurma, '=', 't_filter.'.$tId);
        } else {
            $q->join($turma.' as t_filter', $matAlias.'.'.$mTurma, '=', 't_filter.'.$tId);
        }
    }

    /**
     * Colunas reais em pmieducar.turma para filtros (algumas bases não têm ref_cod_escola; usam cod_escola, etc.).
     *
     * @return array{year: string, escola: string, curso: string, turno: string, serie: string}
     */
    public static function turmaFilterColumns(Connection $db, City $city): array
    {
        $key = $city->getKey().'_'.spl_object_id($db);
        if (isset(self::$turmaFilterColumnsCache[$key])) {
            return self::$turmaFilterColumnsCache[$key];
        }

        $turma = IeducarSchema::resolveTable('turma', $city);

        $year = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.year'),
            'ano',
            'year',
        ]), $city) ?? 'ano';

        $escola = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.escola'),
            'ref_cod_escola',
            'cod_escola',
            'ref_escola',
            'escola_id',
            'id_escola',
            'cod_escola_turma',
        ]), $city) ?? '';

        $curso = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.curso'),
            'ref_cod_curso',
            'cod_curso',
        ]), $city) ?? '';

        $turno = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.turno'),
            'ref_cod_turno',
            'cod_turno',
        ]), $city) ?? '';

        $serie = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.serie'),
            'ref_cod_serie',
            'cod_serie',
            'ref_serie',
            'serie_id',
            'id_serie',
            'cod_serie_turma',
        ]), $city) ?? '';

        return self::$turmaFilterColumnsCache[$key] = [
            'year' => $year,
            'escola' => $escola,
            'curso' => $curso,
            'turno' => $turno,
            'serie' => $serie,
        ];
    }

    /**
     * Igualdade na coluna da turma com valor do filtro (FK numérica ou texto).
     * Use $turmaAlias vazio quando a query usa {@see Builder::table()} sem alias (só o nome da coluna).
     */
    public static function whereTurmaColumnEqualsFilterId(Builder $q, Connection $db, string $turmaAlias, string $column, ?string $value): void
    {
        if ($value === null || $column === '') {
            return;
        }

        $trim = trim($value);
        if ($trim === '') {
            return;
        }

        $grammar = $db->getQueryGrammar();
        $colSql = $turmaAlias !== ''
            ? $grammar->wrap($turmaAlias).'.'.$grammar->wrap($column)
            : $grammar->wrap($column);

        if (is_numeric($trim)) {
            $q->whereRaw($colSql.' = ?', [(int) $trim]);

            return;
        }

        $q->whereRaw($colSql.' = ?', [$trim]);
    }

    /**
     * Filtros de dimensão na turma (ano letivo, escola, curso, turno).
     */
    public static function applyTurmaFiltersWhere(Builder $q, Connection $db, City $city, IeducarFilterState $filters, string $turmaAlias = 't_filter'): void
    {
        $cols = self::turmaFilterColumns($db, $city);
        $yearVal = $filters->yearFilterValue();

        if ($yearVal !== null && $cols['year'] !== '') {
            $q->where($turmaAlias.'.'.$cols['year'], $yearVal);
        }
        self::whereTurmaColumnEqualsFilterId($q, $db, $turmaAlias, $cols['escola'], $filters->escola_id);
        self::whereTurmaColumnEqualsFilterId($q, $db, $turmaAlias, $cols['curso'], $filters->curso_id);
        self::whereTurmaColumnEqualsFilterId($q, $db, $turmaAlias, $cols['turno'], $filters->turno_id);
    }

    /**
     * Junta matricula (alias m) à turma quando ano/escola/curso/turno exigem recorte por turma.
     */
    public static function applyTurmaFiltersFromMatricula(Builder $q, Connection $db, City $city, IeducarFilterState $filters): void
    {
        $yearVal = $filters->yearFilterValue();
        $needsTurma = $yearVal !== null
            || $filters->escola_id !== null
            || $filters->curso_id !== null
            || $filters->turno_id !== null;

        if (! $needsTurma) {
            return;
        }

        self::joinMatriculaToTurma($q, $db, $city, 'm');
        self::applyPivotAtivoIfNeeded($q, $db, $city);
        self::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
    }

    /**
     * Filtro ativo em matricula_turma quando há pivô (alinhado a OverviewRepository::countMatriculas).
     */
    public static function applyPivotAtivoIfNeeded(Builder $q, Connection $db, City $city): void
    {
        if (! self::usePivotTable($db, $city)) {
            return;
        }
        $mtAtivo = (string) config('ieducar.columns.matricula_turma.ativo');
        MatriculaAtivoFilter::apply($q, $db, 'mt_filter.'.$mtAtivo);
    }
}
