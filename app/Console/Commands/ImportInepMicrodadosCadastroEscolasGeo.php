<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use App\Services\Inep\InepCensoEscolaGeoAggService;
use App\Services\Inep\InepMicrodadosCadastroEscolasDownloader;
use App\Support\InepMicrodadosCadastroEscolasPath;
use App\Support\InepMicrodadosEscolasCsv;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:import-inep-microdados-cadastro-escolas-geo
    {--path= : Caminho relativo ao disco public, glob (ex.: inep/MICRODADOS_*.csv) ou absoluto; sobrepõe config}
    {--delimiter= : Separador CSV (vazio = detetar automaticamente na primeira linha)}
    {--city= : ID da cidade (opcional; todas forAnalytics se omitido)}
    {--only-missing=1 : Se 1, só INEPs em school_unit_geos ainda sem coordenadas oficiais (ou mapa com --also-map-coords)}
    {--also-map-coords=0 : Se 1, também preenche lat/lng quando vazios e a linha do CSV tiver coordenadas}
    {--threshold=100 : Limiar de divergência em metros (0 = usar config ieducar.inep_geocoding.divergence_threshold_meters)}
    {--skip-if-missing=0 : Se 1, termina com sucesso se o ficheiro não existir (apenas aviso)}
    {--fetch=1 : Se 1 e o CSV não existir, descarrega o ZIP oficial do INEP (apaga CSVs antigos do mesmo tipo antes)}'
)]
#[Description('Lê microdados do Censo (CSV local) e atualiza APENAS linhas school_unit_geos existentes (INEP válido)')]
class ImportInepMicrodadosCadastroEscolasGeo extends Command
{
    public function handle(): int
    {
        $cityOpt = $this->option('city');
        $onlyMissing = (string) $this->option('only-missing') !== '0';
        $alsoMap = (string) $this->option('also-map-coords') === '1';
        $threshold = (float) $this->option('threshold');
        if ($threshold <= 0) {
            $threshold = (float) config('ieducar.inep_geocoding.divergence_threshold_meters', 100);
        }

        $pathOpt = $this->option('path');
        $rel = is_string($pathOpt) && trim($pathOpt) !== ''
            ? trim($pathOpt)
            : (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/microdados_ed_basica_*.csv');
        $path = InepMicrodadosCadastroEscolasPath::resolve($rel);

        $wantFetch = (string) $this->option('fetch') === '1';
        $fetchEnabled = filter_var(config('ieducar.inep_geocoding.microdados_fetch_enabled', true), FILTER_VALIDATE_BOOLEAN);
        if (($path === null || ! is_readable($path)) && $wantFetch && $fetchEnabled) {
            $this->info('A descarregar microdados oficiais do INEP (ZIP → CSV) …');
            try {
                $dl = app(InepMicrodadosCadastroEscolasDownloader::class);
                $yearOpt = config('ieducar.inep_geocoding.microdados_download_year');
                $year = is_string($yearOpt) && trim($yearOpt) !== '' && ctype_digit(trim($yearOpt))
                    ? (int) trim($yearOpt)
                    : null;
                $path = $dl->downloadAndExtract($year);
                $this->info('Descarga concluída: '.$path);
            } catch (\Throwable $e) {
                $this->error('Descarga INEP falhou: '.$e->getMessage());
                if ((string) $this->option('skip-if-missing') === '1') {
                    return self::SUCCESS;
                }

                return self::FAILURE;
            }
        }

        if ($path === null || ! is_readable($path)) {
            if ((string) $this->option('skip-if-missing') === '1') {
                $this->warn('Ficheiro de microdados não encontrado; import omitido.');
                $this->line('Valor configurado: '.$rel);
                $this->line('Ative --fetch=1 (e IEDUCAR_INEP_MICRODADOS_FETCH) ou coloque o CSV em storage/app/public/inep/.');

                return self::SUCCESS;
            }

            $this->error('Ficheiro não encontrado ou ilegível.');
            $this->line('Valor resolvido: '.$rel);

            return self::FAILURE;
        }

        $this->info('A usar ficheiro: '.$path);

        $allowedCityIds = City::query()->forAnalytics();
        if ($cityOpt !== null && $cityOpt !== '') {
            $allowedCityIds->whereKey((int) $cityOpt);
        }
        $cityIdList = $allowedCityIds->pluck('id')->all();
        if ($cityIdList === []) {
            $this->warn('Nenhuma cidade no escopo.');
            $this->maybeIndexCensoGeoAggFromPath($path);

            return self::SUCCESS;
        }

        $neededIneps = $this->queryNeededInepCodes($cityIdList, $onlyMissing, $alsoMap);
        if ($neededIneps === []) {
            $this->info('Nenhum código INEP em falta no escopo; nada a importar.');
            $this->maybeIndexCensoGeoAggFromPath($path);

            return self::SUCCESS;
        }

        $neededFlip = array_fill_keys($neededIneps, true);
        $this->line('INEPs na base ainda sem coordenadas (escopo): '.count($neededIneps));

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            $this->error('Não foi possível abrir o ficheiro.');

            return self::FAILURE;
        }

        $firstLine = fgets($fh);
        if ($firstLine === false) {
            fclose($fh);
            $this->error('CSV vazio.');

            return self::FAILURE;
        }

        $delimiter = $this->resolveDelimiter((string) $this->option('delimiter'), $firstLine);
        rewind($fh);
        $header = fgetcsv($fh, 0, $delimiter);
        if ($header === false) {
            fclose($fh);
            $this->error('Cabeçalho inválido.');

            return self::FAILURE;
        }

        $map = InepMicrodadosEscolasCsv::mapHeader($header);

        $inepIdx = InepMicrodadosEscolasCsv::inepColumnIndex($map);
        if ($inepIdx === null) {
            fclose($fh);
            $this->error('Cabeçalho deve incluir coluna INEP (ex.: CO_ENTIDADE). Colunas: '.implode(', ', array_keys($map)));

            return self::FAILURE;
        }

        if (! InepMicrodadosEscolasCsv::headerHasGeoColumns($map)) {
            fclose($fh);
            $this->warn('Este ficheiro não contém colunas de latitude/longitude (comum nos microdados públicos do Censo após restrições de privacidade). Nada a atualizar em official_lat/lng; use o passo ArcGIS ou um CSV com coords.');
            $this->info('Importação terminada sem alterações (0 registos).');
            $this->maybeIndexCensoGeoAggFromPath($path);

            return self::SUCCESS;
        }

        $ll = InepMicrodadosEscolasCsv::latLngColumnIndices($map);
        $latIdx = $ll['lat'];
        $lngIdx = $ll['lng'];
        if ($latIdx === null || $lngIdx === null) {
            fclose($fh);
            $this->maybeIndexCensoGeoAggFromPath($path);

            return self::SUCCESS;
        }

        $hits = [];

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $inepRaw = $row[$inepIdx] ?? '';
            $inep = InepMicrodadosEscolasCsv::parseInepCode($inepRaw);
            if ($inep <= 0 || ! isset($neededFlip[$inep])) {
                continue;
            }

            $lat = InepMicrodadosEscolasCsv::parseCoordinate($row[$latIdx] ?? null);
            $lng = InepMicrodadosEscolasCsv::parseCoordinate($row[$lngIdx] ?? null);
            if ($lat === null || $lng === null) {
                continue;
            }
            if (abs($lat) < 0.01 && abs($lng) < 0.01) {
                continue;
            }

            $hits[$inep] = ['lat' => $lat, 'lng' => $lng];
        }
        fclose($fh);

