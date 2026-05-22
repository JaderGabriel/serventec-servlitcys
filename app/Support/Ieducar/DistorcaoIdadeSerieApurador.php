<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Expressões SQL e contagens partilhadas — cadeia de fallback para limite etário e nascimento.
 */
final class DistorcaoIdadeSerieApurador
{
    public const NASC_PESSOA = 'pessoa';

    public const NASC_FISICA = 'fisica';

    public const NASC_HIBRIDO = 'hibrido';

    public const LIMITE_SERIE = 'serie';

    public const LIMITE_ETAPA = 'etapa_educacenso';

    public const LIMITE_FALLBACK = 'fallback';

    /**
     * @return array{com: int, sem: int, total: int}|null
     */
    public static function contagem(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        DistorcaoIdadeSerieContext $ctx,
        string $nascimentoModo,
        string $limiteModo,
        bool $refFromMatriculaAno,
        int $margemAnos,
    ): ?array {
        $exprs = self::buildExpressions($db, $ctx, $nascimentoModo, $limiteModo, $refFromMatriculaAno);
        if ($exprs === null) {
            return null;
        }

        try {
            $distorcaoCond = '('.$exprs['idade'].') > ('.$exprs['limite'].' + '.$margemAnos.')';

            $base = function () use ($db, $city, $filters, $ctx, $exprs, $distorcaoCond): Builder {
                $q = $db->table($ctx->matTable.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$ctx->mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm', left: true);
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city, allowNullPivot: true);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
                $q->join($ctx->alunoTable.' as a', 'm.'.$ctx->mAluno, '=', 'a.'.$ctx->aId);
                self::applyNascimentoJoins($q, $db, $ctx, $exprs['nascimento_modo']);
                if ($exprs['nascimento_modo'] !== self::NASC_FISICA) {
                    $q->join($ctx->pessoaTable.' as p', 'a.'.$ctx->aPessoa, '=', 'p.'.$ctx->pId);
                }
                self::joinSerieFlexible($q, $db, $ctx);
                $q->whereNotNull($exprs['birth_check'])
                    ->whereRaw('('.$exprs['limite'].') IS NOT NULL');

                return $q;
            };

            $com = (int) $base()->whereRaw($distorcaoCond)->count();
            $sem = (int) $base()->whereRaw('NOT ('.$distorcaoCond.')')->count();
            if ($com === 0 && $sem === 0) {
                return null;
            }

            return ['com' => $com, 'sem' => $sem, 'total' => $com + $sem];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * @return ?array{
     *   idade: string,
     *   limite: string,
     *   birth_check: string,
     *   nascimento_modo: string,
     *   year_expr: string
     * }
     */
    public static function buildExpressions(
        Connection $db,
        DistorcaoIdadeSerieContext $ctx,
        string $nascimentoModo,
        string $limiteModo,
        bool $refFromMatriculaAno,
    ): ?array {
        $limite = self::limiteExprSql($db, $ctx, $limiteModo);
        if ($limite === null) {
            return null;
        }

        $birth = self::nascimentoExprSql($db, $ctx, $nascimentoModo);
        if ($birth === null) {
            return null;
        }

        $yearExpr = self::yearExprSql($db, $ctx, $refFromMatriculaAno);
        if ($yearExpr === null) {
            return null;
        }

        $refDate = DistorcaoIdadeSerieEngine::refDateCorteEscolarSql($db, $yearExpr);
        $idade = DistorcaoIdadeSerieEngine::idadeAnosCompletosSql($db, $refDate, $birth['expr']);

        return [
            'idade' => $idade,
            'limite' => $limite,
            'birth_check' => $birth['not_null'],
            'nascimento_modo' => $nascimentoModo,
            'year_expr' => $yearExpr,
        ];
    }

    public static function limiteExprSql(Connection $db, DistorcaoIdadeSerieContext $ctx, string $modo): ?string
    {
        $g = $db->getQueryGrammar();
        $wrapS = $g->wrap('s');

        if ($modo === self::LIMITE_SERIE) {
            if ($ctx->serieLimitCol === null) {
                return null;
            }

            return $wrapS.'.'.$g->wrap($ctx->serieLimitCol);
        }

        if ($modo === self::LIMITE_ETAPA) {
            $etapaCase = self::etapaEducacensoCaseSql($db, $ctx);
            if ($etapaCase === null) {
                return null;
            }

            return $etapaCase;
        }

        $parts = [];
        if ($ctx->serieLimitCol !== null) {
            $col = $wrapS.'.'.$g->wrap($ctx->serieLimitCol);
            $parts[] = 'NULLIF('.$col.', 0)';
        }
        if ($ctx->serieIdadeFinalCol !== null) {
            $col = $wrapS.'.'.$g->wrap($ctx->serieIdadeFinalCol);
            $parts[] = 'NULLIF('.$col.', 0)';
        }
        if ($ctx->serieIdadeIdealCol !== null) {
            $col = $wrapS.'.'.$g->wrap($ctx->serieIdadeIdealCol);
            $parts[] = 'NULLIF('.$col.', 0)';
        }
        $etapaCase = self::etapaEducacensoCaseSql($db, $ctx);
        if ($etapaCase !== null) {
            $parts[] = $etapaCase;
        }
        if ($parts === []) {
            return null;
        }

        return 'COALESCE('.implode(', ', $parts).', 99)';
    }

    /**
     * @return ?array{expr: string, not_null: string}
     */
    public static function nascimentoExprSql(Connection $db, DistorcaoIdadeSerieContext $ctx, string $modo): ?array
    {
        $g = $db->getQueryGrammar();

        if ($modo === self::NASC_PESSOA) {
            if ($ctx->birthColPessoa === null) {
                return null;
            }
            $expr = $g->wrap('p').'.'.$g->wrap($ctx->birthColPessoa);

            return ['expr' => $expr, 'not_null' => $expr];
        }

        if ($modo === self::NASC_FISICA) {
            if ($ctx->fisicaTable === null || $ctx->fisicaBirthCol === null) {
                return null;
            }
            $expr = $g->wrap('f').'.'.$g->wrap($ctx->fisicaBirthCol);

            return ['expr' => $expr, 'not_null' => $expr];
        }

        if ($modo === self::NASC_HIBRIDO) {
            $p = $ctx->birthColPessoa !== null
                ? $g->wrap('p').'.'.$g->wrap($ctx->birthColPessoa)
                : null;
            $f = ($ctx->fisicaBirthCol !== null && $ctx->fisicaTable !== null)
                ? $g->wrap('f').'.'.$g->wrap($ctx->fisicaBirthCol)
                : null;
            if ($p === null && $f === null) {
                return null;
            }
            if ($p !== null && $f !== null) {
                $expr = 'COALESCE('.$f.', '.$p.')';

                return ['expr' => $expr, 'not_null' => $expr];
            }

            $expr = $p ?? $f;

            return ['expr' => $expr, 'not_null' => $expr];
        }

        return null;
    }

    public static function yearExprSql(Connection $db, DistorcaoIdadeSerieContext $ctx, bool $refFromMatriculaAno): ?string
    {
        $grammar = $db->getQueryGrammar();

        if ($refFromMatriculaAno) {
            if ($ctx->matriculaAnoCol === null) {
                return null;
            }
            $mAnoW = $grammar->wrap('m').'.'.$grammar->wrap($ctx->matriculaAnoCol);
            if ($ctx->tc['year'] !== '') {
                $tYearW = $grammar->wrap('t_filter').'.'.$grammar->wrap($ctx->tc['year']);

                return 'COALESCE(NULLIF(CAST('.$mAnoW.' AS text), \'\'), CAST('.$tYearW.' AS text))';
            }

            return 'CAST('.$mAnoW.' AS text)';
        }

        if ($ctx->tc['year'] === '') {
            return null;
        }

        return $grammar->wrap('t_filter').'.'.$grammar->wrap($ctx->tc['year']);
    }

    public static function applyNascimentoJoins(Builder $q, Connection $db, DistorcaoIdadeSerieContext $ctx, string $modo): void
    {
        if ($modo === self::NASC_FISICA || $modo === self::NASC_HIBRIDO) {
            if ($ctx->fisicaTable !== null && $ctx->fisicaLinkCol !== null) {
                $q->leftJoin($ctx->fisicaTable.' as f', 'a.'.$ctx->aPessoa, '=', 'f.'.$ctx->fisicaLinkCol);
            }
        }
    }

    public static function joinSerieFlexible(Builder $q, Connection $db, DistorcaoIdadeSerieContext $ctx): void
    {
        $grammar = $db->getQueryGrammar();
        $sPk = $grammar->wrap('s').'.'.$grammar->wrap($ctx->sId);
        $parts = [];
        if ($ctx->serieJoinMatricula !== '') {
            $lhs = $grammar->wrap('m').'.'.$grammar->wrap($ctx->serieJoinMatricula);
            $parts[] = $db->getDriverName() === 'pgsql'
                ? '('.$lhs.')::text = ('.$sPk.')::text'
                : 'CAST('.$lhs.' AS UNSIGNED) = CAST('.$sPk.' AS UNSIGNED)';
        }
        if ($ctx->serieJoinTurma !== '') {
            $lhs = $grammar->wrap('t_filter').'.'.$grammar->wrap($ctx->serieJoinTurma);
            $parts[] = $db->getDriverName() === 'pgsql'
                ? '('.$lhs.')::text = ('.$sPk.')::text'
                : 'CAST('.$lhs.' AS UNSIGNED) = CAST('.$sPk.' AS UNSIGNED)';
        }
        if ($parts === []) {
            throw new \InvalidArgumentException('serie join missing');
        }
        $on = implode(' OR ', $parts);
        $q->join($ctx->serieTable.' as s', function ($join) use ($on): void {
            $join->whereRaw($on);
        });
    }

    /**
     * Histograma de anos de defasagem (idade − limite) entre matrículas com distorção INEP.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function histogramaDefasagemChart(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        DistorcaoIdadeSerieContext $ctx,
        int $margemAnos,
        string $groupBy = 'faixa',
    ): ?array {
        $exprs = self::buildExpressions($db, $ctx, self::NASC_HIBRIDO, self::LIMITE_FALLBACK, true);
        if ($exprs === null) {
            return null;
        }
        if ($groupBy === 'serie' && $ctx->serieNameCol === null) {
            return null;
        }

        try {
            $defasagemExpr = 'GREATEST(0, ('.$exprs['idade'].') - ('.$exprs['limite'].'))';
            $distorcaoCond = '('.$exprs['idade'].') > ('.$exprs['limite'].' + '.$margemAnos.')';
            $bucketExpr = $db->getDriverName() === 'pgsql'
                ? "CASE WHEN ({$defasagemExpr}) >= 5 THEN '5+' ELSE CAST(({$defasagemExpr}) AS text) END"
                : "CASE WHEN ({$defasagemExpr}) >= 5 THEN '5+' ELSE CAST(({$defasagemExpr}) AS CHAR) END";

            $q = $db->table($ctx->matTable.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$ctx->mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm', left: true);
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city, allowNullPivot: true);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $q->join($ctx->alunoTable.' as a', 'm.'.$ctx->mAluno, '=', 'a.'.$ctx->aId);
            self::applyNascimentoJoins($q, $db, $ctx, self::NASC_HIBRIDO);
            $q->join($ctx->pessoaTable.' as p', 'a.'.$ctx->aPessoa, '=', 'p.'.$ctx->pId);
            self::joinSerieFlexible($q, $db, $ctx);
            $q->whereNotNull($exprs['birth_check'])
                ->whereRaw($distorcaoCond);

            if ($groupBy === 'serie') {
                $g = $db->getQueryGrammar();
                $labelExpr = 'MAX('.$g->wrap('s').'.'.$g->wrap($ctx->serieNameCol).')';
                $q->selectRaw($labelExpr.' as lbl')
                    ->selectRaw('count(*) as c')
                    ->groupBy('s.'.$ctx->sId)
                    ->orderByDesc('c')
                    ->limit(12);
            } elseif ($groupBy === 'escola') {
                $escolaSpec = DiscrepanciesAvailability::joinEscola($q, $db, $city);
                if ($escolaSpec === null) {
                    return null;
                }
                $g = $db->getQueryGrammar();
                $eId = $escolaSpec['idCol'];
                $eName = $escolaSpec['nameCol'];
                $labelExpr = 'MAX('.$g->wrap('e').'.'.$g->wrap($eName).')';
                $q->selectRaw($labelExpr.' as lbl')
                    ->selectRaw('count(*) as c')
                    ->groupBy('e.'.$eId)
                    ->orderByDesc('c')
                    ->limit(12);
            } else {
                $q->selectRaw($bucketExpr.' as lbl')
                    ->selectRaw('count(*) as c')
                    ->groupByRaw($bucketExpr)
                    ->orderByRaw("CASE WHEN {$bucketExpr} = '5+' THEN 99 ELSE CAST({$bucketExpr} AS integer) END");
            }

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $labels[] = (string) ($row->lbl ?? '—');
                $values[] = (int) ($row->c ?? 0);
            }

            $title = match ($groupBy) {
                'serie' => __('Defasagem (idade − limite) — por série'),
                'escola' => __('Defasagem (idade − limite) — por escola'),
                default => __('Defasagem (idade − limite) — faixas de anos'),
            };

            return ChartPayload::barHorizontal(
                $title,
                __('Matrículas com distorção'),
                $labels,
                $values,
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Distorção × situação INEP da matrícula (códigos na tabela matricula_situacao).
     *
     * @return list<array{situacao: string, codigo: string, total: int, com_distorcao: int, pct_distorcao: ?float}>
     */
    public static function cruzamentoSituacaoInep(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        DistorcaoIdadeSerieContext $ctx,
        int $margemAnos,
    ): array {
        $situacao = MatriculaSituacaoResolver::resolveChaveAgrupamento($db, $city, 'm');
        $exprs = self::buildExpressions($db, $ctx, self::NASC_HIBRIDO, self::LIMITE_FALLBACK, true);
        if ($situacao === null || $exprs === null) {
            return [];
        }

        try {
            $distorcaoCond = '('.$exprs['idade'].') > ('.$exprs['limite'].' + '.$margemAnos.')';

            $q = $db->table($ctx->matTable.' as m');
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$ctx->mAtivo, $city);
            ($situacao['applyJoins'])($q);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm', left: true);
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city, allowNullPivot: true);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            $q->join($ctx->alunoTable.' as a', 'm.'.$ctx->mAluno, '=', 'a.'.$ctx->aId);
            self::applyNascimentoJoins($q, $db, $ctx, self::NASC_HIBRIDO);
            $q->join($ctx->pessoaTable.' as p', 'a.'.$ctx->aPessoa, '=', 'p.'.$ctx->pId);
            self::joinSerieFlexible($q, $db, $ctx);
            $q->whereNotNull($exprs['birth_check']);

            $chave = $situacao['groupByExpr'];
            $rows = $q->selectRaw($chave.' as cod')
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when '.$distorcaoCond.' then 1 else 0 end) as com_dist')
                ->groupByRaw($chave)
                ->orderByDesc('total')
                ->limit(20)
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $cod = trim((string) ($row->cod ?? ''));
                $total = (int) ($row->total ?? 0);
                $com = (int) ($row->com_dist ?? 0);
                if ($total <= 0) {
                    continue;
                }
                $out[] = [
                    'situacao' => self::rotuloSituacaoInep($cod),
                    'codigo' => $cod !== '' ? $cod : '—',
                    'total' => $total,
                    'com_distorcao' => $com,
                    'pct_distorcao' => round(100.0 * $com / $total, 1),
                ];
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    public static function etapaEducacensoCaseSql(Connection $db, DistorcaoIdadeSerieContext $ctx): ?string
    {
        if ($ctx->serieEtapaCol === null) {
            return null;
        }

        $map = config('ieducar.distorcao.etapa_educacenso_idade_maxima', []);
        if (! is_array($map) || $map === []) {
            return null;
        }

        $g = $db->getQueryGrammar();
        $col = $g->wrap('s').'.'.$g->wrap($ctx->serieEtapaCol);
        $parts = [];
        foreach ($map as $etapa => $idadeMax) {
            if (! is_numeric($idadeMax)) {
                continue;
            }
            $etapaKey = trim((string) $etapa);
            if ($etapaKey === '') {
                continue;
            }
            if ($db->getDriverName() === 'pgsql') {
                $parts[] = 'WHEN trim('.$col.'::text) = '.intval($etapaKey).' THEN '.(int) $idadeMax;
                $parts[] = 'WHEN trim('.$col.'::text) = \''.addslashes($etapaKey).'\' THEN '.(int) $idadeMax;
            } else {
                $parts[] = 'WHEN trim(CAST('.$col.' AS CHAR)) = \''.addslashes($etapaKey).'\' THEN '.(int) $idadeMax;
            }
        }
        if ($parts === []) {
            return null;
        }

        return 'CASE '.implode(' ', $parts).' ELSE NULL END';
    }

    public static function rotuloSituacaoInep(string $codigo): string
    {
        $labels = [
            '1' => __('Em curso'),
            '2' => __('Aprovado'),
            '3' => __('Reprovado'),
            '4' => __('Transferido'),
            '5' => __('Reclassificado'),
            '6' => __('Abandono (legado)'),
            '11' => __('Abandono'),
            '12' => __('Óbito'),
            '14' => __('Transferência'),
            '16' => __('Remanejamento'),
        ];

        return $labels[$codigo] ?? __('Situação :c', ['c' => $codigo !== '' ? $codigo : '—']);
    }
}
