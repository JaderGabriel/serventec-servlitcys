<?php

namespace App\Console\Commands;

use App\Services\Inep\SaebPlanilhaInepImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('saeb:import-planilhas-inep
    {--years= : Anos separados por vírgula (ex.: 2021,2023); vazio = todos em config/ieducar.php}
    {--url= : URL ou caminho local de uma planilha (XLSX/XLSB ou RAR INEP)}
    {--year= : Ano de referência quando usar --url}
    {--no-download : Usar arquivos já em storage (não voltar a descarregar)}
    {--no-merge : Substituir pontos SAEB em vez de fundir}
    {--no-resolve-inep : Não mapear INEP→cod_escola}
    {--keep-cache : Manter pastas extraídas de RAR em storage}')]
#[Description('Descarrega planilhas oficiais INEP (Municípios/CO_MUNICIPIO), converte e importa SAEB para municípios cadastrados.')]
class SaebImportPlanilhasInepCommand extends Command
{
    public function handle(SaebPlanilhaInepImportService $service): int
    {
        $saebMemory = trim((string) config('horizonte.fortnightly_feed.saeb_memory_limit', '2048M'));
        if ($saebMemory !== '') {
            @ini_set('memory_limit', $saebMemory);
        }

        $merge = ! $this->option('no-merge');
        $resolveInep = ! $this->option('no-resolve-inep');
        $download = ! $this->option('no-download');
        $keepCache = (bool) $this->option('keep-cache');

        $url = trim((string) $this->option('url'));
        if ($url !== '') {
            $yearOpt = $this->option('year');
            $yearHint = is_numeric($yearOpt) ? max(1995, min(2100, (int) $yearOpt)) : null;

            $this->info(__('A processar planilha: :url', ['url' => $url]));

            $result = $service->importFromUrl($url, $yearHint, $download, $merge, $resolveInep, $keepCache);
        } else {
            $years = SaebPlanilhaInepImportService::parseYearsOption($this->option('years'));
            if ($years === []) {
                $this->error(__('Nenhum ano configurado. Use --years=2021,2023 ou defina saeb.planilha_resultados_urls.'));

                return self::FAILURE;
            }

            $this->info(__('A importar planilhas INEP para os anos: :y', ['y' => implode(', ', array_map('strval', $years))]));

            $result = $service->importYears($years, $download, $merge, $resolveInep, $keepCache);
        }

        if (! $result['ok']) {
            $this->error($result['message']);
            $this->printWarnings($result);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->printWarnings($result);
        if (isset($result['detalhes']['per_year']) && is_array($result['detalhes']['per_year'])) {
            foreach ($result['detalhes']['per_year'] as $year => $stats) {
                if (! is_array($stats)) {
                    continue;
                }
                $this->line(__('  :y — :n linha(s), :m município(s)', [
                    'y' => (string) $year,
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
        $warnings = [];
        if (! empty($result['avisos']) && is_array($result['avisos'])) {
            $warnings = array_merge($warnings, $result['avisos']);
        }
        if (isset($result['detalhes']['warnings']) && is_array($result['detalhes']['warnings'])) {
            $warnings = array_merge($warnings, $result['detalhes']['warnings']);
        }
        foreach (array_slice($warnings, 0, 30) as $w) {
            $this->warn((string) $w);
        }
    }
}
