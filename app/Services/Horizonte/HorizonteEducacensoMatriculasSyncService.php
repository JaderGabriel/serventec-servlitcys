<?php

namespace App\Services\Horizonte;

use App\Services\Inep\InepCensoMunicipioMatriculasIndexer;
use App\Services\Inep\InepMicrodadosCadastroEscolasDownloader;
use App\Support\Horizonte\HorizonteEducacensoImportProgress;
use App\Support\Horizonte\HorizonteEducacensoYearWindow;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\InepMicrodadosCadastroEscolasPath;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Importa microdados Educacenso (INEP) por ano × UF para a janela do gráfico de matrículas Horizonte.
 */
final class HorizonteEducacensoMatriculasSyncService
{
    public function __construct(
        private readonly InepCensoMunicipioMatriculasIndexer $indexer,
        private readonly InepMicrodadosCadastroEscolasDownloader $downloader,
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     message: string,
     *     partial?: bool,
     *     skipped?: bool,
     *     indexed?: int,
     *     educacenso_done?: int,
     *     educacenso_total?: int,
     *     completed_steps?: list<array{year: int, uf: string, indexed: int}>,
     *     debug_lines?: list<string>
     * }
     */
    public function syncBatch(array $options = []): array
    {
        if (! filter_var(config('horizonte.fortnightly_feed.educacenso_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('Educacenso — fase desactivada (HORIZONTE_EDUCACENSO_ENABLED=false).'),
            ];
        }

        if (! Schema::hasTable('inep_censo_municipio_matriculas')) {
            return [
                'success' => false,
                'message' => __('Tabela inep_censo_municipio_matriculas indisponível — execute php artisan migrate.'),
            ];
        }

        if (filter_var($options['reset'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            HorizonteEducacensoImportProgress::reset();
        }

        $memory = trim((string) config('horizonte.fortnightly_feed.educacenso_memory_limit', '1024M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $allYears = is_array($options['years'] ?? null) && $options['years'] !== []
            ? array_values(array_map('intval', $options['years']))
            : HorizonteEducacensoYearWindow::years();
        $totalSteps = HorizonteEducacensoImportProgress::totalSteps($allYears);

        if (HorizonteEducacensoImportProgress::isComplete($allYears)) {
            return [
                'success' => true,
                'message' => __('Educacenso: todos os :n passos (ano × UF) já indexados.', ['n' => (string) $totalSteps]),
                'partial' => false,
                'educacenso_done' => $totalSteps,
                'educacenso_total' => $totalSteps,
                'completed_steps' => [],
            ];
        }

        $remaining = $this->filterRemainingSteps($allYears, $options);
        if ($remaining === []) {
            HorizonteEducacensoImportProgress::reset();

            return [
                'success' => true,
                'message' => __('Educacenso: nenhum passo pendente para o filtro indicado.'),
                'partial' => false,
                'educacenso_done' => HorizonteEducacensoImportProgress::doneStepCount(),
                'educacenso_total' => $totalSteps,
            ];
        }

        $stepsPerInvocation = max(1, (int) ($options['steps'] ?? config('horizonte.fortnightly_feed.educacenso_steps_per_step', 1)));
        $batch = array_slice($remaining, 0, $stepsPerInvocation);

        $debugLines = [];
        $indexedTotal = 0;
        /** @var list<array{year: int, uf: string, indexed: int}> $completedSteps */
        $completedSteps = [];
        $batchOk = true;
        $lastFailure = null;
        $onStep = $options['on_step'] ?? null;

        foreach ($batch as $step) {
            $year = (int) $step['year'];
            $uf = (string) $step['uf'];
            $pathResult = $this->resolveCsvPathForYear($year);
            if ($pathResult['path'] === null) {
                $lastFailure = HorizonteEducacensoImportProgress::stepKey($year, $uf);
                $batchOk = false;
                $msg = (string) ($pathResult['message'] ?? '');
                $debugLines[] = $msg;
                HorizonteEducacensoImportProgress::markStepFailed($year, $uf);
                if (is_callable($onStep)) {
                    $onStep(__('✗ Educacenso :ano / :uf — CSV ausente', ['ano' => (string) $year, 'uf' => $uf]), 'error');
                }
                continue;
            }

            $ibgeFilter = HorizonteUfScope::ibgeCodesForUf($uf, $this->ibgeCatalog);
            $debugLines[] = __('Educacenso :ano / :uf — :file', [
                'ano' => (string) $year,
                'uf' => $uf,
                'file' => basename((string) $pathResult['path']),
            ]);

            $indexed = $this->indexer->indexFromMicrodadosCsv((string) $pathResult['path'], $ibgeFilter);
            $line = __('Educacenso :ano / :uf — :n municípios indexados.', [
                'ano' => (string) $year,
                'uf' => $uf,
                'n' => (string) $indexed,
            ]);
            $debugLines[] = $line;

            if ($indexed > 0) {
                HorizonteEducacensoImportProgress::markStepDone($year, $uf);
                $indexedTotal += $indexed;
                $completedSteps[] = ['year' => $year, 'uf' => $uf, 'indexed' => $indexed];
                if (is_callable($onStep)) {
                    $onStep('✓ '.$line, 'info');
                }
            } else {
                $allowEmpty = filter_var(
                    config('horizonte.fortnightly_feed.educacenso_allow_empty', false),
                    FILTER_VALIDATE_BOOLEAN,
                );
                if ($allowEmpty) {
                    HorizonteEducacensoImportProgress::markStepDone($year, $uf);
                    $completedSteps[] = ['year' => $year, 'uf' => $uf, 'indexed' => 0];
                    if (is_callable($onStep)) {
                        $onStep(__('✓ Educacenso :ano / :uf — sem matrículas (vazio permitido)', ['ano' => (string) $year, 'uf' => $uf]), 'warn');
                    }
                } else {
                    $lastFailure = HorizonteEducacensoImportProgress::stepKey($year, $uf);
                    $batchOk = false;
                    HorizonteEducacensoImportProgress::markStepFailed($year, $uf);
                    $debugLines[] = __('Educacenso :ano / :uf — nenhuma matrícula agregada.', ['ano' => (string) $year, 'uf' => $uf]);
                    if (is_callable($onStep)) {
                        $onStep(__('✗ Educacenso :ano / :uf — nenhuma matrícula agregada', ['ano' => (string) $year, 'uf' => $uf]), 'error');
                    }
                }
            }
        }

        $doneCount = HorizonteEducacensoImportProgress::doneStepCount();
        $stillRemaining = count(HorizonteEducacensoImportProgress::remainingSteps($allYears));
        $partial = $stillRemaining > 0;

        $message = $partial
            ? __('Educacenso: :done/:total passos (ano × UF) — repita o comando.', [
                'done' => (string) $doneCount,
                'total' => (string) $totalSteps,
            ])
            : __('Educacenso: janela completa (:n combinações município/ano nesta execução).', [
                'n' => (string) $indexedTotal,
            ]);

        if ($lastFailure !== null && $partial) {
            $message .= ' '.__('Última falha: :step.', ['step' => $lastFailure]);
        }

        Log::info('horizonte.educacenso_matriculas', [
            'years' => $allYears,
            'batch' => $batch,
            'indexed' => $indexedTotal,
            'partial' => $partial,
            'ok' => $batchOk,
            'done_steps' => $doneCount,
            'total_steps' => $totalSteps,
        ]);

        return [
            'success' => ! $partial || $doneCount > 0,
            'partial' => $partial,
            'message' => $message,
            'indexed' => $indexedTotal,
            'educacenso_done' => $doneCount,
            'educacenso_total' => $totalSteps,
            'completed_steps' => $completedSteps,
            'debug_lines' => $debugLines,
        ];
    }

    /**
     * @param  list<int>  $allYears
     * @param  array<string, mixed>  $options
     * @return list<array{year: int, uf: string}>
     */
    private function filterRemainingSteps(array $allYears, array $options): array
    {
        $remaining = HorizonteEducacensoImportProgress::orderedRemainingSteps($allYears);

        $yearFilter = isset($options['year']) && is_numeric($options['year']) ? (int) $options['year'] : null;
        $ufFilter = HorizonteUfScope::normalize(isset($options['uf']) ? (string) $options['uf'] : null);

        if ($yearFilter !== null) {
            $remaining = array_values(array_filter(
                $remaining,
                static fn (array $step): bool => (int) $step['year'] === $yearFilter,
            ));
        }

        if ($ufFilter !== null) {
            $remaining = array_values(array_filter(
                $remaining,
                static fn (array $step): bool => strtoupper((string) $step['uf']) === $ufFilter,
            ));
        }

        return $remaining;
    }

    /**
     * @return array{path: ?string, message?: string}
     */
    private function resolveCsvPathForYear(int $year): array
    {
        $path = InepMicrodadosCadastroEscolasPath::resolveForYear($year);
        if ($path !== null) {
            return ['path' => $path];
        }

        $fetch = filter_var(
            config('horizonte.fortnightly_feed.educacenso_fetch_if_missing', true),
            FILTER_VALIDATE_BOOLEAN,
        );
        if (! $fetch) {
            return [
                'path' => null,
                'message' => __('Educacenso :ano — CSV local ausente (coloque em storage/app/public/inep/microdados_ed_basica_:ano.csv).', [
                    'ano' => (string) $year,
                ]),
            ];
        }

        try {
            $downloaded = $this->downloader->downloadAndExtractForYear($year);

            return ['path' => $downloaded];
        } catch (\Throwable $e) {
            $allowSkip = filter_var(
                config('horizonte.fortnightly_feed.educacenso_skip_if_missing', true),
                FILTER_VALIDATE_BOOLEAN,
            );
            $msg = __('Educacenso :ano — download INEP falhou: :erro', [
                'ano' => (string) $year,
                'erro' => $e->getMessage(),
            ]);

            if ($allowSkip) {
                return ['path' => null, 'message' => $msg];
            }

            throw $e;
        }
    }
}
