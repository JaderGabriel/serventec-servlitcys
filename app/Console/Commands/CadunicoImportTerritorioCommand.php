<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Cadunico\CadunicoTerritorioCsvImportService;
use Illuminate\Console\Command;

class CadunicoImportTerritorioCommand extends Command
{
    protected $signature = 'cadunico:import-territorio
                            {path : Caminho do CSV (;)}
                            {--ano= : Ano de referência}
                            {--city= : ID da cidade (opcional se IBGE no CSV)}';

    protected $description = 'Importa agregados territoriais CadÚnico (bairro/setor) sem dados pessoais';

    public function handle(CadunicoTerritorioCsvImportService $import): int
    {
        $ano = (int) ($this->option('ano') ?: date('Y'));
        $cityId = $this->option('city');
        $city = $cityId !== null ? City::query()->find((int) $cityId) : null;

        $result = $import->importFile((string) $this->argument('path'), $ano, $city);
        $this->line($result['message']);

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
