<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Motor de apuração da distorção idade/série com vários mecanismos sobre a base i-Educar.
 *
 * Critério INEP habitual no painel: idade completa na data de corte (31/03 do ano letivo) >
 * idade máxima prevista para a série + margem (por defeito 2 anos).
 */
final class DistorcaoIdadeSerieEngine
{
    public const METODO_CUSTOM = 'custom_sql';

    public const METODO_INEP_PESSOA_TURMA = 'inep_pessoa_turma_31mar';

    public const METODO_INEP_PESSOA_MATRICULA = 'inep_pessoa_matricula_31mar';

    public const METODO_FISICA_MATRICULA_MARCO1 = 'fisica_matricula_marco1';

    public const METODO_AVANCO_IDADE_MINIMA = 'avanco_idade_minima_serie';

    public const METODO_INEP_NASCIMENTO_HIBRIDO = 'inep_nascimento_hibrido_31mar';

    public const METODO_INEP_LIMITE_FALLBACK = 'inep_limite_fallback_chain';

    public const METODO_INEP_ETAPA_EDUCACENSO = 'inep_etapa_educacenso_31mar';

    public const METODO_CRUZAMENTO_SITUACAO = 'cruzamento_situacao_inep';

    /** @var list<string> */
    private const PRIORIDADE_KPIS = [
        self::METODO_CUSTOM,
        self::METODO_INEP_NASCIMENTO_HIBRIDO,
        self::METODO_INEP_LIMITE_FALLBACK,
        self::METODO_INEP_PESSOA_MATRICULA,
        self::METODO_INEP_ETAPA_EDUCACENSO,
        self::METODO_INEP_PESSOA_TURMA,
        self::METODO_FISICA_MATRICULA_MARCO1,
    ];

