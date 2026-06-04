<?php

namespace App\Console\Commands;

use App\Enums\AdminSyncDomain;
use App\Models\City;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use App\Services\Cadunico\CadunicoTerritorioOfficialImportService;
use Illuminate\Console\Command;

class CadunicoSyncTerritorioCommand extends Command
{
    protected $signature = 'cadunico:sync-territorio
                            {city? : ID da cidade ou omita com --all}
                            {--ano= : Ano de referência (CadÚnico + Censo)}
                            {--all : Todos os municípios com analytics}
                            {--queue : Enfileirar na fila admin-sync (recomendado em produção/cron)}';

    protected $description = 'Importa territórios oficiais IBGE (Censo 2022 bairro/setor + WFS) e rateia CadÚnico municipal';

    public function handle(
        CadunicoTerritorioOfficialImportService $import,
        AdminSyncQueueService $syncQueue,
    ): int {
        $ano = $this->option('ano') !== null
            ? (int) $this->option('ano')
            : CadunicoOpenDataImportService::suggestedImportYear();

        if ($this->option('queue')) {
            if (! $this->option('all') && $this->argument('city') === null) {
                $this->error(__('Indique city_id, --all ou use sem --queue.'));

                return self::FAILURE;
            }

            if ($this->option('all')) {
                $task = $syncQueue->dispatch(
                    AdminSyncDomain::Cadastro,
                    'sync_territorio_all',
                    __('CadÚnico — mapa territorial IBGE (:ano)', ['ano' => (string) $ano]),
                    ['ano' => $ano],
                    null,
                );
            } else {
                $cityId = (int) $this->argument('city');
                $city = City::query()->find($cityId);
                if ($city === null) {
                    $this->error(__('Município não encontrado.'));

                    return self::FAILURE;
                }
                $task = $syncQueue->dispatch(
                    AdminSyncDomain::Cadastro,
                    'sync_territorio_city',
                    __('CadÚnico — território IBGE (:city, :ano)', [
                        'city' => $city->name,
                        'ano' => (string) $ano,
                    ]),
                    ['city_id' => $city->id, 'ano' => $ano],
                    $city->id,
                );
            }

            $this->info(__('Tarefa #:id enfileirada.', ['id' => (string) $task->id]));

            return self::SUCCESS;
        }

        $cities = $this->option('all')
            ? City::query()->forAnalytics()->get()
            : collect([(int) $this->argument('city')])->filter()->map(
                fn (int $id) => City::query()->find($id)
            )->filter();

        if ($cities->isEmpty()) {
            $this->error(__('Indique city_id ou use --all.'));

            return self::FAILURE;
        }

        $this->info(__('CadÚnico — sincronização territorial (IBGE Censo 2022 + WFS)'));
        $this->line(__('Ano de referência: :ano', ['ano' => $ano]));
        $this->line($this->option('all')
            ? __('Municípios no escopo: :n (--all, analytics)', ['n' => $cities->count()])
            : __('Município único no escopo.'));
        $this->newLine();
        $this->comment(__('Fluxo por município: IBGE → snapshot CadÚnico → agregados Censo (FTP/cache) → WFS → rateio → cadunico_territorio_snapshots'));
        $this->comment(__('Pré-requisito: `cadunico:sync-city` no mesmo ano para ter base municipal 4–17.'));
        $this->newLine();

        $failures = 0;
        $importedTotal = 0;
        $total = $cities->count();
        $index = 0;

        foreach ($cities as $city) {
            if (! $city instanceof City) {
                continue;
            }
            $index++;
            $this->info(__('[:i/:t] :name (id=:id)', [
                'i' => $index,
                't' => $total,
                'name' => $city->name,
                'id' => $city->id,
            ]));

            $log = fn (string $message) => $this->comment('  '.$message);

            $result = $import->importForCity($city, $ano, $log);
            $imported = (int) ($result['imported'] ?? 0);
            $importedTotal += $imported;

            if ($result['success'] ?? false) {
                $this->line('  ✓ '.($result['message'] ?? ''));
                if (($result['fonte'] ?? '') !== '') {
                    $this->comment('    '.__('Fonte: :f', ['f' => $result['fonte']]));
                }
            } else {
                $failures++;
                $this->error('  ✗ '.($result['message'] ?? __('Falha.')));
            }
            $this->newLine();
        }

        $ok = $total - $failures;
        $this->info(__('Resumo: :ok OK, :fail falha(s), :reg registo(s) territorial(is) gravados.', [
            'ok' => $ok,
            'fail' => $failures,
            'reg' => $importedTotal,
        ]));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
