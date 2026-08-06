<?php

namespace App\Console\Commands;

use App\Services\Inep\IdebDivulgacaoInepImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ideb:import-divulgacao-inep
    {--scopes=ai,af,em : Pacotes a importar (ai=anos iniciais, af=anos finais, em=ensino médio)}
    {--min-year= : Ano mínimo da série (default config ideb.min_year, tipicamente 2015)}
    {--no-download : Usar ZIPs já em storage/app/ideb/divulgacao}
    {--no-saeb : Não importar notas SAEB (LP/MAT) embutidas nas planilhas IDEB}
    {--only-catalog : Apenas municípios com cidade no catálogo analítico}')]
#[Description('Descarrega ZIPs oficiais IDEB (municípios), importa série histórica IDEB (+ SAEB 2025) para saeb_indicator_points via upsert.')]
class IdebImportDivulgacaoInepCommand extends Command
{
    public function handle(IdebDivulgacaoInepImportService $service): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.saeb_memory_limit', '2048M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $scopesRaw = trim((string) $this->option('scopes'));
        $scopes = array_values(array_filter(array_map(
            static fn (string $s): string => strtolower(trim($s)),
            $scopesRaw !== '' ? explode(',', $scopesRaw) : ['ai', 'af', 'em']
        )));

        $minYearOpt = $this->option('min-year');
        $minYear = is_numeric($minYearOpt) ? max(2005, min(2100, (int) $minYearOpt)) : null;

        $this->info(__('A importar divulgação IDEB (scopes: :s, min-year: :y)…', [
            's' => implode(',', $scopes),
            'y' => (string) ($minYear ?? config('ieducar.ideb.min_year', 2015)),
        ]));

        $result = $service->import(
            $scopes,
            ! $this->option('no-download'),
            ! $this->option('no-saeb'),
            $minYear,
            (bool) $this->option('only-catalog'),
        );

        if (! $result['ok']) {
            $this->error($result['message']);
            $this->printWarnings($result);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->printWarnings($result);
        if (isset($result['detalhes']['per_scope']) && is_array($result['detalhes']['per_scope'])) {
            foreach ($result['detalhes']['per_scope'] as $scope => $stats) {
                if (! is_array($stats)) {
                    continue;
                }
                if (! empty($stats['error'])) {
                    $this->warn("  {$scope}: {$stats['error']}");

                    continue;
                }
                $this->line(__('  :s (:e) — :n ponto(s), :m município(s)', [
                    's' => (string) $scope,
                    'e' => (string) ($stats['etapa'] ?? ''),
                    'n' => (string) ($stats['rows'] ?? 0),
                    'm' => (string) ($stats['municipios'] ?? 0),
                ]));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function printWarnings(array $result): void
    {
        foreach (array_slice($result['avisos'] ?? [], 0, 40) as $w) {
            $this->warn((string) $w);
        }
    }
}