        $this->line('Linhas úteis no CSV (INEP no escopo): '.count($hits));

        $now = now();
        $updated = 0;
        $table = (new SchoolUnitGeo)->getTable();

        foreach ($hits as $inep => $coords) {
            $lat = $coords['lat'];
            $lng = $coords['lng'];

            $locals = SchoolUnitGeo::query()
                ->whereIn('city_id', $cityIdList)
                ->where('inep_code', $inep)
                ->where('inep_code', '>', 0)
                ->get();

            foreach ($locals as $g) {
                $ieduLat = is_numeric($g->ieducar_lat) ? (float) $g->ieducar_lat : null;
                $ieduLng = is_numeric($g->ieducar_lng) ? (float) $g->ieducar_lng : null;

                $divMeters = null;
                $hasDiv = false;
                if ($ieduLat !== null && $ieduLng !== null) {
                    $divMeters = $this->haversineMeters($ieduLat, $ieduLng, $lat, $lng);
                    $hasDiv = $divMeters !== null && $divMeters >= $threshold;
                }

                $row = [
                    'official_lat' => $lat,
                    'official_lng' => $lng,
                    'official_source' => 'inep_microdados',
                    'official_seen_at' => $now,
                    'has_divergence' => $hasDiv,
                    'divergence_meters' => $divMeters,
                    'updated_at' => $now,
                ];

                if ($alsoMap && ($g->lat === null || $g->lng === null)) {
                    $row['lat'] = $lat;
                    $row['lng'] = $lng;
                }

                DB::table($table)->where('id', $g->id)->update($row);
                $updated++;
            }
        }

        $this->info("Importação concluída. Registos atualizados: {$updated}; INEPs distintos no CSV (escopo): ".count($hits).'.');
        $this->maybeIndexCensoGeoAggFromPath($path);

        return self::SUCCESS;
    }

    private function maybeIndexCensoGeoAggFromPath(string $absolutePath): void
    {
        if (! filter_var(config('ieducar.inep_geocoding.censo_geo_agg_index_on_import', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $this->line('A atualizar índice de geografia Censo (modal) a partir do mesmo CSV …');
        $n = app(InepCensoEscolaGeoAggService::class)->indexFromMicrodadosCsv($absolutePath);
        $this->info("Índice Censo (geo agregada): {$n} linhas.");
    }

    /**
     * @param  list<int>  $cityIdList
     * @return list<int>
     */
    private function queryNeededInepCodes(array $cityIdList, bool $onlyMissing, bool $alsoMap): array
    {
        $q = SchoolUnitGeo::query()
            ->whereIn('city_id', $cityIdList)
            ->whereNotNull('inep_code')
            ->where('inep_code', '>', 0);

        if ($onlyMissing) {
            $q->where(function ($w) use ($alsoMap): void {
                $w->where(function ($w2): void {
                    $w2->whereNull('official_lat')
                        ->orWhereNull('official_lng');
                });
                if ($alsoMap) {
                    $w->orWhere(function ($w2): void {
                        $w2->whereNull('lat')
                            ->orWhereNull('lng');
                    });
                }
            });
        }

        return $q->pluck('inep_code')
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveDelimiter(string $configured, string $firstLine): string
    {
        $configured = trim($configured);
        if ($configured !== '') {
            return $configured;
        }

        return InepMicrodadosEscolasCsv::delimiterFromFirstLine($firstLine);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        if (abs($lat1) > 90 || abs($lat2) > 90 || abs($lng1) > 180 || abs($lng2) > 180) {
            return null;
        }
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * (sin($dl / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $r * $c;
    }
}
