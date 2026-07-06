<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Gráficos e tabelas extra da aba Inclusão: detalhe por catálogo de deficiências, três grupos (def./síndrome/NE)
 * e cruzamento AEE ↔ outros segmentos (heurística por nomes de turma/curso).
 */
final class InclusionDashboardQueries
{
    /**
     * Matrículas distintas por nome de deficiência (cadastro.deficiencia), alinhado ao BIS:
     * prioriza `cadastro.fisica_deficiencia` (pessoa ↔ deficiência via ref_idpes); senão `aluno_deficiencia`.
     * Respeita MatriculaAtivoFilter e filtros de turma (ano, escola, curso, turno).
     *
     * @param  ?int  $limit  Limite de linhas por catálogo (null = sem limite — para detalhe completo no painel).
     * @return Collection<int, object{deficiencia: string, total: int}>
     */
    public static function getMatriculasPorDeficiencia(Connection $db, City $city, IeducarFilterState $filters, ?int $limit = 22): Collection
    {
        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        if ($defTable === null) {
            return collect();
        }

        $mapRows = static fn (array $rows): Collection => collect($rows)->map(fn ($r) => (object) [
            'def_id' => (string) ($r->def_id ?? ''),
            'deficiencia' => (string) ($r->deficiencia ?? ''),
            'total' => (int) ($r->total ?? 0),
        ]);

        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        $alunoT = IeducarSchema::resolveTable('aluno', $city);
        $aIdpes = self::resolveAlunoIdpesColumn($db, $alunoT, $city);
        $fisicaRows = [];

        if ($fisica !== null && $aIdpes !== null) {
            try {
                $fisicaRows = self::queryMatriculasPorDeficienciaFisicaPath($db, $city, $filters, $fisica, $defTable, $limit);
            } catch (\Throwable) {
                $fisicaRows = [];
            }
        }

        $alunoRows = self::queryMatriculasPorDeficienciaAlunoDefPath($db, $city, $filters, $limit);

        $merged = self::mergeDeficienciaAggregateRows($fisicaRows, $alunoRows);
        if ($merged !== []) {
            return $mapRows($merged);
        }

        $neeCadastro = self::countMatriculasNeeComCadastroDeficiencia($db, $city, $filters);
        if ($neeCadastro > 0) {
            $fallback = self::queryMatriculasPorDeficienciaNeeCadastroFallback($db, $city, $filters, $limit);

            return $mapRows($fallback);
        }

        return collect();
    }

    public static function incluirTurmaAeeNoRecorteNee(): bool
    {
        return (bool) config('ieducar.inclusion.nee_incluir_turma_aee', true);
    }

