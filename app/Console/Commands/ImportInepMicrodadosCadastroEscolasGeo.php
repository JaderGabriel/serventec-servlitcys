<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use App\Support\InepMicrodadosCadastroEscolasPath;
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
    {--skip-if-missing=0 : Se 1, termina com sucesso se o ficheiro não existir (apenas aviso)}'
)]
#[Description('Lê MICRODADOS_CADASTRO_ESCOLAS_*.CSV do INEP e atualiza APENAS linhas school_unit_geos existentes (INEP válido)')]
class ImportInepMicrodadosCadastroEscolasGeo extends Command
{
    /** @var list<string> */
    private const INEP_HEADER_ALIASES = ['co_entidade', 'codigo_inep', 'nu_inep', 'inep', 'cod_inep', 'cod_inep_escola'];

    /** @var list<string> */
    private const LAT_HEADER_ALIASES = ['nu_latitude', 'latitude', 'lat', 'vl_latitude', 'y'];

    /** @var list<string> */
    private const LNG_HEADER_ALIASES = ['nu_longitude', 'longitude', 'lng', 'vl_longitude', 'x'];

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
            : (string) config('ieducar.inep_geocoding.microdados_cadastro_escolas_path', 'inep/MICRODADOS_CADASTRO_ESCOLAS_*.csv');
        $path = InepMicrodadosCadastroEscolasPath::resolve($rel);

        if ($path === null || ! is_readable($path)) {
            if ((string) $this->option('skip-if-missing') === '1') {
                $this->warn('Ficheiro MICRODADOS_CADASTRO_ESCOLAS não encontrado; import omitido.');
                $this->line('Configure IEDUCAR_INEP_MICRODADOS_CADASTRO_ESCOLAS (disco public / storage/app/public) ou use --path=');
                $this->line('Valor configurado: '.$rel);

                return self::SUCCESS;
            }

            $this->error('Ficheiro não encontrado ou ilegível. Coloque o CSV em storage/app/public/ (ex.: inep/MICRODADOS_CADASTRO_ESCOLAS_2024.csv) ou defina IEDUCAR_INEP_MICRODADOS_CADASTRO_ESCOLAS.');
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

            return self::SUCCESS;
        }

        $neededIneps = $this->queryNeededInepCodes($cityIdList, $onlyMissing, $alsoMap);
        if ($neededIneps === []) {
            $this->info('Nenhum código INEP em falta no escopo; nada a importar.');

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

        $map = [];
        foreach ($header as $i => $h) {
            $map[mb_strtolower(trim((string) $h))] = $i;
        }

        $inepIdx = $this->pickColumnIndex($map, self::INEP_HEADER_ALIASES);
        $latIdx = $this->pickColumnIndex($map, self::LAT_HEADER_ALIASES);
        $lngIdx = $this->pickColumnIndex($map, self::LNG_HEADER_ALIASES);

        if ($inepIdx === null || $latIdx === null || $lngIdx === null) {
            fclose($fh);
            $this->error('Cabeçalho deve incluir coluna INEP (ex.: CO_ENTIDADE) e latitude/longitude (ex.: NU_LATITUDE, NU_LONGITUDE). Colunas encontradas: '.implode(', ', array_keys($map)));

            return self::FAILURE;
        }

        $hits = [];

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $inepRaw = $row[$inepIdx] ?? '';
            $inep = $this->parseInep($inepRaw);
            if ($inep <= 0 || ! isset($neededFlip[$inep])) {
                continue;
            }

            $lat = $this->parseCoordinate($row[$latIdx] ?? null);
            $lng = $this->parseCoordinate($row[$lngIdx] ?? null);
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

        return self::SUCCESS;
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

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $aliases
     */
    private function pickColumnIndex(array $map, array $aliases): ?int
    {
        foreach ($aliases as $a) {
            if (isset($map[$a])) {
                return $map[$a];
            }
        }

        return null;
    }

    private function resolveDelimiter(string $configured, string $firstLine): string
    {
        $configured = trim($configured);
        if ($configured !== '') {
            return $configured;
        }

        $semi = substr_count($firstLine, ';');
        $comma = substr_count($firstLine, ',');

        return $semi >= $comma ? ';' : ',';
    }

    private function parseInep(mixed $raw): int
    {
        $s = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($s === '') {
            return 0;
        }
        if (strlen($s) > 8) {
            $s = substr($s, -8);
        }

        return (int) $s;
    }

    private function parseCoordinate(mixed $raw): ?float
    {
        $v = trim((string) $raw);
        if ($v === '' || $v === 'null') {
            return null;
        }
        $v = str_replace(',', '.', str_replace(' ', '', $v));
        if (! is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
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
