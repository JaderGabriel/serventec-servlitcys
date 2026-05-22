<?php

namespace App\Support\Rx;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarCensoEscolaQueries;
use App\Support\Ieducar\IeducarWorkActivityQueries;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\QueryException;

/**
 * Métricas RX por município (ano vigente vs anterior) — sem indicadores financeiros.
 */
final class RxCityMetricsCollector
{
    public function __construct(
        private CityDataConnection $cityData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(City $city, int $vigenteYear): array
    {
        $prevYear = $vigenteYear - 1;
        $base = $this->emptyRow($city, $vigenteYear, $prevYear);

        if (! $city->hasDataSetup()) {
            return $this->finalizeRow($base, [
                'situacao_codigo' => 'setup',
                'error' => __('Credenciais de base incompletas (host, base ou utilizador).'),
                'conexao_ok' => false,
            ]);
        }

        $conn = $this->cityData->connectionStatus($city);
        $base['conexao_status'] = $conn['status'] ?? 'error';
        $base['conexao_ok'] = in_array($conn['status'] ?? '', ['ok', 'slow'], true);

        if (! $base['conexao_ok']) {
            return $this->finalizeRow($base, [
                'situacao_codigo' => 'conexao',
                'error' => $conn['message'] ?? __('Falha ao ligar à base i-Educar.'),
                'conexao_ok' => false,
            ]);
        }

        try {
            return $this->cityData->run($city, function ($db) use ($city, $vigenteYear, $prevYear, $base) {
                return $this->collectFromDatabase($db, $city, $vigenteYear, $prevYear, $base);
            });
        } catch (QueryException $e) {
            return $this->finalizeRow($base, [
                'situacao_codigo' => 'consulta',
                'error' => $this->shortErrorMessage($e),
                'conexao_ok' => true,
                'consulta_warnings' => [$e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            $isConnection = $this->looksLikeConnectionError($e);

            return $this->finalizeRow($base, [
                'situacao_codigo' => $isConnection ? 'conexao' : 'consulta',
                'error' => $this->shortErrorMessage($e),
                'conexao_ok' => ! $isConnection,
                'consulta_warnings' => $isConnection ? [] : [$e->getMessage()],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collectFromDatabase(
        $db,
        City $city,
        int $vigenteYear,
        int $prevYear,
        array $base,
    ): array {
        $warnings = [];
        $filtersVigente = new IeducarFilterState((string) $vigenteYear, null, null, null);
        $filtersAnterior = new IeducarFilterState((string) $prevYear, null, null, null);

        $matV = $this->safeInt(
            fn () => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filtersVigente),
            $warnings,
            __('matrículas vigentes')
        );
        $matA = $this->safeInt(
            fn () => MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filtersAnterior),
            $warnings,
            __('matrículas ano anterior')
        );

        if ($matV === null) {
            return $this->finalizeRow($base, [
                'situacao_codigo' => 'consulta',
                'error' => __('Não foi possível contar matrículas ativas no ano vigente.'),
                'conexao_ok' => true,
                'consulta_warnings' => $warnings,
            ]);
        }

        $alunosV = $this->safeInt(
            fn () => IeducarWorkActivityQueries::countAlunosAtivosForYear($db, $city, $filtersVigente),
            $warnings,
            __('alunos vigentes')
        ) ?? 0;
        $alunosA = $this->safeInt(
            fn () => IeducarWorkActivityQueries::countAlunosAtivosForYear($db, $city, $filtersAnterior),
            $warnings,
            __('alunos ano anterior')
        ) ?? 0;
        $turmasV = $this->safeInt(
            fn () => IeducarWorkActivityQueries::countTurmasForYear($db, $city, $filtersVigente),
            $warnings,
            __('turmas vigentes')
        ) ?? 0;
        $turmasA = $this->safeInt(
            fn () => IeducarWorkActivityQueries::countTurmasForYear($db, $city, $filtersAnterior),
            $warnings,
            __('turmas ano anterior')
        ) ?? 0;
        $entV = $this->safeInt(
            fn () => IeducarWorkActivityQueries::countEnturmacoesForYear($db, $city, $filtersVigente),
            $warnings,
            __('enturmações vigentes')
        ) ?? 0;
        $entA = $this->safeInt(
            fn () => IeducarWorkActivityQueries::countEnturmacoesForYear($db, $city, $filtersAnterior),
            $warnings,
            __('enturmações ano anterior')
        ) ?? 0;

        $baselineResolved = $this->safe(
            fn () => RxBaselineResolver::resolve($db, $city, $vigenteYear),
            $warnings,
            __('meta de cadastro'),
            [
                'turmas' => 0,
                'matriculas' => 0,
                'enturmacoes' => 0,
                'ano' => 0,
                'referencia_ano' => 0,
                'referencia_turmas' => 0,
                'referencia_matriculas' => 0,
                'referencia_enturmacoes' => 0,
                'saltos' => 0,
                'fator_meta' => 1.0,
                'acrescimo_pct' => 0.0,
                'encontrou_referencia' => false,
            ]
        );

        $periods = ['day' => 0, 'week' => 0, 'fortnight' => 0];
        $ctx = $this->safe(
            fn () => IeducarWorkActivityQueries::matriculaActivityContext($db, $city),
            $warnings,
            __('ritmo de cadastro'),
            ['available' => false, 'date_col' => null, 'user_col' => null]
        );
        if (is_array($ctx) && ($ctx['available'] ?? false) && filled($ctx['date_col'] ?? null)) {
            $periods = $this->safe(
                fn () => IeducarWorkActivityQueries::matriculaCountsByPeriod(
                    $db,
                    $city,
                    $filtersVigente,
                    (string) $ctx['date_col'],
                    $ctx['user_col'] ?? null,
                ),
                $warnings,
                __('cadastros por período'),
                $periods
            ) ?? $periods;
        }

        $baselineForEstimate = [
            'turmas' => (int) ($baselineResolved['turmas'] ?? 0),
            'matriculas' => (int) ($baselineResolved['matriculas'] ?? 0),
            'enturmacoes' => (int) ($baselineResolved['enturmacoes'] ?? 0),
            'ano' => (int) ($baselineResolved['ano'] ?? 0),
        ];

        $estimativa = $this->safe(
            fn () => IeducarWorkActivityQueries::buildEstimate(
                $baselineForEstimate,
                $periods,
                $turmasV,
                $matV,
                $entV,
                [],
            ),
            $warnings,
            __('estimativa de trabalho'),
            []
        ) ?? [];

        $censo = $this->safe(
            fn () => IeducarCensoEscolaQueries::schoolStatuses($db, $city, $filtersVigente),
            $warnings,
            __('status Censo'),
            ['available' => false, 'summary' => []]
        ) ?? ['available' => false, 'summary' => []];

        $summary = is_array($censo['summary'] ?? null) ? $censo['summary'] : [];
        $totalEsc = (int) ($summary['total_escolas'] ?? 0);
        $exportadas = (int) ($summary['exportadas'] ?? 0);
        $fechadas = (int) ($summary['fechadas'] ?? 0);
        $pendentes = (int) ($summary['pendentes'] ?? 0);
        $concluidas = $exportadas + $fechadas;
        $pctCenso = $totalEsc > 0 ? round(100.0 * $concluidas / $totalEsc, 1) : null;

        $metaTurmas = (int) ($baselineResolved['turmas'] ?? 0);
        $metaMat = (int) ($baselineResolved['matriculas'] ?? 0);
        $metaEnt = (int) ($baselineResolved['enturmacoes'] ?? 0);

        $gap = RxCadastroGap::compute(
            $metaTurmas,
            $metaMat,
            $metaEnt,
            $turmasV,
            $matV,
            $entV,
        );
        $deltaInfo = RxCadastroGap::matriculasDelta($matV, $matA);

        $row = array_merge($base, [
            'ok' => true,
            'alunos_vigente' => $alunosV,
            'alunos_anterior' => $alunosA,
            'matriculas_vigente' => $matV,
            'matriculas_anterior' => $matA,
            'turmas_vigente' => $turmasV,
            'turmas_anterior' => $turmasA,
            'enturmacoes_vigente' => $entV,
            'enturmacoes_anterior' => $entA,
            'matriculas_delta' => $deltaInfo['delta'],
            'matriculas_delta_pct' => $deltaInfo['delta_pct'],
            'matriculas_delta_sem_base' => $deltaInfo['delta_sem_base'],
            'progresso_cadastro_pct' => $gap['progresso_cadastro_pct'],
            'progresso_turmas_pct' => $gap['progresso_turmas_pct'],
            'progresso_matriculas_pct' => $gap['progresso_matriculas_pct'],
            'falta_turmas' => $gap['falta_turmas'],
            'falta_matriculas' => $gap['falta_matriculas'],
            'falta_enturmacoes' => $gap['falta_enturmacoes'],
            'registros_restantes' => $gap['registros_restantes'],
            'dias_para_meta' => $estimativa['dias_para_concluir_ritmo_atual'] ?? null,
            'horas_estimadas' => $estimativa['horas_totais_estimadas'] ?? null,
            'meta_referencia_ano' => (int) ($baselineResolved['referencia_ano'] ?? 0),
            'meta_referencia_turmas' => (int) ($baselineResolved['referencia_turmas'] ?? 0),
            'meta_referencia_matriculas' => (int) ($baselineResolved['referencia_matriculas'] ?? 0),
            'meta_referencia_enturmacoes' => (int) ($baselineResolved['referencia_enturmacoes'] ?? 0),
            'meta_saltos' => (int) ($baselineResolved['saltos'] ?? 0),
            'meta_fator' => (float) ($baselineResolved['fator_meta'] ?? 1.0),
            'meta_acrescimo_pct' => (float) ($baselineResolved['acrescimo_pct'] ?? 0.0),
            'meta_encontrou_referencia' => (bool) ($baselineResolved['encontrou_referencia'] ?? false),
            'meta_turmas_alvo' => $metaTurmas,
            'meta_matriculas_alvo' => $metaMat,
            'meta_enturmacoes_alvo' => $metaEnt,
            'censo' => [
                'available' => (bool) ($censo['available'] ?? false),
                'total_escolas' => $totalEsc,
                'exportadas' => $exportadas,
                'fechadas' => $fechadas,
                'pendentes' => $pendentes,
                'pct_concluido' => $pctCenso,
                'source_label' => $censo['source_label'] ?? null,
            ],
            'cadastro_ritmo_quinzena' => (int) ($estimativa['cadastros_ultima_quinzena'] ?? 0),
            'consulta_warnings' => $warnings,
            'situacao_codigo' => $warnings === [] ? 'ok' : 'parcial',
            'conexao_ok' => true,
        ]);

        return $this->finalizeRow($row, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRow(City $city, int $vigenteYear, int $prevYear): array
    {
        return [
            'city_id' => (int) $city->id,
            'city_name' => (string) $city->name,
            'uf' => (string) $city->uf,
            'driver' => $city->effectiveIeducarDriver(),
            'ok' => false,
            'error' => null,
            'vigente_ano' => $vigenteYear,
            'anterior_ano' => $prevYear,
            'conexao_ok' => null,
            'conexao_status' => null,
            'situacao_codigo' => 'pending',
            'consulta_warnings' => [],
            'alunos_vigente' => 0,
            'alunos_anterior' => 0,
            'matriculas_vigente' => 0,
            'matriculas_anterior' => 0,
            'turmas_vigente' => 0,
            'turmas_anterior' => 0,
            'enturmacoes_vigente' => 0,
            'enturmacoes_anterior' => 0,
            'matriculas_delta' => 0,
            'matriculas_delta_pct' => null,
            'matriculas_delta_sem_base' => false,
            'progresso_cadastro_pct' => null,
            'progresso_turmas_pct' => null,
            'progresso_matriculas_pct' => null,
            'falta_turmas' => 0,
            'falta_matriculas' => 0,
            'falta_enturmacoes' => 0,
            'registros_restantes' => 0,
            'dias_para_meta' => null,
            'horas_estimadas' => null,
            'meta_referencia_ano' => 0,
            'meta_referencia_turmas' => 0,
            'meta_referencia_matriculas' => 0,
            'meta_referencia_enturmacoes' => 0,
            'meta_saltos' => 0,
            'meta_fator' => 1.0,
            'meta_acrescimo_pct' => 0.0,
            'meta_encontrou_referencia' => false,
            'meta_turmas_alvo' => 0,
            'meta_matriculas_alvo' => 0,
            'meta_enturmacoes_alvo' => 0,
            'semaforo' => 'neutral',
            'semaforo_label' => '',
            'semaforo_title' => '',
            'censo' => [
                'available' => false,
                'total_escolas' => 0,
                'exportadas' => 0,
                'fechadas' => 0,
                'pendentes' => 0,
                'pct_concluido' => null,
            ],
            'cadastro_ritmo_quinzena' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function finalizeRow(array $row, array $patch): array
    {
        $row = array_merge($row, $patch);

        if (($row['ok'] ?? false) || ($row['situacao_codigo'] ?? '') === 'parcial') {
            $sem = RxSemaphore::fromRow($row);
            $row['semaforo'] = $sem['status'];
            $row['semaforo_label'] = $sem['label'];
            $row['semaforo_title'] = $sem['title'];
        } else {
            $row['semaforo'] = match ($row['situacao_codigo'] ?? '') {
                'conexao' => 'error',
                'consulta' => 'yellow',
                default => 'neutral',
            };
            $row['semaforo_label'] = match ($row['situacao_codigo'] ?? '') {
                'conexao' => __('Sem conexão'),
                'consulta' => __('Consulta'),
                'setup' => __('Sem base'),
                default => __('Erro'),
            };
            $row['semaforo_title'] = (string) ($row['error'] ?? '');
        }

        $row['situacao_label'] = $this->situacaoLabel($row);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function situacaoLabel(array $row): string
    {
        return match ($row['situacao_codigo'] ?? '') {
            'ok' => __('OK'),
            'parcial' => __('Parcial'),
            'conexao' => __('Conexão'),
            'consulta' => __('Consulta'),
            'setup' => __('Config.'),
            default => __('Erro'),
        };
    }

    /**
     * @param  list<string>  $warnings
     */
    private function safeInt(callable $fn, array &$warnings, string $label): ?int
    {
        try {
            $v = $fn();
            if ($v === null) {
                $warnings[] = $label.': '.__('consulta indisponível (erro SQL ou esquema i-Educar).');
            }

            return $v === null ? null : (int) $v;
        } catch (\Throwable $e) {
            $warnings[] = $label.': '.$this->shortErrorMessage($e);

            return null;
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    private function safe(callable $fn, array &$warnings, string $label, mixed $default): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $warnings[] = $label.': '.$this->shortErrorMessage($e);

            return $default;
        }
    }

    private function shortErrorMessage(\Throwable $e): string
    {
        $msg = trim($e->getMessage());
        if (strlen($msg) > 220) {
            $msg = substr($msg, 0, 217).'…';
        }

        return $msg !== '' ? $msg : $e::class;
    }

    private function looksLikeConnectionError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        foreach ([
            'connection refused',
            'connection timed out',
            'could not connect',
            'no connection',
            'server has gone away',
            'lost connection',
            'password authentication failed',
            'access denied',
            'descriptografar',
            'decrypt',
            'app_key',
        ] as $needle) {
            if (str_contains($msg, $needle)) {
                return true;
            }
        }

        return $e instanceof \Illuminate\Contracts\Encryption\DecryptException;
    }
}
