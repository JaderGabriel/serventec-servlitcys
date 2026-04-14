<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Orquestra a ordem correta para encher school_unit_geos com dados do i-Educar e,
 * em seguida, coordenadas oficiais por INEP.
 *
 * O serviço InepCatalogoEscolasGeoService (passo oficial) aplica internamente, por código INEP:
 * tabela legada inep_school_geos (se existir) → CSV de fallback (config) → cache Redis → ArcGIS (URLs em config).
 */
#[Signature('app:sync-school-unit-geos-pipeline
    {--city= : ID da cidade (opcional; todas as cidades forAnalytics se omitido)}
    {--skip-ieducar=0 : Se 1, não executa app:sync-school-unit-geos (só import CSV opcional + oficial)}
    {--ieducar-only-missing=0 : Repassado a app:sync-school-unit-geos (1 = só escolas sem linha em school_unit_geos)}
    {--official-only-missing=1 : Repassado a app:sync-school-unit-geos-official}
    {--threshold=100 : Limiar de divergência em metros (0 = usar config ieducar.inep_geocoding.divergence_threshold_meters)}
    {--dry-run=0 : Se 1, só o passo oficial simula gravação (app:sync-school-unit-geos-official)}
    {--with-csv-import=0 : Se 1, executa app:import-inep-geo-fallback-csv entre i-Educar e oficial (se o ficheiro existir)}
    {--csv-path= : Caminho do CSV (opcional; repassado ao import; default em config)}
    {--csv-also-map-coords=0 : Se 1, repassado ao import CSV}
    {--skip-csv-on-missing-file=1 : Com --with-csv-import=1, se o CSV não existir apenas avisa e segue (1) ou falha (0)}'
)]
#[Description('Pipeline: (1) i-Educar → school_unit_geos; (2) opcional CSV; (3) INEP oficial + fallbacks internos + divergência')]
class SyncSchoolUnitGeosPipeline extends Command
{
    public function handle(): int
    {
        $city = $this->option('city');
        $cityArg = is_string($city) && trim($city) !== '' ? trim($city) : null;

        $skipIeducar = (string) $this->option('skip-ieducar') === '1';
        $ieducarOnlyMissing = (string) $this->option('ieducar-only-missing') === '1' ? '1' : '0';
        $officialOnlyMissing = (string) $this->option('official-only-missing') !== '0' ? '1' : '0';
        $threshold = (string) $this->option('threshold');
        $dryRun = (string) $this->option('dry-run') === '1' ? '1' : '0';
        $withCsvImport = (string) $this->option('with-csv-import') === '1';
        $skipCsvOnMissing = (string) $this->option('skip-csv-on-missing-file') !== '0';
        $csvPathOpt = $this->option('csv-path');
        $csvAlsoMap = (string) $this->option('csv-also-map-coords') === '1' ? '1' : '0';

        $this->info('=== Pipeline school_unit_geos (INEP + dados oficiais) ===');
        $this->line('Ordem: i-Educar (opc.) → import CSV offline (opc.) → coordenadas oficiais INEP.');
        $this->line('No passo INEP, o serviço tenta: tabela inep_school_geos (se existir) → CSV em config → cache → ArcGIS.');
        $this->newLine();

        $step = 0;

        if (! $skipIeducar) {
            $step++;
            $this->info("[{$step}] app:sync-school-unit-geos — i-Educar → school_unit_geos (INEP e coords da escola)");
            $args = [
                '--only-missing' => $ieducarOnlyMissing,
            ];
            if ($cityArg !== null) {
                $args['--city'] = $cityArg;
            }
            $exit = $this->call('app:sync-school-unit-geos', $args);
            if ($exit !== self::SUCCESS) {
                $this->error('O passo i-Educar terminou com código '.$exit.'.');

                return $exit;
            }
            $this->newLine();
        } else {
            $this->warn('[—] app:sync-school-unit-geos ignorado (--skip-ieducar=1).');
            $this->newLine();
        }

        if ($withCsvImport) {
            $step++;
            $this->info("[{$step}] app:import-inep-geo-fallback-csv — atualizar linhas existentes a partir do CSV");
            $importArgs = [
                '--also-map-coords' => $csvAlsoMap,
                '--skip-if-missing' => $skipCsvOnMissing ? '1' : '0',
            ];
            if (is_string($csvPathOpt) && trim($csvPathOpt) !== '') {
                $importArgs['--path'] = trim($csvPathOpt);
            }
            $exit = $this->call('app:import-inep-geo-fallback-csv', $importArgs);
            if ($exit !== self::SUCCESS) {
                $this->error('O import CSV terminou com código '.$exit.'.');

                return $exit;
            }
            $this->newLine();
        }

        $step++;
        $this->info("[{$step}] app:sync-school-unit-geos-official — INEP (ArcGIS + fallbacks) e divergência vs i-Educar");
        $officialArgs = [
            '--only-missing' => $officialOnlyMissing,
            '--threshold' => $threshold,
            '--dry-run' => $dryRun,
        ];
        if ($cityArg !== null) {
            $officialArgs['--city'] = $cityArg;
        }
        $exit = $this->call('app:sync-school-unit-geos-official', $officialArgs);
        if ($exit !== self::SUCCESS) {
            $this->error('O passo oficial INEP terminou com código '.$exit.'.');

            return $exit;
        }

        $this->newLine();
        $this->info('Pipeline concluído com sucesso.');

        return self::SUCCESS;
    }
}
