<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;

/**
 * Executa rotinas de discrepância com estados explícitos (ok / alerta / indisponível).
 */
final class DiscrepanciesCheckRunner
{
    /**
     * @param  callable(Connection, City, IeducarFilterState): list<array{escola_id: string, escola: string, total: int}>  $queryFn
     * @param  callable(Connection, City): bool  $canRunFn
     * @return array{
     *   availability: string,
     *   has_issue: bool,
     *   rows: list<array{escola_id: string, escola: string, total: int}>,
     *   unavailable_reason: ?string
     * }
     */
    public static function evaluate(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        callable $queryFn,
        callable $canRunFn,
        ?string $unavailableHint = null,
    ): array {
        if (! $canRunFn($db, $city)) {
            return [
                'availability' => 'unavailable',
                'has_issue' => false,
                'rows' => [],
                'unavailable_reason' => $unavailableHint ?? __('Rotina indisponível: tabelas ou colunas necessárias não existem nesta base i-Educar.'),
            ];
        }

        try {
            $rows = $queryFn($db, $city, $filters);
            if (! is_array($rows)) {
                $rows = [];
            }

            return [
                'availability' => 'available',
                'has_issue' => $rows !== [],
                'rows' => $rows,
                'unavailable_reason' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'availability' => 'unavailable',
                'has_issue' => false,
                'rows' => [],
                'unavailable_reason' => __('Erro ao executar a rotina: :msg', ['msg' => $e->getMessage()]),
            ];
        }
    }

    /**
     * @return array<string, array{fn: callable, probe: callable}>
     */
    public static function queryMap(): array
    {
        return [
            'sem_raca' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculasSemRacaPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::corRacaCadastro($db, $city),
                'hint' => __('Requer matrícula, aluno e cadastro de cor/raça (mesma lógica da aba Inclusão: fisica_raca → raca ou pessoa).'),
            ],
            'sem_sexo' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculasSemSexoPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::pessoaCadastro($db, $city),
                'hint' => __('Requer vínculo aluno↔pessoa (ou fisica) com coluna de sexo.'),
            ],
            'sem_data_nascimento' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculasSemDataNascimentoPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::pessoaCadastro($db, $city),
                'hint' => __('Requer data de nascimento em pessoa ou fisica.'),
            ],
            'nee_sem_aee' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::neeSemTurmaAeePorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::neeComTurma($db, $city),
                'hint' => __('Requer matrícula, aluno, turma e cadastro de NEE.'),
            ],
            'aee_sem_nee' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::turmaAeeSemCadastroNeePorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::neeComTurma($db, $city),
                'hint' => __('Requer matrícula, aluno, turma e cadastro de NEE.'),
            ],
            'escola_sem_inep' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::escolasSemInepComMatriculas($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::escolaComMatricula($db, $city),
                'hint' => __('Requer matrícula ligada à escola e coluna INEP ou educacenso_cod_escola.'),
            ],
            'escola_inativa_matricula' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::escolasInativasComMatriculas($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::escolaAtivoColumn($db, $city),
                'hint' => __('Requer coluna de situação ativa/inativa na tabela escola.'),
            ],
            'escola_sem_geo' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::escolasSemGeolocalizacaoComMatriculas($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::escolaGeoColumns($db, $city),
                'hint' => __('Requer colunas de latitude/longitude na tabela escola.'),
            ],
            'matricula_duplicada' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculaDuplicadaAtivoPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::matriculaCore($db, $city),
                'hint' => __('Requer tabela matricula com vínculo a turma ou escola.'),
            ],
            'matricula_situacao_invalida' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculasSituacaoNaoEmCursoPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::matriculaSituacao($db, $city),
                'hint' => __('Requer campo de situação da matrícula (catálogo ou legado).'),
            ],
            'distorcao_idade_serie' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => MatriculaChartQueries::distorcaoMatriculasPorEscolaRows($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => DiscrepanciesAvailability::canJoinTurma($db, $city),
                'hint' => __('Requer vínculo matrícula↔turma e série para cálculo de idade.'),
            ],
        ];
    }
}
