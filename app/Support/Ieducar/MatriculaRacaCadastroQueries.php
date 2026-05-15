<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Cor/raça no cadastro — mesma lógica de join da aba Inclusão (fisica_raca → raca, «Não declarado»).
 */
final class MatriculaRacaCadastroQueries
{
    public static function canQuery(Connection $db, City $city): bool
    {
        return self::buildRacaJoinContext($db, $city) !== null;
    }

    /**
     * Matrículas sem cor/raça declarada (vazio, sem vínculo ou rótulo «não declarado»), por escola.
     *
     * @return list<array{escola_id: string, escola: string, total: int}>
     */
    public static function matriculasSemRacaDeclaradaPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $ctx = self::buildRacaJoinContext($db, $city);
            if ($ctx === null) {
                return [];
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mId = (string) config('ieducar.columns.matricula.id');
            $aId = (string) config('ieducar.columns.aluno.id');
            $grammar = $db->getQueryGrammar();
            $distinctMat = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);

            self::applyRacaJoins($db, $q, $ctx, 'a', $aluno, $city);
            self::applySemRacaDeclaradaWhere($db, $q, $ctx);

            return DiscrepanciesQueries::aggregatePorEscolaPublic($db, $city, $filters, $q, $distinctMat);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Total de matrículas «não declarado» na rede (para cruzamento com gráfico de Inclusão).
     */
    public static function totalMatriculasSemRacaDeclarada(Connection $db, City $city, IeducarFilterState $filters): int
    {
        $rows = self::matriculasSemRacaDeclaradaPorEscola($db, $city, $filters);

        return array_sum(array_column($rows, 'total'));
    }

