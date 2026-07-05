<?php

namespace App\Services\Cadunico;

use App\Services\Horizonte\HorizonteFortnightlyFeedService;
use App\Support\Cadunico\CadunicoEscolarizacaoFeedPhaseCatalog;
use App\Support\Cadunico\CadunicoEscolarizacaoFeedPipeline;
use Illuminate\Support\Facades\Log;

/** Abastecimento bimestral: CadÚnico + Censo para o card Escolarização na consultoria Analytics. */
final class CadunicoEscolarizacaoFeedService
{
    public function __construct(
        private readonly HorizonteFortnightlyFeedService $horizonteFeed,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string, idle?: bool, phase?: array<string, mixed>|null, pipeline?: array<string, mixed>|null}
     */
    public function runStaged(array $options = []): array
    {
        if (! filter_var(config('ieducar.cadunico.escolarizacao_feed.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'phases' => [],
                'message' => __('Abastecimento escolarização desactivado.'),
                'idle' => true,
            ];
        }

        $reset = (bool) ($options['reset'] ?? false);
        $continue = (bool) ($options['continue'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $runtimeOptions = array_merge($options, ['verbose' => (bool) ($options['verbose'] ?? false)]);

        if ($reset) {
            CadunicoEscolarizacaoFeedPipeline::forget();
            $state = CadunicoEscolarizacaoFeedPipeline::start();
        } elseif ($continue) {
            $state = CadunicoEscolarizacaoFeedPipeline::get();
            if ($state === null || ($state['status'] ?? '') !== 'running') {
                return [
                    'success' => true,
                    'phases' => [],
                    'message' => __('Nenhum abastecimento escolarização em curso.'),
                    'idle' => true,
                    'pipeline' => $state,
                ];
            }
        } else {
            $state = CadunicoEscolarizacaoFeedPipeline::get();
            if ($state === null || ! in_array($state['status'] ?? '', ['running', 'partial'], true)) {
                $state = CadunicoEscolarizacaoFeedPipeline::start();
            }
        }

        if (in_array($state['status'] ?? '', ['completed', 'partial'], true)) {
            return $this->pipelineResponse($state);
        }

        $queue = is_array($state['phase_queue'] ?? null) ? $state['phase_queue'] : [];
        $index = (int) ($state['current_index'] ?? 0);
        if ($index >= count($queue)) {
            return $this->pipelineResponse($state);
        }

        $phaseKey = (string) $queue[$index];
        if (! in_array($phaseKey, CadunicoEscolarizacaoFeedPhaseCatalog::phaseKeys(), true)) {
            return [
                'success' => false,
                'phases' => [],
                'message' => __('Fase inválida no pipeline escolarização: :key', ['key' => $phaseKey]),
            ];
        }

        $state = CadunicoEscolarizacaoFeedPipeline::markPhaseRunning($state, $phaseKey);

        $phaseResult = $dryRun
            ? $this->dryRunPhase($phaseKey)
            : $this->horizonteFeed->runPhase($phaseKey, $runtimeOptions);

        $state = CadunicoEscolarizacaoFeedPipeline::recordPhaseResult($state, $phaseResult);

        Log::info('cadunico.escolarizacao_feed.staged', [
            'run_id' => $state['run_id'] ?? null,
            'phase' => $phaseKey,
            'status' => $state['status'] ?? null,
            'success' => $phaseResult['success'] ?? false,
        ]);

        return $this->pipelineResponse($state, $phaseResult);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string}
     */
    public function runAll(array $options = []): array
    {
        $phases = [];
        foreach (CadunicoEscolarizacaoFeedPhaseCatalog::phaseKeys() as $phaseKey) {
            $result = ($options['dry_run'] ?? false)
                ? $this->dryRunPhase($phaseKey)
                : $this->horizonteFeed->runPhase($phaseKey, $options);
            $phases[] = $result;
            if (! ($result['success'] ?? false)) {
                break;
            }
        }

        return [
            'success' => collect($phases)->every(static fn (array $p): bool => (bool) ($p['success'] ?? false)),
            'phases' => $phases,
            'message' => collect($phases)->every(static fn (array $p): bool => (bool) ($p['success'] ?? false))
                ? __('Abastecimento escolarização concluído.')
                : __('Abastecimento escolarização concluído com falhas — reveja os logs.'),
        ];
    }

    /**
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string, phase?: array<string, mixed>|null, pipeline?: array<string, mixed>|null}
     */
    private function pipelineResponse(array $state, ?array $lastPhase = null): array
    {
        $phaseResults = [];
        foreach (is_array($state['phases'] ?? null) ? $state['phases'] : [] as $row) {
            if (is_array($row['result'] ?? null)) {
                $phaseResults[] = $row['result'];
            }
        }

        return [
            'success' => (bool) ($state['success'] ?? collect($phaseResults)->every(static fn (array $p): bool => (bool) ($p['success'] ?? false))),
            'phases' => $lastPhase !== null ? [$lastPhase] : $phaseResults,
            'message' => (string) ($state['message'] ?? ($lastPhase['message'] ?? '')),
            'phase' => $lastPhase,
            'pipeline' => $state,
        ];
    }

    /**
     * @return array{key: string, success: bool, message: string, dry_run: true}
     */
    private function dryRunPhase(string $phaseKey): array
    {
        return [
            'key' => $phaseKey,
            'success' => true,
            'message' => __('[dry-run] :label', ['label' => CadunicoEscolarizacaoFeedPhaseCatalog::label($phaseKey)]),
            'dry_run' => true,
        ];
    }
}
