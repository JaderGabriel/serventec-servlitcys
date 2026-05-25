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
        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        $alunoT = IeducarSchema::resolveTable('aluno', $city);
        $aIdpes = self::resolveAlunoIdpesColumn($db, $alunoT, $city);

        if ($fisica !== null && $defTable !== null && $aIdpes !== null) {
            try {
                $rows = self::queryMatriculasPorDeficienciaFisicaPath($db, $city, $filters, $fisica, $defTable, $limit);
                if ($rows !== []) {
                    return collect($rows)->map(fn ($r) => (object) [
                        'def_id' => (string) ($r->def_id ?? ''),
                        'deficiencia' => (string) ($r->deficiencia ?? ''),
                        'total' => (int) ($r->total ?? 0),
                    ]);
                }
            } catch (\Throwable) {
                // fallback aluno_deficiência
            }
        }

        $rows = self::queryMatriculasPorDeficienciaAlunoDefPath($db, $city, $filters, $limit);

        return collect($rows)->map(fn ($r) => (object) [
            'def_id' => (string) ($r->def_id ?? ''),
            'deficiencia' => (string) ($r->deficiencia ?? ''),
            'total' => (int) ($r->total ?? 0),
        ]);
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
     */
    public static function countMatriculasComNee(Connection $db, City $city, IeducarFilterState $filters): int
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');

            $cadastroSub = self::alunosComCadastroNeeSubquery($db, $city);
            $includeAee = self::incluirTurmaAeeNoRecorteNee();

            if ($cadastroSub === null && ! $includeAee) {
                return 0;
            }

            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
            self::applyInclusionScope($q, $db, $city, $filters);

            $q->where(function (Builder $w) use ($db, $city, $filters, $mat, $mId, $mAluno, $mAtivo, $aId, $cadastroSub, $includeAee): void {
                if ($cadastroSub !== null) {
                    $w->whereIn('a.'.$aId, $cadastroSub);
                }
                if ($includeAee) {
                    $clause = $cadastroSub !== null ? 'orWhereExists' : 'whereExists';
                    $w->{$clause}(function ($ex) use ($db, $city, $filters, $mat, $aluno, $mId, $mAluno, $mAtivo, $aId): void {
                        $ex->from($mat.' as m_aee')
                            ->join($aluno.' as a_aee', 'm_aee.'.$mAluno, '=', 'a_aee.'.$aId)
                            ->whereColumn('m_aee.'.$mId, 'm.'.$mId);
                        MatriculaAtivoFilter::apply($ex, $db, 'm_aee.'.$mAtivo, $city);
                        MatriculaTurmaJoin::joinMatriculaToTurma($ex, $db, $city, 'm_aee');
                        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($ex, $db, $city);
                        MatriculaTurmaJoin::applyTurmaFiltersWhere($ex, $db, $city, $filters, 't_filter');
                        self::applyTurmaAeeRawWhere($ex, $db, $city);
                    });
                }
            });

            $row = $q->selectRaw('COUNT(DISTINCT m.'.$db->getQueryGrammar()->wrap($mId).') as c')->first();

            return (int) ($row->c ?? 0);
        } catch (\Throwable) {
            return 0;
        }
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
            $pct = static fn (int $n) => round(100.0 * $n / $den, 1);

            $pathNote = self::inclusionNeeUsesFisicaPath($db, $city)
                ? __('cadastro.fisica_deficiencia + deficiência')
                : __('aluno_deficiencia + deficiência');

            return [
                [
                    'title' => __('Deficiências'),
                    'percent' => $pct($nDef),
                    'caption' => __(':n de :d matrículas com registro NEE classificado como deficiência (:path).', [
                        'n' => $nDef,
                        'd' => $den,
                        'path' => $pathNote,
                    ]),
                ],
                [
                    'title' => __('Síndromes e TEA'),
                    'percent' => $pct($nSin),
                    'caption' => __(':n de :d matrículas (palavras-chave no nome da deficiência, :path).', [
                        'n' => $nSin,
                        'd' => $den,
                        'path' => $pathNote,
                    ]),
                ],
                [
                    'title' => __('Altas habilidades / superdotação'),
                    'percent' => $pct($nAh),
                    'caption' => __(':n de :d matrículas (palavras-chave no nome da deficiência, :path).', [
                        'n' => $nAh,
                        'd' => $den,
                        'path' => $pathNote,
                    ]),
                ],
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Referência FUNDEB (VAAF) aplicada às matrículas NEE do filtro — base e adicional indicativo de educação especial.
     *
     * @return array<string, mixed>
     */
    public static function buildFundebNeeIndicativo(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $nee = self::countMatriculasComNee($db, $city, $filters);
        if ($nee <= 0) {
            return ['available' => false];
        }

        $calc = FundebMunicipalReferenceResolver::vaafParaCalculo($city, $filters);
        $vaaf = (float) ($calc['vaaf'] ?? 0);
        if ($vaaf <= 0) {
            return ['available' => false, 'matriculas_nee' => $nee];
        }

        $pesoEsp = max(1.0, (float) config('ieducar.inclusion.fundeb_peso_educacao_especial', 1.2));
        $baseAnual = round($nee * $vaaf, 2);
        $adicionalAnual = $pesoEsp > 1.0
            ? round($nee * $vaaf * ($pesoEsp - 1.0), 2)
            : 0.0;
        $fmt = [DiscrepanciesFundingImpact::class, 'formatBrl'];

        return [
            'available' => true,
            'matriculas_nee' => $nee,
            'vaaf' => $vaaf,
            'vaaf_fmt' => $fmt($vaaf),
            'vaaf_fonte' => (string) ($calc['fonte_label'] ?? ''),
            'vaaf_origem' => (string) ($calc['origem'] ?? ''),
            'vaa_municipal_importado' => ($calc['origem'] ?? '') === 'municipal',
            'base_anual' => $baseAnual,
            'base_anual_fmt' => $fmt($baseAnual),
            'peso_educacao_especial' => $pesoEsp,
            'adicional_anual' => $adicionalAnual,
            'adicional_anual_fmt' => $adicionalAnual > 0 ? $fmt($adicionalAnual) : null,
            'total_indicativo_anual' => round($baseAnual + $adicionalAnual, 2),
            'total_indicativo_anual_fmt' => $fmt($baseAnual + $adicionalAnual),
        ];
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
        if ($neeDataset !== null) {
            $g3 = InclusionNeeDesignacaoDataset::chartGrupo($neeDataset, $den);
            if ($g3 !== null) {
                $out[] = self::withChartId($g3, 'nee_grupo');
            }
            $catalogoAtivo = InclusionNeeDesignacaoDataset::chartCatalogo($neeDataset, $den, false);
            if ($catalogoAtivo !== null) {
                $out[] = self::withChartId($catalogoAtivo, 'nee_catalogo');
            }
        }

        // Total de matrículas NEE por unidade (barras simples) — em seguida ao resumo por grupos.
        $porEscolaTotal = self::chartNeeMatriculasPorEscolaTop($db, $city, $filters, $den);
        if ($porEscolaTotal !== null) {
            $out[] = self::withChartId($porEscolaTotal, 'nee_escola_top');
        }
        $det = self::chartMatriculasPorNomeDeficiencia($db, $city, $filters, $den);
        if ($det !== null) {
            $out[] = self::withChartId($det, 'nee_por_designacao');
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
        try {
            $rows = self::getMatriculasPorDeficiencia($db, $city, $filters, null);
            $entries = InclusionEducacensoCatalog::mergedDeficienciaEntriesForChart($db, $city);
            if ($entries === [] && $rows->isEmpty()) {
                return null;
            }

            $maps = InclusionEducacensoCatalog::deficienciaCountMapsFromRows(
                $rows,
                static fn ($row) => (string) ($row->deficiencia ?? ''),
                static fn ($row) => (int) ($row->total ?? 0),
                static fn ($row) => (string) ($row->def_id ?? ''),
            );

            $catalogRows = [];
            foreach ($entries as $entry) {
                $catalogRows[] = [
                    'label' => InclusionEducacensoCatalog::deficienciaChartLabel($entry),
                    'value' => (float) InclusionEducacensoCatalog::countForDeficienciaEntry($entry, $maps),
                    'kind' => (string) ($entry['kind'] ?? InclusionEducacensoCatalog::classifyDeficienciaKind($entry)),
                ];
            }

            usort(
                $catalogRows,
                static fn (array $a, array $b): int => $b['value'] <=> $a['value']
                    ?: strcmp((string) $a['label'], (string) $b['label'])
            );

            $series = InclusionEducacensoCatalog::neeCatalogChartSeries($catalogRows);
            if ($series['labels'] === []) {
                return null;
            }

            $den = $denominator ?? MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);

            $chart = ChartPayload::barHorizontal(
                __('NEE — catálogo completo MEC e i-Educar (todas as opções)'),
                __('Matrículas distintas'),
                $series['labels'],
                $series['values']
            );
            $chart['datasets'][0]['backgroundColor'] = $series['colors'];
            $chart['datasets'][0]['borderColor'] = $series['colors'];
            $chart['subtitle'] = __(
                'Barras com sufixo «INEP/Censo» seguem o Educacenso (campo deficiência). «Complementar» são tipos frequentes no i-Educar que devem ser mapeados para um código oficial na exportação. «Cadastro i-Educar» são designações só na base local. Valor zero significa nenhuma matrícula com aquele vínculo no ano/filtro — o resumo por grupo pode ser > 0 quando há NEE sem designação alinhada ao catálogo.'
            );
            $chart['footnote'] = __(
                'Legenda de cores: índigo = INEP/Censo · violeta = complementar (adaptável) · âmbar = só i-Educar.'
            );
            $chart['options'] = array_merge(
                is_array($chart['options'] ?? null) ? $chart['options'] : [],
                ['panelHeight' => 'xxl', 'skipHorizontalBarAutoHeight' => false]
            );

            if (array_sum($series['values']) <= 0.0) {
                $grupo = self::chartTresGruposDeficienciaSindromeNe($db, $city, $filters, $den);
                $grupoTotal = 0.0;
                if (is_array($grupo['datasets'][0]['data'] ?? null)) {
                    foreach ($grupo['datasets'][0]['data'] as $v) {
                        $grupoTotal += (float) $v;
                    }
                }
                if ($grupoTotal > 0.0) {
                    $chart['footnote'] = ($chart['footnote'] ?? '').' '
                        .__('O gráfico por grupo indica :n matrícula(s) NEE, mas nenhuma barra recebeu contagem por designação — revise vínculos em fisica_deficiencia/aluno_deficiencia e nomes em cadastro.deficiencia.', [
                            'n' => (string) (int) $grupoTotal,
                        ]);
                }
            }

            return self::attachMatriculaKpiTotal(
                $chart,
                $den,
                true,
                __('Soma das barras (pode exceder o total por vínculos múltiplos)')
            );
        } catch (\Throwable) {
            return null;
        }
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
                $t = strtolower((string) ($r['nm_turma'] ?? ''));
                $c = strtolower((string) ($r['nm_curso'] ?? ''));
                $isAee = self::matchKeywords($t.' '.$c, 'aee_keywords');
                $seg = $isAee
                    ? 'AEE'
                    : self::segmentLabel($c !== '' ? $c : $t);
                $byAluno[$aid][$mid] = ['aee' => $isAee, 'seg' => $seg];
            }

            $uniqueMid = [];
            foreach ($byAluno as $mats) {
                foreach (array_keys($mats) as $mid) {
                    $uniqueMid[$mid] = true;
                }
            }
            $neeMatriculas = count($uniqueMid);

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

            return [
                'nee_matriculas_total' => $neeMatriculas,
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

        $cadastroSub = self::alunosComCadastroNeeSubquery($db, $city);
        $includeAee = self::incluirTurmaAeeNoRecorteNee();
        if ($cadastroSub === null && ! $includeAee) {
            return [];
        }

        $q->where(function (Builder $w) use ($db, $city, $filters, $mat, $aluno, $mId, $mAluno, $mAtivo, $aId, $cadastroSub, $includeAee): void {
            if ($cadastroSub !== null) {
                $w->whereIn('a.'.$aId, $cadastroSub);
            }
            if ($includeAee) {
                $clause = $cadastroSub !== null ? 'orWhereExists' : 'whereExists';
                $w->{$clause}(function ($ex) use ($db, $city, $filters, $mat, $aluno, $mId, $mAluno, $mAtivo, $aId): void {
                    $ex->from($mat.' as m_aee')
                        ->join($aluno.' as a_aee', 'm_aee.'.$mAluno, '=', 'a_aee.'.$aId)
                        ->whereColumn('m_aee.'.$mId, 'm.'.$mId);
                    MatriculaAtivoFilter::apply($ex, $db, 'm_aee.'.$mAtivo, $city);
                    MatriculaTurmaJoin::joinMatriculaToTurma($ex, $db, $city, 'm_aee');
                    MatriculaTurmaJoin::applyPivotAtivoIfNeeded($ex, $db, $city);
                    MatriculaTurmaJoin::applyTurmaFiltersWhere($ex, $db, $city, $filters, 't_filter');
                    self::applyTurmaAeeRawWhere($ex, $db, $city);
                });
            }
        });

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

    private static function segmentLabel(string $haystack): string
    {
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

        return __('Outros segmentos / não classificado');
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
        $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
            (string) config('ieducar.columns.deficiencia.name'),
            'nm_deficiencia',
        ]), $city);
        if ($defPk === null || $nmCol === null) {
            return [];
        }

        $g = $db->getQueryGrammar();
        $wNm = $g->wrap($nmCol);
        $wPk = $g->wrap($defPk);

        $q = $db->table($mat.' as m')
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
        self::applyInclusionScope($q, $db, $city, $filters);
        $q->join($fisica['table'].' as fd', 'a.'.$aIdpes, '=', 'fd.'.$fisica['idpes_col'])
            ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk);

        $q->selectRaw('d.'.$wPk.' as def_id')
            ->selectRaw('MAX(COALESCE(d.'.$wNm.', \'Não informado\')) as deficiencia')
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

        $g = $db->getQueryGrammar();
        $q = $db->table($mat.' as m')
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);
        self::applyInclusionScope($q, $db, $city, $filters);
        $q->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
            ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk);

        $q->selectRaw('d.'.$g->wrap($defPk).' as def_id')
            ->selectRaw('MAX(d.'.$g->wrap($nmCol).') as deficiencia')
            ->selectRaw('COUNT(DISTINCT m.'.$g->wrap($mId).') as total')
            ->groupBy('d.'.$g->wrap($defPk))
            ->orderByDesc('total');
        if ($limit !== null) {
            $q->limit($limit);
        }

        return $q->get()->all();
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
