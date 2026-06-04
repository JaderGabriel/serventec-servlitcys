<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Cadunico\CadunicoOpenDataImportService;
use App\Services\Cadunico\CadunicoTerritorioCsvFetcher;
use App\Services\Cadunico\CadunicoTerritorioCsvImportService;
use Illuminate\Console\Command;

class CadunicoPullTerritorioCommand extends Command
{
    protected $signature = 'cadunico:pull-territorio
                            {city? : ID da cidade ou omita com --all}
                            {--ano= : Ano de referência}
                            {--all : Todos os municípios com analytics}
                            {--url= : URL do CSV (sobrepõe IEDUCAR_CADUNICO_TERRITORIO_CSV_URL)}
                            {--force : Forçar novo download mesmo com cache válido}
                            {--download-only : Apenas descarregar, sem importar para a BD}';

    protected $description = 'Descarrega CSV territorial (URL configurada) e importa bairro/setor/CRAS em cadunico_territorio_snapshots';

    public function handle(
        CadunicoTerritorioCsvFetcher $fetcher,
        CadunicoTerritorioCsvImportService $import,
    ): int {
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

        $urlOverride = $this->option('url');
        $urlOverride = is_string($urlOverride) && trim($urlOverride) !== '' ? trim($urlOverride) : null;
        $force = (bool) $this->option('force');
        $downloadOnly = (bool) $this->option('download-only');

        $this->info(__('CadÚnico — pull territorial (download + import CSV)'));
        $this->line(__('Ano: :ano · Municípios: :n', ['ano' => $ano, 'n' => $cities->count()]));
        if ($urlOverride !== null) {
            $this->line(__('URL: opção --url= (por município com placeholders {ibge}, {ano}, {city_id})'));
        } else {
            $template = trim((string) config('ieducar.cadunico.territorio.csv_url_template', ''));
            if ($template === '') {
                $this->warn(__('Configure IEDUCAR_CADUNICO_TERRITORIO_CSV_URL no .env ou passe --url='));
            } else {
                $this->comment(__('Template: :t', ['t' => $template]));
            }
        }
        $this->comment(__('Destino: storage/app/:path/', [
            'path' => trim((string) config('ieducar.cadunico.territorio.storage_path', 'cadunico/territorio'), '/'),
        ]));
        if ($downloadOnly) {
            $this->comment(__('Modo: apenas download (--download-only).'));
        }
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

            $this->comment('  '.__('1/2 — Download do CSV…'));
            $fetch = $fetcher->ensureForCity($city, $ano, $urlOverride, $force);
            if (($fetch['url'] ?? null) !== null) {
                $this->comment('      '.__('URL: :u', ['u' => (string) $fetch['url']]));
            }
            $this->comment('      '.($fetch['message'] ?? ''));

            if (! ($fetch['ok'] ?? false) || ! is_string($fetch['path'] ?? null)) {
                $failures++;
                $this->error('  ✗ '.__('Download indisponível.'));
                $this->newLine();

                continue;
            }

            $path = $fetch['path'];
            $this->comment('      '.__('Ficheiro: :p', ['p' => $path]));

            if ($downloadOnly) {
                $this->line('  ✓ '.__('Download concluído (import omitido).'));
                $this->newLine();

                continue;
            }

            $this->comment('  '.__('2/2 — Importação para cadunico_territorio_snapshots…'));
            $result = $import->importFile($path, $ano, $city);
            $imported = (int) ($result['imported'] ?? 0);
            $importedTotal += $imported;

            if ($result['success'] ?? false) {
                $this->line('  ✓ '.($result['message'] ?? ''));
            } else {
                $failures++;
                $this->error('  ✗ '.($result['message'] ?? __('Importação falhou.')));
            }
            $this->newLine();
        }

        if (! $downloadOnly) {
            $this->info(__('Resumo: :ok OK, :fail falha(s), :reg território(s) importados.', [
                'ok' => $total - $failures,
                'fail' => $failures,
                'reg' => $importedTotal,
            ]));
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
