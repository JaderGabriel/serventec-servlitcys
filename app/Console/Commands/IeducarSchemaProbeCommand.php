<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarCompatibilityProbe;
use Illuminate\Console\Command;

class IeducarSchemaProbeCommand extends Command
{
    protected $signature = 'ieducar:schema-probe
                            {city : ID da cidade}
                            {--output= : Caminho do ficheiro JSON (default: storage/app/schema_probe_{id}.json)}
                            {--ano= : Ano letivo (default: all)}';

    protected $description = 'Gera schema_probe.json com compatibilidade da base i-Educar e rotinas de discrepância';

    public function __construct(
        private CityDataConnection $cityData,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cityId = (int) $this->argument('city');
        $city = City::query()->find($cityId);
        if ($city === null) {
            $this->error(__('Cidade não encontrada: :id', ['id' => $cityId]));

            return self::FAILURE;
        }

        $ano = trim((string) $this->option('ano'));
        $filters = new IeducarFilterState(
            ano_letivo: $ano !== '' ? $ano : 'all',
            escola_id: null,
            curso_id: null,
            turno_id: null,
        );

        try {
            $document = $this->cityData->run($city, function ($db) use ($city, $filters) {
                return IeducarCompatibilityProbe::exportDocument($db, $city, $filters);
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $defaultPath = storage_path('app/schema_probe_'.$cityId.'.json');
        $path = trim((string) $this->option('output')) ?: $defaultPath;
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error(__('Falha ao serializar JSON.'));

            return self::FAILURE;
        }

        if (file_put_contents($path, $json) === false) {
            $this->error(__('Não foi possível gravar: :path', ['path' => $path]));

            return self::FAILURE;
        }

        $summary = $document['summary'] ?? [];
        $this->info(__('schema_probe gravado em :path', ['path' => $path]));
        $this->line(__('Rotinas: :avail/:total disponíveis; :issues com pendência.', [
            'avail' => (int) ($summary['routines_available'] ?? 0),
            'total' => (int) ($summary['routines_total'] ?? 0),
            'issues' => (int) ($summary['routines_with_issue'] ?? 0),
        ]));

        return self::SUCCESS;
    }
}
