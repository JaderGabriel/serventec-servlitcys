<?php

namespace App\Console\Commands;

use App\Services\Inep\SaebCsvPedagogicalImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('saeb:import-csv {file : Caminho do CSV (absoluto ou relativo ao projecto)} {--no-merge : Substituir historico.json em vez de fundir} {--no-resolve-inep : Não mapear INEP→cod_escola}')]
#[Description('Importa SAEB real a partir de CSV: IBGE, ano, disciplina, etapa, valor; opcional INEP (escola) com ligação ao i-Educar.')]
class SaebImportCsvCommand extends Command
{
    public function handle(SaebCsvPedagogicalImportService $service): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));
        if ($path === null) {
            $this->error(__('Ficheiro não encontrado. Tente caminho absoluto ou relativo ao projecto.'));

            return self::FAILURE;
        }

        $merge = ! $this->option('no-merge');
        $resolveInep = ! $this->option('no-resolve-inep');

        $this->info(__('A importar :path …', ['path' => $path]));
        if (! $merge) {
            $this->warn(__('Modo sem fusão: o ficheiro JSON será regravado só com os pontos deste CSV.'));
        }

        $result = $service->importFromCsvFile($path, $merge, $resolveInep);

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        if (! empty($result['avisos']) && is_array($result['avisos'])) {
            foreach (array_slice($result['avisos'], 0, 40) as $a) {
                $this->line((string) $a);
            }
            if (count($result['avisos']) > 40) {
                $this->warn(__('… :n avisos adicionais.', ['n' => (string) (count($result['avisos']) - 40)]));
            }
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $file): ?string
    {
        if (is_file($file) && is_readable($file)) {
            return realpath($file) ?: $file;
        }

        $base = base_path(trim($file, '/'));
        if (is_file($base) && is_readable($base)) {
            return realpath($base) ?: $base;
        }

        $storage = storage_path('app/'.trim($file, '/'));
        if (is_file($storage) && is_readable($storage)) {
            return realpath($storage) ?: $storage;
        }

        return null;
    }
}
