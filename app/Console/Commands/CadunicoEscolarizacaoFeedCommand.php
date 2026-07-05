<?php

namespace App\Console\Commands;

use App\Services\Cadunico\CadunicoEscolarizacaoFeedService;
use App\Support\Cadunico\CadunicoEscolarizacaoFeedPhaseCatalog;
use App\Support\Cadunico\CadunicoEscolarizacaoFeedScheduleCadence;
use Illuminate\Console\Command;

class CadunicoEscolarizacaoFeedCommand extends Command
{
    protected $signature = 'cadunico:escolarizacao-feed
                            {--dry-run : Listar fases sem executar importações}
                            {--all : Executar CadÚnico + Censo numa só invocação}
                            {--staged : Uma fase por invocação (recomendado em produção)}
                            {--continue : Continuar pipeline em curso}
                            {--reset : Reiniciar pipeline do zero}';

    protected $description = 'Abastecimento bimestral CadÚnico + Censo para o card Escolarização (Analytics)';

    public function handle(CadunicoEscolarizacaoFeedService $feed): int
    {
        $memory = trim((string) config('ieducar.cadunico.escolarizacao_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        if (! filter_var(config('ieducar.cadunico.escolarizacao_feed.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn(__('Abastecimento escolarização desactivado (IEDUCAR_CADUNICO_ESCOLARIZACAO_FEED_ENABLED=false).'));

            return self::SUCCESS;
        }

        $this->info(__('CadÚnico — abastecimento bimestral do card Escolarização'));
        $this->line(CadunicoEscolarizacaoFeedScheduleCadence::summary());

        $options = [
            'dry_run' => (bool) $this->option('dry-run'),
            'reset' => (bool) $this->option('reset'),
            'continue' => (bool) $this->option('continue'),
            'verbose' => $this->getOutput()->isVerbose(),
        ];

        if ($this->option('all')) {
            $result = $feed->runAll($options);
        } elseif ($this->shouldRunStaged()) {
            $result = $feed->runStaged($options);
        } else {
            $result = $feed->runAll($options);
        }

        if (($result['idle'] ?? false) === true) {
            $this->line($result['message']);

            return self::SUCCESS;
        }

        foreach ($result['phases'] as $phaseRow) {
            $this->renderPhaseLine($phaseRow);
        }

        if (filled($result['message'] ?? null)) {
            ($result['success'] ?? false) ? $this->info($result['message']) : $this->warn($result['message']);
        }

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function shouldRunStaged(): bool
    {
        if ($this->option('staged')) {
            return true;
        }
        if ($this->option('continue') || $this->option('reset')) {
            return true;
        }

        return filter_var(config('ieducar.cadunico.escolarizacao_feed.staged', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $phaseRow
     */
    private function renderPhaseLine(array $phaseRow): void
    {
        $key = (string) ($phaseRow['key'] ?? '');
        $label = CadunicoEscolarizacaoFeedPhaseCatalog::label($key);
        $message = trim((string) ($phaseRow['message'] ?? ''));
        $success = (bool) ($phaseRow['success'] ?? false);
        $skipped = (bool) ($phaseRow['skipped'] ?? false);
        $dryRun = (bool) ($phaseRow['dry_run'] ?? false);

        $prefix = $dryRun ? '[dry-run] ' : ($skipped ? '⊘ ' : ($success ? '✓ ' : '✗ '));
        $this->line($prefix.$label.($message !== '' ? ' — '.$message : ''));
    }
}
