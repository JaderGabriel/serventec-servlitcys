<?php

namespace App\Console\Commands;

use App\Services\Cadunico\CadunicoCecadCsvImportService;
use Illuminate\Console\Command;

class ImportCadunicoCecadCsv extends Command
{
    protected $signature = 'cadunico:import-cecad
        {path : Caminho absoluto ou relativo a storage/app do CSV Cecad}
        {--ano= : Ano de referência quando a coluna ano estiver ausente}';

    protected $description = 'Importa agregados municipais CadÚnico (exportação Cecad CSV)';

    public function handle(CadunicoCecadCsvImportService $import): int
    {
        $path = (string) $this->argument('path');
        if (! str_starts_with($path, '/')) {
            $path = storage_path('app/'.ltrim($path, '/'));
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
