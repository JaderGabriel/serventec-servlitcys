<?php

namespace App\Support\Ieducar;

use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * Verifica se cada rotina de discrepância pode executar nesta base i-Educar
 * (alinhado às mesmas regras de join usadas em MatriculaChartQueries).
 */
final class DiscrepanciesAvailability
{
    public static function matriculaCore(Connection $db, City $city): bool
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);

            return IeducarColumnInspector::tableExists($db, $mat, $city)
                && (self::canJoinTurma($db, $city) || self::matriculaEscolaColumn($db, $city) !== null);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function canJoinTurma(Connection $db, City $city): bool
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $turmaCol = (string) config('ieducar.columns.matricula.turma');
            if (IeducarColumnInspector::columnExists($db, $mat, $turmaCol, $city)) {
                return IeducarColumnInspector::tableExists($db, IeducarSchema::resolveTable('turma', $city), $city);
            }

            return MatriculaTurmaJoin::usePivotTable($db, $city);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function matriculaEscolaColumn(Connection $db, City $city): ?string
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);

            return IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.escola'),
                'ref_ref_cod_escola',
                'ref_cod_escola',
                'cod_escola',
            ]), $city);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function matriculaAluno(Connection $db, City $city): bool
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);

            return IeducarColumnInspector::tableExists($db, $mat, $city)
                && IeducarColumnInspector::tableExists($db, $aluno, $city);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function corRacaCadastro(Connection $db, City $city): bool
    {
        if (! self::matriculaAluno($db, $city) || ! self::matriculaCore($db, $city)) {
            return false;
        }

        return MatriculaRacaCadastroQueries::canQuery($db, $city)
            && self::matriculaCore($db, $city);
    }

    public static function pessoaCadastro(Connection $db, City $city): bool
    {
        return DiscrepanciesQueries::hasPessoaAlunoCadastroPath($db, $city);
    }

    public static function escolaComMatricula(Connection $db, City $city): bool
    {
        try {
            return self::matriculaCore($db, $city)
                && IeducarColumnInspector::tableExists($db, IeducarSchema::resolveTable('escola', $city), $city);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function escolaAtivoColumn(Connection $db, City $city): bool
    {
        if (! self::escolaComMatricula($db, $city)) {
            return false;
        }
        $escola = IeducarSchema::resolveTable('escola', $city);
        $activeCol = (string) config('ieducar.columns.escola.active', 'ativo');

        return $activeCol !== '' && IeducarColumnInspector::columnExists($db, $escola, $activeCol, $city);
    }

    public static function escolaGeoColumns(Connection $db, City $city): bool
    {
        if (! self::escolaComMatricula($db, $city)) {
            return false;
        }
        $escola = IeducarSchema::resolveTable('escola', $city);
        $lat = IeducarColumnInspector::firstExistingColumn($db, $escola, ['latitude', 'lat', 'geo_lat'], $city);
        $lng = IeducarColumnInspector::firstExistingColumn($db, $escola, ['longitude', 'lng', 'lon', 'geo_lng'], $city);

        return $lat !== null || $lng !== null;
    }

    /**
     * Rotina de posição no mapa: colunas na escola i-Educar e/ou cache local school_unit_geos.
     */
    public static function escolaPosicaoMapa(Connection $db, City $city): bool
    {
        if (! self::escolaComMatricula($db, $city)) {
            return false;
        }

        return self::escolaGeoColumns($db, $city) || SchoolGeoPositionResolver::cacheTableUsable($city);
    }

    public static function recursoProvaCadastro(Connection $db, City $city): bool
    {
        return self::matriculaAluno($db, $city)
            && InclusionRecursoProvaQueries::canQuery($db, $city);
    }

    public static function matriculaSituacao(Connection $db, City $city): bool
    {
        return self::matriculaCore($db, $city)
            && MatriculaSituacaoResolver::resolveChaveAgrupamento($db, $city) !== null;
    }

    public static function neeComTurma(Connection $db, City $city): bool
    {
        return self::matriculaAluno($db, $city) && self::canJoinTurma($db, $city);
    }

    /**
     * Junta escola (alias e) à query de matrícula — turma.ref_escola ou FK na matrícula.
     *
     * @return ?array{qualified: string, idCol: string, nameCol: string}
     */
    public static function joinEscola(Builder $q, Connection $db, City $city): ?array
    {
        $escolaSpec = DiscrepanciesQueries::escolaJoinSpecPublic($db, $city);
        if ($escolaSpec === null) {
            return null;
        }

        ['qualified' => $escolaT, 'idCol' => $eId] = $escolaSpec;
        $grammar = $db->getQueryGrammar();
        $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eId);

        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        $sql = strtolower($q->toSql());
        $usesTurma = str_contains($sql, 't_filter');

        if ($usesTurma && $tc['escola'] !== '') {
            $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($tc['escola']);
            $q->join($escolaT.' as e', function ($join) use ($db, $tEsc, $ePk): void {
                if ($db->getDriverName() === 'pgsql') {
                    $join->whereRaw('('.$tEsc.')::text = ('.$ePk.')::text');
                } else {
                    $join->whereRaw('CAST('.$tEsc.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
                }
            });

            return $escolaSpec;
        }

        $mEsc = self::matriculaEscolaColumn($db, $city);
        if ($mEsc === null) {
            return null;
        }

        $mEscW = $grammar->wrap('m').'.'.$grammar->wrap($mEsc);
        $q->join($escolaT.' as e', function ($join) use ($db, $mEscW, $ePk): void {
            if ($db->getDriverName() === 'pgsql') {
                $join->whereRaw('('.$mEscW.')::text = ('.$ePk.')::text');
            } else {
                $join->whereRaw('CAST('.$mEscW.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
            }
        });

        return $escolaSpec;
    }
}
