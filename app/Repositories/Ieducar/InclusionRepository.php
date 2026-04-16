<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use App\Support\Ieducar\IeducarSqlPlaceholders;
use App\Support\Ieducar\InclusionDashboardQueries;
use App\Support\Ieducar\InclusionSpecialEducationGauges;
use App\Support\Ieducar\MatriculaAtivoFilter;
use App\Support\Ieducar\MatriculaChartQueries;
use App\Support\Ieducar\MatriculaTurmaJoin;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Inclusão & Diversidade: educação especial (NEE), género, distribuição por etapa (equidade), cor ou raça e SQL opcional.
 *
 * Regras: denominador comum = matrículas ativas no filtro (MatriculaAtivoFilter + turma);
 * educação especial via aluno_deficiência (detecção em vários schemas) + cadastro ou SQL em ieducar.sql.inclusion_gauge_*;
 * cor/raça e distorção idade/série como nas outras abas (queries partilhadas).
 */
class InclusionRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>,
     *   nee_charts_count: int,
     *   nee_detalhe_catalogo: ?array<string, mixed>,
     *   aee_cross: ?array<string, mixed>,
     *   gauges: list<array{chart: array<string, mixed>, caption: string}>,
     *   notes: list<string>,
     *   error: ?string,
     *   total_matriculas: ?int,
     *   equidade_fonte: ?string,
     *   methodology: list<string>,
     *   nee_grupo_resumo: ?array{deficiencias: int, sindromes_tea: int, ne_altas_habilidades: int}
     * }
     */
    public function snapshot(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return [
                'charts' => [],
                'nee_charts_count' => 0,
                'nee_detalhe_catalogo' => null,
                'aee_cross' => null,
                'gauges' => [],
                'notes' => [],
                'error' => null,
                'total_matriculas' => null,
                'equidade_fonte' => null,
                'methodology' => [],
                'nee_grupo_resumo' => null,
                'chart_raca_por_escola_stacked' => null,
            ];
        }

        $charts = [];
        $neeCharts = [];
        $neeDetalheCatalogo = null;
        $aeeCross = null;
        $gauges = [];
        $notes = [];
        $totalMatriculas = null;
        $equidadeFonte = null;
        $neeGrupoResumo = null;
        $chartRacaPorEscolaStacked = null;

        try {
            $this->cityData->run($city, function (Connection $db) use ($city, $filters, &$charts, &$neeCharts, &$neeDetalheCatalogo, &$aeeCross, &$gauges, &$notes, &$totalMatriculas, &$equidadeFonte, &$neeGrupoResumo, &$chartRacaPorEscolaStacked) {
                $totalMatriculas = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);

                try {
                    $neeCharts = InclusionDashboardQueries::buildCharts($db, $city, $filters);
                } catch (\Throwable) {
                    $notes[] = __('Não foi possível carregar os gráficos de educação especial para estes filtros.');
                    $neeCharts = [];
                }

                try {
                    $neeDetalheCatalogo = InclusionDashboardQueries::buildNeeDetalheCatalogoPorCategoria($db, $city, $filters);
                } catch (\Throwable) {
                    $neeDetalheCatalogo = null;
                }

                try {
                    $aeeCross = InclusionDashboardQueries::buildAeeCrossEnrollment($db, $city, $filters);
                } catch (\Throwable) {
                    $aeeCross = null;
                }

                try {
                    foreach (InclusionSpecialEducationGauges::build($db, $city, $filters) as $row) {
                        $gauges[] = [
                            'chart' => ChartPayload::gaugePercent($row['title'], $row['percent']),
                            'caption' => $row['caption'],
                        ];
                    }
                } catch (\Throwable) {
                    $notes[] = __('Não foi possível calcular os medidores de educação especial.');
                }

                $tailCharts = [];

                $sex = MatriculaChartQueries::matriculasPorSexo($db, $city, $filters);
                if ($sex !== null) {
                    $sex['options'] = array_merge($sex['options'] ?? [], ['panelHeight' => 'lg']);
                    $sex['compact_panel'] = true;
                    $tailCharts[] = $sex;
                }

                $customRaca = config('ieducar.sql.inclusion_raca');
                if (is_string($customRaca) && trim($customRaca) !== '') {
                    $racaChart = $this->chartFromRawSql($db, $city, trim($customRaca), __('Matrículas por cor ou raça (SQL personalizado)'));
                    if ($racaChart !== null) {
                        $racaChart['options'] = array_merge($racaChart['options'] ?? [], ['panelHeight' => 'lg']);
                        $racaChart['compact_panel'] = true;
                        $tailCharts[] = $racaChart;
                    }
                } else {
                    $racaChart = $this->raceDistributionChart($db, $city, $filters);
                    if ($racaChart !== null) {
                        $racaChart['options'] = array_merge($racaChart['options'] ?? [], ['panelHeight' => 'lg']);
                        $racaChart['compact_panel'] = true;
                        $tailCharts[] = $racaChart;
                    }
                }

                try {
                    $dist = MatriculaChartQueries::distorcaoIdadeSeriePorEscolaFisica($db, $city, $filters)
                        ?? MatriculaChartQueries::distorcaoIdadeSerieRedeChart($db, $city, $filters);
                    if ($dist !== null) {
                        $tailCharts[] = $dist;
                    }
                } catch (QueryException) {
                    // Base sem colunas para distorção (série / idade / física).
                }

                if (($tmp = MatriculaChartQueries::matriculasPorSerieTop($db, $city, $filters)) !== null) {
                    $tailCharts[] = $tmp;
                    $equidadeFonte = 'serie';
                }

                $customExtra = config('ieducar.sql.inclusion_extra');
                if (is_string($customExtra) && trim($customExtra) !== '') {
                    $extra = $this->chartFromRawSql($db, $city, trim($customExtra), __('Indicador complementar (SQL personalizado)'));
                    if ($extra !== null) {
                        $tailCharts[] = $extra;
                    }
                }

                try {
                    $chartRacaPorEscolaStacked = $this->escolaRacaPorEscolaStackedChart($db, $city, $filters);
                } catch (\Throwable) {
                    $chartRacaPorEscolaStacked = null;
                }

                $escolaRacaNee = $this->escolaRacaENeeMultiLineChart($db, $city, $filters);
                $charts = array_merge(
                    $neeCharts,
                    array_values(array_filter([$escolaRacaNee])),
                    $tailCharts
                );

                if ($neeCharts !== [] && isset($neeCharts[0]['datasets'][0]['data'])) {
                    $data = $neeCharts[0]['datasets'][0]['data'];
                    if (is_array($data) && count($data) === 3) {
                        $neeGrupoResumo = [
                            'deficiencias' => (int) round((float) ($data[0] ?? 0)),
                            'sindromes_tea' => (int) round((float) ($data[1] ?? 0)),
                            'ne_altas_habilidades' => (int) round((float) ($data[2] ?? 0)),
                        ];
                    }
                }
            });
        } catch (\Throwable $e) {
            return [
                'charts' => [],
                'nee_charts_count' => 0,
                'nee_detalhe_catalogo' => null,
                'aee_cross' => null,
                'gauges' => [],
                'notes' => [],
                'error' => $e->getMessage(),
                'total_matriculas' => null,
                'equidade_fonte' => null,
                'methodology' => [],
                'nee_grupo_resumo' => null,
                'chart_raca_por_escola_stacked' => null,
            ];
        }

        $methodology = $this->methodologyLines($equidadeFonte);

        return [
            'charts' => $charts,
            'nee_charts_count' => count($neeCharts),
            'nee_detalhe_catalogo' => $neeDetalheCatalogo,
            'aee_cross' => $aeeCross,
            'gauges' => $gauges,
            'notes' => $notes,
            'error' => null,
            'total_matriculas' => $totalMatriculas,
            'equidade_fonte' => $equidadeFonte,
            'methodology' => $methodology,
            'nee_grupo_resumo' => $neeGrupoResumo,
            'chart_raca_por_escola_stacked' => $chartRacaPorEscolaStacked,
        ];
    }

    /**
     * Textos de referência (Educacenso / INEP / LBI) para interpretação dos indicadores.
     *
     * @return list<string>
     */
    private function methodologyLines(?string $equidadeFonte): array
    {
        $eq = match ($equidadeFonte) {
            'serie' => __('O gráfico complementar de equidade usa séries (turma → série). Nesta aba não se usa o gráfico por curso (top 10).'),
            default => __('O gráfico de série (equidade) não foi gerado (dados insuficientes).'),
        };

        return [
            __('Todos os indicadores respeitam os filtros atuais (ano letivo, escola, segmento, turno) através da turma, com matrícula considerada ativa conforme config/ieducar.php.'),
            __('Distorção idade/série: em PostgreSQL com física e série, mostra-se a contagem por unidade escolar (referência 1 de março); caso contrário usa-se o gráfico de barras por série com quantidades de alunos com distorção (critério INEP +2 anos).'),
            __('Educação especial: com SQL personalizado (IEDUCAR_SQL_INCLUSION_GAUGE_*), as percentagens seguem a regra definida pelo município; sem SQL, usa-se o pivô aluno_deficiência (procurado em vários schemas) e o nome no cadastro de deficiências — pode divergir de outros relatórios.'),
            __('Cruzamento AEE: turmas «AEE» são identificadas por palavras-chave no nome da turma e do curso (config/ieducar.php, inclusão). Os segmentos das outras matrículas do mesmo aluno são heurísticos; ajuste IEDUCAR_INCLUSION_* se os rótulos não coincidirem com a rede.'),
            $eq,
        ];
    }

    /**
     * Distribuição por cor/raça alinhada ao BI i-Educar típico: prioriza `cadastro.fisica_raca`
     * (aluno.ref_idpes → fisica_raca → raca), com COUNT(DISTINCT cod_matricula) e rótulo «Não declarado» quando vazio.
     *
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function raceDistributionChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);

            if (! IeducarColumnInspector::tableExists($db, $mat, $city)
                || ! IeducarColumnInspector::tableExists($db, $aluno, $city)) {
                return null;
            }

            $racaSpec = self::resolveRacaJoinSpec($db, $city);
            if ($racaSpec === null) {
                return null;
            }

            $racaT = $racaSpec['qualified'];
            $rIdCol = $racaSpec['idCol'];
            $rNameCol = $racaSpec['nameCol'];

            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mId = (string) config('ieducar.columns.matricula.id');
            $aId = (string) config('ieducar.columns.aluno.id');
            $grammar = $db->getQueryGrammar();
            $distinctMatriculas = 'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).')';

            $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                (string) config('ieducar.columns.aluno.pessoa'),
                'ref_cod_pessoa',
                'ref_idpes',
                'idpes',
            ]), $city);
            if ($aPessoa === null) {
                return null;
            }

            $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                'ref_idpes',
                'idpes',
            ]), $city);
            $fisicaRacaPivot = self::resolveFisicaRacaPivotSpec($db, $city);

            $pRaca = null;
            $pId = null;
            if (IeducarColumnInspector::tableExists($db, $pessoa, $city)) {
                $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                    (string) config('ieducar.columns.pessoa.id'),
                    'idpes',
                    'id',
                    'cod_pessoa',
                ]), $city);
                $pRaca = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                    (string) config('ieducar.columns.pessoa.raca'),
                    'ref_cod_raca',
                    'cod_raca',
                    'id_raca',
                    'raca_id',
                    'cor_raca',
                    'cor',
                    'ref_cod_cor',
                ]), $city);
            }

            $aRaca = null;
            if ($pRaca === null) {
                $aRaca = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                    'ref_cod_raca',
                    'cod_raca',
                    'id_raca',
                    'raca_cor',
                ]), $city);
            }

            $fisicaTable = null;
            $fisicaRacaCol = null;
            $fisicaLinkCol = null;
            if ($pRaca === null && $aRaca === null && $pId !== null) {
                foreach (self::fisicaTableCandidates($city) as $cand) {
                    if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                        continue;
                    }
                    $fisicaRacaCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                        'ref_cod_raca',
                        'cod_raca',
                        'raca_cor',
                        'id_raca',
                    ], $city);
                    $fisicaLinkCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                        'idpes',
                        'ref_idpes',
                    ], $city);
                    if ($fisicaRacaCol !== null && $fisicaLinkCol !== null) {
                        $fisicaTable = $cand;
                        break;
                    }
                }
            }

            /** @var array{table: string, racaCol: string, fisicaLink: string, alunoCol: string}|null */
            $fisicaViaAluno = null;
            if ($pRaca === null && $aRaca === null && $fisicaTable === null && $fisicaRacaPivot === null) {
                $aIdpesCol = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                    'ref_idpes',
                    'idpes',
                ]), $city);
                if ($aIdpesCol !== null) {
                    foreach (self::fisicaTableCandidates($city) as $cand) {
                        if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                            continue;
                        }
                        $fc = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                            'ref_cod_raca',
                            'cod_raca',
                            'raca_cor',
                            'id_raca',
                        ], $city);
                        $fl = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                            'idpes',
                            'ref_idpes',
                        ], $city);
                        if ($fc !== null && $fl !== null) {
                            $fisicaViaAluno = [
                                'table' => $cand,
                                'racaCol' => $fc,
                                'fisicaLink' => $fl,
                                'alunoCol' => $aIdpesCol,
                            ];
                            break;
                        }
                    }
                }
            }

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);

            if ($fisicaRacaPivot !== null && $aIdpes !== null) {
                $q->leftJoin($fisicaRacaPivot['qualified'].' as fr', 'a.'.$aIdpes, '=', 'fr.'.$fisicaRacaPivot['idpesCol']);
                self::leftJoinRacaCatalogOnFk($db, $q, 'fr', $fisicaRacaPivot['racaFkCol'], $racaT, 'r', $rIdCol);
            } elseif ($pRaca !== null && $pId !== null) {
                $q->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId);
                self::leftJoinRacaCatalogOnFk($db, $q, 'p', $pRaca, $racaT, 'r', $rIdCol);
            } elseif ($aRaca !== null) {
                self::leftJoinRacaCatalogOnFk($db, $q, 'a', $aRaca, $racaT, 'r', $rIdCol);
            } elseif ($fisicaTable !== null && $fisicaRacaCol !== null && $fisicaLinkCol !== null && $pId !== null) {
                $q->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId)
                    ->leftJoin($fisicaTable.' as pf', 'p.'.$pId, '=', 'pf.'.$fisicaLinkCol);
                self::leftJoinRacaCatalogOnFk($db, $q, 'pf', $fisicaRacaCol, $racaT, 'r', $rIdCol);
            } elseif ($fisicaViaAluno !== null) {
                $q->leftJoin($fisicaViaAluno['table'].' as pf', 'a.'.$fisicaViaAluno['alunoCol'], '=', 'pf.'.$fisicaViaAluno['fisicaLink']);
                self::leftJoinRacaCatalogOnFk($db, $q, 'pf', $fisicaViaAluno['racaCol'], $racaT, 'r', $rIdCol);
            } else {
                return null;
            }

            $q->selectRaw('r.'.$rIdCol.' as rid')
                ->selectRaw('MAX(r.'.$rNameCol.') as rname')
                ->selectRaw($distinctMatriculas.' as c')
                ->groupBy('r.'.$rIdCol);

            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);

            $rows = $q->orderByDesc('c')->limit(16)->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $nm = $row->rname ?? null;
                $labels[] = $nm !== null && (string) $nm !== '' ? (string) $nm : __('Não declarado');
                $values[] = (int) ($row->c ?? 0);
            }

            return ChartPayload::barHorizontal(
                __('Matrículas por cor ou raça (cadastro — referência INEP)'),
                __('Matrículas'),
                $labels,
                $values
            );
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Pivô cadastro.fisica_raca (BI: aluno.ref_idpes = fr.ref_idpes, fr.ref_cod_raca → raca).
     *
     * @return ?array{qualified: string, idpesCol: string, racaFkCol: string}
     */
    private static function resolveFisicaRacaPivotSpec(Connection $db, City $city): ?array
    {
        foreach (self::fisicaRacaTableCandidates($city) as $qualified) {
            if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
                continue;
            }
            $idpesCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, [
                'ref_idpes',
                'idpes',
            ], $city);
            $racaFkCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, [
                'ref_cod_raca',
                'cod_raca',
            ], $city);
            if ($idpesCol !== null && $racaFkCol !== null) {
                return [
                    'qualified' => $qualified,
                    'idpesCol' => $idpesCol,
                    'racaFkCol' => $racaFkCol,
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function fisicaRacaTableCandidates(City $city): array
    {
        $out = [];
        try {
            $out[] = IeducarSchema::resolveTable('fisica_raca', $city);
        } catch (\InvalidArgumentException) {
            // ignorado
        }

        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica_raca';
        $sch = IeducarSchema::effectiveSchema($city);

        foreach (array_unique(array_filter([
            $cad,
            $sch !== '' ? $sch.'.fisica_raca' : '',
            'cadastro.fisica_raca',
            'public.fisica_raca',
        ])) as $t) {
            if ($t !== '' && ! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Primeira tabela raca acessível com colunas id + nome (para JOIN).
     *
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    private static function resolveRacaJoinSpec(Connection $db, City $city): ?array
    {
        foreach (IeducarSchema::racaTableCandidates($city) as $qualified) {
            if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
                continue;
            }

            $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.raca.id'),
                'cod_raca',
                'id',
                'id_raca',
                'codigo',
            ]), $city);
            $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
                (string) config('ieducar.columns.raca.name'),
                'nm_raca',
                'nome',
                'nm_cor',
                'descricao',
                'ds_raca',
                'rac_nome',
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
     * @return list<string>
     */
    private static function fisicaTableCandidates(City $city): array
    {
        $cad = trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica';
        $sch = IeducarSchema::effectiveSchema($city);

        return array_values(array_unique(array_filter([
            $cad,
            $sch !== '' ? $sch.'.fisica' : '',
            'cadastro.fisica',
            'public.fisica',
        ])));
    }

    /**
     * leftJoin pessoa/aluno/fisica.FK → cadastro.raca com cast (evita 0 linhas por int ≠ bigint).
     */
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
        $q->leftJoin($racaQualified.' as '.$racaAlias, function ($join) use ($db, $lhs, $rhs) {
            if ($db->getDriverName() === 'pgsql') {
                $join->whereRaw('('.$lhs.')::text = ('.$rhs.')::text');
            } else {
                $join->whereRaw('CAST('.$lhs.' AS UNSIGNED) = CAST('.$rhs.' AS UNSIGNED)');
            }
        });
    }

    /**
     * @return array{qualified: string, idCol: string, nameCol: string}|null
     */
    private static function resolveEscolaJoinSpec(Connection $db, City $city): ?array
    {
        $qualified = IeducarSchema::resolveTable('escola', $city);
        if (! IeducarColumnInspector::tableExists($db, $qualified, $city)) {
            return null;
        }

        $idCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.escola.id'),
            'cod_escola',
            'id',
        ]), $city);
        $nameCol = IeducarColumnInspector::firstExistingColumn($db, $qualified, array_filter([
            (string) config('ieducar.columns.escola.name'),
            'nome',
            'nm_escola',
            'fantasia',
            'razao_social',
            'sigla',
        ]), $city);

        if ($idCol === null || $nameCol === null) {
            return null;
        }

        return ['qualified' => $qualified, 'idCol' => $idCol, 'nameCol' => $nameCol];
    }

    private static function resolveAlunoDeficienciaTable(Connection $db, City $city): ?string
    {
        foreach (array_values(array_unique(array_filter([
            IeducarSchema::resolveTable('aluno_deficiencia', $city),
            'pmieducar.aluno_deficiencia',
            'public.aluno_deficiencia',
            'educacenso.aluno_deficiencia',
            trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.aluno_deficiencia',
        ]))) as $t) {
            if ($t !== '' && IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, [
            'aluno_deficiencia',
            'aluno_deficiencias',
        ], $city);
    }

    /**
     * Matrículas com registo em aluno_deficiência, por escola (turma → unidade).
     *
     * @return array<string, int> chave = id escola em string
     */
    private function neeMatriculasPorEscola(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $adTable = self::resolveAlunoDeficienciaTable($db, $city);
            if ($adTable === null) {
                return [];
            }

            $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
                (string) config('ieducar.columns.aluno_deficiencia.aluno'),
                'ref_cod_aluno',
                'cod_aluno',
                'aluno_id',
                'id_aluno',
            ]), $city);
            if ($adAluno === null) {
                return [];
            }

            $escolaSpec = self::resolveEscolaJoinSpec($db, $city);
            if ($escolaSpec === null) {
                return [];
            }
            ['qualified' => $escolaT, 'idCol' => $eId] = $escolaSpec;

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mId = (string) config('ieducar.columns.matricula.id');
            $aId = (string) config('ieducar.columns.aluno.id');

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['escola'] === '') {
                return [];
            }
            $refEscola = $tc['escola'];
            $grammar = $db->getQueryGrammar();
            $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($refEscola);
            $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eId);

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->whereIn('a.'.$aId, function ($sub) use ($adTable, $adAluno) {
                    $sub->from($adTable)->select($adAluno)->distinct();
                });

            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $q->join($escolaT.' as e', function ($join) use ($db, $tEsc, $ePk) {
                if ($db->getDriverName() === 'pgsql') {
                    $join->whereRaw('('.$tEsc.')::text = ('.$ePk.')::text');
                } else {
                    $join->whereRaw('CAST('.$tEsc.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
                }
            })
                ->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).') as c')
                ->groupBy('e.'.$eId);

            $out = [];
            foreach ($q->get() as $row) {
                $out[(string) $row->eid] = (int) ($row->c ?? 0);
            }

            return $out;
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * Agregação matrículas × escola × cor/raça (mesma base do gráfico de linhas NEE×raça).
     *
     * @return ?array{
     *   labels: list<string>,
     *   race_series: list<array{label: string, data: list<float>},
     *   rows: \Illuminate\Support\Collection,
     *   nSchoolsBefore: int,
     *   outrosLabel: string,
     *   eids_order: list<string>
     * }
     */
    private function buildMatriculaPorEscolaRacaAggregation(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);

            if (! IeducarColumnInspector::tableExists($db, $mat, $city)
                || ! IeducarColumnInspector::tableExists($db, $aluno, $city)) {
                return null;
            }

            $racaSpec = self::resolveRacaJoinSpec($db, $city);
            if ($racaSpec === null) {
                return null;
            }

            $racaT = $racaSpec['qualified'];
            $rIdCol = $racaSpec['idCol'];
            $rNameCol = $racaSpec['nameCol'];

            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mId = (string) config('ieducar.columns.matricula.id');
            $aId = (string) config('ieducar.columns.aluno.id');
            $grammar = $db->getQueryGrammar();

            $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                (string) config('ieducar.columns.aluno.pessoa'),
                'ref_cod_pessoa',
                'ref_idpes',
                'idpes',
            ]), $city);
            if ($aPessoa === null) {
                return null;
            }

            $aIdpes = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                'ref_idpes',
                'idpes',
            ]), $city);
            $fisicaRacaPivot = self::resolveFisicaRacaPivotSpec($db, $city);

            $pRaca = null;
            $pId = null;
            if (IeducarColumnInspector::tableExists($db, $pessoa, $city)) {
                $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                    (string) config('ieducar.columns.pessoa.id'),
                    'idpes',
                    'id',
                    'cod_pessoa',
                ]), $city);
                $pRaca = IeducarColumnInspector::firstExistingColumn($db, $pessoa, array_filter([
                    (string) config('ieducar.columns.pessoa.raca'),
                    'ref_cod_raca',
                    'cod_raca',
                    'id_raca',
                    'raca_id',
                    'cor_raca',
                    'cor',
                    'ref_cod_cor',
                ]), $city);
            }

            $aRaca = null;
            if ($pRaca === null) {
                $aRaca = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                    'ref_cod_raca',
                    'cod_raca',
                    'id_raca',
                    'raca_cor',
                ]), $city);
            }

            $fisicaTable = null;
            $fisicaRacaCol = null;
            $fisicaLinkCol = null;
            if ($pRaca === null && $aRaca === null && $pId !== null) {
                foreach (self::fisicaTableCandidates($city) as $cand) {
                    if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                        continue;
                    }
                    $fisicaRacaCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                        'ref_cod_raca',
                        'cod_raca',
                        'raca_cor',
                        'id_raca',
                    ], $city);
                    $fisicaLinkCol = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                        'idpes',
                        'ref_idpes',
                    ], $city);
                    if ($fisicaRacaCol !== null && $fisicaLinkCol !== null) {
                        $fisicaTable = $cand;
                        break;
                    }
                }
            }

            /** @var array{table: string, racaCol: string, fisicaLink: string, alunoCol: string}|null */
            $fisicaViaAluno = null;
            if ($pRaca === null && $aRaca === null && $fisicaTable === null && $fisicaRacaPivot === null) {
                $aIdpesCol = IeducarColumnInspector::firstExistingColumn($db, $aluno, array_filter([
                    'ref_idpes',
                    'idpes',
                ]), $city);
                if ($aIdpesCol !== null) {
                    foreach (self::fisicaTableCandidates($city) as $cand) {
                        if (! IeducarColumnInspector::tableExists($db, $cand, $city)) {
                            continue;
                        }
                        $fc = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                            'ref_cod_raca',
                            'cod_raca',
                            'raca_cor',
                            'id_raca',
                        ], $city);
                        $fl = IeducarColumnInspector::firstExistingColumn($db, $cand, [
                            'idpes',
                            'ref_idpes',
                        ], $city);
                        if ($fc !== null && $fl !== null) {
                            $fisicaViaAluno = [
                                'table' => $cand,
                                'racaCol' => $fc,
                                'fisicaLink' => $fl,
                                'alunoCol' => $aIdpesCol,
                            ];
                            break;
                        }
                    }
                }
            }

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);

            if ($fisicaRacaPivot !== null && $aIdpes !== null) {
                $q->leftJoin($fisicaRacaPivot['qualified'].' as fr', 'a.'.$aIdpes, '=', 'fr.'.$fisicaRacaPivot['idpesCol']);
                self::leftJoinRacaCatalogOnFk($db, $q, 'fr', $fisicaRacaPivot['racaFkCol'], $racaT, 'r', $rIdCol);
            } elseif ($pRaca !== null && $pId !== null) {
                $q->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId);
                self::leftJoinRacaCatalogOnFk($db, $q, 'p', $pRaca, $racaT, 'r', $rIdCol);
            } elseif ($aRaca !== null) {
                self::leftJoinRacaCatalogOnFk($db, $q, 'a', $aRaca, $racaT, 'r', $rIdCol);
            } elseif ($fisicaTable !== null && $fisicaRacaCol !== null && $fisicaLinkCol !== null && $pId !== null) {
                $q->join($pessoa.' as p', 'a.'.$aPessoa, '=', 'p.'.$pId)
                    ->leftJoin($fisicaTable.' as pf', 'p.'.$pId, '=', 'pf.'.$fisicaLinkCol);
                self::leftJoinRacaCatalogOnFk($db, $q, 'pf', $fisicaRacaCol, $racaT, 'r', $rIdCol);
            } elseif ($fisicaViaAluno !== null) {
                $q->leftJoin($fisicaViaAluno['table'].' as pf', 'a.'.$fisicaViaAluno['alunoCol'], '=', 'pf.'.$fisicaViaAluno['fisicaLink']);
                self::leftJoinRacaCatalogOnFk($db, $q, 'pf', $fisicaViaAluno['racaCol'], $racaT, 'r', $rIdCol);
            } else {
                return null;
            }

            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $escolaSpec = self::resolveEscolaJoinSpec($db, $city);
            if ($escolaSpec === null) {
                return null;
            }
            ['qualified' => $escolaT, 'idCol' => $eId, 'nameCol' => $eName] = $escolaSpec;

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['escola'] === '') {
                return null;
            }
            $refEscola = $tc['escola'];
            $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($refEscola);
            $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eId);

            $q->join($escolaT.' as e', function ($join) use ($db, $tEsc, $ePk) {
                if ($db->getDriverName() === 'pgsql') {
                    $join->whereRaw('('.$tEsc.')::text = ('.$ePk.')::text');
                } else {
                    $join->whereRaw('CAST('.$tEsc.' AS UNSIGNED) = CAST('.$ePk.' AS UNSIGNED)');
                }
            });

            $q->selectRaw('e.'.$eId.' as eid')
                ->selectRaw('MAX(e.'.$eName.') as ename')
                ->selectRaw('r.'.$rIdCol.' as rid')
                ->selectRaw('MAX(r.'.$rNameCol.') as rname')
                ->selectRaw('COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).') as c')
                ->groupBy('e.'.$eId)
                ->groupBy('r.'.$rIdCol);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $raceTotals = [];
            $cell = [];
            foreach ($rows as $row) {
                $eid = (string) $row->eid;
                $rk = $row->rid === null ? "\0null" : 'id:'.(string) $row->rid;
                $c = (int) ($row->c ?? 0);
                $raceTotals[$rk] = ($raceTotals[$rk] ?? 0) + $c;
                if (! isset($cell[$eid])) {
                    $cell[$eid] = [
                        'name' => (string) (($row->ename ?? '') !== '' ? $row->ename : ('#'.$eid)),
                        'byR' => [],
                    ];
                }
                $cell[$eid]['byR'][$rk] = ($cell[$eid]['byR'][$rk] ?? 0) + $c;
            }

            arsort($raceTotals);
            $topRaceKeys = array_slice(array_keys($raceTotals), 0, 5);
            $raceLabels = [];
            foreach ($topRaceKeys as $rk) {
                $raceLabels[$rk] = $rk === "\0null"
                    ? __('Não declarado')
                    : $this->raceLabelFromRows($rows, $rk);
            }

            uasort($cell, static fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

            $maxSchools = 60;
            $nSchoolsBefore = count($cell);
            if ($nSchoolsBefore > $maxSchools) {
                $totByE = [];
                foreach ($cell as $eid => $meta) {
                    $totByE[$eid] = array_sum($meta['byR']);
                }
                arsort($totByE);
                $keepIds = array_slice(array_keys($totByE), 0, $maxSchools);
                $cell = array_intersect_key($cell, array_flip($keepIds));
            }

            $labels = [];
            foreach ($cell as $meta) {
                $labels[] = (string) $meta['name'];
            }

            $series = [];
            foreach ($topRaceKeys as $rk) {
                $data = [];
                foreach ($cell as $meta) {
                    $data[] = (float) ($meta['byR'][$rk] ?? 0);
                }
                $series[] = [
                    'label' => $raceLabels[$rk] ?? $rk,
                    'data' => $data,
                ];
            }

            $outrosLabel = __('Outras categorias de raça/cor');
            $outrosData = [];
            foreach ($cell as $meta) {
                $sum = 0;
                foreach ($meta['byR'] as $rk => $v) {
                    if (! in_array($rk, $topRaceKeys, true)) {
                        $sum += (int) $v;
                    }
                }
                $outrosData[] = (float) $sum;
            }
            if (array_sum($outrosData) > 0) {
                $series[] = ['label' => $outrosLabel, 'data' => $outrosData];
            }

            return [
                'labels' => $labels,
                'race_series' => $series,
                'rows' => $rows,
                'nSchoolsBefore' => $nSchoolsBefore,
                'outrosLabel' => $outrosLabel,
                'eids_order' => array_keys($cell),
            ];
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * Linhas por escola: séries de matrículas por cor/raça (top categorias + «Outros») e matrículas NEE (aluno_deficiência).
     *
     * @return ?array<string, mixed>
     */
    private function escolaRacaENeeMultiLineChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $agg = $this->buildMatriculaPorEscolaRacaAggregation($db, $city, $filters);
        if ($agg === null) {
            return null;
        }

        $labels = $agg['labels'];
        $series = $agg['race_series'];
        $nSchoolsBefore = $agg['nSchoolsBefore'];
        $outrosLabel = $agg['outrosLabel'];
        $maxSchools = 60;

        $neeByE = $this->neeMatriculasPorEscola($db, $city, $filters);
        $neeData = [];
        foreach ($agg['eids_order'] as $eid) {
            $neeData[] = (float) ($neeByE[$eid] ?? 0);
        }
        $series[] = [
            'label' => __('Matrículas NEE (aluno_deficiência)'),
            'data' => $neeData,
        ];

        $chart = ChartPayload::lineMulti(
            __('Matrículas por cor/raça e NEE — por escola (filtro actual)'),
            $labels,
            $series
        );
        $chart['panel_layout'] = 'full';
        $footnote = __('Cada ponto no eixo horizontal é uma unidade escolar (turma→escola). As linhas de raça/cor usam as categorias mais frequentes na rede; as restantes entram em «:outros». NEE: contagem de matrículas de alunos com registo em aluno_deficiência.', ['outros' => $outrosLabel]);
        if ($nSchoolsBefore > $maxSchools) {
            $footnote .= ' '.__('Mostram-se as :n escolas com maior volume de matrículas no filtro.', ['n' => $maxSchools]);
        }
        $chart['footnote'] = $footnote;

        return $chart;
    }

    /**
     * Barras horizontais empilhadas: por escola, segmentos coloridos por cor/raça (cadastro).
     *
     * @return ?array<string, mixed>
     */
    private function escolaRacaPorEscolaStackedChart(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $agg = $this->buildMatriculaPorEscolaRacaAggregation($db, $city, $filters);
        if ($agg === null) {
            return null;
        }

        $nSchoolsBefore = $agg['nSchoolsBefore'];
        $outrosLabel = $agg['outrosLabel'];
        $maxSchools = 60;

        $chart = ChartPayload::barHorizontalStacked(
            __('Matrículas por cor ou raça — por escola (segmentos empilhados)'),
            __('Matrículas'),
            $agg['labels'],
            $agg['race_series']
        );
        $chart['panel_layout'] = 'full';
        $chart['options'] = array_merge($chart['options'] ?? [], ['panelHeight' => 'xxl']);
        $footnote = __('Cada barra horizontal é uma unidade escolar; os segmentos coloridos são as categorias de raça/cor (as mais frequentes na rede; as restantes em «:outros»). Mesmos filtros e critérios de ligação aluno↔raça que o gráfico de distribuição global por raça.', ['outros' => $outrosLabel]);
        if ($nSchoolsBefore > $maxSchools) {
            $footnote .= ' '.__('Mostram-se as :n escolas com maior volume de matrículas no filtro.', ['n' => $maxSchools]);
        }
        $chart['footnote'] = $footnote;

        return $chart;
    }

    /**
     * Rótulo legível para uma chave de raça já agregada (procura nas linhas SQL).
     */
    private function raceLabelFromRows(Collection $rows, string $rk): string
    {
        if ($rk === "\0null") {
            return __('Não declarado');
        }
        if (! str_starts_with($rk, 'id:')) {
            return $rk;
        }
        $want = substr($rk, 3);
        foreach ($rows as $row) {
            if ($row->rid !== null && (string) $row->rid === $want) {
                $nm = $row->rname ?? null;

                return $nm !== null && (string) $nm !== '' ? (string) $nm : __('Raça #:id', ['id' => $want]);
            }
        }

        return __('Raça #:id', ['id' => $want]);
    }

    /**
     * @return ?array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function chartFromRawSql(Connection $db, City $city, string $sql, string $title): ?array
    {
        try {
            $rows = $db->select($this->appendLimit(IeducarSqlPlaceholders::interpolate($sql, $city), 32));
            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $vals = array_values($arr);
                if (count($vals) < 2) {
                    continue;
                }
                $labels[] = (string) $vals[0];
                $values[] = (float) $vals[1];
            }
            if ($labels === []) {
                return null;
            }

            return ChartPayload::barHorizontal($title, __('Matrículas'), $labels, $values);
        } catch (QueryException) {
            return null;
        }
    }

    private function appendLimit(string $sql, int $max): string
    {
        $sql = trim($sql);
        if ($sql === '' || preg_match('/\blimit\s+\d+\s*$/i', $sql)) {
            return $sql;
        }

        return $sql.' LIMIT '.max(1, $max);
    }
}
