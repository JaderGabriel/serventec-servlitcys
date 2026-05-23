<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Conexão matrícula ↔ turma: coluna direta em matricula (ref_cod_turma) ou pivô pmieducar.matricula_turma.
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
     * Junta matricula (alias m) à turma (aliases mt_filter + t_filter como no iEducar).
     *
     * @param  bool  $left  Quando true, inclui matrículas ainda sem enturmação (LEFT JOIN).
     */
    public static function joinMatriculaToTurma(Builder $q, Connection $db, City $city, string $matAlias = 'm', bool $left = false): void
    {
        $turma = IeducarSchema::resolveTable('turma', $city);
        $mId = (string) config('ieducar.columns.matricula.id');
        $mTurma = (string) config('ieducar.columns.matricula.turma');
        $tId = (string) config('ieducar.columns.turma.id');
        $join = $left ? 'leftJoin' : 'join';

        if (self::usePivotTable($db, $city)) {
            $mt = IeducarSchema::resolveTable('matricula_turma', $city);
            $mtMat = (string) config('ieducar.columns.matricula_turma.matricula');
            $mtTurma = (string) config('ieducar.columns.matricula_turma.turma');
            $q->{$join}($mt.' as mt_filter', $matAlias.'.'.$mId, '=', 'mt_filter.'.$mtMat)
                ->{$join}($turma.' as t_filter', 'mt_filter.'.$mtTurma, '=', 't_filter.'.$tId);
        } else {
            $q->{$join}($turma.' as t_filter', $matAlias.'.'.$mTurma, '=', 't_filter.'.$tId);
        }
    }

    /**
     * Coluna «ano letivo» na matricula, quando existir na base.
     */
    public static function matriculaAnoColumn(Connection $db, City $city): ?string
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);

            return IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.ano'),
                'ano',
                'ref_ano_letivo',
                'ano_letivo',
            ]), $city);
        } catch (\InvalidArgumentException) {
            return null;
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
            'ref_ano_letivo',
            'ano_letivo',
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

        // Preferir FK para pmieducar.turma_turno (i-Educar 2.x) antes de cadastro.turno.
        $turno = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            'ref_cod_turma_turno',
            'turma_turno_id',
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
    public static function applyTurmaFiltersWhere(Builder $q, Connection $db, City $city, IeducarFilterState $filters, string $turmaAlias = 't_filter', string $matAlias = 'm'): void
    {
        $cols = self::turmaFilterColumns($db, $city);
        self::applyYearFilter($q, $db, $city, $filters, $turmaAlias, $matAlias);
        self::whereTurmaColumnEqualsFilterId($q, $db, $turmaAlias, $cols['escola'], $filters->escola_id);
        self::whereTurmaColumnEqualsFilterId($q, $db, $turmaAlias, $cols['curso'], $filters->curso_id);
        self::whereTurmaColumnEqualsFilterId($q, $db, $turmaAlias, $cols['turno'], $filters->turno_id);
    }

    /**
     * Ano letivo: coincide com turma.ano ou matricula.ano (i-Educar costuma gravar o ano na matrícula
     * mesmo com enturmação; turma.ano pode estar vazio ou desatualizado em bases PostgreSQL).
     */
    public static function applyYearFilter(Builder $q, Connection $db, City $city, IeducarFilterState $filters, string $turmaAlias = 't_filter', string $matAlias = 'm'): void
    {
        $yearVal = $filters->yearFilterValue();
        if ($yearVal === null) {
            return;
        }

        $cols = self::turmaFilterColumns($db, $city);
        $turmaYear = $cols['year'];
        $mAno = self::matriculaAnoColumn($db, $city);
        $grammar = $db->getQueryGrammar();

        if ($turmaYear !== '' && $mAno !== null) {
            $tYear = $grammar->wrap($turmaAlias).'.'.$grammar->wrap($turmaYear);
            $mYear = $grammar->wrap($matAlias).'.'.$grammar->wrap($mAno);
            $q->where(function (Builder $w) use ($yearVal, $tYear, $mYear): void {
                $w->whereRaw($tYear.' = ?', [$yearVal])
                    ->orWhereRaw($mYear.' = ?', [$yearVal]);
            });

            return;
        }

        if ($turmaYear !== '') {
            $q->where($turmaAlias.'.'.$turmaYear, $yearVal);

            return;
        }

        if ($mAno !== null) {
            $q->where($matAlias.'.'.$mAno, $yearVal);
        }
    }

    /**
     * Ano letivo em consultas que partem só da turma (ex.: capacidade no mapa).
     * Alinha com {@see applyYearFilter}: turma.ano OU matrícula ativa no ano quando ambas existirem.
     */
    public static function applyYearFilterOnTurmaQuery(
        Builder $q,
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $turmaAlias = 't',
    ): void {
        $yearVal = $filters->yearFilterValue();
        if ($yearVal === null) {
            return;
        }

        $cols = self::turmaFilterColumns($db, $city);
        $turmaYear = $cols['year'];
        $mAno = self::matriculaAnoColumn($db, $city);
        $grammar = $db->getQueryGrammar();
        $tId = (string) config('ieducar.columns.turma.id');
        $tIdSql = $grammar->wrap($turmaAlias).'.'.$grammar->wrap($tId);

        if ($turmaYear !== '' && $mAno !== null) {
            $tYear = $grammar->wrap($turmaAlias).'.'.$grammar->wrap($turmaYear);
            $q->where(function (Builder $w) use ($db, $city, $yearVal, $tYear, $tIdSql, $mAno): void {
                $w->whereRaw($tYear.' = ?', [$yearVal]);
                $w->orWhereExists(function (Builder $ex) use ($db, $city, $yearVal, $tIdSql, $mAno): void {
                    $mat = IeducarSchema::resolveTable('matricula', $city);
                    $mAtivo = (string) config('ieducar.columns.matricula.ativo');
                    $mId = (string) config('ieducar.columns.matricula.id');
                    $ex->from($mat.' as m_yf');
                    MatriculaAtivoFilter::apply($ex, $db, 'm_yf.'.$mAtivo, $city);
                    $ex->where('m_yf.'.$mAno, $yearVal);
                    if (self::usePivotTable($db, $city)) {
                        $mt = IeducarSchema::resolveTable('matricula_turma', $city);
                        $mtMat = (string) config('ieducar.columns.matricula_turma.matricula');
                        $mtTurma = (string) config('ieducar.columns.matricula_turma.turma');
                        $ex->join($mt.' as mt_yf', 'm_yf.'.$mId, '=', 'mt_yf.'.$mtMat)
                            ->whereColumn('mt_yf.'.$mtTurma, '=', $tIdSql);
                    } else {
                        $mTurma = (string) config('ieducar.columns.matricula.turma');
                        $ex->whereColumn('m_yf.'.$mTurma, '=', $tIdSql);
                    }
                });
            });

            return;
        }

        if ($turmaYear !== '') {
            $q->where($turmaAlias.'.'.$turmaYear, $yearVal);
        }
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
     *
     * @param  bool  $allowNullPivot  Com LEFT JOIN, linhas sem pivô ou com ativo NULL continuam na contagem.
     */
    public static function applyPivotAtivoIfNeeded(Builder $q, Connection $db, City $city, bool $allowNullPivot = false): void
    {
        if (! self::usePivotTable($db, $city)) {
            return;
        }
        $mtAtivo = (string) config('ieducar.columns.matricula_turma.ativo');
        if ($mtAtivo === '') {
            return;
        }

        $col = 'mt_filter.'.$mtAtivo;
        if (! $allowNullPivot) {
            MatriculaAtivoFilter::apply($q, $db, $col, $city);

            return;
        }

        if ($db->getDriverName() === 'pgsql') {
            $grammar = $db->getQueryGrammar();
            $wrapped = $grammar->wrap('mt_filter').'.'.$grammar->wrap($mtAtivo);
            $q->where(function (Builder $w) use ($wrapped, $col): void {
                $w->whereNull($col)
                    ->orWhereRaw(MatriculaAtivoFilter::pgsqlActiveExpression($wrapped));
            });

            return;
        }

        $q->where(function (Builder $w) use ($col): void {
            $w->whereNull($col)
                ->orWhereIn($col, [1, '1', true, 't', 'true']);
        });
    }
}
