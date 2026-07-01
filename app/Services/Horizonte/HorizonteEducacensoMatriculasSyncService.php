<?php

namespace App\Services\Horizonte;

use App\Models\InepCensoMunicipioMatricula;
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
 * Importa microdados Educacenso (INEP) por ano para a janela do gráfico de matrículas Horizonte.
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

        $allYears = HorizonteEducacensoYearWindow::years();
        $total = count($allYears);
        $remaining = HorizonteEducacensoImportProgress::orderedRemainingYears($allYears);

        if ($remaining === [] && $this->windowHasIndexedRows($allYears)) {
            HorizonteEducacensoImportProgress::reset();

            return [
                'success' => true,
                'message' => __('Educacenso: todos os :n ano(s) da janela já indexados (:anos).', [
                    'n' => (string) $total,
                    'anos' => implode(', ', array_map('strval', $allYears)),
                ]),
                'partial' => false,
                'educacenso_done' => $total,
                'educacenso_total' => $total,
            ];
        }

        if ($remaining === []) {
            HorizonteEducacensoImportProgress::reset();
            $remaining = HorizonteEducacensoImportProgress::orderedRemainingYears($allYears);
        }

        $yearsPerStep = max(1, (int) config('horizonte.fortnightly_feed.educacenso_years_per_step', 1));
        $batch = array_slice($remaining, 0, $yearsPerStep);
        $ibgeFilter = HorizonteUfScope::ibgeCodesForUf($options['uf'] ?? null, $this->ibgeCatalog);
        $debugLines = [];
        $indexedTotal = 0;
        $batchOk = true;
        $lastFailure = null;

        foreach ($batch as $year) {
            $pathResult = $this->resolveCsvPathForYear($year);
            if ($pathResult['path'] === null) {
                $lastFailure = $year;
                $batchOk = false;
                $debugLines[] = (string) ($pathResult['message'] ?? '');
                HorizonteEducacensoImportProgress::markFailed($year);
                continue;
            }

            $debugLines[] = __('Educacenso :ano — :path', [
                'ano' => (string) $year,
                'path' => basename((string) $pathResult['path']),
            ]);

            $indexed = $this->indexer->indexFromMicrodadosCsv((string) $pathResult['path'], $ibgeFilter);
            $debugLines[] = __('Educacenso :ano — :n combinações município/ano.', [
                'ano' => (string) $year,
                'n' => (string) $indexed,
            ]);

            if ($indexed > 0) {
                HorizonteEducacensoImportProgress::markDone($year);
                $indexedTotal += $indexed;
            } else {
                $allowEmpty = filter_var(
                    config('horizonte.fortnightly_feed.educacenso_allow_empty', false),
                    FILTER_VALIDATE_BOOLEAN,
                );
                if ($allowEmpty) {
                    HorizonteEducacensoImportProgress::markDone($year);
                } else {
                    $lastFailure = $year;
                    $batchOk = false;
                    HorizonteEducacensoImportProgress::markFailed($year);
                    $debugLines[] = __('Educacenso :ano — nenhuma matrícula agregada.', ['ano' => (string) $year]);
                }
            }
        }

        $doneCount = count(HorizonteEducacensoImportProgress::doneYears());
        $stillRemaining = count(HorizonteEducacensoImportProgress::remainingYears($allYears));
        $partial = $stillRemaining > 0;

        if (! $partial) {
            HorizonteEducacensoImportProgress::reset();
        }

        $message = $partial
            ? __('Educacenso: :done/:total anos — repita: php artisan horizonte:fortnightly-feed --phase=educacenso', [
                'done' => (string) $doneCount,
                'total' => (string) $total,
            ])
            : __('Educacenso: janela :anos indexada (:n combinações nesta execução).', [
                'anos' => implode(', ', array_map('strval', $allYears)),
                'n' => (string) $indexedTotal,
            ]);

        if ($lastFailure !== null && $partial) {
            $message .= ' '.__('Última falha: :ano.', ['ano' => (string) $lastFailure]);
        }

        Log::info('horizonte.educacenso_matriculas', [
            'years' => $allYears,
            'batch' => $batch,
            'indexed' => $indexedTotal,
            'partial' => $partial,
            'ok' => $batchOk,
        ]);

        return [
            'success' => ! $partial || $doneCount > 0,
            'partial' => $partial,
            'message' => $message,
            'indexed' => $indexedTotal,
            'educacenso_done' => $doneCount,
            'educacenso_total' => $total,
            'debug_lines' => $debugLines,
        ];
    }

    /**
     * @param  list<int>  $years
     */
    private function windowHasIndexedRows(array $years): bool
    {
        if ($years === []) {
            return false;
        }

        return InepCensoMunicipioMatricula::query()
            ->whereIn('ano', $years)
            ->where('matriculas_total', '>', 0)
            ->exists();
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