    /**
     * Subquery de alunos com cadastro NEE (fisica_deficiência + deficiência, ou aluno_deficiência + deficiência).
     * Alinhado aos gráficos da aba Inclusão.
     *
     * @return \Closure(Builder): void|null
     */
    public static function alunosComCadastroNeeSubquery(Connection $db, City $city): ?\Closure
    {
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');
        $aIdpes = self::resolveAlunoIdpesColumn($db, $aluno, $city);
        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        if ($defTable === null) {
            return null;
        }

        $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.id'),
            'cod_deficiencia',
        ]), $city);
        if ($defPk === null) {
            return null;
        }

        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        if ($fisica !== null && $aIdpes !== null) {
            return static function ($sub) use ($aluno, $aId, $aIdpes, $fisica, $defTable, $defPk): void {
                $sub->select('a_nee.'.$aId)
                    ->from($aluno.' as a_nee')
                    ->whereExists(function ($ex) use ($aIdpes, $fisica, $defTable, $defPk): void {
                        $ex->from($fisica['table'].' as fd')
                            ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk)
                            ->whereColumn('fd.'.$fisica['idpes_col'], 'a_nee.'.$aIdpes);
                    });
            };
        }

        $adTable = self::resolveAlunoDeficienciaTable($db, $city);
        if ($adTable === null) {
            return null;
        }
        $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
            (string) config('ieducar.columns.aluno_deficiencia.aluno'),
            'ref_cod_aluno',
            'cod_aluno',
        ]), $city);
        $adDef = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
            (string) config('ieducar.columns.aluno_deficiencia.deficiencia'),
            'ref_cod_deficiencia',
        ]), $city);
        if ($adAluno === null || $adDef === null) {
            return null;
        }

        return static function ($sub) use ($aluno, $aId, $adTable, $adAluno, $adDef, $defTable, $defPk): void {
            $sub->select('a_nee.'.$aId)
                ->from($aluno.' as a_nee')
                ->whereExists(function ($ex) use ($adTable, $adAluno, $adDef, $defTable, $defPk): void {
                    $ex->from($adTable.' as ad')
                        ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                        ->whereColumn('ad.'.$adAluno, 'a_nee.'.$aId);
                });
        };
    }

    /**
     * Matrículas activas distintas em educação especial: cadastro NEE e, se configurado, turma/curso AEE.
     * Usa a mesma base SQL que {@see fetchNeeMatriculasComTurmaCurso()} (evita divergência com o bloco AEE).
     */
    public static function countMatriculasComNee(Connection $db, City $city, IeducarFilterState $filters): int
    {
        try {
            $ids = [];
            foreach (self::fetchNeeMatriculasComTurmaCurso($db, $city, $filters) as $row) {
                $mid = (int) ($row['matricula_id'] ?? 0);
                if ($mid > 0) {
                    $ids[$mid] = true;
                }
            }

            return count($ids);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Alunos distintos com matrícula activa em educação especial (evita dupla ponderação FUNDEB por matrícula duplicada).
     */
    public static function countAlunosComNee(Connection $db, City $city, IeducarFilterState $filters): int
    {
        try {
            $alunos = [];
            foreach (self::fetchNeeMatriculasComTurmaCurso($db, $city, $filters) as $row) {
                $aid = (int) ($row['aluno_id'] ?? 0);
                if ($aid > 0) {
                    $alunos[$aid] = true;
                }
            }

            return count($alunos);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Matrículas activas com vínculo em fisica_deficiencia / aluno_deficiencia (sem turma AEE por heurística).
     */
    public static function countMatriculasComCadastroNee(Connection $db, City $city, IeducarFilterState $filters): int
    {
        try {
            $cadastroSub = self::alunosComCadastroNeeSubquery($db, $city);
            if ($cadastroSub === null) {
                return 0;
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            self::applyMatriculaTurmaScopeForInclusionCharts($q, $db, $city, $filters);
            self::applyInclusionScope($q, $db, $city, $filters);
            $q->whereIn('a.'.$aId, $cadastroSub);

            $grammar = $db->getQueryGrammar();
            $row = $q->selectRaw(
                'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).') as c'
            )->first();

            return (int) ($row->c ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Matrículas NEE no recorte com cadastro de deficiência (interseção total NEE × cadastro).
     */
    public static function countMatriculasNeeComCadastroDeficiencia(Connection $db, City $city, IeducarFilterState $filters): int
    {
        try {
            $cadastroSub = self::alunosComCadastroNeeSubquery($db, $city);
            if ($cadastroSub === null) {
                return 0;
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            self::applyMatriculaTurmaScopeForInclusionCharts($q, $db, $city, $filters);
            self::applyInclusionScope($q, $db, $city, $filters);
            self::applyRecorteMatriculasNeeWhere($q, $db, $city, $filters);
            $q->whereIn('a.'.$aId, $cadastroSub);

            $grammar = $db->getQueryGrammar();
            $row = $q->selectRaw(
                'COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).') as c'
            )->first();

            return (int) ($row->c ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Predicado NEE (cadastro e/ou turma AEE) sobre query que já tem `m` (matrícula) e `a` (aluno).
     */
    public static function applyRecorteMatriculasNeeWhere(
        Builder $q,
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $matAlias = 'm',
        string $alunoAlias = 'a',
    ): void {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $mId = (string) config('ieducar.columns.matricula.id');
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $aId = (string) config('ieducar.columns.aluno.id');

        $cadastroSub = self::alunosComCadastroNeeSubquery($db, $city);
        $includeAee = self::incluirTurmaAeeNoRecorteNee();

        if ($cadastroSub === null && ! $includeAee) {
            $q->whereRaw('0 = 1');

            return;
        }

        $q->where(function (Builder $w) use (
            $db,
            $city,
            $filters,
            $mat,
            $aluno,
            $matAlias,
            $alunoAlias,
            $mId,
            $mAluno,
            $mAtivo,
            $aId,
            $cadastroSub,
            $includeAee
        ): void {
            if ($cadastroSub !== null) {
                $w->whereIn($alunoAlias.'.'.$aId, $cadastroSub);
            }
            if ($includeAee) {
                $clause = $cadastroSub !== null ? 'orWhereExists' : 'whereExists';
                $w->{$clause}(function ($ex) use (
                    $db,
                    $city,
                    $filters,
                    $mat,
                    $aluno,
                    $matAlias,
                    $alunoAlias,
                    $mId,
                    $mAluno,
                    $mAtivo,
                    $aId
                ): void {
                    $ex->from($mat.' as m_aee')
                        ->join($aluno.' as a_aee', 'm_aee.'.$mAluno, '=', 'a_aee.'.$aId)
                        ->whereColumn('m_aee.'.$mId, $matAlias.'.'.$mId);
                    MatriculaAtivoFilter::apply($ex, $db, 'm_aee.'.$mAtivo, $city);
                    MatriculaTurmaJoin::joinMatriculaToTurma($ex, $db, $city, 'm_aee');
                    MatriculaTurmaJoin::applyPivotAtivoIfNeeded($ex, $db, $city);
                    MatriculaTurmaJoin::applyTurmaFiltersWhere($ex, $db, $city, $filters, 't_filter');
                    self::applyTurmaAeeRawWhere($ex, $db, $city);
                });
            }
        });
    }

    /**
     * Medidores (%): deficiências, síndromes/TEA e altas habilidades — mesma origem SQL que os gráficos NEE.
     *
     * @return list<array{title: string, percent: float, caption: string}>
     */
    public static function medidoresEducacaoEspecialPorGrupo(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            $den = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);
            if ($den === null || $den <= 0) {
                return [];
            }

            $dataset = InclusionNeeDesignacaoDataset::build($db, $city, $filters);
            if ($dataset !== null) {
                $nee = (int) $dataset['matriculas_nee'];
                $g = $dataset['grupos'] ?? [];
                $nDef = (int) ($g['deficiencias'] ?? 0);
                $nSin = (int) ($g['sindromes_tea'] ?? 0);
                $nAh = (int) ($g['ne_altas_habilidades'] ?? 0);
                $pctRede = static fn (int $n): float => round(100.0 * $n / $den, 1);
                $pathNote = ($dataset['uses_fisica'] ?? false)
                    ? __('cadastro.fisica_deficiencia + deficiência (+ turma AEE quando configurado)')
                    : __('aluno_deficiencia + deficiência (+ turma AEE quando configurado)');

                $gaugeRow = static function (string $title, int $count, int $neeTotal) use ($pctRede): array {
                    $pctNee = $neeTotal > 0 ? round(100.0 * $count / $neeTotal, 1) : 0.0;
                    $pctRedeVal = $pctRede($count);

                    return [
                        'title' => $title,
                        'percent' => $pctRedeVal,
                        'percent_rede' => $pctRedeVal,
                        'percent_nee' => $pctNee,
                        'count' => $count,
                        'caption' => __(':n matrículas · :pct_nee% do universo NEE · :pct_rede% da rede.', [
                            'n' => $count,
                            'pct_nee' => $pctNee,
                            'pct_rede' => $pctRedeVal,
                        ]),
                    ];
                };

                return [
                    $gaugeRow(__('Deficiências'), $nDef, $nee),
                    $gaugeRow(__('Síndromes e TEA'), $nSin, $nee),
                    $gaugeRow(__('Altas habilidades / superdotação'), $nAh, $nee),
                ];
            }

            $defTable = self::resolveDeficienciaCatalogTable($db, $city);
            if ($defTable === null) {
                return [];
            }

            $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                (string) config('ieducar.columns.deficiencia.id'),
                'cod_deficiencia',
            ]), $city);
            $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                (string) config('ieducar.columns.deficiencia.name'),
                'nm_deficiencia',
                'nome',
            ]), $city);
            if ($defPk === null || $nmCol === null) {
                return [];
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');

            $base = static function () use ($db, $mat, $aluno, $mAluno, $aId, $mAtivo, $city, $filters): ?Builder {
                $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
                $aIdpes = self::resolveAlunoIdpesColumn($db, $aluno, $city);
                $defTable = self::resolveDeficienciaCatalogTable($db, $city);
                if ($defTable === null) {
                    return null;
                }
                $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                    (string) config('ieducar.columns.deficiencia.id'),
                    'cod_deficiencia',
                ]), $city);
                if ($defPk === null) {
                    return null;
                }

                $q = $db->table($mat.' as m')
                    ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
                self::applyInclusionScope($q, $db, $city, $filters);

                if ($fisica !== null && $aIdpes !== null) {
                    $q->join($fisica['table'].' as fd', 'a.'.$aIdpes, '=', 'fd.'.$fisica['idpes_col'])
                        ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk);
                } else {
                    $adTable = self::resolveAlunoDeficienciaTable($db, $city);
                    if ($adTable === null) {
                        return null;
                    }
                    $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
                        (string) config('ieducar.columns.aluno_deficiencia.aluno'),
                        'ref_cod_aluno',
                    ]), $city);
                    $adDef = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
                        (string) config('ieducar.columns.aluno_deficiencia.deficiencia'),
                        'ref_cod_deficiencia',
                    ]), $city);
                    if ($adAluno === null || $adDef === null) {
                        return null;
                    }
                    $q->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                        ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk);
                }

                return $q;
            };

            $countDistinct = static function (?Builder $q) use ($mId): int {
                if ($q === null) {
                    return 0;
                }
                try {
                    $row = $q->selectRaw('COUNT(DISTINCT m.'.$mId.') as c')->first();

                    return (int) ($row->c ?? 0);
                } catch (QueryException) {
                    return 0;
                }
            };

            $sinExpr = self::keywordMatchExpression('d.'.$nmCol, self::sindromeKeywords());
            $ahExpr = self::keywordMatchExpression('d.'.$nmCol, self::altasHabilidadesKeywords());
            $defExpr = '(NOT ('.$sinExpr.')) AND (NOT ('.$ahExpr.'))';

            $nSin = $countDistinct($base()?->whereRaw($sinExpr));
            $nAh = $countDistinct($base()?->whereRaw($ahExpr));
            $nDef = $countDistinct($base()?->whereRaw($defExpr));
            $neeTotal = self::countMatriculasComNee($db, $city, $filters);
            $pctRede = static fn (int $n): float => round(100.0 * $n / $den, 1);
            $pctNee = static fn (int $n): float => $neeTotal > 0 ? round(100.0 * $n / $neeTotal, 1) : 0.0;

            $gaugeRow = static function (string $title, int $count) use ($pctRede, $pctNee): array {
                $pctRedeVal = $pctRede($count);
                $pctNeeVal = $pctNee($count);

                return [
                    'title' => $title,
                    'percent' => $pctRedeVal,
                    'percent_rede' => $pctRedeVal,
                    'percent_nee' => $pctNeeVal,
                    'count' => $count,
                    'caption' => __(':n matrículas · :pct_nee% do universo NEE · :pct_rede% da rede.', [
                        'n' => $count,
                        'pct_nee' => $pctNeeVal,
                        'pct_rede' => $pctRedeVal,
                    ]),
                ];
            };

            return [
                $gaugeRow(__('Deficiências'), $nDef),
                $gaugeRow(__('Síndromes e TEA'), $nSin),
                $gaugeRow(__('Altas habilidades / superdotação'), $nAh),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Impacto FUNDEB/VAAR indicativo das matrículas NEE (ponderação Lei 14.113/2020).
     *
     * @return array<string, mixed>
     */
    public static function buildFundebNeeIndicativo(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $totalMatriculas = null,
    ): array {
        return InclusionFundebImpact::build($db, $city, $filters, $totalMatriculas);
    }

    /**
     * Contagem por designação no catálogo, separada em deficiências, síndromes/TEA e NE (altas habilidades),
     * sem agregar em «Outros» — para o card de detalhe na aba Inclusão.
     *
     * @return ?array{
     *   deficiencias: list<array{nome: string, total: int}>,
     *   sindromes_tea: list<array{nome: string, total: int}>,
     *   ne_altas_habilidades: list<array{nome: string, total: int}>,
     *   totais_por_secao: array{deficiencias: int, sindromes_tea: int, ne_altas_habilidades: int},
     *   footnote: string
     * }
     */
    public static function buildNeeDetalheCatalogoPorCategoria(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $dataset = InclusionNeeDesignacaoDataset::build($db, $city, $filters);

        return $dataset !== null ? InclusionNeeDesignacaoDataset::detalhePorCategoria($dataset) : null;
    }

    /**
     * Alinha-se ao gráfico de três grupos: NE (altas habilidades) e síndromes/TEA por palavras-chave; o restante conta como deficiência.
     */
    private static function classificarDesignacaoNee(string $nome): string
    {
        $h = mb_strtolower($nome);
        foreach (self::altasHabilidadesKeywords() as $w) {
            $w = trim((string) $w);
            if ($w !== '' && str_contains($h, mb_strtolower($w))) {
                return 'ne';
            }
        }
        foreach (self::sindromeKeywords() as $w) {
            $w = trim((string) $w);
            if ($w !== '' && str_contains($h, mb_strtolower($w))) {
                return 'sindrome';
            }
        }

        return 'deficiencia';
    }

    /**
     * @return list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string, footnote?: string}>
     */
    public static function inclusionNeeUsesFisicaPath(Connection $db, City $city): bool
    {
        $alunoT = IeducarSchema::resolveTable('aluno', $city);

        return self::resolveFisicaDeficienciaJoinSpec($db, $city) !== null
            && self::resolveDeficienciaCatalogTable($db, $city) !== null
            && self::resolveAlunoIdpesColumn($db, $alunoT, $city) !== null;
    }

    public static function classificarDesignacaoNeeGrupo(string $nome): string
    {
        return self::classificarDesignacaoNee($nome);
    }

    public static function grupoNeeLabel(string $grupoKey): string
    {
        return match ($grupoKey) {
            'sindrome' => __('Síndromes e TEA'),
            'ne' => __('NE — altas habilidades'),
            default => __('Deficiências'),
        };
    }

    /**
     * Contagem de matrículas NEE por grupo — mesma classificação da exportação (fisica + aluno_deficiencia por aluno).
     *
     * @return array{deficiencias: int, sindromes_tea: int, ne_altas_habilidades: int}
     */
    /**
     * Contagens do catálogo NEE: 1 matrícula NEE → +1 por designação catalogada (fisica + aluno_deficiencia, sem duplicar o mesmo código).
     *
     * @return array{by_id: array<string, int>, by_norm: array<string, int>}
     */
    public static function deficienciaCountMapsFromNeeExportAligned(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): array {
        try {
            $neeRows = self::fetchNeeMatriculasComTurmaCurso($db, $city, $filters);
            if ($neeRows === []) {
                return ['by_id' => [], 'by_norm' => []];
            }

            $alunoIds = array_values(array_unique(array_map(
                static fn (array $r): int => (int) ($r['aluno_id'] ?? 0),
                $neeRows,
            )));
            $alunoIds = array_values(array_filter($alunoIds, static fn (int $id): bool => $id > 0));
            $byAluno = self::deficienciasPorAlunoIdsForExport($db, $city, $alunoIds);

            return self::aggregateCatalogCountMapsFromNeeMatriculas($neeRows, $byAluno);
        } catch (\Throwable) {
            return ['by_id' => [], 'by_norm' => []];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $neeRows
     * @param  array<int, array{labels?: list<string>, designacoes?: list<array{nome: string, def_id: string, norm: string}>}>  $byAluno
     * @return array{by_id: array<string, int>, by_norm: array<string, int>}
     */
    public static function aggregateCatalogCountMapsFromNeeMatriculas(array $neeRows, array $byAluno): array
    {
        $byId = [];
        $byNorm = [];

        foreach ($neeRows as $row) {
            $aid = (int) ($row['aluno_id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }

            $designacoes = $byAluno[$aid]['designacoes'] ?? [];
            if ($designacoes === [] && isset($byAluno[$aid]['labels'])) {
                foreach ($byAluno[$aid]['labels'] as $nome) {
                    $nome = trim((string) $nome);
                    if ($nome === '') {
                        continue;
                    }
                    $designacoes[] = [
                        'nome' => $nome,
                        'def_id' => '',
                        'norm' => InclusionEducacensoCatalog::resolveCatalogNorm($nome),
                    ];
                }
            }

            foreach ($designacoes as $d) {
                $defId = trim((string) ($d['def_id'] ?? ''));
                $norm = trim((string) ($d['norm'] ?? ''));
                if ($norm === '' && trim((string) ($d['nome'] ?? '')) !== '') {
                    $norm = InclusionEducacensoCatalog::resolveCatalogNorm((string) $d['nome']);
                }

                if ($defId !== '') {
                    $byId[$defId] = ($byId[$defId] ?? 0) + 1;
                } elseif ($norm !== '') {
                    $byNorm[$norm] = ($byNorm[$norm] ?? 0) + 1;
                }
            }
        }

        return ['by_id' => $byId, 'by_norm' => $byNorm];
    }

    public static function aggregateGruposPorMatriculaNeeExportAligned(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): array {
        $out = [
            'deficiencias' => 0,
            'sindromes_tea' => 0,
            'ne_altas_habilidades' => 0,
        ];

        try {
            $neeRows = self::fetchNeeMatriculasComTurmaCurso($db, $city, $filters);
            if ($neeRows === []) {
                return $out;
            }

            $alunoIds = array_values(array_unique(array_map(
                static fn (array $r): int => (int) ($r['aluno_id'] ?? 0),
                $neeRows,
            )));
            $alunoIds = array_values(array_filter($alunoIds, static fn (int $id): bool => $id > 0));
            $byAluno = self::deficienciasPorAlunoIdsForExport($db, $city, $alunoIds);

            foreach ($neeRows as $row) {
                $aid = (int) ($row['aluno_id'] ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                $keys = $byAluno[$aid]['grupo_keys'] ?? [];
                if ($keys === []) {
                    continue;
                }
                if (in_array('deficiencia', $keys, true)) {
                    $out['deficiencias']++;
                }
                if (in_array('sindrome', $keys, true)) {
                    $out['sindromes_tea']++;
                }
                if (in_array('ne', $keys, true)) {
                    $out['ne_altas_habilidades']++;
                }
            }
        } catch (\Throwable) {
            return $out;
        }

        return $out;
    }

    /**
     * Junta contagens por designação dos caminhos fisica_deficiencia e aluno_deficiencia (como na exportação).
     *
     * @param  list<object|array<string, mixed>>  $fisicaRows
     * @param  list<object|array<string, mixed>>  $alunoRows
     * @return list<array{def_id: string, deficiencia: string, total: int}>
     */
    private static function mergeDeficienciaAggregateRows(array $fisicaRows, array $alunoRows): array
    {
        $byKey = [];

        foreach (array_merge($fisicaRows, $alunoRows) as $row) {
            $arr = (array) $row;
            $defId = trim((string) ($arr['def_id'] ?? ''));
            $nome = trim((string) ($arr['deficiencia'] ?? ''));
            $total = (int) ($arr['total'] ?? 0);
            if ($total <= 0) {
                continue;
            }
            $key = $defId !== '' ? 'id:'.$defId : 'nome:'.$nome;
            if ($key === 'nome:' || $key === 'id:') {
                continue;
            }
            if (! isset($byKey[$key])) {
                $byKey[$key] = [
                    'def_id' => $defId,
                    'deficiencia' => $nome,
                    'total' => 0,
                ];
            }
            $byKey[$key]['total'] += $total;
        }

        $rows = array_values($byKey);
        usort($rows, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    public static function attachMatriculaKpiTotalPublic(
        array $chart,
        ?int $denominator,
        bool $multiVinculoHint = false,
        ?string $secondaryLabel = null
    ): array {
        return self::attachMatriculaKpiTotal($chart, $denominator, $multiVinculoHint, $secondaryLabel);
    }

    public static function buildCharts(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $out = [];
        $den = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);

        $neeDataset = InclusionNeeDesignacaoDataset::build($db, $city, $filters);
        $hasCatalogoNee = false;
        if ($neeDataset !== null) {
            // Gráfico por grupo omitido: mesmos dados nos medidores e cartões da seção «Indicadores NEE».
            // Catálogo completo (inclui opções com zero) — cores INEP / complementar / só i-Educar.
            $catalogoCompleto = InclusionNeeDesignacaoDataset::chartCatalogo($neeDataset, $den, true);
            if ($catalogoCompleto !== null) {
                $hasCatalogoNee = true;
                $out[] = self::withChartId($catalogoCompleto, 'nee_catalogo');
            }
        } else {
            $catalogoFallback = self::chartNeeCatalogoCompletoMecIeducar($db, $city, $filters, $den);
            if ($catalogoFallback !== null) {
                $hasCatalogoNee = true;
                $out[] = self::withChartId($catalogoFallback, 'nee_catalogo');
            }
        }

        // Total de matrículas NEE por unidade (barras simples) — em seguida ao resumo por grupos.
        $porEscolaTotal = self::chartNeeMatriculasPorEscolaTop($db, $city, $filters, $den);
        if ($porEscolaTotal !== null) {
            $out[] = self::withChartId($porEscolaTotal, 'nee_escola_top');
        }
        if (! $hasCatalogoNee) {
            $det = self::chartMatriculasPorNomeDeficiencia($db, $city, $filters, $den);
            if ($det !== null) {
                $out[] = self::withChartId($det, 'nee_por_designacao');
            }
        }
        // Detalhe por tipo de deficiência no catálogo (empilhado), além do total acima.
        $porEscolaStacked = self::chartNeeDeficienciasPorEscolaStacked($db, $city, $filters, $den);
        if ($porEscolaStacked !== null) {
            $out[] = self::withChartId($porEscolaStacked, 'nee_escola_empilhado');
        }

        return $out;
    }

    /**
     * Catálogo MEC + i-Educar com todas as designações (valor 0 quando não há matrículas).
     *
     * @return ?array<string, mixed>
     */
    public static function chartNeeCatalogoCompletoMecIeducar(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $denominator = null
    ): ?array {
        $den = $denominator ?? MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);
        $dataset = InclusionNeeDesignacaoDataset::build($db, $city, $filters);

        return $dataset !== null
            ? InclusionNeeDesignacaoDataset::chartCatalogo($dataset, $den, true)
            : null;
    }

    /**
     * Resumo NEE (três grupos) para a aba Visão geral — mesmos critérios que o gráfico principal em Inclusão & Diversidade.
     *
     * @return ?array<string, mixed>
     */
    public static function chartNeeResumoVisaoGeral(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        $dataset = InclusionNeeDesignacaoDataset::build($db, $city, $filters);
        $chart = $dataset !== null ? InclusionNeeDesignacaoDataset::chartGrupo($dataset) : null;
        if ($chart === null) {
            return null;
        }

        $chart['title'] = __('NEE (resumo) — educação especial');
        $chart['options'] = array_merge(
            is_array($chart['options'] ?? null) ? $chart['options'] : [],
            ['panelHeight' => 'lg']
        );

        return $chart;
    }

    /**
     * Barras horizontais empilhadas: por escola, segmentos = designação no catálogo de deficiências (onde existe cada NEE).
     *
     * @return ?array<string, mixed>
     */
    private static function chartNeeDeficienciasPorEscolaStacked(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $denominator = null
    ): ?array {
        try {
            $rows = self::fetchNeeEscolaDeficienciaAggregationRows($db, $city, $filters);
            if ($rows === []) {
                return null;
            }

            $totByE = [];
            $totByD = [];
            /** @var array<string, array<string, int>> $cell */
            $cell = [];
            $names = [];
            foreach ($rows as $r) {
                $eid = $r['eid'];
                $did = $r['did'];
                $c = $r['c'];
                if ($c <= 0) {
                    continue;
                }
                $totByE[$eid] = ($totByE[$eid] ?? 0) + $c;
                $totByD[$did] = ($totByD[$did] ?? 0) + $c;
                $cell[$eid][$did] = ($cell[$eid][$did] ?? 0) + $c;
                $nm = trim((string) ($r['dname'] ?? ''));
                $names[$did] = $nm !== '' ? $nm : __('Não informado');
            }

            if ($totByE === []) {
                return null;
            }

            $maxSchools = 24;
            $maxDefTypes = 14;
            arsort($totByE);
            $eidOrder = array_slice(array_keys($totByE), 0, $maxSchools, true);

            arsort($totByD);
            $defKeysAll = array_keys($totByD);
            $topDefKeys = array_slice($defKeysAll, 0, $maxDefTypes);
            $topDefSet = array_flip($topDefKeys);

            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eIdCol = (string) config('ieducar.columns.escola.id');
            $eNameCol = (string) config('ieducar.columns.escola.name');

            $labels = [];
            foreach ($eidOrder as $eidStr) {
                $name = $db->table($escolaT)->where($eIdCol, $eidStr)->value($eNameCol);
                $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$eidStr);
            }

            $series = [];
            foreach ($topDefKeys as $did) {
                $data = [];
                foreach ($eidOrder as $eidStr) {
                    $data[] = (float) ($cell[$eidStr][$did] ?? 0);
                }
                $series[] = [
                    'label' => $names[$did] ?? ('#'.$did),
                    'data' => $data,
                ];
            }

            $outrosData = [];
            foreach ($eidOrder as $eidStr) {
                $sum = 0;
                foreach ($cell[$eidStr] ?? [] as $did => $v) {
                    if (! isset($topDefSet[$did])) {
                        $sum += $v;
                    }
                }
                $outrosData[] = (float) $sum;
            }
            if (array_sum($outrosData) > 0.5) {
                $series[] = [
                    'label' => __('Outras designações'),
                    'data' => $outrosData,
                ];
            }

            $chart = ChartPayload::barHorizontalStacked(
                __('NEE por tipo de deficiência — por escola'),
                __('Matrículas (distintas)'),
                $labels,
                $series
            );
            $chart['subtitle'] = __(
                'Cada barra é uma unidade escolar; os segmentos mostram quantas matrículas activas distintas existem por designação no catálogo (cadastro.deficiencia). Até :n escolas e :m tipos mais frequentes no filtro.',
                ['n' => count($labels), 'm' => count($topDefKeys)]
            );
            $chart['options'] = array_merge($chart['options'] ?? [], ['panelHeight' => 'xxxl']);
            $chart['footnote'] = __(
                'Mesma origem que «Matrículas por tipo (cadastro)»: prioridade a cadastro.fisica_deficiência; senão aluno_deficiência. Uma matrícula pode contar em mais do que um segmento se o aluno tiver vários vínculos.'
            );

            $den = $denominator ?? MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);

            return self::attachMatriculaKpiTotal($chart, $den, true);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{eid: string, did: string, dname: string, c: int}>
     */
    private static function fetchNeeEscolaDeficienciaAggregationRows(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        if ($defTable === null) {
            return [];
        }

        $alunoT = IeducarSchema::resolveTable('aluno', $city);
        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        $aIdpes = self::resolveAlunoIdpesColumn($db, $alunoT, $city);

        if ($fisica !== null && $aIdpes !== null) {
            try {
                $r = self::queryNeeEscolaDeficienciaFisicaPath($db, $city, $filters, $fisica, $defTable);
                if ($r !== []) {
                    return $r;
                }
            } catch (\Throwable) {
                // tenta aluno_deficiência
            }
        }

        return self::queryNeeEscolaDeficienciaAlunoDefPath($db, $city, $filters, $defTable);
    }

    /**
     * @param  array{table: string, idpes_col: string, def_fk: string}  $fisica
     * @return list<array{eid: string, did: string, dname: string, c: int}>
     */
    private static function queryNeeEscolaDeficienciaFisicaPath(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        array $fisica,
        string $defTable,
    ): array {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $mId = (string) config('ieducar.columns.matricula.id');
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $aId = (string) config('ieducar.columns.aluno.id');
        $aIdpes = self::resolveAlunoIdpesColumn($db, $aluno, $city);
        if ($aIdpes === null) {
            return [];
        }

        $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.id'),
            'cod_deficiencia',
        ]), $city);
        $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.name'),
            'nm_deficiencia',
        ]), $city);
        if ($defPk === null || $nmCol === null) {
            return [];
        }

        $escolaT = IeducarSchema::resolveTable('escola', $city);
        $eId = (string) config('ieducar.columns.escola.id');

        $g = $db->getQueryGrammar();
        $wNm = $g->wrap($nmCol);
        $wPk = $g->wrap($defPk);

        $q = $db->table($mat.' as m')
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
            ->join($fisica['table'].' as fd', 'a.'.$aIdpes, '=', 'fd.'.$fisica['idpes_col'])
            ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
        self::applyInclusionScope($q, $db, $city, $filters);

        if (! self::joinEscolaOnMatriculaTurmaQuery($db, $city, $q, $escolaT, $eId)) {
            return [];
        }

        $q->selectRaw('e.'.$eId.' as eid')
            ->selectRaw('d.'.$wPk.' as did')
            ->selectRaw('MAX(COALESCE(d.'.$wNm.', \'Não informado\')) as dname')
            ->selectRaw('COUNT(DISTINCT '.$g->wrap('m').'.'.$g->wrap($mId).') as c')
            ->groupBy('e.'.$eId)
            ->groupBy('d.'.$wPk);

        return self::mapNeeEscolaDeficienciaRows($q->get());
    }

    /**
     * @return list<array{eid: string, did: string, dname: string, c: int}>
     */
    private static function queryNeeEscolaDeficienciaAlunoDefPath(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $defTable,
    ): array {
        $adTable = self::resolveAlunoDeficienciaTable($db, $city);
        if ($adTable === null) {
            return [];
        }
        $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
            (string) config('ieducar.columns.aluno_deficiencia.aluno'),
            'ref_cod_aluno',
            'cod_aluno',
        ]), $city);
        $adDef = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
            (string) config('ieducar.columns.aluno_deficiencia.deficiencia'),
            'ref_cod_deficiencia',
        ]), $city);
        $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.id'),
            'cod_deficiencia',
        ]), $city);
        $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.name'),
            'nm_deficiencia',
        ]), $city);
        if ($adAluno === null || $adDef === null || $defPk === null || $nmCol === null) {
            return [];
        }

        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $mId = (string) config('ieducar.columns.matricula.id');
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $aId = (string) config('ieducar.columns.aluno.id');

        $escolaT = IeducarSchema::resolveTable('escola', $city);
        $eId = (string) config('ieducar.columns.escola.id');

        $g = $db->getQueryGrammar();
        $wNm = $g->wrap($nmCol);
        $wPk = $g->wrap($defPk);

        $q = $db->table($mat.' as m')
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
            ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
            ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
        self::applyInclusionScope($q, $db, $city, $filters);

        if (! self::joinEscolaOnMatriculaTurmaQuery($db, $city, $q, $escolaT, $eId)) {
            return [];
        }

        $q->selectRaw('e.'.$eId.' as eid')
            ->selectRaw('d.'.$wPk.' as did')
            ->selectRaw('MAX(COALESCE(d.'.$wNm.', \'Não informado\')) as dname')
            ->selectRaw('COUNT(DISTINCT '.$g->wrap('m').'.'.$g->wrap($mId).') as c')
            ->groupBy('e.'.$eId)
            ->groupBy('d.'.$wPk);

        return self::mapNeeEscolaDeficienciaRows($q->get());
    }

    /**
     * Junta escola a uma query que já tem matricula m e turma t_filter (mesma regra que neeMatriculasDistinctCountByEscolaMap).
     */
    private static function joinEscolaOnMatriculaTurmaQuery(
        Connection $db,
        City $city,
        Builder $q,
        string $escolaT,
        string $eId,
    ): bool {
        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        $grammar = $db->getQueryGrammar();
        if ($tc['escola'] !== '') {
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

            return true;
        }

        $mat = IeducarSchema::resolveTable('matricula', $city);
        $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
            (string) config('ieducar.columns.matricula.escola'),
            'ref_cod_escola',
            'ref_ref_cod_escola',
            'cod_escola',
        ]), $city);
        if ($mEsc === null) {
            return false;
        }
        $q->join($escolaT.' as e', 'm.'.$mEsc, '=', 'e.'.$eId);

        return true;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{eid: string, did: string, dname: string, c: int}>
     */
    private static function mapNeeEscolaDeficienciaRows(Collection $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $eid = trim((string) ($row->eid ?? ''));
            $did = trim((string) ($row->did ?? ''));
            if ($eid === '' || $eid === '0' || $did === '') {
                continue;
            }
            $out[] = [
                'eid' => $eid,
                'did' => $did,
                'dname' => (string) ($row->dname ?? ''),
                'c' => (int) ($row->c ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Barras horizontais: matrículas NEE (DISTINCT) por escola (turma → escola; fallback matrícula → escola).
     *
     * @return ?array<string, mixed>
     */
    private static function chartNeeMatriculasPorEscolaTop(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $denominator = null
    ): ?array {
        try {
            $map = self::neeMatriculasDistinctCountByEscolaMap($db, $city, $filters);
            if ($map === []) {
                return null;
            }
            arsort($map);
            $map = array_slice($map, 0, 24, true);
            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');
            $eName = (string) config('ieducar.columns.escola.name');
            $labels = [];
            $values = [];
            foreach ($map as $eidStr => $cnt) {
                $name = $db->table($escolaT)->where($eId, $eidStr)->value($eName);
                $labels[] = $name !== null && (string) $name !== '' ? (string) $name : ('#'.$eidStr);
                $values[] = (float) $cnt;
            }
            $chart = ChartPayload::barHorizontal(
                __('Matrículas NEE (educação especial) por escola'),
                __('Matrículas (distintas)'),
                $labels,
                $values
            );
            $chart['subtitle'] = __(
                'Contagem de matrículas activas distintas com educação especial (prioridade: cadastro.fisica_deficiência + deficiência; senão aluno_deficiência), agrupadas por unidade escolar. Top :n escolas no filtro.',
                ['n' => count($labels)]
            );
            $chart['options'] = array_merge($chart['options'] ?? [], ['panelHeight' => 'xxl']);

            $den = $denominator ?? MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);

            return self::attachMatriculaKpiTotal($chart, $den, false);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mapa escola → contagem DISTINCT de matrículas NEE, via cadastro.fisica_deficiência (BIS), alinhado ao gráfico de três grupos.
     *
     * @return array<string, int>
     */
    private static function neeMatriculasDistinctCountByEscolaMapFisicaPath(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        $alunoT = IeducarSchema::resolveTable('aluno', $city);
        $aIdpes = self::resolveAlunoIdpesColumn($db, $alunoT, $city);
        if ($defTable === null || $fisica === null || $aIdpes === null) {
            return [];
        }
        $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.id'),
            'cod_deficiencia',
        ]), $city);
        if ($defPk === null) {
            return [];
        }

        $escolaT = IeducarSchema::resolveTable('escola', $city);
        $eId = (string) config('ieducar.columns.escola.id');

        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $mId = (string) config('ieducar.columns.matricula.id');
        $aId = (string) config('ieducar.columns.aluno.id');

        $grammar = $db->getQueryGrammar();

        $baseMatriculaAlunoComNeeFisica = function () use ($db, $mat, $aluno, $mAluno, $aId, $fisica, $defTable, $defPk, $aIdpes): Builder {
            return $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->join($fisica['table'].' as fd', 'a.'.$aIdpes, '=', 'fd.'.$fisica['idpes_col'])
                ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk);
        };

        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        if ($tc['escola'] !== '') {
            $refEscola = $tc['escola'];
            $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($refEscola);
            $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eId);

            $q = $baseMatriculaAlunoComNeeFisica();
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            self::applyInclusionScope($q, $db, $city, $filters);
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
        }

        $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
            (string) config('ieducar.columns.matricula.escola'),
            'ref_cod_escola',
            'ref_ref_cod_escola',
            'cod_escola',
        ]), $city);
        if ($mEsc === null) {
            return [];
        }

        $q = $baseMatriculaAlunoComNeeFisica();
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
        self::applyInclusionScope($q, $db, $city, $filters);
        $q->join($escolaT.' as e', 'm.'.$mEsc, '=', 'e.'.$eId)
            ->selectRaw('e.'.$eId.' as eid')
            ->selectRaw('COUNT(DISTINCT '.$grammar->wrap('m').'.'.$grammar->wrap($mId).') as c')
            ->groupBy('e.'.$eId);
        $out = [];
        foreach ($q->get() as $row) {
            $out[(string) $row->eid] = (int) ($row->c ?? 0);
        }

        return $out;
    }

    /**
     * @return array<string, int> eid => contagem
     */
    private static function neeMatriculasDistinctCountByEscolaMap(Connection $db, City $city, IeducarFilterState $filters): array
    {
        try {
            try {
                $mapFisica = self::neeMatriculasDistinctCountByEscolaMapFisicaPath($db, $city, $filters);
                if ($mapFisica !== []) {
                    return $mapFisica;
                }
            } catch (QueryException|\Throwable) {
                // Continua com aluno_deficiência.
            }

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

            $escolaT = IeducarSchema::resolveTable('escola', $city);
            $eId = (string) config('ieducar.columns.escola.id');

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $mId = (string) config('ieducar.columns.matricula.id');
            $aId = (string) config('ieducar.columns.aluno.id');

            $grammar = $db->getQueryGrammar();
            $baseAlunosComNee = function () use ($db, $mat, $aluno, $mAluno, $aId, $adTable, $adAluno): Builder {
                return $db->table($mat.' as m')
                    ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                    ->whereIn('a.'.$aId, function ($sub) use ($adTable, $adAluno) {
                        $sub->from($adTable)->select($adAluno)->distinct();
                    });
            };

            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['escola'] !== '') {
                $refEscola = $tc['escola'];
                $tEsc = $grammar->wrap('t_filter').'.'.$grammar->wrap($refEscola);
                $ePk = $grammar->wrap('e').'.'.$grammar->wrap($eId);

                $q = $baseAlunosComNee();
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
                MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
                MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
                self::applyInclusionScope($q, $db, $city, $filters);
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
            }

            $mEsc = IeducarColumnInspector::firstExistingColumn($db, $mat, array_filter([
                (string) config('ieducar.columns.matricula.escola'),
                'ref_cod_escola',
                'ref_ref_cod_escola',
                'cod_escola',
            ]), $city);
            if ($mEsc === null) {
                return [];
            }

            $q = $baseAlunosComNee();
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
            self::applyInclusionScope($q, $db, $city, $filters);
            $q->join($escolaT.' as e', 'm.'.$mEsc, '=', 'e.'.$eId)
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
     * Matrículas activas em turma/curso AEE (heurística) sem cadastro de deficiência/NEE no aluno.
     * Alinhado a {@see DiscrepanciesQueries::turmaAeeSemCadastroNeePorEscola()} — conta só o vínculo AEE, não
     * matrículas do mesmo aluno em segmento regular ou complementar.
     */
    public static function countMatriculasTurmaAeeSemCadastroNee(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): int {
        try {
            $porEscola = DiscrepanciesQueries::turmaAeeSemCadastroNeePorEscola($db, $city, $filters);

            return (int) array_sum(array_map(
                static fn (array $row): int => (int) ($row['total'] ?? 0),
                $porEscola,
            ));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Alunos distintos em turma AEE sem cadastro NEE — base indicativa de risco FUNDEB (uma ponderação por aluno).
     */
    public static function countAlunosTurmaAeeSemCadastroNee(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): int {
        return DiscrepanciesQueries::countAlunosTurmaAeeSemCadastroNee($db, $city, $filters);
    }

    /**
     * @return ?array<string, mixed>
     */
    public static function buildAeeCrossEnrollment(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $rows = self::fetchNeeMatriculasComTurmaCurso($db, $city, $filters);
            if ($rows === []) {
                return [
                    'nee_matriculas_total' => 0,
                    'matriculas_em_turmas_aee' => 0,
                    'alunos_com_aee' => 0,
                    'alunos_nee_com_aee_e_outro_segmento' => 0,
                    'matriculas_fora_aee_por_segmento' => [],
                    'note' => __('Sem matrículas com vínculo a necessidades especiais no filtro ou tabelas indisponíveis.'),
                ];
            }

            $byAluno = [];
            foreach ($rows as $r) {
                $aid = (int) ($r['aluno_id'] ?? 0);
                $mid = (int) ($r['matricula_id'] ?? 0);
                if ($aid <= 0 || $mid <= 0) {
                    continue;
                }
                $turmaNome = trim((string) ($r['nm_turma'] ?? ''));
                $cursoNome = trim((string) ($r['nm_curso'] ?? ''));
                $isAee = self::matchKeywords(
                    mb_strtolower($turmaNome.' '.$cursoNome),
                    'aee_keywords'
                );
                $seg = $isAee
                    ? 'AEE'
                    : self::segmentLabelFromCursoTurma($cursoNome, $turmaNome);
                $byAluno[$aid][$mid] = ['aee' => $isAee, 'seg' => $seg];
            }

            $uniqueMid = [];
            foreach ($byAluno as $mats) {
                foreach (array_keys($mats) as $mid) {
                    $uniqueMid[$mid] = true;
                }
            }
            $neeMatriculas = self::countMatriculasComNee($db, $city, $filters);

            $aeeMids = [];
            $alunosComAee = [];
            foreach ($byAluno as $aid => $mats) {
                foreach ($mats as $mid => $info) {
                    if ($info['aee']) {
                        $aeeMids[$mid] = true;
                        $alunosComAee[$aid] = true;
                    }
                }
            }
            $matAee = count($aeeMids);

            $nAlunosAee = count($alunosComAee);
            $segCount = [];
            $alunosAeeEOutro = 0;

            foreach ($byAluno as $aid => $mats) {
                $hasAee = false;
                $hasOutro = false;
                foreach ($mats as $info) {
                    if ($info['aee']) {
                        $hasAee = true;
                    } else {
                        $hasOutro = true;
                    }
                }
                if ($hasAee && $hasOutro) {
                    $alunosAeeEOutro++;
                    foreach ($mats as $info) {
                        if (! $info['aee']) {
                            $seg = $info['seg'];
                            $segCount[$seg] = ($segCount[$seg] ?? 0) + 1;
                        }
                    }
                }
            }

            arsort($segCount);
            $porSeg = [];
            foreach ($segCount as $seg => $n) {
                $porSeg[] = ['segmento' => $seg, 'matriculas' => $n];
            }

            $note = null;
            if ($nAlunosAee === 0 && $matAee === 0) {
                $note = __(
                    'Não foram encontradas turmas identificadas como AEE com os critérios actuais (nome da turma ou do curso).'
                );
            }

            $comCadastro = self::countMatriculasComCadastroNee($db, $city, $filters);
            $somenteAee = max(0, $neeMatriculas - $comCadastro);
            $matAeeSemCadastro = self::countMatriculasTurmaAeeSemCadastroNee($db, $city, $filters);

            return [
                'nee_matriculas_total' => $neeMatriculas,
                'matriculas_com_cadastro_nee' => $comCadastro,
                'matriculas_somente_turma_aee' => $somenteAee,
                'matriculas_aee_sem_cadastro' => $matAeeSemCadastro,
                'matriculas_em_turmas_aee' => $matAee,
                'alunos_com_aee' => $nAlunosAee,
                'alunos_nee_com_aee_e_outro_segmento' => $alunosAeeEOutro,
                'matriculas_fora_aee_por_segmento' => $porSeg,
                'note' => $note,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{aluno_id: int, matricula_id: int, nm_turma: string, nm_curso: string}>
     */
    private static function fetchNeeMatriculasComTurmaCurso(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $turma = IeducarSchema::resolveTable('turma', $city);
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $mId = (string) config('ieducar.columns.matricula.id');
        $aId = (string) config('ieducar.columns.aluno.id');
        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        $tCurso = $tc['curso'];
        $tName = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.name'),
            'nm_turma',
        ]), $city) ?? 'nm_turma';

        $cursoT = IeducarSchema::resolveTable('curso', $city);
        $cName = IeducarColumnInspector::firstExistingColumn($db, $cursoT, array_filter([
            (string) config('ieducar.columns.curso.name'),
            'nm_curso',
        ]), $city) ?? 'nm_curso';
        $cId = (string) config('ieducar.columns.curso.id');

        $q = $db->table($mat.' as m')
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);

        self::applyRecorteMatriculasNeeWhere($q, $db, $city, $filters);

        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
        self::applyInclusionScope($q, $db, $city, $filters);

        $q->leftJoin($cursoT.' as c', 't_filter.'.$tCurso, '=', 'c.'.$cId)
            ->selectRaw('a.'.$aId.' as aluno_id')
            ->selectRaw('m.'.$mId.' as matricula_id')
            ->selectRaw('t_filter.'.$tName.' as nm_turma')
            ->selectRaw('c.'.$cName.' as nm_curso');

        $rows = $q->get();
        $out = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $out[] = [
                'aluno_id' => (int) ($arr['aluno_id'] ?? 0),
                'matricula_id' => (int) ($arr['matricula_id'] ?? 0),
                'nm_turma' => (string) ($arr['nm_turma'] ?? ''),
                'nm_curso' => (string) ($arr['nm_curso'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Rótulo de segmento para cruzamento AEE e exportação: agrupa EJA/infantil/fundamental/médio;
     * caso contrário usa o nome do curso (cadastro.curso) ou, se vazio, o da turma.
     */
    public static function segmentLabelFromCursoTurma(string $curso, string $turma = ''): string
    {
        $curso = trim($curso);
        $turma = trim($turma);
        $haystack = $curso !== '' ? $curso.' '.$turma : $turma;

        if ($haystack !== '') {
            if (self::matchKeywords($haystack, 'eja_keywords')) {
                return __('EJA / Educação de jovens e adultos');
            }
            if (self::matchKeywords($haystack, 'infantil_keywords')) {
                return __('Educação infantil');
            }
            if (preg_match('/fundamental|ensino fundamental|anos iniciais|anos finais/i', $haystack)) {
                return __('Ensino fundamental (regular)');
            }
            if (preg_match('/m[eé]dio|ensino m[eé]dio/i', $haystack)) {
                return __('Ensino médio');
            }
        }

        if ($curso !== '') {
            return $curso;
        }

        if ($turma !== '') {
            return $turma;
        }

        return __('Sem curso informado');
    }

    private static function matchKeywords(string $haystack, string $configKey): bool
    {
        $words = config('ieducar.inclusion.'.$configKey);
        if (! is_array($words) || $words === []) {
            return false;
        }
        $h = mb_strtolower($haystack);

        foreach ($words as $w) {
            $w = trim((string) $w);
            if ($w !== '' && str_contains($h, mb_strtolower($w))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function chartTresGruposDeficienciaSindromeNe(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $denominator = null
    ): ?array {
        try {
            $den = $denominator ?? MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);
            if ($den === null || $den <= 0) {
                return null;
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');

            $defTable = self::resolveDeficienciaCatalogTable($db, $city);
            if ($defTable === null) {
                return null;
            }

            $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                (string) config('ieducar.columns.deficiencia.id'),
                'cod_deficiencia',
            ]), $city);
            $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                (string) config('ieducar.columns.deficiencia.name'),
                'nm_deficiencia',
            ]), $city);
            if ($defPk === null || $nmCol === null) {
                return null;
            }

            $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
            $aIdpes = self::resolveAlunoIdpesColumn($db, $aluno, $city);

            $base = static function () use ($db, $mat, $aluno, $mAluno, $mAtivo, $aId, $city, $filters): Builder {
                $q = $db->table($mat.' as m')
                    ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
                MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
                MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
                self::applyInclusionScope($q, $db, $city, $filters);

                return $q;
            };

            $countDistinct = static function (Builder $q, string $mIdCol): int {
                try {
                    $row = $q->selectRaw('COUNT(DISTINCT m.'.$mIdCol.') as c')->first();

                    return (int) ($row->c ?? 0);
                } catch (QueryException) {
                    return 0;
                }
            };

            $sinExpr = self::keywordSqlOr('d.'.$nmCol, self::sindromeKeywords());
            $ahExpr = self::keywordSqlOr('d.'.$nmCol, self::altasHabilidadesKeywords());
            $defExpr = '(NOT ('.$sinExpr.')) AND (NOT ('.$ahExpr.'))';

            if ($fisica !== null && $aIdpes !== null) {
                $joinNe = static function (Builder $q) use ($fisica, $defTable, $defPk, $aIdpes): Builder {
                    return $q
                        ->join($fisica['table'].' as fd', 'a.'.$aIdpes, '=', 'fd.'.$fisica['idpes_col'])
                        ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk);
                };

                $nSin = $countDistinct(
                    $joinNe($base())->whereRaw($sinExpr),
                    $mId
                );
                $nAh = $countDistinct(
                    $joinNe($base())->whereRaw($ahExpr),
                    $mId
                );
                $nDef = $countDistinct(
                    $joinNe($base())->whereRaw($defExpr),
                    $mId
                );

                $sub = __(
                    'Contagem de matrículas activas distintas com vínculo em cadastro.fisica_deficiência (pessoa) e cadastro.deficiencia, alinhada ao critério BIS; grupos por palavras-chave no nome da deficiência. Denominador do filtro: :n matrículas.',
                    ['n' => $den]
                );
            } else {
                $adTable = self::resolveAlunoDeficienciaTable($db, $city);
                if ($adTable === null) {
                    return null;
                }
                $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
                    (string) config('ieducar.columns.aluno_deficiencia.aluno'),
                    'ref_cod_aluno',
                ]), $city);
                $adDef = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
                    (string) config('ieducar.columns.aluno_deficiencia.deficiencia'),
                    'ref_cod_deficiencia',
                ]), $city);
                if ($adAluno === null || $adDef === null) {
                    return null;
                }

                $joinNe = static function (Builder $q) use ($adTable, $defTable, $defPk, $adAluno, $adDef, $aId): Builder {
                    return $q
                        ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                        ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk);
                };

                $nSin = $countDistinct(
                    $joinNe($base())->whereRaw($sinExpr),
                    $mId
                );
                $nAh = $countDistinct(
                    $joinNe($base())->whereRaw($ahExpr),
                    $mId
                );
                $nDef = $countDistinct(
                    $joinNe($base())->whereRaw($defExpr),
                    $mId
                );

                $sub = __(
                    'Contagem de matrículas activas distintas com pelo menos um registro em aluno_deficiência cuja designação no catálogo se enquadra em cada grupo (palavras-chave para síndromes/TEA e para altas habilidades). O mesmo aluno pode contar em mais do que um grupo se existirem vários vínculos. Denominador geral do filtro: :n matrículas.',
                    ['n' => $den]
                );
            }

            $chart = ChartPayload::bar(
                __('Matrículas por grupo: deficiências, síndromes/TEA e NE (altas habilidades)'),
                __('Matrículas (distintas)'),
                [
                    __('Deficiências (cadastro)'),
                    __('Síndromes e TEA'),
                    __('NE — altas habilidades / superdotação'),
                ],
                [(float) $nDef, (float) $nSin, (float) $nAh]
            );
            $chart['subtitle'] = $sub;

            return self::attachMatriculaKpiTotal($chart, $den, true);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    private static function withChartId(array $chart, string $chartId): array
    {
        $chart['chart_id'] = $chartId;

        return $chart;
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function chartMatriculasPorNomeDeficiencia(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $denominator = null
    ): ?array {
        try {
            $col = self::getMatriculasPorDeficiencia($db, $city, $filters);
            if ($col->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($col as $row) {
                $nm = trim((string) ($row->deficiencia ?? ''));
                $labels[] = $nm !== '' ? $nm : __('Não informado');
                $values[] = (float) ($row->total ?? 0);
            }

            [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 14, __('Outras deficiências / NE'));

            $alunoT = IeducarSchema::resolveTable('aluno', $city);
            $usesFisica = self::resolveFisicaDeficienciaJoinSpec($db, $city) !== null
                && self::resolveDeficienciaCatalogTable($db, $city) !== null
                && self::resolveAlunoIdpesColumn($db, $alunoT, $city) !== null;

            $chart = ChartPayload::barHorizontal(
                __('Matrículas por tipo (cadastro deficiência — NE, síndromes, deficiências)'),
                __('Matrículas distintas'),
                $labels,
                $values
            );
            $chart['subtitle'] = $usesFisica
                ? __(
                    'Contagem DISTINCT de matrículas activas por tipo em cadastro.deficiencia, com vínculo em cadastro.fisica_deficiência (ref_idpes), como no BIS — respeitando filtros de turma.'
                )
                : __(
                    'Cada barra representa uma designação no catálogo cadastro.deficiencia ligada a aluno_deficiência; a mesma matrícula pode aparecer em mais do que uma barra se o aluno tiver vários registros.'
                );

            $den = $denominator ?? MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);

            return self::attachMatriculaKpiTotal($chart, $den, true);
        } catch (QueryException|\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    private static function attachMatriculaKpiTotal(
        array $chart,
        ?int $denominator,
        bool $multiVinculoHint = false,
        ?string $secondaryLabel = null
    ): array {
        if ($denominator === null || $denominator < 0) {
            return $chart;
        }

        $hint = $multiVinculoHint
            ? __('Uma matrícula pode contar em várias categorias; a soma das barras pode exceder o total de matrículas no filtro.')
            : null;

        $chart = ChartPayload::withKpiStudentTotal(
            $chart,
            $denominator,
            __('Total de matrículas no filtro (denominador)'),
            $hint
        );

        if ($secondaryLabel !== null) {
            $chart['kpi_total_secondary'] = ChartPayload::sumFirstDataset($chart);
            $chart['kpi_total_secondary_label'] = $secondaryLabel;
        }

        return $chart;
    }

    /**
     * @param  list<string>  $words
     */
    private static function keywordSqlOr(string $col, array $words): string
    {
        $checks = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') {
                continue;
            }
            $esc = str_replace("'", "''", $w);
            $checks[] = 'LOWER('.$col.') LIKE \'%'.$esc.'%\'';
        }

        return $checks !== [] ? '('.implode(' OR ', $checks).')' : 'FALSE';
    }

    /**
     * @return list<string>
     */
    private static function sindromeKeywords(): array
    {
        return [
            'síndrome', 'sindrome', 'syndrome', 'tea', 'autis', 'asperger', 'down',
            'espectro autista', 'transtorno do espectro',
            'turner', 'fragil', 'x frag', 'rett', 'prader', 'willi', 'angelman',
        ];
    }

    /**
     * @return list<string>
     */
    private static function altasHabilidadesKeywords(): array
    {
        return [
            'superdota', 'super dot', 'alta habilidade', 'altas habilidades', 'gifted', 'talento',
            'precoce', 'habilidade intelectual', 'ah sd', 'superdotacao',
        ];
    }

    private static function resolveAlunoDeficienciaTable(Connection $db, City $city): ?string
    {
        foreach (self::alunoDeficienciaCandidates($city) as $t) {
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, [
            'aluno_deficiencia',
            'aluno_deficiencias',
        ], $city);
    }

    private static function resolveDeficienciaCatalogTable(Connection $db, City $city): ?string
    {
        foreach (self::deficienciaCatalogCandidates($city) as $t) {
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return IeducarColumnInspector::findQualifiedTableByNames($db, [
            'deficiencia',
            'deficiencias',
        ], $city);
    }

    /**
     * Coluna de rótulo em cadastro.deficiencia (alinhada a InclusionEducacensoCatalog::loadDeficienciaCatalogRows).
     */
    private static function resolveDeficienciaNameColumn(Connection $db, string $defTable, City $city): ?string
    {
        return IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.name'),
            'nm_deficiencia',
            'nome',
            'descricao',
        ]), $city);
    }

    /**
     * Expressão SQL para o rótulo da deficiência (quando não há coluna de nome, usa o código).
     */
    private static function deficienciaLabelSelectExpression(
        \Illuminate\Database\Query\Grammars\Grammar $grammar,
        string $defPk,
        ?string $nmCol,
    ): string {
        $wPk = 'd.'.$grammar->wrap($defPk);
        if ($nmCol !== null) {
            $wNm = 'd.'.$grammar->wrap($nmCol);

            return 'MAX(COALESCE('.$wNm.', \'Não informado\'))';
        }

        return 'MAX(CONCAT(\'Designação #\', CAST('.$wPk.' AS TEXT)))';
    }

    /**
     * @return list<string>
     */
    private static function alunoDeficienciaCandidates(City $city): array
    {
        $primary = IeducarSchema::resolveTable('aluno_deficiencia', $city);

        return array_values(array_unique(array_filter([
            $primary,
            'pmieducar.aluno_deficiencia',
            'public.aluno_deficiencia',
            trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.aluno_deficiencia',
        ])));
    }

    /**
     * @return list<string>
     */
    private static function deficienciaCatalogCandidates(City $city): array
    {
        $primary = IeducarSchema::resolveTable('deficiencia', $city);

        return array_values(array_unique(array_filter([
            $primary,
            trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.deficiencia',
            'public.deficiencia',
        ])));
    }

    /**
     * Tabela cadastro.fisica_deficiencia (ou nome em IEDUCAR_TABLE_FISICA_DEFICIENCIA).
     */
    private static function resolveFisicaDeficienciaTable(Connection $db, City $city): ?string
    {
        $fromEnv = trim((string) config('ieducar.tables.fisica_deficiencia', ''));
        $candidates = array_values(array_unique(array_filter([
            $fromEnv !== '' ? $fromEnv : null,
            trim((string) config('ieducar.pgsql_schema_cadastro', 'cadastro')).'.fisica_deficiencia',
            'cadastro.fisica_deficiencia',
            'pmieducar.fisica_deficiencia',
            'public.fisica_deficiencia',
            'fisica_deficiencia',
        ])));

        foreach ($candidates as $t) {
            if (IeducarColumnInspector::tableExists($db, $t, $city)) {
                return $t;
            }
        }

        return null;
    }

    /**
     * @return ?array{table: string, idpes_col: string, def_fk: string}
     */
    private static function resolveFisicaDeficienciaJoinSpec(Connection $db, City $city): ?array
    {
        $fdTable = self::resolveFisicaDeficienciaTable($db, $city);
        if ($fdTable === null) {
            return null;
        }

        $idpesCol = IeducarColumnInspector::firstExistingColumn($db, $fdTable, [
            'ref_idpes', 'idpes',
        ], $city);
        $defFk = IeducarColumnInspector::firstExistingColumn($db, $fdTable, [
            'ref_cod_deficiencia', 'cod_deficiencia', 'ref_deficiencia', 'deficiencia_id',
        ], $city);
        if ($idpesCol === null || $defFk === null) {
            return null;
        }

        return [
            'table' => $fdTable,
            'idpes_col' => $idpesCol,
            'def_fk' => $defFk,
        ];
    }

    private static function resolveAlunoIdpesColumn(Connection $db, string $alunoTable, City $city): ?string
    {
        return IeducarColumnInspector::firstExistingColumn($db, $alunoTable, array_filter([
            'ref_idpes',
            'idpes',
        ]), $city);
    }

    /**
     * Caminho BIS: matricula → aluno (ref_idpes) → fisica_deficiencia → deficiencia.
     *
     * @param  array{table: string, idpes_col: string, def_fk: string}  $fisica
     * @return list<object|array<string, mixed>>
     */
    /**
     * Quando fisica_deficiencia e aluno_deficiencia devolvem 0 linhas mas há cadastro NEE,
     * repete a consulta com o mesmo recorte de {@see countMatriculasNeeComCadastroDeficiencia()}.
     *
     * @return list<object|array<string, mixed>>
     */
    private static function queryMatriculasPorDeficienciaNeeCadastroFallback(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $limit,
    ): array {
        $cadastroSub = self::alunosComCadastroNeeSubquery($db, $city);
        if ($cadastroSub === null) {
            return [];
        }

        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        if ($defTable === null) {
            return [];
        }

        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        $alunoT = IeducarSchema::resolveTable('aluno', $city);
        $aIdpes = self::resolveAlunoIdpesColumn($db, $alunoT, $city);

        if ($fisica !== null && $aIdpes !== null) {
            try {
                $rows = self::queryMatriculasPorDeficienciaFisicaPath($db, $city, $filters, $fisica, $defTable, $limit);
                if ((int) array_sum(array_map(static fn ($r) => (int) ($r->total ?? 0), $rows)) > 0) {
                    return $rows;
                }
            } catch (\Throwable) {
                // tenta aluno_deficiencia
            }
        }

        $rows = self::queryMatriculasPorDeficienciaAlunoDefPath($db, $city, $filters, $limit);
        if ((int) array_sum(array_map(static fn ($r) => (int) ($r->total ?? 0), $rows)) > 0) {
            return $rows;
        }

        return [];
    }

    /**
     * Caminho BIS: matricula → aluno (ref_idpes) → fisica_deficiencia → deficiencia.
     *
     * @param  array{table: string, idpes_col: string, def_fk: string}  $fisica
     * @return list<object|array<string, mixed>>
     */
    private static function queryMatriculasPorDeficienciaFisicaPath(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        array $fisica,
        string $defTable,
        ?int $limit = 22,
    ): array {
        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $mId = (string) config('ieducar.columns.matricula.id');
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $aId = (string) config('ieducar.columns.aluno.id');
        $aIdpes = self::resolveAlunoIdpesColumn($db, $aluno, $city);
        if ($aIdpes === null) {
            return [];
        }

        $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.id'),
            'cod_deficiencia',
        ]), $city);
        $nmCol = self::resolveDeficienciaNameColumn($db, $defTable, $city);
        if ($defPk === null) {
            return [];
        }

        $g = $db->getQueryGrammar();
        $wPk = $g->wrap($defPk);
        $defLabelExpr = self::deficienciaLabelSelectExpression($g, $defPk, $nmCol);

        $q = $db->table($mat.' as m')
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        self::applyMatriculaTurmaScopeForInclusionCharts($q, $db, $city, $filters);
        self::applyInclusionScope($q, $db, $city, $filters);
        self::applyRecorteMatriculasNeeWhere($q, $db, $city, $filters);
        $q->join($fisica['table'].' as fd', 'a.'.$aIdpes, '=', 'fd.'.$fisica['idpes_col'])
            ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk);

        $q->selectRaw('d.'.$wPk.' as def_id')
            ->selectRaw($defLabelExpr.' as deficiencia')
            ->selectRaw('COUNT(DISTINCT m.'.$g->wrap($mId).') as total')
            ->groupBy('d.'.$wPk)
            ->orderByDesc('total');
        if ($limit !== null) {
            $q->limit($limit);
        }

        return $q->get()->all();
    }

    /**
     * Fallback: pivô aluno_deficiência (pmieducar) quando fisica_deficiência não existe ou falha.
     *
     * @return list<object|array<string, mixed>>
     */
    private static function queryMatriculasPorDeficienciaAlunoDefPath(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        ?int $limit = 22,
    ): array {
        $adTable = self::resolveAlunoDeficienciaTable($db, $city);
        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        if ($adTable === null || $defTable === null) {
            return [];
        }

        $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
            (string) config('ieducar.columns.aluno_deficiencia.aluno'),
            'ref_cod_aluno',
        ]), $city);
        $adDef = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
            (string) config('ieducar.columns.aluno_deficiencia.deficiencia'),
            'ref_cod_deficiencia',
        ]), $city);
        $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.id'),
            'cod_deficiencia',
        ]), $city);
        $nmCol = self::resolveDeficienciaNameColumn($db, $defTable, $city);
        if ($adAluno === null || $adDef === null || $defPk === null) {
            return [];
        }

        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $mId = (string) config('ieducar.columns.matricula.id');
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $aId = (string) config('ieducar.columns.aluno.id');

        $g = $db->getQueryGrammar();
        $defLabelExpr = self::deficienciaLabelSelectExpression($g, $defPk, $nmCol);
        $q = $db->table($mat.' as m')
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        self::applyMatriculaTurmaScopeForInclusionCharts($q, $db, $city, $filters);
        self::applyInclusionScope($q, $db, $city, $filters);
        self::applyRecorteMatriculasNeeWhere($q, $db, $city, $filters);
        $q->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
            ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk);

        $q->selectRaw('d.'.$g->wrap($defPk).' as def_id')
            ->selectRaw($defLabelExpr.' as deficiencia')
            ->selectRaw('COUNT(DISTINCT m.'.$g->wrap($mId).') as total')
            ->groupBy('d.'.$g->wrap($defPk))
            ->orderByDesc('total');
        if ($limit !== null) {
            $q->limit($limit);
        }

        return $q->get()->all();
    }

    /**
     * Enturmação + filtros de turma alinhados a {@see countMatriculasComCadastroNee()} e ao total NEE.
     */
    private static function applyMatriculaTurmaScopeForInclusionCharts(
        Builder $q,
        Connection $db,
        City $city,
        IeducarFilterState $filters,
    ): void {
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');
    }

    private static function applyInclusionScope(
        Builder $q,
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $alunoAlias = 'a',
    ): void {
        if (InclusionMatriculaScope::isActive($filters)) {
            InclusionMatriculaScope::apply($q, $db, $city, $filters, $alunoAlias);
        }
    }

    public static function applyInclusionScopeForExport(
        Builder $q,
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        string $alunoAlias = 'a',
    ): void {
        self::applyInclusionScope($q, $db, $city, $filters, $alunoAlias);
    }

    public static function segmentLabelForExport(string $turma, string $curso = ''): string
    {
        return self::segmentLabelFromCursoTurma($curso, $turma);
    }

    /**
     * @param  list<int>  $alunoIds
     * @return array<int, array{
     *   labels: list<string>,
     *   grupos: list<string>,
     *   grupo_keys: list<string>,
     *   designacoes: list<array{nome: string, def_id: string, norm: string}>
     * }>
     */
    public static function deficienciasPorAlunoIdsForExport(Connection $db, City $city, array $alunoIds): array
    {
        if ($alunoIds === []) {
            return [];
        }

        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        if ($defTable === null) {
            return [];
        }

        $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.id'),
            'cod_deficiencia',
        ]), $city);
        $nmCol = self::resolveDeficienciaNameColumn($db, $defTable, $city);
        if ($defPk === null) {
            return [];
        }

        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $aId = (string) config('ieducar.columns.aluno.id');
        $map = [];
        $g = $db->getQueryGrammar();
        $wPk = $g->wrap($defPk);
        $defLabelExpr = $nmCol !== null
            ? 'd.'.$g->wrap($nmCol)
            : 'CONCAT(\'Designação #\', CAST(d.'.$wPk.' AS TEXT))';

        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        $aIdpes = self::resolveAlunoIdpesColumn($db, $aluno, $city);

        if ($fisica !== null && $aIdpes !== null) {
            $rows = $db->table($aluno.' as a')
                ->join($fisica['table'].' as fd', 'a.'.$aIdpes, '=', 'fd.'.$fisica['idpes_col'])
                ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk)
                ->whereIn('a.'.$aId, $alunoIds)
                ->selectRaw('a.'.$aId.' as aid')
                ->selectRaw('d.'.$wPk.' as def_id')
                ->selectRaw($defLabelExpr.' as deficiencia')
                ->distinct()
                ->get();
            self::mergeDeficienciaExportRows($map, $rows);
        }

        $adTable = self::resolveAlunoDeficienciaTable($db, $city);
        if ($adTable !== null) {
            $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, [
                (string) config('ieducar.columns.aluno_deficiencia.aluno'),
                'ref_cod_aluno',
            ], $city);
            $adDef = IeducarColumnInspector::firstExistingColumn($db, $adTable, [
                (string) config('ieducar.columns.aluno_deficiencia.deficiencia'),
                'ref_cod_deficiencia',
            ], $city);
            if ($adAluno !== null && $adDef !== null) {
                $rows = $db->table($aluno.' as a')
                    ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                    ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                    ->whereIn('a.'.$aId, $alunoIds)
                    ->selectRaw('a.'.$aId.' as aid')
                    ->selectRaw('d.'.$wPk.' as def_id')
                    ->selectRaw($defLabelExpr.' as deficiencia')
                    ->distinct()
                    ->get();
                self::mergeDeficienciaExportRows($map, $rows);
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{labels: list<string>, grupos: list<string>, grupo_keys: list<string>, designacoes: list<array{nome: string, def_id: string, norm: string}>}>  $map
     * @param  iterable<object>  $rows
     */
    private static function mergeDeficienciaExportRows(array &$map, iterable $rows): void
    {
        foreach ($rows as $row) {
            $aid = (int) ($row->aid ?? 0);
            $nome = trim((string) ($row->deficiencia ?? ''));
            $defId = trim((string) ($row->def_id ?? ''));
            if ($aid <= 0 || $nome === '') {
                continue;
            }

            $map[$aid] ??= ['labels' => [], 'grupos' => [], 'grupo_keys' => [], 'designacoes' => []];
            $dedupeKey = $defId !== '' ? 'id:'.$defId : 'nome:'.InclusionEducacensoCatalog::normalizeLabel($nome);
            if (! isset($map[$aid]['_dedupe'][$dedupeKey])) {
                $map[$aid]['_dedupe'][$dedupeKey] = true;
                $norm = InclusionEducacensoCatalog::resolveCatalogNorm($nome);
                $map[$aid]['designacoes'][] = [
                    'nome' => $nome,
                    'def_id' => $defId,
                    'norm' => $norm,
                ];
            }

            if (! in_array($nome, $map[$aid]['labels'], true)) {
                $map[$aid]['labels'][] = $nome;
                $grupoKey = self::classificarDesignacaoNeeGrupo($nome);
                $labelGrupo = self::grupoNeeLabel($grupoKey);
                if (! in_array($labelGrupo, $map[$aid]['grupos'], true)) {
                    $map[$aid]['grupos'][] = $labelGrupo;
                }
                if (! in_array($grupoKey, $map[$aid]['grupo_keys'], true)) {
                    $map[$aid]['grupo_keys'][] = $grupoKey;
                }
            }
        }

        foreach ($map as $aid => $payload) {
            unset($map[$aid]['_dedupe']);
        }
    }

    /**
     * Filtro SQL: turma ou curso com palavras-chave AEE (config/ieducar.php → inclusion.aee_keywords).
     */
    private static function applyTurmaAeeRawWhere(Builder $q, Connection $db, City $city, string $turmaAlias = 't_filter'): void
    {
        $turma = IeducarSchema::resolveTable('turma', $city);
        $cursoT = IeducarSchema::resolveTable('curso', $city);
        $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
        $tName = IeducarColumnInspector::firstExistingColumn($db, $turma, array_filter([
            (string) config('ieducar.columns.turma.name'),
            'nm_turma',
        ]), $city) ?? 'nm_turma';
        $cName = IeducarColumnInspector::firstExistingColumn($db, $cursoT, array_filter([
            (string) config('ieducar.columns.curso.name'),
            'nm_curso',
        ]), $city) ?? 'nm_curso';
        $tCurso = $tc['curso'];
        $cId = (string) config('ieducar.columns.curso.id');

        $q->leftJoin($cursoT.' as c_aee', $turmaAlias.'.'.$tCurso, '=', 'c_aee.'.$cId);

        $checks = [];
        foreach (config('ieducar.inclusion.aee_keywords', []) as $w) {
            $w = trim((string) $w);
            if ($w === '') {
                continue;
            }
            $esc = str_replace("'", "''", $w);
            $checks[] = 'LOWER(CONCAT(COALESCE('.$turmaAlias.'.'.$tName.", ''), ' ', COALESCE(c_aee.".$cName.", ''))) LIKE '%".mb_strtolower($esc)."%'";
        }
        if ($checks !== []) {
            $q->whereRaw('('.implode(' OR ', $checks).')');
        }
    }
}
