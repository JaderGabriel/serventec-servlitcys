<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use App\Services\Inep\InepCatalogoEscolasGeoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:sync-school-unit-geos-official 
    {--city= : ID da cidade (opcional; se omitido, processa todas forAnalytics)} 
    {--only-missing=1 : Se 1, só busca coordenadas oficiais quando official_lat/lng estiverem vazios} 
    {--threshold=100 : Divergência em metros para marcar has_divergence=1} 
    {--dry-run=0 : Se 1, não grava na base (apenas imprime resumo)}'
)]
#[Description('Consulta coordenadas oficiais por Código INEP e calcula divergência vs iEducar')]
class SyncSchoolUnitGeosOfficial extends Command
{
    public function __construct(private InepCatalogoEscolasGeoService $inepGeo)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cityOpt = $this->option('city');
        $onlyMissing = (string) $this->option('only-missing') !== '0';
        $dryRun = (string) $this->option('dry-run') === '1';
        $threshold = (float) $this->option('threshold');
        if ($threshold <= 0) {
            $threshold = (float) config('ieducar.inep_geocoding.divergence_threshold_meters', 100);
        }

        $citiesQ = City::query()->forAnalytics();
        if ($cityOpt !== null && $cityOpt !== '') {
            $citiesQ->whereKey((int) $cityOpt);
        }
        $cities = $citiesQ->orderBy('id')->get();
        if ($cities->isEmpty()) {
            $this->warn('Nenhuma cidade encontrada para processar.');

            return self::SUCCESS;
        }

        $totalUpdated = 0;
        $totalHits = 0;
        $totalMiss = 0;

        foreach ($cities as $cityModel) {
            /** @var City $city */
            $city = $cityModel;

            $this->info("Cidade {$city->id} — {$city->name}: consultando coordenadas oficiais (INEP) …");

            $inepEnabled = filter_var(config('ieducar.inep_geocoding.enabled', true), FILTER_VALIDATE_BOOLEAN);
            $urls = config('ieducar.inep_geocoding.arcgis_layer_query_urls');
            $urls = is_array($urls) ? array_values(array_filter(array_map(fn ($u) => is_string($u) ? trim($u) : '', $urls))) : [];
            $this->line(' - INEP geocoding ativo: '.($inepEnabled ? 'sim' : 'não'));
            if ($urls !== []) {
                $this->line(' - ArcGIS URLs (ordem de tentativa):');
                foreach ($urls as $u) {
                    $this->line('   • '.$u);
                }
            } else {
                $this->warn(' - ArcGIS URLs: vazio (configure IEDUCAR_INEP_ARCGIS_QUERY_URLS)');
            }

            $q = SchoolUnitGeo::query()
                ->where('city_id', $city->id)
                ->whereNotNull('inep_code')
                ->where('inep_code', '>', 0);

            if ($onlyMissing) {
                $q->where(function ($w) {
                    $w->whereNull('official_lat')
                        ->orWhereNull('official_lng');
                });
            }

            $codes = $q->pluck('inep_code')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->values()
                ->all();

            $this->line(' - INEPs no escopo: '.count($codes));
            if ($codes === []) {
                continue;
            }

            $hits = $this->inepGeo->lookupByInepCodes($codes);
            $totalHits += count($hits);
            $totalMiss += max(0, count($codes) - count($hits));

            $this->line(' - Hits (com coordenadas oficiais): '.count($hits));
            if ($hits === []) {
                continue;
            }

            $now = now();
            $updates = [];

            foreach ($hits as $inep => $row) {
                $lat = (float) ($row['lat'] ?? 0);
                $lng = (float) ($row['lng'] ?? 0);
                if (abs($lat) < 0.01 && abs($lng) < 0.01) {
                    continue;
                }

                // Carrega os registros locais desse INEP (pode existir mais de uma escola_id por cidade, mas normalmente 1:1)
                $locals = SchoolUnitGeo::query()
                    ->where('city_id', $city->id)
                    ->where('inep_code', (int) $inep)
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

                    $updates[] = [
                        'id' => $g->id,
                        'official_lat' => $lat,
                        'official_lng' => $lng,
                        'official_source' => 'inep_arcgis',
                        'official_seen_at' => $now,
                        'has_divergence' => $hasDiv,
                        'divergence_meters' => $divMeters,
                        'updated_at' => $now,
                    ];
                }
            }

            $this->line(' - Registos para atualizar: '.count($updates));

            if ($dryRun) {
                $this->warn(' - dry-run=1: nenhuma alteração gravada.');

                continue;
            }

            if ($updates !== []) {
                // Apenas UPDATE por id — nunca INSERT (upsert() gerava INSERT incompleto e falhava em city_id sem default).
                $table = (new SchoolUnitGeo)->getTable();
                DB::transaction(function () use ($table, $updates): void {
                    foreach ($updates as $row) {
                        $id = $row['id'];
                        unset($row['id']);
                        DB::table($table)->where('id', $id)->update($row);
                    }
                });
                $totalUpdated += count($updates);
            }
        }

        $this->info("Concluído. Updates: {$totalUpdated}; Hits: {$totalHits}; Miss: {$totalMiss}");

        return self::SUCCESS;
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
