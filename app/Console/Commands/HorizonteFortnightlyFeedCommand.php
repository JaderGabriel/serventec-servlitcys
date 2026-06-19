<?php

namespace App\Console\Commands;

use App\Services\Inep\SaebPlanilhaInepImportService;
use App\Services\Horizonte\HorizonteFortnightlyFeedService;
use App\Support\Horizonte\HorizonteFortnightlyFeedMonolithicProgress;
use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;
use App\Support\Horizonte\HorizonteIbgeWarmProgress;
use App\Support\Horizonte\HorizonteSaebImportProgress;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteFortnightlyFeedCommand extends Command
{
    protected $signature = 'horizonte:fortnightly-feed
                            {--dry-run : Listar fases sem executar importações}
                            {--all : Executar todas as fases numa só invocação (mais exigente em RAM)}
                            {--staged : Executar uma fase por invocação (recomendado em produção)}
                            {--continue : Continuar pipeline ou execução --all pendente}
                            {--reset : Reiniciar pipeline ou execução --all do zero}
                            {--phase= : Executar apenas a fase indicada (fundeb_receita, censo_matriculas, …)}
                            {--skip-fundeb : Ignorar sincronização FUNDEB (CSV receita FNDE)}
                            {--skip-censo : Ignorar indexação Censo matrículas}
                            {--skip-saeb : Ignorar planilhas SAEB INEP}
                            {--skip-ibge : Ignorar aquecimento catálogo IBGE}
                            {--skip-sge : Ignorar registo de sistemas de gestão educacional (SGE)}
                            {--skip-verify : Ignorar verificação public-data:check-official}
                            {--uf= : Restringir todo o abastecimento a uma UF (ex.: SP — FUNDEB, Censo, SAEB, IBGE, SGE)}';

    protected $description = 'Abastecimento bimestral de dados públicos para o mapa Horizonte (FUNDEB nacional, Censo, SAEB, IBGE)';

    public function handle(HorizonteFortnightlyFeedService $feed): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        if (! filter_var(config('horizonte.fortnightly_feed.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn(__('Rotina bimestral Horizonte desactivada (HORIZONTE_FORTNIGHTLY_FEED_ENABLED=false).'));

            return self::SUCCESS;
        }

        $verbose = (bool) $this->option('all') || $this->getOutput()->isVerbose();

        $this->info(__('Horizonte — abastecimento bimestral de dados públicos'));
        $this->line(__('Exercício de referência: :ano (:origem)', [
            'ano' => (string) config('horizonte.reference_year'),
            'origem' => $this->referenceYearOriginLabel(),
        ]));

        if ($verbose) {
            $this->line(__('Modo verbose — mensagens de cada etapa activas (--all ou -v).'));
            $this->renderPendingSummary();
        }

        $options = $this->feedOptions($verbose);

        $ufRaw = trim((string) ($options['uf'] ?? ''));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            $this->error(__('UF inválida: :uf — use sigla de estado (ex.: SP).', ['uf' => $ufRaw]));

            return self::FAILURE;
        }

        if (HorizonteUfScope::isActive($options['uf'] ?? null)) {
            $this->line(__('Âmbito: UF :uf — todas as fases filtradas a esta UF.', [
                'uf' => (string) HorizonteUfScope::normalize($options['uf'] ?? null),
            ]));
        }

        $phase = trim((string) $this->option('phase'));
        if ($phase !== '') {
            $validKeys = array_column(HorizonteFortnightlyFeedPhaseCatalog::definitions(), 'key');
            if (! in_array($phase, $validKeys, true)) {
                $this->error(__('Fase inválida: :phase', ['phase' => $phase]));

                return self::FAILURE;
            }
            $result = $feed->runSinglePhase($phase, $options);
        } elseif ($this->shouldRunStaged()) {
            $result = $feed->runStaged(array_merge($options, [
                'reset' => (bool) $this->option('reset'),
                'continue' => (bool) $this->option('continue'),
            ]));
        } else {
            $result = $feed->run(array_merge($options, [
                'continue' => (bool) $this->option('continue'),
                'reset' => (bool) $this->option('reset'),
            ]));
        }

        if (($result['idle'] ?? false) === true) {
            $this->line($result['message']);

            return self::SUCCESS;
        }

        foreach ($result['phases'] as $phaseRow) {
            $this->renderPhaseLine($phaseRow, $verbose);
        }

        if (is_array($result['pipeline'] ?? null)) {
            $this->renderPipelineSummary($result['pipeline']);
        }

        if (is_array($result['monolithic'] ?? null)) {
            $this->renderMonolithicSummary($result['monolithic'], $result['remaining_phases'] ?? []);
        }

        $this->newLine();
        ($result['success'] ?? false) ? $this->info($result['message']) : $this->warn($result['message']);

        if (! empty($result['remaining_phases'] ?? [])) {
            $this->line(__('Retomar: php artisan horizonte:fortnightly-feed --all --continue'));
        }

        $this->line(__('Mapa: :url', ['url' => route('dashboard.horizonte')]));
        $this->line(__('Filas: :url', ['url' => route('admin.sync-queue.index').'#fila-horizonte']));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function feedOptions(bool $verbose): array
    {
        return [
            'dry_run' => (bool) $this->option('dry-run'),
            'skip_fundeb' => (bool) $this->option('skip-fundeb'),
            'skip_censo' => (bool) $this->option('skip-censo'),
            'skip_saeb' => (bool) $this->option('skip-saeb'),
            'skip_ibge' => (bool) $this->option('skip-ibge'),
            'skip_sge' => (bool) $this->option('skip-sge'),
            'skip_verify' => (bool) $this->option('skip-verify'),
            'uf' => trim((string) $this->option('uf')),
            'verbose' => $verbose,
            'debug' => $verbose ? $this->makeDebugCallback() : null,
        ];
    }

    private function makeDebugCallback(): callable
    {
        return function (string $message, string $level = 'info'): void {
            match ($level) {
                'warn' => $this->warn('  '.$message),
                'error' => $this->error('  '.$message),
                default => $this->line('  '.$message),
            };
        };
    }

    private function renderPendingSummary(): void
    {
        $monolithic = HorizonteFortnightlyFeedMonolithicProgress::get();
        $ibgeRemaining = HorizonteIbgeWarmProgress::remainingUfs();
        $saebRemaining = HorizonteSaebImportProgress::remainingYears($this->resolveSaebYearsForCommand());

        if ($monolithic !== null && ($monolithic['status'] ?? '') === 'running') {
            $remaining = HorizonteFortnightlyFeedMonolithicProgress::remainingPhases();
            if ($remaining !== []) {
                $this->line(__('Pendente (--all): :list', [
                    'list' => implode(', ', array_map(
                        static fn (string $k): string => HorizonteFortnightlyFeedPhaseCatalog::label($k),
                        $remaining,
                    )),
                ]));
            }
        }

        if ($ibgeRemaining !== []) {
            $this->line(__('IBGE pendente: :ufs', ['ufs' => implode(', ', $ibgeRemaining)]));
        }

        if ($saebRemaining !== []) {
            $this->line(__('SAEB pendente: :anos', ['anos' => implode(', ', array_map('strval', $saebRemaining))]));
        }
    }

    private function shouldRunStaged(): bool
    {
        if ($this->option('all')) {
            return false;
        }

        if ($this->option('staged') || $this->option('continue') || $this->option('reset')) {
            return true;
        }

        return filter_var(config('horizonte.fortnightly_feed.staged', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $phase
     */
    private function renderPhaseLine(array $phase, bool $verbose = false): void
    {
        $label = match ((string) ($phase['key'] ?? '')) {
            'fundeb_receita' => 'FUNDEB',
            'censo_matriculas' => 'Censo',
            'saeb_planilhas' => 'SAEB',
            'ibge_catalog' => 'IBGE',
            'sge_registry' => 'SGE',
            'official_check' => __('Verificação'),
            default => (string) ($phase['key'] ?? '?'),
        };
        $ok = (bool) ($phase['success'] ?? false);
        $line = sprintf('  [%s] %s: %s', $ok ? 'OK' : '!!', $label, (string) ($phase['message'] ?? ''));
        $ok ? $this->line($line) : $this->warn($line);

        if ($verbose) {
            if (isset($phase['imported'])) {
                $this->line(__('    · importados: :n', ['n' => (string) $phase['imported']]));
            }
            if (isset($phase['indexed'])) {
                $this->line(__('    · indexados: :n', ['n' => (string) $phase['indexed']]));
            }
            if (isset($phase['ibge_done'], $phase['ibge_total'])) {
                $this->line(__('    · IBGE: :done/:total UFs', [
                    'done' => (string) $phase['ibge_done'],
                    'total' => (string) $phase['ibge_total'],
                ]));
            }
            if (isset($phase['saeb_done'], $phase['saeb_total'])) {
                $this->line(__('    · SAEB: :done/:total anos', [
                    'done' => (string) $phase['saeb_done'],
                    'total' => (string) $phase['saeb_total'],
                ]));
            }
            if (is_array($phase['ibge_batch'] ?? null) && ($phase['ibge_batch'] ?? []) !== []) {
                $this->line(__('    · lote IBGE: :ufs', ['ufs' => implode(', ', $phase['ibge_batch'])]));
            }
            if (is_array($phase['saeb_years_batch'] ?? null) && ($phase['saeb_years_batch'] ?? []) !== []) {
                $this->line(__('    · lote SAEB: :anos', ['anos' => implode(', ', array_map('strval', $phase['saeb_years_batch']))]));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $pipeline
     */
    private function renderPipelineSummary(array $pipeline): void
    {
        $this->newLine();
        $this->line(__('Pipeline :id — :status', [
            'id' => (string) ($pipeline['run_id'] ?? '—'),
            'status' => (string) ($pipeline['status'] ?? '—'),
        ]));

        foreach (is_array($pipeline['phases'] ?? null) ? $pipeline['phases'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $status = (string) ($row['status'] ?? 'pending');
            $label = HorizonteFortnightlyFeedPhaseCatalog::label((string) ($row['key'] ?? ''));
            $this->line(sprintf('    · %s [%s]', $label, $status));
        }

        if (($pipeline['status'] ?? '') === 'running' && filled($pipeline['current_phase'] ?? null)) {
            $this->line(__('Próxima fase pendente: :phase (agendador ou --continue)', [
                'phase' => HorizonteFortnightlyFeedPhaseCatalog::label((string) $pipeline['current_phase']),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $monolithic
     * @param  list<string>  $remainingPhases
     */
    private function renderMonolithicSummary(array $monolithic, array $remainingPhases): void
    {
        $this->newLine();
        $this->line(__('Execução --all :id — :status', [
            'id' => (string) ($monolithic['run_id'] ?? '—'),
            'status' => (string) ($monolithic['status'] ?? '—'),
        ]));

        $completed = is_array($monolithic['completed_phases'] ?? null) ? $monolithic['completed_phases'] : [];
        foreach (is_array($monolithic['phase_queue'] ?? null) ? $monolithic['phase_queue'] : [] as $key) {
            $label = HorizonteFortnightlyFeedPhaseCatalog::label((string) $key);
            $status = in_array($key, $completed, true) ? 'completed' : (in_array($key, $remainingPhases, true) ? 'pending' : 'pending');
            $this->line(sprintf('    · %s [%s]', $label, $status));
        }
    }

    private function referenceYearOriginLabel(): string
    {
        $raw = env('HORIZONTE_REFERENCE_YEAR');
        if ($raw !== null && $raw !== '' && is_numeric(trim((string) $raw))) {
            $year = (int) trim((string) $raw);
            if (\App\Support\Horizonte\HorizonteReferenceYear::isPlausible($year)) {
                return __('HORIZONTE_REFERENCE_YEAR');
            }
        }

        return __('ano civil anterior — defina HORIZONTE_REFERENCE_YEAR para fixar');
    }

    /**
     * @return list<int>
     */
    private function resolveSaebYearsForCommand(): array
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
