<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteFortnightlyFeedService;
use App\Support\Horizonte\HorizonteFortnightlyFeedPhaseCatalog;
use App\Support\Horizonte\HorizonteFortnightlyFeedPipeline;
use Illuminate\Console\Command;

class HorizonteFortnightlyFeedCommand extends Command
{
    protected $signature = 'horizonte:fortnightly-feed
                            {--dry-run : Listar fases sem executar importações}
                            {--all : Executar todas as fases numa só invocação (mais exigente em RAM)}
                            {--staged : Executar uma fase por invocação (recomendado em produção)}
                            {--continue : Continuar pipeline activo (próxima fase)}
                            {--reset : Reiniciar pipeline e executar a primeira fase}
                            {--phase= : Executar apenas a fase indicada (fundeb_receita, censo_matriculas, …)}
                            {--skip-fundeb : Ignorar sincronização FUNDEB (CSV receita FNDE)}
                            {--skip-censo : Ignorar indexação Censo matrículas}
                            {--skip-saeb : Ignorar planilhas SAEB INEP}
                            {--skip-ibge : Ignorar aquecimento catálogo IBGE}
                            {--skip-sge : Ignorar registo de sistemas de gestão educacional (SGE)}
                            {--skip-verify : Ignorar verificação public-data:check-official}';

    protected $description = 'Abastecimento quinzenal de dados públicos para o mapa Horizonte (FUNDEB nacional, Censo, SAEB, IBGE)';

    public function handle(HorizonteFortnightlyFeedService $feed): int
    {
        if (! filter_var(config('horizonte.fortnightly_feed.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn(__('Rotina quinzenal Horizonte desactivada (HORIZONTE_FORTNIGHTLY_FEED_ENABLED=false).'));

            return self::SUCCESS;
        }

        $this->info(__('Horizonte — abastecimento quinzenal de dados públicos'));
        $this->line(__('Exercício de referência: :ano (:origem)', [
            'ano' => (string) config('horizonte.reference_year'),
            'origem' => $this->referenceYearOriginLabel(),
        ]));

        $options = $this->feedOptions();

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
            $result = $feed->run($options);
        }

        if (($result['idle'] ?? false) === true) {
            $this->line($result['message']);

            return self::SUCCESS;
        }

        foreach ($result['phases'] as $phaseRow) {
            $this->renderPhaseLine($phaseRow);
        }

        if (is_array($result['pipeline'] ?? null)) {
            $this->renderPipelineSummary($result['pipeline']);
        }

        $this->newLine();
        ($result['success'] ?? false) ? $this->info($result['message']) : $this->warn($result['message']);
        $this->line(__('Mapa: :url', ['url' => route('dashboard.horizonte')]));
        $this->line(__('Filas: :url', ['url' => route('admin.sync-queue.index').'#fila-horizonte']));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function feedOptions(): array
    {
        return [
            'dry_run' => (bool) $this->option('dry-run'),
            'skip_fundeb' => (bool) $this->option('skip-fundeb'),
            'skip_censo' => (bool) $this->option('skip-censo'),
            'skip_saeb' => (bool) $this->option('skip-saeb'),
            'skip_ibge' => (bool) $this->option('skip-ibge'),
            'skip_sge' => (bool) $this->option('skip-sge'),
            'skip_verify' => (bool) $this->option('skip-verify'),
        ];
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
    private function renderPhaseLine(array $phase): void
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
}
