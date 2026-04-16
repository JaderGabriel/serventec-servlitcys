<?php

namespace App\Console\Commands;

use App\Services\Inep\SaebOfficialMunicipalImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('saeb:import-official')]
#[Description('Descarrega séries SAEB oficiais por município (IBGE) e grava o JSON em IEDUCAR_SAEB_JSON_PATH')]
class SaebImportOfficialCommand extends Command
{
    public function handle(SaebOfficialMunicipalImportService $official): int
    {
        $result = $official->importFromOfficialTemplate();
        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
