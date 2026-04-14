<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use App\Support\InepGeoFallbackCsvPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('app:export-inep-geo-fallback-csv
    {--path= : Caminho relativo ao disco public (storage/app/public), legado app/... sob storage/, ou absoluto}
    {--city= : Se definido, só exporta escolas desta cidade (ainda forAnalytics)}'
)]
#[Description('Exporta CSV só com escolas (INEP) das cidades forAnalytics — base para preencher coords e reimportar')]
class ExportInepGeoFallbackCsv extends Command
{
    public function handle(): int
    {
        $pathOpt = $this->option('path');
        $cityOpt = $this->option('city');

        $rel = is_string($pathOpt) && trim($pathOpt) !== ''
            ? trim($pathOpt)
            : (string) config('ieducar.inep_geocoding.fallback_csv_path', 'inep_geo_fallback.csv');
        $path = InepGeoFallbackCsvPath::absolute($rel);

        $citiesQ = City::query()->forAnalytics()->orderBy('id');
        if ($cityOpt !== null && $cityOpt !== '') {
            $citiesQ->whereKey((int) $cityOpt);
        }
        $cityIds = $citiesQ->pluck('id')->all();
        if ($cityIds === []) {
            $this->warn('Nenhuma cidade forAnalytics no escopo.');

            return self::FAILURE;
        }

        $rows = SchoolUnitGeo::query()
            ->whereIn('city_id', $cityIds)
            ->whereNotNull('inep_code')
            ->where('inep_code', '>', 0)
            ->with('city:id,name')
            ->orderBy('city_id')
            ->orderBy('escola_id')
            ->get();

        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $fh = fopen($path, 'wb');
        if ($fh === false) {
            $this->error('Não foi possível criar o ficheiro: '.$path);

            return self::FAILURE;
        }

        fputcsv($fh, [
            'city_id',
            'city_name',
            'escola_id',
            'inep_code',
            'lat',
            'lng',
            'ieducar_lat',
            'ieducar_lng',
            'official_lat',
            'official_lng',
            'official_source',
        ], ';');

        foreach ($rows as $r) {
            fputcsv($fh, [
                $r->city_id,
                $r->city->name ?? '',
                $r->escola_id,
                $r->inep_code,
                $r->lat,
                $r->lng,
                $r->ieducar_lat,
                $r->ieducar_lng,
                $r->official_lat,
                $r->official_lng,
                $r->official_source ?? '',
            ], ';');
        }
        fclose($fh);

        $this->info('CSV exportado: '.$path);
        $this->info('Linhas: '.$rows->count().' (apenas INEP nas cidades forAnalytics no escopo).');
        $this->comment('Edite official_lat/official_lng (ou lat/lng) e importe com: php artisan app:import-inep-geo-fallback-csv');

        return self::SUCCESS;
    }
}