    /**
     * @return ?array{
     *   racaQualified: string,
     *   rIdCol: string,
     *   rNameCol: string,
     *   path: string,
     *   fisicaRacaPivot: ?array{qualified: string, idpesCol: string, racaFkCol: string},
     *   aPessoa: ?string,
     *   pId: ?string,
     *   pRaca: ?string,
     *   aRaca: ?string,
     *   aIdpes: ?string
     * }
     */
    private static function buildRacaJoinContext(Connection $db, City $city): ?array
    {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        if (! IeducarColumnInspector::tableExists($db, $mat, $city)
            || ! IeducarColumnInspector::tableExists($db, $aluno, $city)) {
            return null;
        }

        $racaSpec = self::resolveRacaJoinSpec($db, $city);
        if ($racaSpec === null) {
            return null;
        }

        $alunoTable = $aluno;
        $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $alunoTable, array_filter([
            (string) config('ieducar.columns.aluno.pessoa'),
            'ref_cod_pessoa', 'ref_idpes', 'idpes',
        ]), $city);
        $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $alunoTable, ['ref_idpes', 'idpes'], $city);

        $pessoa = IeducarSchema::resolveTable('pessoa', $city);
        $pRaca = null;
        $pId = null;
        if (IeducarColumnInspector::tableExists($db, $pessoa, $city) && $aPessoa !== null) {
            $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, ['idpes', 'id', 'cod_pessoa'], $city);
            $pRaca = IeducarColumnInspector::firstExistingColumn($db, $pessoa, [
                (string) config('ieducar.columns.pessoa.raca'),
                'ref_cod_raca', 'cod_raca', 'cor_raca',
            ], $city);
        }

        $aRaca = $pRaca === null
            ? IeducarColumnInspector::firstExistingColumn($db, $alunoTable, ['ref_cod_raca', 'cod_raca', 'cor_raca'], $city)
            : null;

        $fisicaRacaPivot = self::resolveFisicaRacaPivotSpec($db, $city);

        $path = match (true) {
            $fisicaRacaPivot !== null && $aIdpes !== null => 'fisica_raca_pivot',
            $pRaca !== null && $pId !== null && $aPessoa !== null => 'pessoa',
            $aRaca !== null => 'aluno',
            default => null,
        };

        if ($path === null) {
            return null;
        }

        return array_merge($racaSpec, [
            'path' => $path,
            'fisicaRacaPivot' => $fisicaRacaPivot,
            'aPessoa' => $aPessoa,
            'pId' => $pId,
            'pRaca' => $pRaca,
            'aRaca' => $aRaca,
            'aIdpes' => $aIdpes,
            'racaQualified' => $racaSpec['qualified'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private static function applyRacaJoins(Connection $db, Builder $q, array $ctx, string $alunoAlias, string $alunoTable, City $city): void
    {
        $racaT = $ctx['racaQualified'];
        $rIdCol = $ctx['rIdCol'];
        $pessoa = IeducarSchema::resolveTable('pessoa', $city);

        if ($ctx['path'] === 'fisica_raca_pivot' && is_array($ctx['fisicaRacaPivot'])) {
            $pivot = $ctx['fisicaRacaPivot'];
            $q->leftJoin($pivot['qualified'].' as fr', $alunoAlias.'.'.$ctx['aIdpes'], '=', 'fr.'.$pivot['idpesCol']);
            self::leftJoinRacaCatalogOnFk($db, $q, 'fr', $pivot['racaFkCol'], $racaT, 'r', $rIdCol);
        } elseif ($ctx['path'] === 'pessoa') {
            $q->join($pessoa.' as p', $alunoAlias.'.'.$ctx['aPessoa'], '=', 'p.'.$ctx['pId']);
            self::leftJoinRacaCatalogOnFk($db, $q, 'p', (string) $ctx['pRaca'], $racaT, 'r', $rIdCol);
        } elseif ($ctx['path'] === 'aluno') {
            self::leftJoinRacaCatalogOnFk($db, $q, $alunoAlias, (string) $ctx['aRaca'], $racaT, 'r', $rIdCol);
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private static function applySemRacaDeclaradaWhere(Connection $db, Builder $q, array $ctx): void
    {
        $grammar = $db->getQueryGrammar();
        $rId = $grammar->wrap('r').'.'.$grammar->wrap($ctx['rIdCol']);
        $rName = $grammar->wrap('r').'.'.$grammar->wrap($ctx['rNameCol']);

        $keywords = config('ieducar.consultoria.raca_nao_declarado_keywords', [
            'não declarado', 'nao declarado', 'sem informação', 'sem informacao', 'ignorado',
        ]);
        if (! is_array($keywords)) {
            $keywords = [];
        }

        $q->where(function (Builder $w) use ($rId, $rName, $keywords, $db, $ctx, $grammar): void {
            $w->whereNull($rId)
                ->orWhere($rId, 0)
                ->orWhere($rName, '')
                ->orWhereNull($rName);
            foreach ($keywords as $kw) {
                $kw = trim((string) $kw);
                if ($kw === '') {
                    continue;
                }
                if ($db->getDriverName() === 'pgsql') {
                    $w->orWhereRaw('LOWER(TRIM(COALESCE('.$rName."::text, ''))) LIKE ?", ['%'.mb_strtolower($kw).'%']);
                } else {
                    $w->orWhereRaw('LOWER(TRIM(COALESCE('.$rName.", ''))) LIKE ?", ['%'.mb_strtolower($kw).'%']);
                }
            }
            if ($ctx['path'] === 'fisica_raca_pivot' && is_array($ctx['fisicaRacaPivot'])) {
                $fk = $grammar->wrap('fr').'.'.$grammar->wrap($ctx['fisicaRacaPivot']['racaFkCol']);
                $w->orWhereNull($fk)->orWhere($fk, 0)->orWhere($fk, '');
            }
        });
    }

    /**
     * @return ?array{qualified: string, idCol: string, nameCol: string}
     */
    private static function resolveRacaJoinSpec(Connection $db, City $city): ?array
    {
        foreach (IeducarSchema::racaTableCandidates($city) as $qualified) {
            if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
                continue;
            }
            $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.raca.id'),
                'cod_raca', 'id', 'id_raca',
            ]), $city);
            $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.raca.name'),
                'nm_raca', 'nome', 'nm_cor', 'descricao',
            ]), $city);
            if ($idCol !== null) {
                return [
                    'qualified' => $qualified,
                    'idCol' => $idCol,
                    'nameCol' => $nameCol ?? $idCol,
                ];
            }
        }

        return null;
    }

    /**
     * @return ?array{qualified: string, idpesCol: string, racaFkCol: string}
     */
    private static function resolveFisicaRacaPivotSpec(Connection $db, City $city): ?array
    {
        $candidates = [];
        try {
            $candidates[] = IeducarSchema::resolveTable('fisica_raca', $city);
        } catch (\InvalidArgumentException) {
        }
        $candidates[] = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica_raca';

        foreach ($candidates as $qualified) {
            if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
                continue;
            }
            $idpesCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, ['ref_idpes', 'idpes'], $city);
            $racaFkCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, ['ref_cod_raca', 'cod_raca'], $city);
            if ($idpesCol !== null && $racaFkCol !== null) {
                return ['qualified' => $qualified, 'idpesCol' => $idpesCol, 'racaFkCol' => $racaFkCol];
            }
        }

        return null;
    }

    private static function leftJoinRacaCatalogOnFk(
        Connection $db,
        Builder $q,
        string $lhsAlias,
        string $lhsCol,
        string $racaQualified,
        string $racaAlias,
        string $rIdCol,
    ): void {
        $g = $db->getQueryGrammar();
        $lhs = $g->wrap($lhsAlias).'.'.$g->wrap($lhsCol);
        $rhs = $g->wrap($racaAlias).'.'.$g->wrap($rIdCol);
        $q->leftJoin($racaQualified.' as '.$racaAlias, function ($join) use ($db, $lhs, $rhs): void {
            if ($db->getDriverName() === 'pgsql') {
                $join->whereRaw('('.$lhs.')::text = ('.$rhs.')::text');
            } else {
                $join->whereRaw('CAST('.$lhs.' AS UNSIGNED) = CAST('.$rhs.' AS UNSIGNED)');
            }
        });
    }
}
