<?php

namespace App\Services\Horizonte;

use App\Models\City;
use App\Models\SaebIndicatorPoint;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Cadunico\CadunicoAutoSyncService;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Services\Ibge\IbgeSidraMunicipalDemographyService;
use App\Services\Inep\InepCensoMunicipioMatriculasIndexer;
use App\Services\Inep\SaebPlanilhaInepImportService;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteFortnightlyFeedMonolithicProgress;
use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;
use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use App\Support\Horizonte\HorizonteIbgeWarmProgress;
use App\Support\Horizonte\HorizonteSaebImportProgress;
use App\Support\Horizonte\HorizonteSidraImportProgress;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\InepMicrodadosCadastroEscolasPath;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Abastecimento bimestral de dados públicos para o mapa Horizonte (cobertura nacional).
 */
final class HorizonteFortnightlyFeedService
{
    public function __construct(
        private readonly FundebFndeReceitaCsvService $fundebReceita,
        private readonly FundebMunicipioReferenceRepository $fundebReferences,
        private readonly InepCensoMunicipioMatriculasIndexer $censoIndexer,
        private readonly SaebPlanilhaInepImportService $saebPlanilhas,
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
        private readonly HorizonteMunicipalSgeRegistryService $sgeRegistry,
        private readonly HorizonteFortnightlyFeedNotifier $notifier,
        private readonly CadunicoAutoSyncService $cadunicoAutoSync,
        private readonly IbgeSidraMunicipalDemographyService $sidraDemography,
        private readonly HorizonteTesouroTransferSyncService $tesouroTransferSync,
    ) {}

