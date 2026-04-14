<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use App\Support\InepGeoFallbackCsvPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:import-inep-geo-fallback-csv
    {--path= : Caminho relativo ao disco public (storage/app/public), legado app/... sob storage/, ou absoluto}
    {--delimiter=; : Separador CSV}
    {--also-map-coords=0 : Se 1, também atualiza lat/lng do mapa quando preenchidos no CSV}
    {--skip-if-missing=0 : Se 1, termina com sucesso se o ficheiro não existir (apenas aviso; útil em cron/produção)}'
)]
#[Description('Importa CSV e atualiza APENAS linhas school_unit_geos existentes (cidades forAnalytics + mesmo city_id/escola_id/inep)')]
class ImportInepGeoFallbackCsv extends Command
{
    public function handle(): int
    {
        $pathOpt = $this->option('path');
        $delim = (string) $this->option('delimiter');
        if ($delim === '') {
            $delim = ';';
        }
        $alsoMap = (string) $this->option('also-map-coords') === '1';

        $rel = is_string($pathOpt) && trim($pathOpt) !== ''
            ? trim($pathOpt)
            : (string) config('ieducar.inep_geocoding.fallback_csv_path', 'inep_geo_fallback.csv');
        $path = InepGeoFallbackCsvPath::absolute($rel);

        if (! is_readable($path)) {
            if ((string) $this->option('skip-if-missing') === '1') {
                $this->warn('CSV de fallback não encontrado; import omitido (passo opcional).');
                $this->line('Caminho resolvido: '.$path);
                $this->line('Para usar o import: coloque o ficheiro em storage/app/public/ (ou defina IEDUCAR_INEP_GEO_FALLBACK_CSV: nome relativo ao disco public ou caminho absoluto).');

                return self::SUCCESS;
            }

            $this->error('Ficheiro não encontrado ou ilegível: '.$path);
            $this->newLine();
            $this->line('O CSV de fallback é opcional (enriquecimento offline). Para corrigir:');
            $this->line('  • Colocar o CSV em storage/app/public/ (disco public) ou ajustar IEDUCAR_INEP_GEO_FALLBACK_CSV.');
            $this->line('  • Gerar/exportar noutro ambiente: php artisan app:export-inep-geo-fallback-csv');
            $this->line('  • Em automação sem ficheiro: php artisan app:import-inep-geo-fallback-csv --skip-if-missing=1');

            return self::FAILURE;
        }

        $allowedCityIds = City::query()->forAnalytics()->pluck('id')->flip()->all();

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            $this->error('Não foi possível abrir o ficheiro.');

            return self::FAILURE;
        }

        $header = fgetcsv($fh, 0, $delim);
        if ($header === false) {
            fclose($fh);
            $this->error('CSV vazio.');

            return self::FAILURE;
        }

        $map = [];
        foreach ($header as $i => $h) {
            $map[mb_strtolower(trim((string) $h))] = $i;
        }

        $need = ['city_id', 'escola_id', 'inep_code'];
        foreach ($need as $k) {
            if (! isset($map[$k])) {
                fclose($fh);
                $this->error('Cabeçalho CSV deve incluir: '.implode(', ', $need).'. Encontrado: '.implode(', ', array_keys($map)));

                return self::FAILURE;
            }
        }

        $updated = 0;
        $skipped = 0;
        $now = now();

        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            $cityId = (int) ($row[$map['city_id']] ?? 0);
            $escolaId = (int) ($row[$map['escola_id']] ?? 0);
            $inepRaw = $row[$map['inep_code']] ?? '';
            $inep = is_numeric($inepRaw) ? (int) $inepRaw : 0;
            if ($cityId <= 0 || $escolaId <= 0 || $inep <= 0) {
                $skipped++;

                continue;
            }
            if (! isset($allowedCityIds[$cityId])) {
                $this->warn("Linha ignorada: city_id {$cityId} não está em forAnalytics.");
                $skipped++;

                continue;
            }

            $geo = SchoolUnitGeo::query()
                ->where('city_id', $cityId)
                ->where('escola_id', $escolaId)
                ->where('inep_code', $inep)
                ->first();
            if ($geo === null) {
                $this->warn("Linha ignorada: não existe school_unit_geo city={$cityId} escola={$escolaId} inep={$inep}.");
                $skipped++;

                continue;
            }

            $officialLat = $this->pickFloat($row, $map, ['official_lat']);
            $officialLng = $this->pickFloat($row, $map, ['official_lng']);
            $lat = $this->pickFloat($row, $map, ['lat']);
            $lng = $this->pickFloat($row, $map, ['lng']);

            $hasOfficial = $officialLat !== null || $officialLng !== null;
            $hasMap = $alsoMap && $lat !== null && $lng !== null;
            if (! $hasOfficial && ! $hasMap) {
                $skipped++;

                continue;
            }

            $payload = [
                'official_source' => 'csv_fallback',
                'official_seen_at' => $now,
                'updated_at' => $now,
            ];
            if ($officialLat !== null) {
                $payload['official_lat'] = $officialLat;
            }
            if ($officialLng !== null) {
                $payload['official_lng'] = $officialLng;
            }
            if ($hasMap) {
                $payload['lat'] = $lat;
                $payload['lng'] = $lng;
            }

            DB::table($geo->getTable())->where('id', $geo->id)->update($payload);
            $updated++;
        }
        fclose($fh);

        $this->info("Importação concluída. Atualizados: {$updated}; ignorados: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $keys
     */
    private function pickFloat(array $row, array $map, array $keys): ?float
    {
        foreach ($keys as $k) {
            $k = mb_strtolower($k);
            if (! isset($map[$k])) {
                continue;
            }
            $v = trim((string) ($row[$map[$k]] ?? ''));
            if ($v === '' || $v === 'null') {
                continue;
            }
            if (is_numeric($v)) {
                return (float) $v;
            }
        }

        return null;
    }
}
