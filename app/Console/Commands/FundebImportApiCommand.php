<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Fundeb\FundebOpenDataImportService;
use Illuminate\Console\Command;

class FundebImportApiCommand extends Command
{
    protected $signature = 'fundeb:import-api
                            {city : ID da cidade}
                            {--ano= : Ano de referência (default: ano corrente)}';

    protected $description = 'Importa VAAF/VAAT via API (CKAN FNDE ou JSON) e grava em fundeb_municipio_references';

    public function __construct(
        private FundebOpenDataImportService $import,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $city = City::query()->find((int) $this->argument('city'));
        if ($city === null) {
            $this->error(__('Cidade não encontrada.'));

            return self::FAILURE;
        }

        $ano = (int) ($this->option('ano') ?: date('Y'));
        $result = $this->import->importForCityYear($city, $ano);

        if ($result['success']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
