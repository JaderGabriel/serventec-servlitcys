<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteIbgeMunicipalGeoImportService;
use App\Support\Horizonte\HorizonteIbgeMunicipalGeoImportProgress;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteImportMunicipalGeoCommand extends Command
{
    protected $signature = 'horizonte:import-municipal-geo
                            {--reset : Reiniciar histórico de passos recentes}
                            {--all : Importar todas as UFs pendentes até concluir}
                            {--ufs-per-step= : UFs processadas por invocação}
                            {--uf= : Processar apenas uma UF}
                            {--force : Rebuscar malha IBGE mesmo com cache válido}
                            {--skip-malha : Não descarregar malha (só área a partir do cache)}
                            {--skip-area : Descarregar malha sem persistir áreas}';

    protected $description = 'Importa malha municipal IBGE (GeoJSON por UF) e área territorial (km²) para o Horizonte';

    public function handle(HorizonteIbgeMunicipalGeoImportService $import): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $ufRaw = trim((string) $this->option('uf'));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            $this->error(__('UF inválida: :uf', ['uf' => $ufRaw]));

            return self::FAILURE;
        }

        if ((bool) $this->option('reset')) {
            HorizonteIbgeMunicipalGeoImportProgress::reset();
            $this->warn(__('Histórico de passos recentes reiniciado.'));
        }

        $this->info(__('Horizonte — malha municipal IBGE + área territorial (nacional)'));
        $this->renderProgressHeader();

        $ufsPerStepOption = $this->option('ufs-per-step');
        $ufsPerStep = $ufsPerStepOption !== null && $ufsPerStepOption !== ''
            ? max(1, (int) $ufsPerStepOption)
            : (int) config('horizonte.municipal_geo.ufs_per_step', 1);

        $baseOptions = [
            'uf' => $ufRaw !== '' ? $ufRaw : null,
            'ufs_per_step' => $ufsPerStep,
            'force' => (bool) $this->option('force'),
            'malha' => ! (bool) $this->option('skip-malha'),
            'area' => ! (bool) $this->option('skip-area'),
            'on_step' => fn (string $message): mixed => $this->line($message),
        ];

        if ((bool) $this->option('all')) {
            return $this->runUntilComplete($import, $baseOptions);
        }

        $result = $import->importNextUfBatch($baseOptions);
        $this->renderResult($result);

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $baseOptions
     */
    private function runUntilComplete(HorizonteIbgeMunicipalGeoImportService $import, array $baseOptions): int
    {
        $iteration = 0;
        $maxIterations = HorizonteIbgeMunicipalGeoImportProgress::totalUfs() + 5;

        while (! HorizonteIbgeMunicipalGeoImportProgress::isComplete() && $iteration < $maxIterations) {
            $iteration++;
            $doneBefore = HorizonteIbgeMunicipalGeoImportProgress::doneCount();
            $result = $import->importNextUfBatch($baseOptions);

            if ($result['skipped'] ?? false) {
                $this->warn($result['message']);

                return self::SUCCESS;
            }

            $this->renderSteps($result['steps'] ?? []);

            if (HorizonteIbgeMunicipalGeoImportProgress::doneCount() === $doneBefore && ($result['partial'] ?? false)) {
                $this->renderResult($result);

                return self::FAILURE;
            }

            if ($result['complete'] ?? false) {
                $this->newLine();
                $this->info($result['message']);

                return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
            }

            if (! ($result['partial'] ?? false)) {
                $this->renderResult($result);

                return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
            }
        }

        if (HorizonteIbgeMunicipalGeoImportProgress::isComplete()) {
            $this->newLine();
            $this->info(__('Malha municipal IBGE nacional completa (:done/:total UF(s)).', [
                'done' => (string) HorizonteIbgeMunicipalGeoImportProgress::doneCount(),
                'total' => (string) HorizonteIbgeMunicipalGeoImportProgress::totalUfs(),
            ]));

            return self::SUCCESS;
        }

        $this->error(__('Interrompido — ainda há UFs pendentes.'));

        return self::FAILURE;
    }

    private function renderProgressHeader(): void
    {
        $done = HorizonteIbgeMunicipalGeoImportProgress::doneCount();
        $total = HorizonteIbgeMunicipalGeoImportProgress::totalUfs();
        $remaining = HorizonteIbgeMunicipalGeoImportProgress::remainingUfs();

        $this->line(__('Progresso nacional: :done/:total UF(s) com malha municipal.', [
            'done' => (string) $done,
            'total' => (string) $total,
        ]));

        if ($remaining !== []) {
            $this->line(__('Próxima(s) UF(s): :ufs', ['ufs' => implode(', ', array_slice($remaining, 0, 6))]));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function renderSteps(array $steps): void
    {
        foreach ($steps as $step) {
            $uf = (string) ($step['uf'] ?? '');
            if ($uf === '') {
                continue;
            }

            if (! ($step['success'] ?? false)) {
                $this->error(__('✗ :uf — :msg', [
                    'uf' => $uf,
                    'msg' => (string) ($step['message'] ?? __('falha')),
                ]));

                continue;
            }

            $this->info(__('✓ :uf — :features polígonos · :imported área(s) km²', [
                'uf' => $uf,
                'features' => (string) ($step['features'] ?? 0),
                'imported' => (string) ($step['imported'] ?? 0),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderResult(array $result): void
    {
        if ($result['skipped'] ?? false) {
            $this->line($result['message']);

            return;
        }

        $this->renderSteps($result['steps'] ?? []);
        $this->newLine();
        ($result['success'] ?? false) ? $this->info($result['message']) : $this->warn($result['message']);

        if (($result['partial'] ?? false) === true) {
            $this->line(__('Retomar: php artisan horizonte:import-municipal-geo --all'));
            $this->line(__('Ou um passo: php artisan horizonte:import-municipal-geo'));
        }
    }
}
