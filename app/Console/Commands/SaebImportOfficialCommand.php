<?php

namespace App\Console\Commands;

use App\Services\Inep\SaebOfficialMunicipalImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('saeb:import-official {--city-id=} {--year=}')]
#[Description('Descarrega séries SAEB oficiais por município (IBGE); fallback microdados INEP se a base estiver vazia')]
class SaebImportOfficialCommand extends Command
{
    public function handle(SaebOfficialMunicipalImportService $official): int
    {
        $options = [];
        $cityId = $this->option('city-id');
        if ($cityId !== null && $cityId !== '' && is_numeric($cityId)) {
            $options['city_id'] = (int) $cityId;
        }
        $year = $this->option('year');
        if ($year !== null && $year !== '' && is_numeric($year)) {
            $options['year'] = (int) $year;
        }

        $result = $official->importFromOfficialTemplate(null, $options);
        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
