<?php

namespace App\Console\Commands;

use App\Services\Cadunico\CadunicoCecadCsvImportService;
use App\Support\Cadunico\CadunicoStoragePaths;
use App\Support\Filesystem\ContainedPathResolver;
use Illuminate\Console\Command;

class ImportCadunicoCecadCsv extends Command
{
    protected $signature = 'cadunico:import-cecad
        {path : Caminho absoluto ou relativo a storage/app do CSV Cecad}
        {--ano= : Ano de referência quando a coluna ano estiver ausente}';

    protected $description = 'Importa agregados municipais CadÚnico (exportação Cecad CSV)';

    public function handle(CadunicoCecadCsvImportService $import): int
    {
        $path = ContainedPathResolver::resolveReadableFile(
            (string) $this->argument('path'),
            [
                storage_path('app'),
                CadunicoStoragePaths::storageRoot(),
            ],
        );

        if ($path === null) {
            $this->error(__('Arquivo inválido ou fora de storage/app e cadunico/cecad.'));

            return self::FAILURE;
        }

        $ano = $this->option('ano') !== null ? (int) $this->option('ano') : null;

        $result = $import->importFile($path, $ano);

        foreach ($result['errors'] as $err) {
            $this->error($err);
        }

        $this->info(__('Importados: :n · Ignorados: :s', [
            'n' => (string) $result['imported'],
            's' => (string) $result['skipped'],
        ]));

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
