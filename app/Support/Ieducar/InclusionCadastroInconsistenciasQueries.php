<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Inconsistências cadastrais NEE × AEE × recurso de prova INEP — detalhe por aluno para a aba Inclusão.
 */
final class InclusionCadastroInconsistenciasQueries
{
    public const LIMITE_LINHAS = 150;

    /**
     * @return array{
     *   linhas: list<array{
     *     aluno_id: int,
     *     nome: string,
     *     escola: string,
     *     tipo: string,
     *     tipo_label: string,
     *     detalhe: string
     *   }>,
     *   contagens: array{aee_sem_cadastro_nee: int, recurso_prova_sem_nee: int},
     *   limite: int,
     *   truncado: array{aee_sem_cadastro_nee: bool, recurso_prova_sem_nee: bool}
     * }
     */
    public static function painelDetalhes(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        int $limite = self::LIMITE_LINHAS,
    ): array {
        $limite = max(1, min(500, $limite));
        $porTipo = max(1, (int) ceil($limite / 2));

        $aee = self::coletarTurmaAeeSemCadastroNee($db, $city, $filters, $porTipo + 1);
        $recurso = self::coletarRecursoProvaSemCadastroNee($db, $city, $filters, $porTipo + 1);

        $truncAee = count($aee) > $porTipo;
        $truncRec = count($recurso) > $porTipo;
        if ($truncAee) {
            $aee = array_slice($aee, 0, $porTipo);
        }
        if ($truncRec) {
            $recurso = array_slice($recurso, 0, $porTipo);
        }

        $linhas = array_merge($aee, $recurso);
        usort($linhas, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        $totalAee = array_sum(array_column(
            DiscrepanciesQueries::turmaAeeSemCadastroNeePorEscola($db, $city, $filters),
            'total',
        ));
        $totalRecurso = (int) (InclusionRecursoProvaQueries::resumoCruzamento($db, $city, $filters)['sem_nee'] ?? 0);

        return [
            'linhas' => $linhas,
            'contagens' => [
                'aee_sem_cadastro_nee' => $totalAee,
                'recurso_prova_sem_nee' => $totalRecurso,
            ],
            'limite' => $limite,
            'truncado' => [
                'aee_sem_cadastro_nee' => $truncAee,
                'recurso_prova_sem_nee' => $truncRec,
            ],
        ];
    }

    /**
     * @return list<array{aluno_id: int, nome: string, escola: string, tipo: string, tipo_label: string, detalhe: string}>
     */
    private static function coletarTurmaAeeSemCadastroNee(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        int $limiteColeta,
    ): array {
        try {
            $neeSub = DiscrepanciesQueries::alunosComNeeSubqueryPublic($db, $city);
            if ($neeSub === null) {
                return [];
            }

            $nomeJoin = self::resolveNomeAlunoJoin($db, $city);
            if ($nomeJoin === null) {
                return [];
            }

            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $turma = IeducarSchema::resolveTable('turma', $city);
            $curso = IeducarSchema::resolveTable('curso', $city);
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $mId = (string) config('ieducar.columns.matricula.id');
            $aId = (string) config('ieducar.columns.aluno.id');
            $tName = IeducarColumnInspector::firstExistingColumn($db, $turma, ['nm_turma', (string) config('ieducar.columns.turma.name')], $city) ?? 'nm_turma';
            $cName = IeducarColumnInspector::firstExistingColumn($db, $curso, ['nm_curso', (string) config('ieducar.columns.curso.name')], $city) ?? 'nm_curso';
            $cId = (string) config('ieducar.columns.curso.id');
            $tc = MatriculaTurmaJoin::turmaFilterColumns($db, $city);
            if ($tc['escola'] === '') {
                return [];
            }

            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->join($nomeJoin['pessoa'].' as p_nome', 'a.'.$nomeJoin['aPessoa'], '=', 'p_nome.'.$nomeJoin['pId'])
                ->whereNotIn('a.'.$aId, $neeSub);

            if ($tc['curso'] !== '') {
                $q->leftJoin($curso.' as c_aee', 't_filter.'.$tc['curso'], '=', 'c_aee.'.$cId);
            }

            $escolaSpec = DiscrepanciesAvailability::joinEscola($q, $db, $city);
            if ($escolaSpec === null) {
                return [];
            }

            $rows = $q->selectRaw('a.'.$aId.' as aid')
                ->selectRaw($nomeJoin['nomeExpr'].' as nome_aluno')
                ->selectRaw('e.'.$escolaSpec['nameCol'].' as nome_escola')
                ->selectRaw('t_filter.'.$tName.' as nm_turma')
                ->selectRaw($tc['curso'] !== '' ? 'c_aee.'.$cName.' as nm_curso' : $db->raw("'' as nm_curso"))
                ->get();

            /** @var array<int, array{nome: string, escola: string, turmas: list<string>}> $porAluno */
            $porAluno = [];
            foreach ($rows as $row) {
                $t = strtolower((string) ($row->nm_turma ?? ''));
                $c = strtolower((string) ($row->nm_curso ?? ''));
                if (! self::matchAeeKeywords($t.' '.$c)) {
                    continue;
                }
                $aid = (int) ($row->aid ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                $turmaLabel = trim((string) ($row->nm_turma ?? ''));
                $cursoLabel = trim((string) ($row->nm_curso ?? ''));
                $turmaTxt = $turmaLabel !== '' ? $turmaLabel : __('Turma AEE');
                if ($cursoLabel !== '') {
                    $turmaTxt .= ' ('.$cursoLabel.')';
                }
                $porAluno[$aid] ??= [
                    'nome' => trim((string) ($row->nome_aluno ?? '')) ?: __('Aluno #:id', ['id' => $aid]),
                    'escola' => trim((string) ($row->nome_escola ?? '')) ?: '—',
                    'turmas' => [],
                ];
                if (! in_array($turmaTxt, $porAluno[$aid]['turmas'], true)) {
                    $porAluno[$aid]['turmas'][] = $turmaTxt;
                }
            }

            if ($porAluno === []) {
                return [];
            }

            $out = [];
            foreach ($porAluno as $aid => $info) {
                $turmas = $info['turmas'];
                sort($turmas, SORT_NATURAL | SORT_FLAG_CASE);
                $lista = implode('; ', array_slice($turmas, 0, 5));
                if (count($turmas) > 5) {
                    $lista .= ' …';
                }
                $out[] = [
                    'aluno_id' => $aid,
                    'nome' => $info['nome'],
                    'escola' => $info['escola'],
                    'tipo' => 'aee_sem_cadastro_nee',
                    'tipo_label' => __('Turma AEE sem deficiência no cadastro'),
                    'detalhe' => __(
                        'Matrícula activa em turma/curso identificado como AEE, sem registo em fisica_deficiencia nem aluno_deficiencia. Oferta: :turmas.',
                        ['turmas' => $lista !== '' ? $lista : __('—')]
                    ),
                ];
            }

            usort($out, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

            return array_slice($out, 0, $limiteColeta);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{aluno_id: int, nome: string, escola: string, tipo: string, tipo_label: string, detalhe: string}>
     */
    private static function coletarRecursoProvaSemCadastroNee(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        int $limiteColeta,
    ): array {
        if (! InclusionRecursoProvaQueries::canQuery($db, $city)) {
            return [];
        }

        try {
            $schema = InclusionRecursoProvaQueries::schema($db, $city);
            if (! ($schema['available'] ?? false)) {
                return [];
            }

            $neeSub = DiscrepanciesQueries::alunosComNeeSubqueryPublic($db, $city);
            if ($neeSub === null) {
                return [];
            }

            $recursoLabels = InclusionRecursoProvaQueries::recursoLabelsPorAlunoPublic($db, $city, $schema);
            if ($recursoLabels === []) {
                return [];
            }

            /** @var array<int, list<string>> $alunosSemNee */
            $alunosSemNee = [];
            foreach ($recursoLabels as $aid => $labels) {
                if ($labels !== []) {
                    $alunosSemNee[(int) $aid] = $labels;
                }
            }
            if ($alunosSemNee === []) {
                return [];
            }

            $nomeJoin = self::resolveNomeAlunoJoin($db, $city);
            if ($nomeJoin === null) {
                return [];
            }

            $aluno = IeducarSchema::resolveTable('aluno', $city);
            $aId = (string) config('ieducar.columns.aluno.id');
            $mAluno = (string) config('ieducar.columns.matricula.aluno');
            $alunoIds = array_keys($alunosSemNee);

            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters)
                ->join($aluno.' as a', 'm.'.$mAluno, '=', 'a.'.$aId)
                ->join($nomeJoin['pessoa'].' as p_nome', 'a.'.$nomeJoin['aPessoa'], '=', 'p_nome.'.$nomeJoin['pId'])
                ->whereIn('a.'.$aId, $alunoIds)
                ->whereNotIn('a.'.$aId, $neeSub);

            $escolaSpec = DiscrepanciesAvailability::joinEscola($q, $db, $city);
            if ($escolaSpec === null) {
                return [];
            }

            $rows = $q->selectRaw('a.'.$aId.' as aid')
                ->selectRaw($nomeJoin['nomeExpr'].' as nome_aluno')
                ->selectRaw('e.'.$escolaSpec['nameCol'].' as nome_escola')
                ->distinct()
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $aid = (int) ($row->aid ?? 0);
                if ($aid <= 0 || ! isset($alunosSemNee[$aid])) {
                    continue;
                }
                $recursos = $alunosSemNee[$aid];
                sort($recursos, SORT_NATURAL | SORT_FLAG_CASE);
                $lista = implode('; ', array_slice($recursos, 0, 8));
                if (count($recursos) > 8) {
                    $lista .= ' …';
                }
                $out[] = [
                    'aluno_id' => $aid,
                    'nome' => trim((string) ($row->nome_aluno ?? '')) ?: __('Aluno #:id', ['id' => $aid]),
                    'escola' => trim((string) ($row->nome_escola ?? '')) ?: '—',
                    'tipo' => 'recurso_prova_sem_nee',
                    'tipo_label' => __('Recurso de prova SAEB/INEP sem deficiência no cadastro'),
                    'detalhe' => __(
                        'Solicitação de apoio nas avaliações (Censo/SAEB) sem vínculo em cadastro.deficiencia / fisica_deficiencia. Recurso(s): :recursos.',
                        ['recursos' => $lista !== '' ? $lista : __('—')]
                    ),
                ];
            }

            usort($out, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

            return array_slice($out, 0, $limiteColeta);
        } catch (QueryException|\Throwable) {
            return [];
        }
    }

    /**
     * @return ?array{pessoa: string, aPessoa: string, pId: string, nomeExpr: string}
     */
    private static function resolveNomeAlunoJoin(Connection $db, City $city): ?array
    {
        $aluno = IeducarSchema::resolveTable('aluno', $city);
        $pessoa = IeducarSchema::resolveTable('pessoa', $city);
        if (! IeducarColumnInspector::tableExists($db, $aluno, $city)
            || ! IeducarColumnInspector::tableExists($db, $pessoa, $city)) {
            return null;
        }

        $aId = (string) config('ieducar.columns.aluno.id');
        $aPessoa = IeducarColumnInspector::firstExistingColumn($db, $aluno, [
            (string) config('ieducar.columns.aluno.pessoa'),
            'ref_cod_pessoa', 'ref_idpes', 'idpes',
        ], $city);
        $pId = IeducarColumnInspector::firstExistingColumn($db, $pessoa, [
            (string) config('ieducar.columns.pessoa.id'),
            'idpes', 'id', 'cod_pessoa',
        ], $city);
        $nomeCol = IeducarColumnInspector::firstExistingColumn($db, $pessoa, [
            (string) config('ieducar.columns.pessoa.name'),
            'nome', 'nm_pessoa',
        ], $city);
        if ($aPessoa === null || $pId === null || $nomeCol === null) {
            return null;
        }

        $grammar = $db->getQueryGrammar();
        $wrapP = $grammar->wrap('p_nome');
        $wrapNome = $wrapP.'.'.$grammar->wrap($nomeCol);
        $wrapAid = $grammar->wrap('a').'.'.$grammar->wrap($aId);
        $nomeExpr = 'COALESCE(NULLIF(TRIM('.$wrapNome.'), \'\'), CONCAT(\''.__('Aluno #').'\', CAST('.$wrapAid.' AS TEXT)))';

        return [
            'pessoa' => $pessoa,
            'aPessoa' => $aPessoa,
            'pId' => $pId,
            'nomeExpr' => $nomeExpr,
        ];
    }

    private static function matchAeeKeywords(string $haystack): bool
    {
        $words = config('ieducar.inclusion.aee_keywords');
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
}
