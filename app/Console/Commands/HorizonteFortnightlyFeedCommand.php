<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteFortnightlyFeedService;
use Illuminate\Console\Command;

class HorizonteFortnightlyFeedCommand extends Command
{
    protected $signature = 'horizonte:fortnightly-feed
                            {--dry-run : Listar fases sem executar importações}
                            {--skip-fundeb : Ignorar sincronização FUNDEB (CSV receita FNDE)}
                            {--skip-censo : Ignorar indexação Censo matrículas}
                            {--skip-saeb : Ignorar planilhas SAEB INEP}
                            {--skip-ibge : Ignorar aquecimento catálogo IBGE}
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

        $result = $feed->run([
            'dry_run' => (bool) $this->option('dry-run'),
            'skip_fundeb' => (bool) $this->option('skip-fundeb'),
            'skip_censo' => (bool) $this->option('skip-censo'),
            'skip_saeb' => (bool) $this->option('skip-saeb'),
            'skip_ibge' => (bool) $this->option('skip-ibge'),
            'skip_verify' => (bool) $this->option('skip-verify'),
        ]);

        foreach ($result['phases'] as $phase) {
            $label = match ((string) ($phase['key'] ?? '')) {
                'fundeb_receita' => 'FUNDEB',
                'censo_matriculas' => 'Censo',
                'saeb_planilhas' => 'SAEB',
                'ibge_catalog' => 'IBGE',
                'official_check' => __('Verificação'),
                default => (string) ($phase['key'] ?? '?'),
            };
            $ok = (bool) ($phase['success'] ?? false);
            $line = sprintf('  [%s] %s: %s', $ok ? 'OK' : '!!', $label, (string) ($phase['message'] ?? ''));
            $ok ? $this->line($line) : $this->warn($line);
        }

        $this->newLine();
        ($result['success'] ?? false) ? $this->info($result['message']) : $this->error($result['message']);
        $this->line(__('Mapa: :url', ['url' => route('dashboard.horizonte')]));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
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
