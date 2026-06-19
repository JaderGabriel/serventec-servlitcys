<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteDataBundleService;
use Illuminate\Console\Command;

class HorizonteImportDataBundleCommand extends Command
{
    protected $signature = 'horizonte:import-data-bundle
                            {path : Caminho do ficheiro .zip exportado}
                            {--dry-run : Contar registos sem gravar}
                            {--only= : Secções separadas por vírgula (fundeb,censo,saeb,ibge_cache,sge_registry)}';

    protected $description = 'Importa pacote Horizonte exportado localmente (sem git)';

    public function handle(HorizonteDataBundleService $bundle): int
    {
        $path = trim((string) $this->argument('path'));
        if ($path !== '' && ! str_starts_with($path, '/')) {
            $candidate = storage_path('app/'.$path);
            if (is_readable($candidate)) {
                $path = $candidate;
            }
        }

        $this->info(__('Horizonte — importação de pacote de dados'));
        $this->line($path);

        $sections = $this->parseOnlyOption();
        $result = $bundle->import($path, $sections, (bool) $this->option('dry-run'));

        foreach ($result['imported'] ?? [] as $section => $count) {
            $this->line(sprintf('  · %s: %s', $section, (string) $count));
        }

        $this->newLine();
        ($result['success'] ?? false) ? $this->info($result['message']) : $this->warn($result['message']);
        $this->line(__('O cache do mapa invalida-se automaticamente pelo fingerprint dos dados.'));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, bool>
     */
    private function parseOnlyOption(): array
    {
        $only = trim((string) $this->option('only'));
        if ($only === '') {
            return [];
        }

        $allowed = ['fundeb', 'censo', 'saeb', 'ibge_cache', 'sge_registry'];
        $selected = array_map('trim', explode(',', $only));
        $sections = [];
        foreach ($allowed as $key) {
            $sections[$key] = in_array($key, $selected, true);
        }

        return $sections;
    }
}