    /**
     * Contagem principal para cartão / KPI (escolhe o mecanismo com maior cobertura).
     *
     * @return array{
     *   com: int,
     *   sem: int,
     *   total: int,
     *   fonte: string,
     *   metodo: string,
     *   cobertura_pct: ?float,
     *   mecanismos: list<array{
     *     id: string,
     *     label: string,
     *     com: int,
     *     sem: int,
     *     total: int,
     *     pct: ?float,
     *     disponivel: bool,
     *     motivo: ?string
     *   }>
     * }|null
     */
    public static function contagens(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $linhas = self::apurarTodosMecanismos($db, $city, $filters);
        $candidatos = array_values(array_filter(
            $linhas,
            static fn (array $r): bool => $r['disponivel'] && ($r['total'] ?? 0) > 0
        ));

        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, static function (array $a, array $b): int {
            $ta = (int) ($a['total'] ?? 0);
            $tb = (int) ($b['total'] ?? 0);
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }
            $pa = array_search($a['id'], self::PRIORIDADE_KPIS, true);
            $pb = array_search($b['id'], self::PRIORIDADE_KPIS, true);
            $pa = $pa === false ? 99 : $pa;
            $pb = $pb === false ? 99 : $pb;

            return $pa <=> $pb;
        });

        $best = $candidatos[0];
        $totalMat = MatriculaChartQueries::totalMatriculasAtivasFiltradasUncached($db, $city, $filters);
        $cobertura = ($totalMat !== null && $totalMat > 0)
            ? round(100.0 * (int) $best['total'] / $totalMat, 1)
            : null;

        return [
            'com' => (int) $best['com'],
            'sem' => (int) $best['sem'],
            'total' => (int) $best['total'],
            'fonte' => $best['id'] === self::METODO_CUSTOM ? 'custom' : 'automatico',
            'metodo' => (string) $best['id'],
            'cobertura_pct' => $cobertura,
            'mecanismos' => $linhas,
            'analiticos' => self::analiticos($db, $city, $filters),
        ];
    }

    /**
     * Histograma de defasagem e cruzamento com situação INEP (melhor mecanismo de limite/nascimento).
     *
     * @return array{
     *   histograma_faixas: ?array<string, mixed>,
     *   histograma_serie: ?array<string, mixed>,
     *   histograma_escola: ?array<string, mixed>,
     *   situacao_cruzada: list<array<string, mixed>>
     * }
     */
    public static function analiticos(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $ctx = self::resolveContext($db, $city);
        if ($ctx === null) {
            return [
                'histograma_faixas' => null,
                'histograma_serie' => null,
                'histograma_escola' => null,
                'situacao_cruzada' => [],
            ];
        }

        $margem = max(0, (int) config('ieducar.distorcao.margem_anos_inep', 2));

        return [
            'histograma_faixas' => DistorcaoIdadeSerieApurador::histogramaDefasagemChart($db, $city, $filters, $ctx, $margem, 'faixa'),
            'histograma_serie' => DistorcaoIdadeSerieApurador::histogramaDefasagemChart($db, $city, $filters, $ctx, $margem, 'serie'),
            'histograma_escola' => DistorcaoIdadeSerieApurador::histogramaDefasagemChart($db, $city, $filters, $ctx, $margem, 'escola'),
            'situacao_cruzada' => DistorcaoIdadeSerieApurador::cruzamentoSituacaoInep($db, $city, $filters, $ctx, $margem),
        ];
    }

    /**
     * Executa todos os mecanismos aplicáveis (diagnóstico / comparativo).
     *
     * @return list<array{
     *   id: string,
     *   label: string,
     *   com: int,
     *   sem: int,
     *   total: int,
     *   pct: ?float,
     *   disponivel: bool,
     *   motivo: ?string
     * }>
     */
    public static function apurarTodosMecanismos(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $out = [];

        $custom = self::contagemCustomSql($db, $city);
        $out[] = self::linhaMecanismo(
            self::METODO_CUSTOM,
            __('SQL personalizado (ieducar.sql.distorcao_rede_chart)'),
            $custom,
            $custom === null ? __('Consulta não configurada ou sem resultado.') : null,
        );

        $ctx = self::resolveContext($db, $city);
        if ($ctx === null) {
            foreach ([
                [self::METODO_INEP_PESSOA_MATRICULA, __('INEP — pessoa + ano na matrícula (31/03)')],
                [self::METODO_INEP_PESSOA_TURMA, __('INEP — pessoa + ano na turma (31/03)')],
                [self::METODO_FISICA_MATRICULA_MARCO1, __('cadastro.fisica + ano na matrícula (1/03)')],
                [self::METODO_AVANCO_IDADE_MINIMA, __('Avanço — idade abaixo do mínimo da série')],
            ] as [$id, $label]) {
                $out[] = self::linhaMecanismo($id, $label, null, __('Tabelas ou colunas mínimas indisponíveis na base.'));
            }

            return $out;
        }

        $margem = max(0, (int) config('ieducar.distorcao.margem_anos_inep', 2));

        $hibrido = DistorcaoIdadeSerieApurador::contagem(
            $db, $city, $filters, $ctx,
            DistorcaoIdadeSerieApurador::NASC_HIBRIDO,
            DistorcaoIdadeSerieApurador::LIMITE_FALLBACK,
            true,
            $margem,
        );
        $out[] = self::linhaMecanismo(
            self::METODO_INEP_NASCIMENTO_HIBRIDO,
            __('INEP — nascimento híbrido (física+pessoa) + limite em cadeia (31/03, +:n anos)', ['n' => $margem]),
            $hibrido,
            $hibrido === null ? __('Sem nascimento ou limite etário resolvível.') : null,
        );

        $fallback = DistorcaoIdadeSerieApurador::contagem(
            $db, $city, $filters, $ctx,
            DistorcaoIdadeSerieApurador::NASC_PESSOA,
            DistorcaoIdadeSerieApurador::LIMITE_FALLBACK,
            true,
            $margem,
        );
        $out[] = self::linhaMecanismo(
            self::METODO_INEP_LIMITE_FALLBACK,
            __('INEP — limite em cadeia (idade série → final → etapa Educacenso)'),
            $fallback,
            $fallback === null ? __('Cadeia de fallback sem dados no recorte.') : null,
        );

        $matriculaAno = DistorcaoIdadeSerieApurador::contagem(
            $db, $city, $filters, $ctx,
            DistorcaoIdadeSerieApurador::NASC_PESSOA,
            DistorcaoIdadeSerieApurador::LIMITE_SERIE,
            true,
            $margem,
        );
        $out[] = self::linhaMecanismo(
            self::METODO_INEP_PESSOA_MATRICULA,
            __('INEP — pessoa + ano na matrícula (31/03, +:n anos)', ['n' => $margem]),
            $matriculaAno,
            $matriculaAno === null ? __('Sem vínculo série/nascimento no recorte.') : null,
        );

        $etapaOnly = DistorcaoIdadeSerieApurador::contagem(
            $db, $city, $filters, $ctx,
            DistorcaoIdadeSerieApurador::NASC_HIBRIDO,
            DistorcaoIdadeSerieApurador::LIMITE_ETAPA,
            true,
            $margem,
        );
        $out[] = self::linhaMecanismo(
            self::METODO_INEP_ETAPA_EDUCACENSO,
            __('INEP — só etapa Educacenso (quando idade da série falta)'),
            $etapaOnly,
            $etapaOnly === null ? __('Coluna etapa_educacenso ausente ou sem mapeamento.') : null,
        );

        $turmaAno = DistorcaoIdadeSerieApurador::contagem(
            $db, $city, $filters, $ctx,
            DistorcaoIdadeSerieApurador::NASC_PESSOA,
            DistorcaoIdadeSerieApurador::LIMITE_SERIE,
            false,
            $margem,
        );
        $out[] = self::linhaMecanismo(
            self::METODO_INEP_PESSOA_TURMA,
            __('INEP — pessoa + ano na turma (31/03, +:n anos)', ['n' => $margem]),
            $turmaAno,
            $turmaAno === null ? __('Sem vínculo série/nascimento no recorte.') : null,
        );

        $fisica = self::contagemFisicaMatriculaMarco1($db, $city, $filters, $ctx, margemAnos: $margem);
        $out[] = self::linhaMecanismo(
            self::METODO_FISICA_MATRICULA_MARCO1,
            __('cadastro.fisica + limite etário (1/03, +:n anos)', ['n' => $margem]),
            $fisica,
            $fisica === null
                ? ($db->getDriverName() !== 'pgsql'
                    ? __('Requer PostgreSQL e tabela cadastro.fisica.')
                    : __('Sem data de nascimento ou série no recorte.'))
                : null,
        );

        $avanco = self::contagemAvancoIdadeMinima($db, $city, $filters, $ctx);
        $out[] = self::linhaMecanismo(
            self::METODO_AVANCO_IDADE_MINIMA,
            __('Avanço — idade abaixo da idade mínima da série (31/03)'),
            $avanco,
            $avanco === null ? __('Coluna idade mínima não encontrada em série.') : null,
        );

        $situacaoRows = DistorcaoIdadeSerieApurador::cruzamentoSituacaoInep($db, $city, $filters, $ctx, $margem);
        $sitCom = array_sum(array_column($situacaoRows, 'com_distorcao'));
        $sitTot = array_sum(array_column($situacaoRows, 'total'));
        $out[] = self::linhaMecanismo(
            self::METODO_CRUZAMENTO_SITUACAO,
            __('Cruzamento — situação INEP × distorção (híbrido + limite cadeia)'),
            $sitTot > 0 ? ['com' => $sitCom, 'sem' => $sitTot - $sitCom, 'total' => $sitTot] : null,
            $situacaoRows === [] ? __('Situação INEP ou nascimento indisponível.') : null,
        );

        return $out;
    }

    /**
     * Motivo quando nenhum mecanismo produz denominador.
     */
    public static function motivoIndisponivel(Connection $db, City $city, IeducarFilterState $filters): ?string
    {
        if (trim((string) config('ieducar.sql.distorcao_rede_chart', '')) !== '') {
            return __('A consulta personalizada de distorção não devolveu dados utilizáveis.');
        }

        $ctx = self::resolveContext($db, $city);
        if ($ctx === null) {
            return __('Faltam dados cadastrais necessários para calcular a distorção idade–série neste município.');
        }

        if ($ctx->birthColPessoa === null && $ctx->fisicaTable === null) {
            return __('É necessário registro de data de nascimento (pessoa ou cadastro.fisica).');
        }

        if ($ctx->serieLimitCol === null && $ctx->serieEtapaCol === null
            && $ctx->serieIdadeFinalCol === null && $ctx->serieIdadeIdealCol === null) {
            return __('Falta limite etário (idade na série, idade_final ou etapa Educacenso).');
        }

        if ($ctx->tc['year'] === '' && $ctx->matriculaAnoCol === null) {
            return __('Verifique ano letivo na matrícula ou na turma.');
        }

        if ($ctx->serieJoinTurma === '' && $ctx->serieJoinMatricula === '') {
            return __('Não foi possível associar matrículas às séries (turma ou matrícula).');
        }

        return __(
            'Nenhum mecanismo de apuração cobriu matrículas activas neste filtro: confirme datas de nascimento, série e ano letivo.'
        );
    }

    /**
     * Data de corte escolar 31/03 do ano letivo (expressão SQL).
     */
    public static function refDateCorteEscolarSql(Connection $db, string $yearExpr): string
    {
        if ($db->getDriverName() === 'pgsql') {
            return 'make_date(CAST(('.$yearExpr.') AS integer), 3, 31)';
        }

        return 'STR_TO_DATE(CONCAT(CAST('.$yearExpr.' AS CHAR), \'-03-31\'), \'%Y-%m-%d\')';
    }

    /**
     * Idade em anos completos na data de referência (expressão SQL).
     */
    public static function idadeAnosCompletosSql(Connection $db, string $refDateExpr, string $birthExpr): string
    {
        if ($db->getDriverName() === 'pgsql') {
            return 'CAST(EXTRACT(YEAR FROM AGE(('.$refDateExpr.')::date, ('.$birthExpr.')::date)) AS integer)';
        }

        return 'TIMESTAMPDIFF(YEAR, '.$birthExpr.', '.$refDateExpr.')';
    }

    /**
     * @return array{com: int, sem: int, total: int}|null
     */
    private static function contagemCustomSql(Connection $db, City $city): ?array
    {
        $custom = trim((string) config('ieducar.sql.distorcao_rede_chart', ''));
        if ($custom === '') {
            return null;
        }
        try {
            $sql = IeducarSqlPlaceholders::interpolate($custom, $city);
            $rows = $db->select($sql);
            $vals = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $val = $arr['valor'] ?? $arr['value'] ?? $arr['quantidade'] ?? $arr['pct'] ?? $arr['total'] ?? null;
                if ($val === null || ! is_numeric($val)) {
                    continue;
                }
                $vals[] = (int) $val;
            }
            if (count($vals) >= 2) {
                $com = $vals[0];
                $sem = $vals[1];

                return ['com' => $com, 'sem' => $sem, 'total' => $com + $sem];
            }
        } catch (QueryException|\Throwable) {
        }

        return null;
    }

    /**
     * @return array{com: int, sem: int, total: int}|null
     */
    private static function contagemInepPessoa(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        DistorcaoIdadeSerieContext $ctx,
        bool $refFromMatriculaAno,
        int $margemAnos,
    ): ?array {
        if ($ctx->birthColPessoa === null || $ctx->serieLimitCol === null) {
            return null;
        }
        if ($refFromMatriculaAno && $ctx->matriculaAnoCol === null) {
            return null;
        }
        if (! $refFromMatriculaAno && $ctx->tc['year'] === '') {
            return null;
        }

        try {
            $grammar = $db->getQueryGrammar();
            $limiteExpr = $grammar->wrap('s').'.'.$grammar->wrap($ctx->serieLimitCol);
            $birthExpr = $grammar->wrap('p').'.'.$grammar->wrap($ctx->birthColPessoa);

            if ($refFromMatriculaAno) {
                $mAnoW = $grammar->wrap('m').'.'.$grammar->wrap($ctx->matriculaAnoCol);
                $tYearW = $ctx->tc['year'] !== ''
                    ? $grammar->wrap('t_filter').'.'.$grammar->wrap($ctx->tc['year'])
                    : null;
                $yearExpr = $tYearW !== null
                    ? 'COALESCE(NULLIF(CAST('.$mAnoW.' AS text), \'\'), CAST('.$tYearW.' AS text))'
                    : 'CAST('.$mAnoW.' AS text)';
            } else {
                $yearExpr = $grammar->wrap('t_filter').'.'.$grammar->wrap($ctx->tc['year']);
            }

            $refDateExpr = self::refDateCorteEscolarSql($db, $yearExpr);
            $idadeExpr = self::idadeAnosCompletosSql($db, $refDateExpr, $birthExpr);
            $distorcaoCond = '('.$idadeExpr.') > ('.$limiteExpr.' + '.$margemAnos.')';

            $base = function () use ($db, $city, $filters, $ctx, $grammar, $distorcaoCond, $limiteExpr): Builder {
                $q = $db->table($ctx->matTable.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$ctx->mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm', left: true);
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city, allowNullPivot: true);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
                $q->join($ctx->alunoTable.' as a', 'm.'.$ctx->mAluno, '=', 'a.'.$ctx->aId)
                    ->join($ctx->pessoaTable.' as p', 'a.'.$ctx->aPessoa, '=', 'p.'.$ctx->pId);
                DistorcaoIdadeSerieApurador::joinSerieFlexible($q, $db, $ctx);
                $q->whereNotNull('p.'.$ctx->birthColPessoa)
                    ->whereRaw('('.$limiteExpr.') IS NOT NULL');

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
     * @return array{com: int, sem: int, total: int}|null
     */
    private static function contagemFisicaMatriculaMarco1(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        DistorcaoIdadeSerieContext $ctx,
        int $margemAnos,
    ): ?array {
        if ($db->getDriverName() !== 'pgsql'
            || $ctx->fisicaTable === null
            || $ctx->fisicaBirthCol === null
            || $ctx->matriculaAnoCol === null
            || ($ctx->serieJoinMatricula === '' && $ctx->serieJoinTurma === '')) {
            return null;
        }

        $idadeFinal = $ctx->serieIdadeFinalCol;
        $idadeIdeal = $ctx->serieIdadeIdealCol;
        if ($idadeFinal === null && $idadeIdeal === null && $ctx->serieLimitCol === null) {
            return null;
        }

        try {
            $g = $db->getQueryGrammar();
            if ($idadeFinal !== null && $idadeIdeal !== null) {
                $limiteSql = 'COALESCE(NULLIF('.$g->wrap('s').'.'.$g->wrap($idadeFinal).', 0), '.$g->wrap('s').'.'.$g->wrap($idadeIdeal).', 99)';
            } elseif ($idadeFinal !== null) {
                $limiteSql = 'COALESCE(NULLIF('.$g->wrap('s').'.'.$g->wrap($idadeFinal).', 0), 99)';
            } elseif ($idadeIdeal !== null) {
                $limiteSql = 'COALESCE('.$g->wrap('s').'.'.$g->wrap($idadeIdeal).', 99)';
            } else {
                $limiteSql = $g->wrap('s').'.'.$g->wrap((string) $ctx->serieLimitCol);
            }

            $mAnoW = $g->wrap('m').'.'.$g->wrap($ctx->matriculaAnoCol);
            $fNascW = $g->wrap('f').'.'.$g->wrap($ctx->fisicaBirthCol);
            $idadeExpr = 'extract(year from age(make_date(cast('.$mAnoW.' as integer), 3, 1), '.$fNascW.'))::int';
            $distorcaoCond = '('.$idadeExpr.') > (('.$limiteSql.') + '.$margemAnos.')';

            $base = function () use ($db, $city, $filters, $ctx, $g, $fNascW, $distorcaoCond, $limiteSql): Builder {
                $q = $db->table($ctx->matTable.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$ctx->mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm', left: true);
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city, allowNullPivot: true);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
                $q->join($ctx->alunoTable.' as a', 'm.'.$ctx->mAluno, '=', 'a.'.$ctx->aId)
                    ->join($ctx->fisicaTable.' as f', 'a.'.$ctx->aPessoa, '=', 'f.'.$ctx->fisicaLinkCol)
                    ->whereNotNull($fNascW);
                DistorcaoIdadeSerieApurador::joinSerieFlexible($q, $db, $ctx);

                return $q;
            };

            $anoVal = $filters->yearFilterValue();
            $qBase = $base();
            if ($anoVal !== null) {
                $qBase->where('m.'.$ctx->matriculaAnoCol, $anoVal);
            } else {
                $y0 = (int) date('Y');
                $qBase->whereBetween('m.'.$ctx->matriculaAnoCol, [$y0 - 4, $y0]);
            }

            $com = (int) (clone $qBase)->whereRaw($distorcaoCond)->count();
            $sem = (int) (clone $qBase)->whereRaw('NOT ('.$distorcaoCond.')')->count();
            if ($com === 0 && $sem === 0) {
                return null;
            }

            return ['com' => $com, 'sem' => $sem, 'total' => $com + $sem];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * @return array{com: int, sem: int, total: int}|null
     */
    private static function contagemAvancoIdadeMinima(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        DistorcaoIdadeSerieContext $ctx,
    ): ?array {
        if ($ctx->birthColPessoa === null || $ctx->serieMinCol === null) {
            return null;
        }
        if ($ctx->matriculaAnoCol === null && $ctx->tc['year'] === '') {
            return null;
        }

        try {
            $grammar = $db->getQueryGrammar();
            $minExpr = $grammar->wrap('s').'.'.$grammar->wrap($ctx->serieMinCol);
            $birthExpr = $grammar->wrap('p').'.'.$grammar->wrap($ctx->birthColPessoa);
            $mAnoW = $ctx->matriculaAnoCol !== null
                ? $grammar->wrap('m').'.'.$grammar->wrap($ctx->matriculaAnoCol)
                : null;
            $tYearW = $ctx->tc['year'] !== ''
                ? $grammar->wrap('t_filter').'.'.$grammar->wrap($ctx->tc['year'])
                : null;
            $yearExpr = $mAnoW !== null && $tYearW !== null
                ? 'COALESCE(NULLIF(CAST('.$mAnoW.' AS text), \'\'), CAST('.$tYearW.' AS text))'
                : ($mAnoW ?? $tYearW);
            if ($yearExpr === null) {
                return null;
            }
            $refDateExpr = self::refDateCorteEscolarSql($db, $yearExpr);
            $idadeExpr = self::idadeAnosCompletosSql($db, $refDateExpr, $birthExpr);
            $avancoCond = '('.$idadeExpr.') < ('.$minExpr.')';

            $base = function () use ($db, $city, $filters, $ctx, $grammar, $avancoCond, $minExpr): Builder {
                $q = $db->table($ctx->matTable.' as m');
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$ctx->mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm', left: true);
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city, allowNullPivot: true);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
                $q->join($ctx->alunoTable.' as a', 'm.'.$ctx->mAluno, '=', 'a.'.$ctx->aId)
                    ->join($ctx->pessoaTable.' as p', 'a.'.$ctx->aPessoa, '=', 'p.'.$ctx->pId);
                DistorcaoIdadeSerieApurador::joinSerieFlexible($q, $db, $ctx);
                $q->whereNotNull('p.'.$ctx->birthColPessoa)
                    ->whereRaw('('.$minExpr.') IS NOT NULL');

                return $q;
            };

            $com = (int) $base()->whereRaw($avancoCond)->count();
            $sem = (int) $base()->whereRaw('NOT ('.$avancoCond.')')->count();
            if ($com === 0 && $sem === 0) {
                return null;
            }

            return ['com' => $com, 'sem' => $sem, 'total' => $com + $sem];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    private static function resolveContext(Connection $db, City $city): ?DistorcaoIdadeSerieContext
    {
        try {
            $matTable = IeducarSchema::resolveTable('matricula', $city);
            $alunoTable = IeducarSchema::resolveTable('aluno', $city);
            $pessoaTable = IeducarSchema::resolveTable('pessoa', $city);
            $serieTable = IeducarSchema::resolveTable('serie', $city);

            if (! IeducarColumnInspector::tableExists($db, $matTable, $city)
                || ! IeducarColumnInspector::tableExists($db, $alunoTable, $city)
                || ! IeducarColumnInspector::tableExists($db, $pessoaTable, $city)
                || ! IeducarColumnInspector::tableExists($db, $serieTable, $city)) {
                return null;
            }

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            $sId = IeducarColumnInspector::firstExistingColumn($db, $serieTable, array_filter([
                (string) config('ieducar.columns.serie.id'),
                'cod_serie',
                'id',
            ]), $city);
            if ($sId === null) {
                return null;
            }

            $cfgMax = trim((string) config('ieducar.columns.serie.idade_limite_max', ''));
            $serieLimitCol = IeducarColumnInspector::firstExistingColumn($db, $serieTable, array_filter([
                $cfgMax !== '' ? $cfgMax : null,
                'idade_maxima',
                'idade_max',
                'idade_maxima_escolar',
                'idade_final',
                'idade_fim',
                'idade_ideal_max',
                'idade_maxima_ideal',
            ]), $city);

            $serieMinCol = IeducarColumnInspector::firstExistingColumn($db, $serieTable, array_filter([
                'idade_minima',
                'idade_inicial',
                'idade_ini',
                'idade_ideal',
            ]), $city);

            $birthColPessoa = IeducarColumnInspector::firstExistingColumn($db, $pessoaTable, array_filter([
                'data_nasc',
                'data_nascimento',
                'dt_nascimento',
                'dt_nasc',
            ]), $city);

            $fisica = self::resolveFisicaTable($db, $city);
            $fisicaBirth = null;
            $fisicaLink = null;
            if ($fisica !== null) {
                $fisicaLink = IeducarColumnInspector::firstExistingColumn($db, $fisica, ['idpes', 'ref_idpes'], $city);
                $fisicaBirth = IeducarColumnInspector::firstExistingColumn($db, $fisica, [
                    'data_nasc', 'data_nascimento', 'dt_nascimento',
                ], $city);
            }

            $matriculaAnoCol = MatriculaTurmaJoin::matriculaAnoColumn($db, $city);
            $serieJoinMatricula = IeducarColumnInspector::firstExistingColumn($db, $matTable, array_filter([
                (string) config('ieducar.columns.matricula.serie'),
                'ref_ref_cod_serie',
                'ref_cod_serie',
            ]), $city) ?? '';

            $serieJoinTurma = $tc['serie'];

            $serieEtapaCol = IeducarColumnInspector::firstExistingColumn($db, $serieTable, array_filter([
                (string) config('ieducar.columns.serie.etapa_educacenso', 'etapa_educacenso'),
                'etapa_educacenso',
            ]), $city);

            $serieNameCol = IeducarColumnInspector::firstExistingColumn($db, $serieTable, array_filter([
                (string) config('ieducar.columns.serie.name'),
                'nm_serie',
                'nome',
            ]), $city);

            $temLimite = $serieLimitCol !== null || $serieEtapaCol !== null
                || IeducarColumnInspector::firstExistingColumn($db, $serieTable, ['idade_final', 'idade_fim'], $city) !== null;

            if (! $temLimite && $serieJoinMatricula === '' && $serieJoinTurma === '') {
                return null;
            }

            if ($birthColPessoa === null && $fisicaBirth === null) {
                return null;
            }

            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $aId = (string) config('ieducar.columns.aluno.id');
            $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $alunoTable, array_filter([
                (string) config('ieducar.columns.aluno.pessoa'),
                'ref_cod_pessoa',
                'ref_idpes',
                'idpes',
            ]), $city);
            $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoaTable, array_filter([
                (string) config('ieducar.columns.pessoa.id'),
                'idpes',
                'id',
                'cod_pessoa',
            ]), $city);

            if ($aPessoa === null || $pId === null) {
                return null;
            }

            return new DistorcaoIdadeSerieContext(
                matTable: $matTable,
                alunoTable: $alunoTable,
                pessoaTable: $pessoaTable,
                serieTable: $serieTable,
                mAtivo: $mAtivo,
                mAluno: $mAluno,
                aId: $aId,
                aPessoa: $aPessoa,
                pId: $pId,
                sId: $sId,
                serieLimitCol: $serieLimitCol,
                serieMinCol: $serieMinCol,
                serieIdadeFinalCol: IeducarColumnInspector::firstExistingColumn($db, $serieTable, ['idade_final', 'idade_fim'], $city),
                serieIdadeIdealCol: IeducarColumnInspector::firstExistingColumn($db, $serieTable, ['idade_ideal', 'idade_ideal_max'], $city),
                birthColPessoa: $birthColPessoa,
                matriculaAnoCol: $matriculaAnoCol,
                serieJoinMatricula: $serieJoinMatricula,
                serieJoinTurma: $serieJoinTurma,
                serieEtapaCol: $serieEtapaCol,
                serieNameCol: $serieNameCol,
                tc: $tc,
                fisicaTable: $fisica,
                fisicaLinkCol: $fisicaLink,
                fisicaBirthCol: $fisicaBirth,
            );
        } catch (\InvalidArgumentException|QueryException) {
            return null;
        }
    }

    private static function resolveFisicaTable(Connection $db, City $city): ?string
    {
        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica';
        $sch = IeducarSchema::effectiveSchema($city);
        foreach (array_values(array_unique(array_filter([
            $cad,
            $sch !== '' ? $sch.'.fisica' : '',
            'public.fisica',
            'cadastro.fisica',
        ]))) as $t) {
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return null;
    }

    /**
     * @param  array{com: int, sem: int, total: int}|null  $contagem
     * @return array{
     *   id: string,
     *   label: string,
     *   com: int,
     *   sem: int,
     *   total: int,
     *   pct: ?float,
     *   disponivel: bool,
     *   motivo: ?string
     * }
     */
    private static function linhaMecanismo(string $id, string $label, ?array $contagem, ?string $motivo): array
    {
        if ($contagem === null) {
            return [
                'id' => $id,
                'label' => $label,
                'com' => 0,
                'sem' => 0,
                'total' => 0,
                'pct' => null,
                'disponivel' => false,
                'motivo' => $motivo,
            ];
        }

        $tot = (int) $contagem['total'];
        $com = (int) $contagem['com'];

        return [
            'id' => $id,
            'label' => $label,
            'com' => $com,
            'sem' => (int) $contagem['sem'],
            'total' => $tot,
            'pct' => $tot > 0 ? round(100.0 * $com / $tot, 1) : null,
            'disponivel' => true,
            'motivo' => null,
        ];
    }
}

/**
 * Colunas e tabelas resolvidas para apuração de distorção num município.
 */
final readonly class DistorcaoIdadeSerieContext
{
    /**
     * @param  array{year: string, escola: string, curso: string, turno: string, serie: string}  $tc
     */
    public function __construct(
        public string $matTable,
        public string $alunoTable,
        public string $pessoaTable,
        public string $serieTable,
        public string $mAtivo,
        public string $mAluno,
        public string $aId,
        public string $aPessoa,
        public string $pId,
        public string $sId,
        public ?string $serieLimitCol,
        public ?string $serieMinCol,
        public ?string $serieIdadeFinalCol,
        public ?string $serieIdadeIdealCol,
        public ?string $birthColPessoa,
        public ?string $matriculaAnoCol,
        public string $serieJoinMatricula,
        public string $serieJoinTurma,
        public ?string $serieEtapaCol,
        public ?string $serieNameCol,
        public array $tc,
        public ?string $fisicaTable,
        public ?string $fisicaLinkCol,
        public ?string $fisicaBirthCol,
    ) {}
}
