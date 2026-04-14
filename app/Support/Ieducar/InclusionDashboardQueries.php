<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Gráficos e tabelas extra da aba Inclusão: detalhe por catálogo de deficiências, três grupos (def./síndrome/NE)
 * e cruzamento AEE ↔ outros segmentos (heurística por nomes de turma/curso).
 */
final class InclusionDashboardQueries
{
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

        $mat = IeducarSchema::resolveTable('matricula', $city);
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $turma = IeducarSchema::resolveTable('turma', $city);
        $mAluno = (string) config('ieducar.columns.matricula.aluno');
        $mAtivo = (string) config('ieducar.columns.matricula.ativo');
        $mId = (string) config('ieducar.columns.matricula.id');
        $aId = (string) config('ieducar.columns.aluno.id');
        $tId = (string) config('ieducar.columns.turma.id');
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
            ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
            ->whereIn('a.'.$aId, function ($sub) use ($adTable, $adAluno) {
                $sub->from($adTable)->select($adAluno)->distinct();
            });
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

            $adTable = self::resolveAlunoDeficienciaTable($db, $city);
            $defTable = self::resolveDeficienciaCatalogTable($db, $city);
            if ($adTable === null || $defTable === null) {
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

            $sinExpr = self::keywordSqlOr('d.'.$nmCol, self::sindromeKeywords());
            $ahExpr = self::keywordSqlOr('d.'.$nmCol, self::altasHabilidadesKeywords());
            $defExpr = '(NOT ('.$sinExpr.')) AND (NOT ('.$ahExpr.'))';

            $nSin = $countDistinct(
                $base()
                    ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                    ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                    ->whereRaw($sinExpr),
                $mId
            );
            $nAh = $countDistinct(
                $base()
                    ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                    ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                    ->whereRaw($ahExpr),
                $mId
            );
            $nDef = $countDistinct(
                $base()
                    ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                    ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk)
                    ->whereRaw($defExpr),
                $mId
            );

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
            $chart['subtitle'] = __(
                'Contagem de matrículas activas distintas com pelo menos um registo em aluno_deficiência cuja designação no catálogo se enquadra em cada grupo (palavras-chave para síndromes/TEA e para altas habilidades). O mesmo aluno pode contar em mais do que um grupo se existirem vários vínculos. Denominador geral do filtro: :n matrículas.',
                ['n' => $den]
            );

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
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $mId = (string) config('ieducar.columns.matricula.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $aId = (string) config('ieducar.columns.aluno.id');

            $adTable = self::resolveAlunoDeficienciaTable($db, $city);
            $defTable = self::resolveDeficienciaCatalogTable($db, $city);
            if ($adTable === null || $defTable === null) {
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
            $defPk = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                (string) config('ieducar.columns.deficiencia.id'),
                'cod_deficiencia',
            ]), $city);
            $nmCol = IeducarColumnInspector::firstExistingColumn($db, $defTable, array_filter([
                (string) config('ieducar.columns.deficiencia.name'),
                'nm_deficiencia',
            ]), $city);
            if ($adAluno === null || $adDef === null || $defPk === null || $nmCol === null) {
                return null;
            }

            $g = $db->getQueryGrammar();
            $q = $db->table($mat.' as m')
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->join($adTable.' as ad', 'a.'.$aId, '=', 'ad.'.$adAluno)
                ->join($defTable.' as d', 'ad.'.$adDef, '=', 'd.'.$defPk);
            MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            MatriculaTurmaJoin::applyTurmaFiltersFromMatricula($q, $db, $city, $filters);

            $q->selectRaw('d.'.$defPk.' as did')
                ->selectRaw('MAX(d.'.$nmCol.') as dnm')
                ->selectRaw('COUNT(DISTINCT m.'.$mId.') as c')
                ->groupBy('d.'.$defPk)
                ->orderByDesc('c')
                ->limit(22);

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $labels = [];
            $values = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $nm = trim((string) ($arr['dnm'] ?? ''));
                $labels[] = $nm !== '' ? $nm : ('#'.$arr['did']);
                $values[] = (float) ($arr['c'] ?? 0);
            }

            [$labels, $values] = ChartPayload::capTailAsOutros($labels, $values, 14, __('Outras deficiências / NE'));

            $chart = ChartPayload::barHorizontal(
                __('Matrículas por tipo (cadastro deficiência — NE, síndromes, deficiências)'),
                __('Matrículas distintas'),
                $labels,
                $values
            );
            $chart['subtitle'] = __(
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
}
