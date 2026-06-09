<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

/**
 * Contagem de alunos distintos por faixa etária Cecad (4–5 … 15–17) via data de nascimento.
 */
final class CadunicoFaixaEtariaCounts
{
    public const METODO_IDADE = 'idade_nascimento';

    public const METODO_RATEIO = 'rateio_proporcional';

    /**
     * @return array{
     *   available: bool,
     *   metodo: string,
     *   por_faixa: array<string, int>,
     *   alunos_com_nascimento: int,
     *   alunos_total: int,
     *   cobertura_nascimento_pct: ?float
     * }
     */
    public static function count(Connection $db, City $city, IeducarFilterState $filters): array
    {
        $faixas = self::faixasConfig();
        $emptyBands = [];
        foreach ($faixas as $faixa) {
            $emptyBands[(string) $faixa['key']] = 0;
        }

        $empty = [
            'available' => false,
            'metodo' => self::METODO_RATEIO,
            'por_faixa' => $emptyBands,
            'alunos_com_nascimento' => 0,
            'alunos_total' => 0,
            'cobertura_nascimento_pct' => null,
        ];

        if ($faixas === []) {
            return $empty;
        }

        $ctx = self::resolveBirthContext($db, $city);
        if ($ctx === null) {
            return $empty;
        }

        try {
            $year = (int) $filters->ano_letivo;
            if ($year < 2000) {
                return $empty;
            }

            $grammar = $db->getQueryGrammar();
            $refDateExpr = DistorcaoIdadeSerieEngine::refDateCorteEscolarSql($db, (string) $year);
            $birthExpr = $grammar->wrap('p').'.'.$grammar->wrap($ctx['birth_col']);
            $idadeExpr = DistorcaoIdadeSerieEngine::idadeAnosCompletosSql($db, $refDateExpr, $birthExpr);
            $alunoCol = $grammar->wrap('m').'.'.$grammar->wrap($ctx['aluno_col']);

            $selects = [];
            foreach ($faixas as $faixa) {
                $key = (string) $faixa['key'];
                $min = (int) ($faixa['idade_min'] ?? 0);
                $max = (int) ($faixa['idade_max'] ?? 0);
                $selects[] = 'COUNT(DISTINCT CASE WHEN ('.$idadeExpr.') BETWEEN '.$min.' AND '.$max.' THEN '.$alunoCol.' END) as '.$key;
            }
            $selects[] = 'COUNT(DISTINCT CASE WHEN '.$birthExpr.' IS NOT NULL THEN '.$alunoCol.' END) as alunos_com_nascimento';
            $selects[] = 'COUNT(DISTINCT '.$alunoCol.') as alunos_total';

            $q = DiscrepanciesQueries::baseMatriculaComTurmaPublic($db, $city, $filters);
            $q->join($ctx['aluno_table'].' as a', 'm.'.$ctx['matricula_aluno'], '=', 'a.'.$ctx['aluno_id'])
                ->join($ctx['pessoa_table'].' as p', 'a.'.$ctx['aluno_pessoa'], '=', 'p.'.$ctx['pessoa_id'])
                ->whereNotNull('p.'.$ctx['birth_col']);

            $row = (array) $q->selectRaw(implode(', ', $selects))->first();
            if ($row === []) {
                return $empty;
            }

            $porFaixa = [];
            $sumBands = 0;
            foreach ($faixas as $faixa) {
                $key = (string) $faixa['key'];
                $n = max(0, (int) ($row[$key] ?? 0));
                $porFaixa[$key] = $n;
                $sumBands += $n;
            }

            $comNasc = max(0, (int) ($row['alunos_com_nascimento'] ?? 0));
            $total = max(0, (int) ($row['alunos_total'] ?? 0));

            if ($sumBands <= 0 || $comNasc <= 0) {
                return $empty;
            }

            $cobertura = $total > 0 ? round(min(100.0, 100.0 * $comNasc / $total), 1) : null;

            return [
                'available' => true,
                'metodo' => self::METODO_IDADE,
                'por_faixa' => $porFaixa,
                'alunos_com_nascimento' => $comNasc,
                'alunos_total' => $total,
                'cobertura_nascimento_pct' => $cobertura,
            ];
        } catch (QueryException|\Throwable) {
            return $empty;
        }
    }

    /**
     * @return list<array{key: string, label: string, idade_min: int, idade_max: int, etapa_keywords?: list<string>}>
     */
    public static function faixasConfig(): array
    {
        $defaults = [
            'criancas_4_5' => ['min' => 4, 'max' => 5],
            'criancas_6_10' => ['min' => 6, 'max' => 10],
            'criancas_11_14' => ['min' => 11, 'max' => 14],
            'criancas_15_17' => ['min' => 15, 'max' => 17],
        ];

        $cfg = config('ieducar.cadunico.faixas_etarias', []);
        if (! is_array($cfg)) {
            return [];
        }

        $out = [];
        foreach ($cfg as $faixa) {
            if (! is_array($faixa)) {
                continue;
            }
            $key = (string) ($faixa['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $def = $defaults[$key] ?? ['min' => 0, 'max' => 0];
            $out[] = [
                'key' => $key,
                'label' => (string) ($faixa['label'] ?? $key),
                'idade_min' => (int) ($faixa['idade_min'] ?? $def['min']),
                'idade_max' => (int) ($faixa['idade_max'] ?? $def['max']),
                'etapa_keywords' => is_array($faixa['etapa_keywords'] ?? null) ? $faixa['etapa_keywords'] : [],
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   aluno_table: string,
     *   pessoa_table: string,
     *   aluno_id: string,
     *   aluno_pessoa: string,
     *   pessoa_id: string,
     *   matricula_aluno: string,
     *   birth_col: string
     * }|null
     */
    private static function resolveBirthContext(Connection $db, City $city): ?array
    {
        try {
            $alunoTable = IeducarSchema::resolveTable('aluno', $city);
            $pessoaTable = IeducarSchema::resolveTable('pessoa', $city);
            $matTable = IeducarSchema::resolveTable('matricula', $city);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $alunoId = (string) config('ieducar.columns.aluno.id');
        $alunoPessoa = (string) config('ieducar.columns.aluno.pessoa');
        $pessoaId = (string) config('ieducar.columns.pessoa.id');
        $matriculaAluno = MatriculaVolumeCounts::matriculaAlunoColumn($db, $city);
        if ($matriculaAluno === null) {
            return null;
        }

        $birthCol = IeducarColumnInspector::firstExistingColumn($db, $pessoaTable, array_filter([
            'data_nasc',
            'data_nascimento',
            'dt_nascimento',
            'dt_nasc',
        ]), $city);

        if ($birthCol === null) {
            return null;
        }

        if (! IeducarColumnInspector::tableExists($db, $alunoTable, $city)
            || ! IeducarColumnInspector::tableExists($db, $pessoaTable, $city)) {
            return null;
        }

        return [
            'aluno_table' => $alunoTable,
            'pessoa_table' => $pessoaTable,
            'aluno_id' => $alunoId,
            'aluno_pessoa' => $alunoPessoa,
            'pessoa_id' => $pessoaId,
            'matricula_aluno' => $matriculaAluno,
            'birth_col' => $birthCol,
        ];
    }
}