    /**
     * @param  array<string, bool>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string, pipeline?: array<string, mixed>|null, idle?: bool, phase?: array<string, mixed>|null}
     */
    public function runStaged(array $options = []): array
    {
        $disabled = $this->disabledPayload();
        if ($disabled !== null) {
            return $disabled;
        }

        $feedOptions = $this->feedOptionsForPipeline($options);
        $runtimeOptions = $this->attachRuntimeOptions($feedOptions, $options);
        $reset = (bool) ($options['reset'] ?? false);
        $continue = (bool) ($options['continue'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);

        if ($reset) {
            HorizonteFortnightlyFeedPipeline::forgetIncludingIbgeProgress();
            $state = HorizonteFortnightlyFeedPipeline::start($feedOptions);
        } elseif ($continue) {
            $state = HorizonteFortnightlyFeedPipeline::get();
            if ($state === null || ($state['status'] ?? '') !== 'running') {
                return [
                    'success' => true,
                    'phases' => [],
                    'message' => __('Nenhum abastecimento Horizonte em curso.'),
                    'idle' => true,
                    'pipeline' => $state,
                ];
            }
            $feedOptions = $this->mergeStoredFeedOptions($feedOptions, is_array($state['options'] ?? null) ? $state['options'] : []);
            $runtimeOptions = $this->attachRuntimeOptions($feedOptions, $options);
        } else {
            $state = HorizonteFortnightlyFeedPipeline::get();
            if ($state === null || ! in_array($state['status'] ?? '', ['running', 'partial'], true)) {
                $state = HorizonteFortnightlyFeedPipeline::start($feedOptions);
            } else {
                $feedOptions = $this->mergeStoredFeedOptions($feedOptions, is_array($state['options'] ?? null) ? $state['options'] : []);
                $runtimeOptions = $this->attachRuntimeOptions($feedOptions, $options);
            }
        }

        $this->debugLog($runtimeOptions, $this->scopeIntroMessage($runtimeOptions));

        if (($state['status'] ?? '') === 'completed' || ($state['status'] ?? '') === 'partial') {
            return $this->pipelineResponse($state);
        }

        $queue = is_array($state['phase_queue'] ?? null) ? $state['phase_queue'] : [];
        $index = (int) ($state['current_index'] ?? 0);
        if ($index >= count($queue)) {
            return $this->pipelineResponse($state);
        }

        $phaseKey = (string) $queue[$index];
        $state = HorizonteFortnightlyFeedPipeline::markPhaseRunning($state, $phaseKey);

        $phaseResult = $dryRun
            ? $this->dryRunPhase($phaseKey)
            : $this->runPhase($phaseKey, $runtimeOptions);

        if ($verbose = (bool) ($runtimeOptions['verbose'] ?? false)) {
            $this->debugLog($runtimeOptions, __('▶ :label', [
                'label' => HorizonteFortnightlyFeedPhaseCatalog::label($phaseKey),
            ]));
            $this->emitPhaseDebugLines($runtimeOptions, $phaseResult);
        }

        $state = HorizonteFortnightlyFeedPipeline::recordPhaseResult($state, $phaseResult);

        if (! $dryRun && ! ($phaseResult['partial'] ?? false)) {
            $this->notifier->phaseFinished(
                (string) ($state['run_id'] ?? ''),
                $phaseResult,
                $index + 1,
                count($queue),
            );
            if (! in_array($state['status'] ?? '', ['running'], true)) {
                $this->notifier->cycleFinished($state);
            }
        }

        Log::info('horizonte.fortnightly_feed.staged', [
            'run_id' => $state['run_id'] ?? null,
            'phase' => $phaseKey,
            'status' => $state['status'] ?? null,
            'success' => $phaseResult['success'] ?? false,
        ]);

        return $this->pipelineResponse($state, $phaseResult);
    }

    /**
     * @param  array<string, bool>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string}
     */
    public function runSinglePhase(string $phaseKey, array $options = []): array
    {
        $disabled = $this->disabledPayload();
        if ($disabled !== null) {
            return $disabled;
        }

        if ((bool) ($options['reset'] ?? false)) {
            $this->resetIncrementalProgressForPhase($phaseKey);
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $phaseResult = $dryRun ? $this->dryRunPhase($phaseKey) : $this->runPhase($phaseKey, $options);

        if (! $dryRun) {
            $this->notifier->phaseFinished('manual-'.$phaseKey, $phaseResult, 1, 1);
        }

        return [
            'success' => (bool) ($phaseResult['success'] ?? false),
            'phases' => [$phaseResult],
            'message' => (string) ($phaseResult['message'] ?? ''),
            'phase' => $phaseResult,
        ];
    }

    private function resetIncrementalProgressForPhase(string $phaseKey): void
    {
        match ($phaseKey) {
            'saeb_planilhas' => HorizonteSaebImportProgress::reset(),
            'ibge_catalog' => HorizonteIbgeWarmProgress::reset(),
            'sidra_demography' => HorizonteSidraImportProgress::reset(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function runPhase(string $phaseKey, array $options = []): array
    {
        $this->applyResourceLimits();
        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);

        $result = match ($phaseKey) {
            'fundeb_receita' => $this->syncFundebReceitaNacional($refYear, $options),
            'censo_matriculas' => $this->indexCensoMatriculas($options),
            'cadunico_sync' => $this->syncCadunicoNacional($options),
            'sidra_demography' => $this->importSidraDemography($options),
            'repasses_tesouro' => $this->syncRepassesTesouro($refYear, $options),
            'saeb_planilhas' => $this->importSaebPlanilhasNacional($options),
            'ibge_catalog' => $this->warmIbgeCatalog($options),
            'sge_registry' => $this->syncSgeRegistry($options),
            'official_check' => $this->runOfficialCheck($options),
            default => [
                'success' => false,
                'message' => __('Fase Horizonte desconhecida: :key', ['key' => $phaseKey]),
            ],
        };

        return array_merge(['key' => $phaseKey], $result);
    }

    /**
     * @param  array<string, bool>  $options
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string}
     */
    public function run(array $options = []): array
    {
        $disabled = $this->disabledPayload();
        if ($disabled !== null) {
            return $disabled;
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $continue = (bool) ($options['continue'] ?? false);
        $reset = (bool) ($options['reset'] ?? false);
        $skipOptions = $this->normalizedSkipOptions($options);
        $feedOptions = $this->feedOptionsForPipeline($options);
        $runtimeOptions = $this->attachRuntimeOptions($feedOptions, $options);
        $queue = HorizonteFortnightlyFeedPhaseCatalog::queueFromOptions($skipOptions);
        $phases = [];

        if ($reset) {
            HorizonteFortnightlyFeedMonolithicProgress::forget();
            HorizonteIbgeWarmProgress::reset();
            HorizonteSaebImportProgress::reset();
            HorizonteSidraImportProgress::reset();
            HorizonteFortnightlyFeedMonolithicProgress::start($queue, $feedOptions);
            $this->debugLog($runtimeOptions, __('Reinício --all — :n fase(s) na fila.', ['n' => (string) count($queue)]));
            $this->debugLog($runtimeOptions, $this->scopeIntroMessage($runtimeOptions));
        } elseif ($continue) {
            $remainingPhases = HorizonteFortnightlyFeedMonolithicProgress::remainingPhases();
            $ibgePending = HorizonteIbgeWarmProgress::remainingUfs();
            $saebPending = HorizonteSaebImportProgress::remainingYears($this->resolveSaebYears());
            if ($remainingPhases === [] && $ibgePending === [] && $saebPending === []) {
                return [
                    'success' => true,
                    'phases' => [],
                    'message' => __('Nenhum abastecimento --all em curso para continuar.'),
                    'idle' => true,
                    'staged' => false,
                ];
            }
            if (! HorizonteFortnightlyFeedMonolithicProgress::isRunning()) {
                HorizonteFortnightlyFeedMonolithicProgress::start($queue, $feedOptions);
            } else {
                $stored = HorizonteFortnightlyFeedMonolithicProgress::get();
                $feedOptions = $this->mergeStoredFeedOptions($feedOptions, is_array($stored['options'] ?? null) ? $stored['options'] : []);
                $runtimeOptions = $this->attachRuntimeOptions($feedOptions, $options);
            }
            $this->debugLog($runtimeOptions, __('Continuar — :pending fase(s) pendente(s), IBGE :ibge UF(s), SAEB :saeb ano(s).', [
                'pending' => (string) count(HorizonteFortnightlyFeedMonolithicProgress::remainingPhases()),
                'ibge' => (string) count($ibgePending),
                'saeb' => (string) count($saebPending),
            ]));
        } elseif (HorizonteFortnightlyFeedMonolithicProgress::isRunning()) {
            $stored = HorizonteFortnightlyFeedMonolithicProgress::get();
            $feedOptions = $this->mergeStoredFeedOptions($feedOptions, is_array($stored['options'] ?? null) ? $stored['options'] : []);
            $runtimeOptions = $this->attachRuntimeOptions($feedOptions, $options);
            $this->debugLog($runtimeOptions, __('Retomando execução --all em curso — :n fase(s) pendente(s).', [
                'n' => (string) count(HorizonteFortnightlyFeedMonolithicProgress::remainingPhases()),
            ]));
        } else {
            HorizonteFortnightlyFeedMonolithicProgress::start($queue, $feedOptions);
            $this->debugLog($runtimeOptions, __('Início --all — :n fase(s) na fila.', ['n' => (string) count($queue)]));
            $this->debugLog($runtimeOptions, $this->scopeIntroMessage($runtimeOptions));
        }

        $completed = HorizonteFortnightlyFeedMonolithicProgress::completedPhases();
        $pendingCount = count(array_diff($queue, $completed));
        $step = 0;

        foreach ($queue as $phaseKey) {
            if (in_array($phaseKey, $completed, true)) {
                $this->debugLog($runtimeOptions, __('⊘ :label — já concluída, a saltar.', [
                    'label' => HorizonteFortnightlyFeedPhaseCatalog::label($phaseKey),
                ]));
                continue;
            }

            $step++;
            $this->debugLog($runtimeOptions, __('▶ Fase :step/:total — :label', [
                'step' => (string) $step,
                'total' => (string) max(1, $pendingCount),
                'label' => HorizonteFortnightlyFeedPhaseCatalog::label($phaseKey),
            ]));

            if (in_array($phaseKey, ['ibge_catalog', 'saeb_planilhas', 'sidra_demography'], true)) {
                do {
                    $result = $dryRun ? $this->dryRunPhase($phaseKey) : $this->runPhase($phaseKey, $runtimeOptions);
                    $this->emitPhaseDebugLines($runtimeOptions, $result);
                    if (! $dryRun) {
                        gc_collect_cycles();
                    }
                } while (! $dryRun && ($result['partial'] ?? false));

                $phases[] = $result;
                if (($result['success'] ?? false) && ! ($result['partial'] ?? false)) {
                    HorizonteFortnightlyFeedMonolithicProgress::markPhaseDone($phaseKey);
                }
                continue;
            }

            $result = $dryRun ? $this->dryRunPhase($phaseKey) : $this->runPhase($phaseKey, $runtimeOptions);
            $this->emitPhaseDebugLines($runtimeOptions, $result);
            $phases[] = $result;

            if (($result['success'] ?? false) && ! $dryRun) {
                HorizonteFortnightlyFeedMonolithicProgress::markPhaseDone($phaseKey);
                gc_collect_cycles();
            }
        }

        $remaining = HorizonteFortnightlyFeedMonolithicProgress::remainingPhases();
        $allDone = $remaining === [];

        Log::info('horizonte.fortnightly_feed', [
            'success' => collect($phases)->every(static fn (array $p): bool => (bool) ($p['success'] ?? false)),
            'phases' => array_map(static fn (array $p): string => (string) ($p['key'] ?? '?'), $phases),
            'continue' => $continue,
            'remaining' => $remaining,
        ]);

        $hasWarnings = collect($phases)->contains(
            static fn (array $p): bool => (bool) ($p['skipped'] ?? false) || ! ($p['success'] ?? false),
        );
        $usable = $this->feedHasUsableOutput($phases);

        $result = [
            'success' => $usable,
            'phases' => $phases,
            'message' => $allDone
                ? ($usable
                    ? ($hasWarnings
                        ? __('Abastecimento Horizonte concluído com avisos — mapa usa os dados disponíveis (fases em falha não bloqueiam).')
                        : __('Abastecimento Horizonte concluído — cache do mapa invalida-se automaticamente pelo fingerprint dos dados.'))
                    : __('Abastecimento Horizonte concluído sem dados novos — reveja os logs e o hub Dados públicos.'))
                : __('Abastecimento parcial — execute --all --continue para retomar (:n fase(s) pendente(s)).', [
                    'n' => (string) count($remaining),
                ]),
            'staged' => false,
            'remaining_phases' => $remaining,
            'monolithic' => HorizonteFortnightlyFeedMonolithicProgress::get(),
        ];

        if (! $dryRun && $allDone) {
            $runId = 'monolithic-'.now()->format('Ymd-His');
            $result['run_id'] = $runId;
            $this->storeFeedResult($result);
            foreach ($phases as $i => $phase) {
                $this->notifier->phaseFinished($runId, $phase, $i + 1, count($phases));
            }
            $this->notifier->cycleFinished([
                'run_id' => $runId,
                'success' => $usable,
                'message' => $result['message'],
                'phase_queue' => array_column($phases, 'key'),
            ]);
        } elseif (! $dryRun && ! $allDone) {
            $this->debugLog($options, __('Parcial — pendente: :list', [
                'list' => implode(', ', array_map(
                    static fn (string $k): string => HorizonteFortnightlyFeedPhaseCatalog::label($k),
                    $remaining,
                )),
            ]), 'warn');
        }

        return $result;
    }

    /**
     * @return array{success: bool, message: string, phases: list<array<string, mixed>>}|null
     */
    private function disabledPayload(): ?array
    {
        if (! (bool) config('horizonte.enabled', true)) {
            return [
                'success' => false,
                'phases' => [],
                'message' => __('Horizonte desactivado (HORIZONTE_ENABLED=false).'),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $lastPhase
     * @return array{success: bool, phases: list<array<string, mixed>>, message: string, pipeline?: array<string, mixed>, phase?: array<string, mixed>|null}
     */
    private function pipelineResponse(array $state, ?array $lastPhase = null): array
    {
        $phaseResults = [];
        foreach (is_array($state['phases'] ?? null) ? $state['phases'] : [] as $row) {
            if (is_array($row['result'] ?? null)) {
                $phaseResults[] = $row['result'];
            }
        }

        $running = ($state['status'] ?? '') === 'running';
        $partial = (bool) ($lastPhase['partial'] ?? false);
        $message = $partial
            ? (string) ($lastPhase['message'] ?? __('Fase em progresso — execute --continue ou aguarde o agendador.'))
            : ($running
                ? __('Fase :label concluída — :done/:total. Próxima etapa pelo agendador.', [
                    'label' => HorizonteFortnightlyFeedPhaseCatalog::label((string) ($lastPhase['key'] ?? '')),
                    'done' => (string) count($phaseResults),
                    'total' => (string) count(is_array($state['phase_queue'] ?? null) ? $state['phase_queue'] : []),
                ])
                : (string) ($state['message'] ?? __('Pipeline Horizonte actualizado.')));

        return [
            'success' => $running ? (bool) ($lastPhase['success'] ?? true) : (bool) ($state['success'] ?? false),
            'phases' => $phaseResults,
            'message' => $message,
            'pipeline' => $state,
            'phase' => $lastPhase,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dryRunPhase(string $phaseKey): array
    {
        $label = HorizonteFortnightlyFeedPhaseCatalog::label($phaseKey);

        return [
            'key' => $phaseKey,
            'success' => true,
            'skipped' => true,
            'message' => __('[dry-run] :label', ['label' => $label]),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, bool|string>
     */
    private function normalizedSkipOptions(array $options): array
    {
        return [
            'skip_fundeb' => (bool) ($options['skip_fundeb'] ?? false),
            'skip_censo' => (bool) ($options['skip_censo'] ?? false),
            'skip_cadunico' => (bool) ($options['skip_cadunico'] ?? false),
            'skip_sidra' => (bool) ($options['skip_sidra'] ?? false),
            'skip_repasses' => (bool) ($options['skip_repasses'] ?? false),
            'skip_saeb' => (bool) ($options['skip_saeb'] ?? false),
            'skip_ibge' => (bool) ($options['skip_ibge'] ?? false),
            'skip_sge' => (bool) ($options['skip_sge'] ?? false),
            'skip_verify' => (bool) ($options['skip_verify'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, bool|string>
     */
    private function feedOptionsForPipeline(array $options): array
    {
        return array_merge($this->normalizedSkipOptions($options), [
            'uf' => HorizonteUfScope::normalize($options['uf'] ?? null) ?? '',
            'verbose' => (bool) ($options['verbose'] ?? false),
        ]);
    }

    /**
     * Opções de execução (ex.: callback debug da CLI) — não serializáveis em cache.
     *
     * @param  array<string, mixed>  $cacheable
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function attachRuntimeOptions(array $cacheable, array $source): array
    {
        if (isset($source['debug']) && is_callable($source['debug'])) {
            $cacheable['debug'] = $source['debug'];
        }

        return $cacheable;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function mergeStoredFeedOptions(array $incoming, array $stored): array
    {
        $merged = array_merge($stored, $this->feedOptionsForPipeline($incoming));
        if (HorizonteUfScope::normalize($incoming['uf'] ?? null) === null && HorizonteUfScope::normalize($stored['uf'] ?? null) !== null) {
            $merged['uf'] = $stored['uf'];
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function scopeIntroMessage(array $options): string
    {
        $uf = HorizonteUfScope::normalize($options['uf'] ?? null);

        return $uf !== null
            ? __('Âmbito: UF :uf — todas as fases filtradas a esta UF.', ['uf' => $uf])
            : __('Âmbito: nacional (27 UFs).');
    }

    private function applyResourceLimits(): void
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }
        @set_time_limit(max(60, (int) config('horizonte.fortnightly_feed.time_limit', 900)));
    }

    /**
     * @param  array{success: bool, phases: list<array<string, mixed>>, message: string}  $result
     */
    private function storeFeedResult(array $result): void
    {
        \App\Support\Horizonte\HorizonteFortnightlyFeedCache::put($result);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, years?: list<int>, debug_lines?: list<string>}
     */
    private function syncFundebReceitaNacional(int $refYear, array $options = []): array
    {
        $yearsConfig = config('horizonte.fortnightly_feed.fundeb_years');
        $years = is_array($yearsConfig) && $yearsConfig !== []
            ? array_values(array_unique(array_filter(array_map('intval', $yearsConfig))))
            : [$refYear, $refYear - 1];

        $this->debugLog($options, __('FUNDEB — anos: :anos', ['anos' => implode(', ', array_map('strval', $years))]));

        $scopedUf = HorizonteUfScope::normalize($options['uf'] ?? null);
        if ($scopedUf !== null) {
            $this->debugLog($options, __('FUNDEB — filtro UF :uf', ['uf' => $scopedUf]));
        }

        $cityIbgeMap = [];
        foreach (City::query()->whereNotNull('ibge_municipio')->get(['id', 'ibge_municipio']) as $city) {
            $ibgeNorm = FundebMunicipioReferenceRepository::normalizeIbge((string) $city->ibge_municipio);
            if ($ibgeNorm === null) {
                continue;
            }
            if ($scopedUf !== null && ! HorizonteUfScope::ibgeBelongsToScope($ibgeNorm, $scopedUf)) {
                continue;
            }
            $cityIbgeMap[$ibgeNorm] = (int) $city->id;
        }
        $this->debugLog($options, __('FUNDEB — :n município(s) ServLITCYS com IBGE mapeado.', ['n' => (string) count($cityIbgeMap)]));

        $imported = 0;
        $emptyYears = [];
        $debugLines = [];

        foreach ($years as $ano) {
            $this->debugLog($options, __('FUNDEB — a carregar CSV ano :ano…', ['ano' => (string) $ano]));
            $index = $this->fundebReceita->loadYearIndex($ano);
            if ($index === []) {
                $emptyYears[] = $ano;
                $this->debugLog($options, __('FUNDEB — ano :ano indisponível (CSV vazio).', ['ano' => (string) $ano]), 'warn');
                continue;
            }

            $yearImported = 0;
            foreach ($index as $ibge => $row) {
                $ibgeNorm = FundebMunicipioReferenceRepository::normalizeIbge((string) $ibge);
                if ($ibgeNorm === null) {
                    continue;
                }
                if ($scopedUf !== null && ! HorizonteUfScope::ibgeBelongsToScope($ibgeNorm, $scopedUf)) {
                    continue;
                }

                $totalReceita = (float) ($row['total_receita'] ?? 0);
                if ($totalReceita <= 0) {
                    continue;
                }

                $this->fundebReferences->upsertHorizontePortariaReceita(
                    $ibgeNorm,
                    $ano,
                    $cityIbgeMap[$ibgeNorm] ?? null,
                    [
                        'receita_total' => $totalReceita,
                        'complementacao_vaaf' => $row['complementacao_vaaf'] ?? null,
                        'complementacao_vaat' => $row['complementacao_vaat'] ?? null,
                        'complementacao_vaar' => $row['complementacao_vaar'] ?? null,
                        'fonte' => 'fnde_portaria_receita_horizonte',
                        'url_portaria' => $row['csv_url'] ?? null,
                    ],
                );
                $imported++;
                $yearImported++;
            }

            $line = __('FUNDEB — ano :ano: :n registo(s).', ['ano' => (string) $ano, 'n' => (string) $yearImported]);
            $debugLines[] = $line;
            $this->debugLog($options, $line);
        }

        if ($imported === 0 && $emptyYears !== []) {
            $allowEmpty = filter_var(
                config('horizonte.fortnightly_feed.fundeb_allow_empty', true),
                FILTER_VALIDATE_BOOLEAN,
            );
            $msg = __('CSV receita FNDE indisponível para os anos: :anos.', [
                'anos' => implode(', ', array_map('strval', $emptyYears)),
            ]);

            if ($allowEmpty) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => $msg,
                    'imported' => 0,
                    'years' => $years,
                    'debug_lines' => $debugLines,
                ];
            }

            return [
                'success' => false,
                'message' => $msg,
                'imported' => 0,
                'years' => $years,
                'debug_lines' => $debugLines,
            ];
        }

        return [
            'success' => true,
            'message' => __('FUNDEB: :n registo(s) municipal(is) actualizados (receita portaria FNDE).', [
                'n' => (string) $imported,
            ]),
            'imported' => $imported,
            'years' => $years,
            'debug_lines' => $debugLines,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, indexed?: int, debug_lines?: list<string>}
     */
    private function indexCensoMatriculas(array $options = []): array
    {
        $rel = (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/microdados_ed_basica_*.csv');
        $path = InepMicrodadosCadastroEscolasPath::resolve($rel);

        if ($path === null || ! is_readable($path)) {
            $allowSkip = filter_var(
                config('horizonte.fortnightly_feed.censo_skip_if_missing', true),
                FILTER_VALIDATE_BOOLEAN,
            );
            $msg = __('Microdados INEP indisponíveis — matrículas Censo não indexadas. Execute o pipeline geo ou coloque o CSV em storage/app/inep/.');
            if ($allowSkip) {
                return ['success' => true, 'message' => $msg, 'indexed' => 0, 'skipped' => true];
            }

            return ['success' => false, 'message' => $msg];
        }

        $this->debugLog($options, __('Censo — a indexar microdados: :path', ['path' => basename($path)]));
        $ibgeFilter = HorizonteUfScope::ibgeCodesForUf($options['uf'] ?? null, $this->ibgeCatalog);
        if ($ibgeFilter !== null) {
            $this->debugLog($options, __('Censo — filtro UF :uf (:n municípios).', [
                'uf' => (string) HorizonteUfScope::normalize($options['uf'] ?? null),
                'n' => (string) count($ibgeFilter),
            ]));
        }
        $indexed = $this->censoIndexer->indexFromMicrodadosCsv($path, $ibgeFilter);
        $this->debugLog($options, __('Censo — indexação concluída: :n combinações.', ['n' => (string) $indexed]));

        return [
            'success' => $indexed > 0 || filter_var(
                config('horizonte.fortnightly_feed.censo_allow_empty', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'message' => $indexed > 0
                ? __('Censo: :n combinações município/ano indexadas.', ['n' => (string) $indexed])
                : __('Censo: nenhuma matrícula agregada no CSV.'),
            'indexed' => $indexed,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, partial?: bool, saeb_done?: int, saeb_total?: int, debug_lines?: list<string>, skipped?: bool, imported?: int}
     */
    private function importSaebPlanilhasNacional(array $options = []): array
    {
        $saebMemory = trim((string) config('horizonte.fortnightly_feed.saeb_memory_limit', '2048M'));
        if ($saebMemory !== '') {
            @ini_set('memory_limit', $saebMemory);
        }

        if (! filter_var(config('ieducar.saeb.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'message' => __('SAEB desactivado — fase ignorada.'),
                'skipped' => true,
            ];
        }

        $allYears = $this->resolveSaebYears();

        if ($allYears === []) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('SAEB: anos não configurados — configure horizonte.fortnightly_feed.saeb_years ou saeb.planilha_resultados_urls.'),
            ];
        }

        $total = count($allYears);
        $remaining = HorizonteSaebImportProgress::remainingYears($allYears);

        if ($remaining === []) {
            $hasPoints = SaebIndicatorPoint::query()
                ->whereIn('ano', $allYears)
                ->exists();

            if ($hasPoints) {
                HorizonteSaebImportProgress::reset();

                return [
                    'success' => true,
                    'message' => __('SAEB: todos os :n ano(s) já importados.', ['n' => (string) $total]),
                    'partial' => false,
                    'saeb_done' => $total,
                    'saeb_total' => $total,
                ];
            }

            HorizonteSaebImportProgress::reset();
            $remaining = $allYears;
        }

        $yearsPerStep = max(1, (int) config('horizonte.fortnightly_feed.saeb_years_per_step', 1));
        $batch = array_slice($remaining, 0, $yearsPerStep);
        $debugLines = [];
        $totalRows = 0;
        $batchOk = true;
        $allowSoftFail = filter_var(
            config('horizonte.fortnightly_feed.censo_skip_if_missing', true),
            FILTER_VALIDATE_BOOLEAN,
        );

        $allowedIbge = HorizonteUfScope::allowedIbgeMap($options['uf'] ?? null, $this->ibgeCatalog);
        if ($allowedIbge !== null) {
            $this->debugLog($options, __('SAEB — filtro UF :uf', ['uf' => (string) HorizonteUfScope::normalize($options['uf'] ?? null)]));
        }

        $this->debugLog($options, __('SAEB — :done/:total anos concluídos; lote: :batch', [
            'done' => (string) count(HorizonteSaebImportProgress::doneYears()),
            'total' => (string) $total,
            'batch' => implode(', ', array_map('strval', $batch)),
        ]));

        foreach ($batch as $year) {
            $this->debugLog($options, __('SAEB — ano :ano: download, conversão e gravação…', ['ano' => (string) $year]));
            $memBefore = round(memory_get_usage(true) / 1024 / 1024, 1);

            $result = $this->saebPlanilhas->importSingleYearNational(
                $year,
                download: true,
                merge: true,
                resolveInep: false,
                keepCache: true,
                allowedIbge: $allowedIbge,
            );

            $rows = (int) ($result['detalhes']['rows'] ?? 0);
            $totalRows += $rows;
            $memAfter = round(memory_get_usage(true) / 1024 / 1024, 1);

            $line = __('SAEB — ano :ano: :msg · :rows linha(s) · mem :mem MB', [
                'ano' => (string) $year,
                'msg' => trim((string) ($result['message'] ?? '')),
                'rows' => (string) $rows,
                'mem' => (string) $memAfter,
            ]);
            $debugLines[] = $line;
            $this->debugLog($options, $line, ($result['ok'] ?? false) ? 'info' : 'warn');
            $this->debugLog($options, __('SAEB — ano :ano mem antes/depois: :before → :after MB', [
                'ano' => (string) $year,
                'before' => (string) $memBefore,
                'after' => (string) $memAfter,
            ]));

            if (($result['ok'] ?? false) && $this->saebYearImportedWithRows($result)) {
                HorizonteSaebImportProgress::markDone($year);
            } else {
                $batchOk = false;
                break;
            }

            gc_collect_cycles();
        }

        $doneCount = count(array_intersect($allYears, HorizonteSaebImportProgress::doneYears()));
        $stillRemaining = count(HorizonteSaebImportProgress::remainingYears($allYears));
        $partial = $stillRemaining > 0;

        if (! $partial) {
            HorizonteSaebImportProgress::reset();
        }

        return [
            'success' => $batchOk || $allowSoftFail,
            'partial' => $partial,
            'message' => $partial
                ? __('SAEB: :done/:total anos — repita: php artisan horizonte:fortnightly-feed --phase=saeb_planilhas', [
                    'done' => (string) $doneCount,
                    'total' => (string) $total,
                ])
                : __('SAEB: :n ano(s) importado(s), :rows linha(s) canónicas.', [
                    'n' => (string) $total,
                    'rows' => (string) $totalRows,
                ]),
            'saeb_done' => $doneCount,
            'saeb_total' => $total,
            'saeb_years_batch' => $batch,
            'imported' => $totalRows,
            'debug_lines' => $debugLines,
            'skipped' => ! $batchOk && $allowSoftFail && ! $partial,
            'details' => ['years' => $allYears, 'remaining' => HorizonteSaebImportProgress::remainingYears($allYears)],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, skipped?: bool}
     */
    private function syncCadunicoNacional(array $options = []): array
    {
        $this->debugLog($options, __('CadÚnico — a sincronizar agregados municipais…'));
        try {
            $fillGaps = filter_var(
                config('horizonte.cadunico_feed.fill_api_gaps', config('ieducar.cadunico.auto_sync.fill_api_gaps', true)),
                FILTER_VALIDATE_BOOLEAN,
            );
            $result = $this->cadunicoAutoSync->syncAllConfiguredYears($fillGaps);
            $imported = 0;
            foreach ($result['by_year'] ?? [] as $yearResult) {
                if (is_array($yearResult)) {
                    $imported += (int) ($yearResult['imported_nacional'] ?? 0);
                    $imported += (int) ($yearResult['gap_filled'] ?? 0);
                }
            }

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => (string) ($result['message'] ?? __('CadÚnico sincronizado.')),
                'imported' => $imported,
                'skipped' => ! ($result['success'] ?? false),
            ];
        } catch (\Throwable $e) {
            Log::warning('horizonte.cadunico_sync_failed', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => __('CadÚnico: falha na sincronização — :msg', ['msg' => $e->getMessage()]),
                'imported' => 0,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function importSidraDemography(array $options = []): array
    {
        $this->debugLog($options, __('SIDRA — população 4–17 por UF…'));

        return $this->sidraDemography->importNextUfBatch($options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int}
     */
    private function syncRepassesTesouro(int $refYear, array $options = []): array
    {
        $this->debugLog($options, __('Repasses Tesouro — FUNDEB CKAN nacional…'));

        return $this->tesouroTransferSync->syncNationalFundeb($refYear, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, matched?: int, skipped?: bool}
     */
    private function syncSgeRegistry(array $options = []): array
    {
        $this->debugLog($options, __('SGE — a sincronizar registo externo…'));
        try {
            $scopedUf = HorizonteUfScope::normalize($options['uf'] ?? null);
            $result = $this->sgeRegistry->sync($scopedUf);
            $this->debugLog($options, (string) ($result['message'] ?? ''));

            return $result;
        } catch (\Throwable $e) {
            Log::warning('horizonte.sge_registry_failed', ['message' => $e->getMessage()]);

            return [
                'success' => true,
                'skipped' => true,
                'message' => __('SGE: registo externo indisponível — mapa continua com catálogo ServLITCYS e dados públicos.'),
                'matched' => 0,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $phases
     */
    private function feedHasUsableOutput(array $phases): bool
    {
        foreach ($phases as $phase) {
            if (! ($phase['success'] ?? false)) {
                continue;
            }
            if (($phase['imported'] ?? 0) > 0
                || ($phase['indexed'] ?? 0) > 0
                || ($phase['matched'] ?? 0) > 0
                || ($phase['ufs'] ?? 0) > 0) {
                return true;
            }
            if (($phase['skipped'] ?? false) && ($phase['key'] ?? '') !== 'sge_registry') {
                return true;
            }
        }

        return collect($phases)->contains(fn (array $p): bool => (bool) ($p['success'] ?? false));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, ufs?: int, partial?: bool, ibge_done?: int, ibge_total?: int}
     */
    private function warmIbgeCatalog(array $options = []): array
    {
        $fetchCentroids = (bool) config('horizonte.map_display.fetch_remote_centroids', false);
        $singleUf = HorizonteUfScope::normalize($options['uf'] ?? null);
        if ($singleUf !== null) {
            $this->ibgeCatalog->municipalitiesForUf($singleUf, $fetchCentroids);
            gc_collect_cycles();

            return [
                'success' => true,
                'message' => __('Catálogo IBGE aquecido para UF :uf.', ['uf' => $singleUf]),
                'ufs' => 1,
                'partial' => false,
                'ibge_done' => 1,
                'ibge_total' => 1,
            ];
        }

        $allUfs = IbgeMunicipalityCatalog::brazilianUfs();
        $total = count($allUfs);
        $remaining = HorizonteIbgeWarmProgress::remainingUfs();

        if ($remaining === []) {
            HorizonteIbgeWarmProgress::reset();

            return [
                'success' => true,
                'message' => __('Catálogo IBGE já completo (:n UFs).', ['n' => (string) $total]),
                'ufs' => $total,
                'partial' => false,
                'ibge_done' => $total,
                'ibge_total' => $total,
            ];
        }

        $ufsPerStep = max(1, (int) config('horizonte.fortnightly_feed.ibge_ufs_per_step', 1));
        $batch = array_slice($remaining, 0, $ufsPerStep);
        $debugLines = [];

        foreach ($batch as $uf) {
            $this->debugLog($options, __('IBGE — aquecendo UF :uf…', ['uf' => $uf]));
            $catalog = $this->ibgeCatalog->municipalitiesForUf($uf, $fetchCentroids);
            $count = is_array($catalog) ? count($catalog) : 0;
            if ($count === 0) {
                $line = __('IBGE — UF :uf: nenhum município indexado (API indisponível ou cache vazio).', ['uf' => $uf]);
                $debugLines[] = $line;
                $this->debugLog($options, $line, 'warn');

                return [
                    'success' => false,
                    'partial' => true,
                    'message' => $line,
                    'ufs' => count(HorizonteIbgeWarmProgress::doneUfs()),
                    'ibge_done' => count(HorizonteIbgeWarmProgress::doneUfs()),
                    'ibge_total' => $total,
                    'ibge_batch' => $batch,
                    'debug_lines' => $debugLines,
                ];
            }
            $memMb = round(memory_get_usage(true) / 1024 / 1024, 1);
            $line = __('IBGE — UF :uf: :n municípios · mem :mem MB', [
                'uf' => $uf,
                'n' => (string) $count,
                'mem' => (string) $memMb,
            ]);
            $debugLines[] = $line;
            $this->debugLog($options, $line);
            gc_collect_cycles();
        }

        HorizonteIbgeWarmProgress::markDone($batch);
        $doneCount = count(HorizonteIbgeWarmProgress::doneUfs());
        $stillRemaining = $total - $doneCount;
        $partial = $stillRemaining > 0;

        if (! $partial) {
            HorizonteIbgeWarmProgress::reset();
        }

        return [
            'success' => true,
            'partial' => $partial,
            'message' => $partial
                ? __('IBGE: :done/:total UFs aquecidas — repita: php artisan horizonte:fortnightly-feed --phase=ibge_catalog', [
                    'done' => (string) $doneCount,
                    'total' => (string) $total,
                ])
                : __('Catálogo IBGE aquecido para :n UFs (coordenadas para prospectos).', [
                    'n' => (string) $total,
                ]),
            'ufs' => $doneCount,
            'ibge_done' => $doneCount,
            'ibge_total' => $total,
            'ibge_batch' => $batch,
            'debug_lines' => $debugLines,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string}
     */
    private function runOfficialCheck(array $options = []): array
    {
        if (HorizonteUfScope::isActive($options['uf'] ?? null)) {
            return [
                'success' => true,
                'message' => __('Verificação oficial ignorada — feed restrito à UF :uf.', [
                    'uf' => (string) HorizonteUfScope::normalize($options['uf'] ?? null),
                ]),
                'skipped' => true,
            ];
        }

        if (! (bool) config('public_data_availability.enabled', true)) {
            return [
                'success' => true,
                'message' => __('Verificação oficial desactivada — fase ignorada.'),
                'skipped' => true,
            ];
        }

        $this->debugLog($options, __('Verificação — a executar public-data:check-official…'));
        $exitCode = Artisan::call('public-data:check-official', ['--no-notify' => true]);
        $output = trim(Artisan::output());
        if ($output !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
                if (trim($line) !== '') {
                    $this->debugLog($options, trim($line));
                }
            }
        }

        return [
            'success' => $exitCode === 0,
            'message' => $exitCode === 0
                ? __('Verificação de fontes oficiais concluída (sem notificação).')
                : __('Verificação de fontes oficiais falhou (código :code).', ['code' => (string) $exitCode]),
            'output' => $output !== '' ? $output : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function debugLog(array $options, string $message, string $level = 'info'): void
    {
        if (! ($options['verbose'] ?? false)) {
            return;
        }

        $callback = $options['debug'] ?? null;
        if (is_callable($callback)) {
            $callback($message, $level);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $phaseResult
     */
    private function emitPhaseDebugLines(array $options, array $phaseResult): void
    {
        foreach (is_array($phaseResult['debug_lines'] ?? null) ? $phaseResult['debug_lines'] : [] as $line) {
            if (is_string($line) && $line !== '') {
                $this->debugLog($options, $line);
            }
        }

        if (($phaseResult['partial'] ?? false) && isset($phaseResult['ibge_done'], $phaseResult['ibge_total'])) {
            $this->debugLog($options, __('IBGE — progresso :done/:total UF(s).', [
                'done' => (string) $phaseResult['ibge_done'],
                'total' => (string) $phaseResult['ibge_total'],
            ]));
        }

        if (($phaseResult['partial'] ?? false) && isset($phaseResult['saeb_done'], $phaseResult['saeb_total'])) {
            $this->debugLog($options, __('SAEB — progresso :done/:total ano(s).', [
                'done' => (string) $phaseResult['saeb_done'],
                'total' => (string) $phaseResult['saeb_total'],
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function saebYearImportedWithRows(array $result): bool
    {
        if (! ($result['ok'] ?? false) || ($result['skipped'] ?? false)) {
            return false;
        }

        $details = is_array($result['detalhes'] ?? null) ? $result['detalhes'] : [];

        return (int) ($details['rows'] ?? 0) > 0;
    }

    /**
     * @return list<int>
     */
    private function resolveSaebYears(): array
    {
        $raw = config('horizonte.fortnightly_feed.saeb_years');
        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map('intval', $raw))));
        }
        if (is_string($raw) && trim($raw) !== '') {
            return SaebPlanilhaInepImportService::parseYearsOption($raw);
        }

        return SaebPlanilhaInepImportService::parseYearsOption(null);
    }
}
