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
    ): array {
        if (! $canRunFn($db, $city)) {
            return [
                'availability' => 'unavailable',
                'has_issue' => false,
                'rows' => [],
                'unavailable_reason' => __('Rotina indisponível: tabelas ou colunas necessárias não existem nesta base i-Educar.'),
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
                'unavailable_reason' => $e->getMessage(),
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
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaAlunoPessoa($db, $city),
            ],
            'sem_sexo' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculasSemSexoPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaAlunoPessoa($db, $city),
            ],
            'sem_data_nascimento' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculasSemDataNascimentoPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaAlunoPessoa($db, $city),
            ],
            'nee_sem_aee' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::neeSemTurmaAeePorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaAluno($db, $city),
            ],
            'aee_sem_nee' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::turmaAeeSemCadastroNeePorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaAluno($db, $city),
            ],
            'escola_sem_inep' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::escolasSemInepComMatriculas($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaEscola($db, $city),
            ],
            'escola_inativa_matricula' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::escolasInativasComMatriculas($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaEscolaActive($db, $city),
            ],
            'escola_sem_geo' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::escolasSemGeolocalizacaoComMatriculas($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeEscolaGeo($db, $city),
            ],
            'matricula_duplicada' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculaDuplicadaAtivoPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaAluno($db, $city),
            ],
            'matricula_situacao_invalida' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => DiscrepanciesQueries::matriculasSituacaoNaoEmCursoPorEscola($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaSituacao($db, $city),
            ],
            'distorcao_idade_serie' => [
                'fn' => static fn (Connection $db, City $city, IeducarFilterState $f) => MatriculaChartQueries::distorcaoMatriculasPorEscolaRows($db, $city, $f),
                'probe' => static fn (Connection $db, City $city): bool => self::probeMatriculaTurma($db, $city),
            ],
        ];
    }

    private static function probeMatriculaAluno(Connection $db, City $city): bool
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $aluno = IeducarSchema::resolveTable('aluno', $city);

            return IeducarColumnInspector::tableExists($db, $mat, $city)
                && IeducarColumnInspector::tableExists($db, $aluno, $city);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function probeMatriculaAlunoPessoa(Connection $db, City $city): bool
    {
        if (! self::probeMatriculaAluno($db, $city)) {
            return false;
        }
        try {
            $pessoa = IeducarSchema::resolveTable('pessoa', $city);

            return IeducarColumnInspector::tableExists($db, $pessoa, $city);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function probeMatriculaEscola(Connection $db, City $city): bool
    {
        return self::probeMatriculaTurma($db, $city) && IeducarColumnInspector::tableExists(
            $db,
            IeducarSchema::resolveTable('escola', $city),
            $city
        );
    }

    private static function probeMatriculaEscolaActive(Connection $db, City $city): bool
    {
        if (! self::probeMatriculaEscola($db, $city)) {
            return false;
        }
        $escola = IeducarSchema::resolveTable('escola', $city);
        $activeCol = (string) config('ieducar.columns.escola.active', 'ativo');

        return $activeCol !== '' && IeducarColumnInspector::columnExists($db, $escola, $activeCol, $city);
    }

    private static function probeMatriculaTurma(Connection $db, City $city): bool
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);

            return IeducarColumnInspector::tableExists($db, $mat, $city)
                && MatriculaTurmaJoin::turmaFilterColumns($db, $city)['escola'] !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    private static function probeMatriculaSituacao(Connection $db, City $city): bool
    {
        return self::probeMatriculaTurma($db, $city)
            && MatriculaSituacaoResolver::resolveChaveAgrupamento($db, $city) !== null;
    }

    private static function probeEscolaGeo(Connection $db, City $city): bool
    {
        if (! self::probeMatriculaEscola($db, $city)) {
            return false;
        }
        $escola = IeducarSchema::resolveTable('escola', $city);
        $lat = IeducarColumnInspector::firstExistingColumn($db, $escola, ['latitude', 'lat', 'geo_lat'], $city);
        $lng = IeducarColumnInspector::firstExistingColumn($db, $escola, ['longitude', 'lng', 'lon', 'geo_lng'], $city);

        return $lat !== null || $lng !== null;
    }
}
