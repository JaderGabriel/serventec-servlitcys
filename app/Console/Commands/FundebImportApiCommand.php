<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Fundeb\FundebOpenDataImportService;
use Illuminate\Console\Command;

class FundebImportApiCommand extends Command
{
    protected $signature = 'fundeb:import-api
                            {city? : ID da cidade (omitir com --all)}
                            {--ano= : Ano de referência (default: ano anterior)}
                            {--nearest : Se o ano pedido não existir na API, gravar o mais recente disponível}
                            {--all : Importar todos os municípios com IBGE}';

    protected $description = 'Importa VAAF/VAAT via API (CKAN FNDE ou JSON) e grava em fundeb_municipio_references';

    public function __construct(
        private FundebOpenDataImportService $import,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $ano = (int) ($this->option('ano') ?: FundebOpenDataImportService::suggestedImportYear());
        $useNearest = (bool) $this->option('nearest');

        if ($this->option('all')) {
            $result = $this->import->importBulk($ano, $useNearest);
            $this->line($result['message']);
            foreach ($result['failed'] ?? [] as $fail) {
                $this->warn(($fail['city'] ?? '').': '.($fail['message'] ?? ''));
            }

            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $city = City::query()->find((int) $this->argument('city'));
        if ($city === null) {
            $this->error(__('Indique o ID da cidade ou use --all.'));

            return self::FAILURE;
        }

        $result = $this->import->importForCityYear($city, $ano, $useNearest);

        if ($result['success']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
