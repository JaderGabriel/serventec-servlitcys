<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use Illuminate\Console\Command;

class SyncCadunicoCity extends Command
{
    protected $signature = 'cadunico:sync-city
        {city? : ID da cidade ou omita com --all}
        {--ano= : Ano de referência}
        {--all : Sincronizar todos os municípios com analytics}';

    protected $description = 'Sincroniza CadÚnico/Cecad (API → cache → CSV) para município(s)';

    public function handle(CadunicoOpenDataImportService $import): int
    {
        $ano = $this->option('ano') !== null
            ? (int) $this->option('ano')
            : CadunicoOpenDataImportService::suggestedImportYear();

        $cities = $this->option('all')
            ? City::query()->forAnalytics()->get()
            : collect([(int) $this->argument('city')])->filter()->map(
                fn (int $id) => City::query()->find($id)
            )->filter();

        if ($cities->isEmpty()) {
            $this->error(__('Indique city_id ou use --all.'));

            return self::FAILURE;
        }

        $failures = 0;
        foreach ($cities as $city) {
            if (! $city instanceof City) {
                continue;
            }
            $result = $import->importForCity($city, $ano);
            $this->line($city->name.': '.($result['message'] ?? ''));
            foreach ($result['attempts'] ?? [] as $attempt) {
                $this->comment('  · '.$attempt);
            }
            if (! ($result['success'] ?? false)) {
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
