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
     * @return Collection<int, object{deficiencia: string, total: int}>
     */
    public static function getMatriculasPorDeficiencia(Connection $db, City $city, IeducarFilterState $filters): Collection
    {
        $defTable = self::resolveDeficienciaCatalogTable($db, $city);
        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        $alunoT = IeducarSchema::resolveTable('aluno', $city);
        $aIdpes = self::resolveAlunoIdpesColumn($db, $alunoT, $city);

        if ($fisica !== null && $defTable !== null && $aIdpes !== null) {
            try {
                $rows = self::queryMatriculasPorDeficienciaFisicaPath($db, $city, $filters, $fisica, $defTable);
                if ($rows !== []) {
                    return collect($rows)->map(fn ($r) => (object) [
                        'deficiencia' => (string) ($r->deficiencia ?? ''),
                        'total' => (int) ($r->total ?? 0),
                    ]);
                }
            } catch (\Throwable) {
                // fallback aluno_deficiência
            }
        }

        $rows = self::queryMatriculasPorDeficienciaAlunoDefPath($db, $city, $filters);

        return collect($rows)->map(fn ($r) => (object) [
            'deficiencia' => (string) ($r->deficiencia ?? ''),
            'total' => (int) ($r->total ?? 0),
        ]);
    }

    /**
     * @return list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>, options?: array<string, mixed>, subtitle?: string, footnote?: string}>
     */
    public static function buildCharts(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $out = [];
        $g3 = self::chartTresGruposDeficienciaSindromeNe($db, $city, $filters);
        if ($g3 !== null) {
            $out[] = $g3;
        }
        $det = self::chartMatriculasPorNomeDeficiencia($db, $city, $filters);
        if ($det !== null) {
            $out[] = $det;
        }

        return $out;
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
                    'Nenhuma turma foi classificada como AEE pelas palavras-chave configuradas (IEDUCAR_INCLUSION_AEE_KEYWORDS). Ajuste o .env ou o nome das turmas/cursos na base para incluir termos como «AEE» ou «atendimento educacional especializado».'
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

        $fisica = self::resolveFisicaDeficienciaJoinSpec($db, $city);
        $aIdpes = self::resolveAlunoIdpesColumn($db, $aluno, $city);
        if ($fisica !== null && $aIdpes !== null) {
            $q->whereExists(function ($sub) use ($fisica, $aIdpes) {
                $sub->from($fisica['table'].' as fd_ne')
                    ->whereColumn('fd_ne.'.$fisica['idpes_col'], 'a.'.$aIdpes);
            });
        } else {
            $adTable = self::resolveAlunoDeficienciaTable($db, $city);
            if ($adTable === null) {
                return [];
            }
            $adAluno = IeducarColumnInspector::firstExistingColumn($db, $adTable, array_filter([
                (string) config('ieducar.columns.aluno_deficiencia.aluno'),
                'ref_cod_aluno',
                'cod_aluno',
            ]), $city);
            if ($adAluno === null) {
                return [];
            }
            $q->whereIn('a.'.$aId, function ($sub) use ($adTable, $adAluno) {
                $sub->from($adTable)->select($adAluno)->distinct();
            });
        }

        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
        MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
        MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

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
    private static function chartTresGruposDeficienciaSindromeNe(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $den = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters);
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
                    'Contagem de matrículas activas distintas com pelo menos um registo em aluno_deficiência cuja designação no catálogo se enquadra em cada grupo (palavras-chave para síndromes/TEA e para altas habilidades). O mesmo aluno pode contar em mais do que um grupo se existirem vários vínculos. Denominador geral do filtro: :n matrículas.',
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

            return $chart;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function chartMatriculasPorNomeDeficiencia(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
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
                    'Cada barra representa uma designação no catálogo cadastro.deficiencia ligada a aluno_deficiência; a mesma matrícula pode aparecer em mais do que uma barra se o aluno tiver vários registos.'
                );

            return $chart;
        } catch (QueryException|\Throwable) {
            return null;
        }
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
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
            ->join($fisica['table'].' as fd', 'a.'.$aIdpes, '=', 'fd.'.$fisica['idpes_col'])
            ->join($defTable.' as d', 'fd.'.$fisica['def_fk'], '=', 'd.'.$defPk);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);

        $q->selectRaw('MAX(COALESCE(d.'.$wNm.', \'Não informado\')) as deficiencia')
            ->selectRaw('COUNT(DISTINCT m.'.$g->wrap($mId).') as total')
            ->groupBy('d.'.$wPk)
            ->orderByDesc('total')
            ->limit(22);

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
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
            ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
            ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk);
        MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
        MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);

        $q->selectRaw('MAX(d.'.$g->wrap($nmCol).') as deficiencia')
            ->selectRaw('COUNT(DISTINCT m.'.$g->wrap($mId).') as total')
            ->groupBy('d.'.$g->wrap($defPk))
            ->orderByDesc('total')
            ->limit(22);

        return $q->get()->all();
    }
}
