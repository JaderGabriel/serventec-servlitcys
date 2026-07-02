<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteEducacensoMatriculasSyncService;
use App\Support\Horizonte\HorizonteEducacensoImportProgress;
use App\Support\Horizonte\HorizonteEducacensoYearWindow;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteSyncEducacensoCommand extends Command
{
    protected $signature = 'horizonte:sync-educacenso
                            {--reset : Reiniciar progresso ano×UF}
                            {--all : Executar passos até concluir a janela}
                            {--year= : Restringir a um ano (ex.: 2024)}
                            {--uf= : Restringir a uma UF (ex.: BA)}
                            {--steps= : Passos por invocação (default: config educacenso_steps_per_step)}';

    protected $description = 'Reimporta Educacenso por ano e UF (gráfico Horizonte — segmentos e dependência)';

    public function handle(HorizonteEducacensoMatriculasSyncService $sync): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.educacenso_memory_limit', '1024M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $years = HorizonteEducacensoYearWindow::years();
        $total = HorizonteEducacensoImportProgress::totalSteps($years);

        $this->info(__('Educacenso — reimportação por ano × UF'));
        $this->line(__('Janela: :anos (:total passos)', [
            'anos' => implode(', ', array_map('strval', $years)),
            'total' => (string) $total,
        ]));

        if ((bool) $this->option('reset')) {
            HorizonteEducacensoImportProgress::reset();
            $this->warn(__('Progresso reiniciado.'));
        }

        $uf = trim((string) $this->option('uf'));
        if ($uf !== '' && HorizonteUfScope::normalize($uf) === null) {
            $this->error(__('UF inválida: :uf', ['uf' => $uf]));

            return self::FAILURE;
        }

        $yearOpt = trim((string) $this->option('year'));
        $year = $yearOpt !== '' && ctype_digit($yearOpt) ? (int) $yearOpt : null;
        if ($year !== null && ! in_array($year, $years, true)) {
            $this->error(__('Ano :ano fora da janela configurada.', ['ano' => (string) $year]));

            return self::FAILURE;
        }

        $stepsOpt = trim((string) $this->option('steps'));
        $steps = $stepsOpt !== '' && ctype_digit($stepsOpt) ? max(1, (int) $stepsOpt) : null;

        $baseOptions = array_filter([
            'reset' => false,
            'year' => $year,
            'uf' => $uf !== '' ? $uf : null,
            'steps' => $steps,
            'on_step' => function (string $message, string $level = 'info'): void {
                match ($level) {
                    'error' => $this->error($message),
                    'warn' => $this->warn($message),
                    default => $this->line($message),
                };
            },
        ], static fn ($v): bool => $v !== null);

        if ((bool) $this->option('all')) {
            return $this->runUntilComplete($sync, $years, $baseOptions);
        }

        $result = $sync->syncBatch($baseOptions);
        $this->renderResult($result);

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<int>  $years
     * @param  array<string, mixed>  $baseOptions
     */
    private function runUntilComplete(HorizonteEducacensoMatriculasSyncService $sync, array $years, array $baseOptions): int
    {
        $iteration = 0;
        $maxIterations = HorizonteEducacensoImportProgress::totalSteps($years) + 5;

        while (! HorizonteEducacensoImportProgress::isComplete($years) && $iteration < $maxIterations) {
            $iteration++;
            $doneBefore = HorizonteEducacensoImportProgress::doneStepCount();
            $result = $sync->syncBatch($baseOptions);

            if (($result['skipped'] ?? false) === true) {
                $this->warn($result['message']);

                return self::SUCCESS;
            }

            if (HorizonteEducacensoImportProgress::doneStepCount() === $doneBefore && ($result['partial'] ?? false)) {
                $this->renderResult($result);

                return self::FAILURE;
            }

            if (! ($result['partial'] ?? false)) {
                $this->newLine();
                $this->info($result['message']);

                return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
            }
        }

        if (HorizonteEducacensoImportProgress::isComplete($years)) {
            $this->newLine();
            $this->info(__('Educacenso: janela completa (:done passos).', [
                'done' => (string) HorizonteEducacensoImportProgress::doneStepCount(),
            ]));

            return self::SUCCESS;
        }

        $this->error(__('Interrompido — ainda há passos pendentes.'));

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderResult(array $result): void
    {
        foreach ($result['debug_lines'] ?? [] as $line) {
            if (is_string($line) && $line !== '' && ! str_starts_with($line, '✓') && ! str_starts_with($line, '✗')) {
                $this->line('  '.$line);
            }
        }

        $this->newLine();
        ($result['success'] ?? false) ? $this->info($result['message']) : $this->warn($result['message']);

        if (($result['partial'] ?? false) === true) {
            $this->line(__('Retomar: php artisan horizonte:sync-educacenso --all'));
            $this->line(__('Ou um passo: php artisan horizonte:sync-educacenso'));
        }
    }
}
