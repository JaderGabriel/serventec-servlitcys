<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use App\Services\Inep\InepCatalogoEscolasGeoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:probe-inep-geo-fallbacks
    {--codes= : Lista separada por vírgula de códigos INEP (ex.: 29309255,31090841)}
    {--city= : ID da cidade: usa inep_code distintos de school_unit_geos}
    {--limit=30 : Máximo de INEPs quando usar --city}
    {--json=0 : Se 1, imprime só JSON (útil para scripts)}'
)]
#[Description('Executa e mostra a saída de cada fallback de geocodificação INEP (tabela local, Redis, ArcGIS)')]
class ProbeInepGeoFallbacks extends Command
{
    public function __construct(private InepCatalogoEscolasGeoService $inepGeo)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $codesOpt = $this->option('codes');
        $cityOpt = $this->option('city');
        $limit = max(1, min(200, (int) $this->option('limit')));
        $asJson = (string) $this->option('json') === '1';

        $codes = [];
        if (is_string($codesOpt) && trim($codesOpt) !== '') {
            foreach (preg_split('/\s*,\s*/', trim($codesOpt)) as $p) {
                if ($p !== '') {
                    $codes[] = $p;
                }
            }
        }

        if ($codes === [] && $cityOpt !== null && $cityOpt !== '') {
            $city = City::query()->forAnalytics()->whereKey((int) $cityOpt)->first();
            if ($city === null) {
                $this->error('Cidade não encontrada ou sem analytics.');

                return self::FAILURE;
            }
            $codes = SchoolUnitGeo::query()
                ->where('city_id', $city->id)
                ->whereNotNull('inep_code')
                ->where('inep_code', '>', 0)
                ->distinct()
                ->orderBy('inep_code')
                ->limit($limit)
                ->pluck('inep_code')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();
        }

        if ($codes === []) {
            $this->warn('Nenhum código INEP. Use --codes=... ou --city=ID.');

            return self::FAILURE;
        }

        $diag = $this->inepGeo->diagnoseInepGeocodingFallbacks($codes);

        if ($asJson) {
            $this->line(json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('=== Diagnóstico de fallbacks INEP (geocodificação) ===');
        $this->line('inep_geocoding.enabled: '.($diag['inep_geocoding_enabled'] ? 'true' : 'false'));
        $this->line('códigos normalizados: '.implode(', ', $diag['codes_normalized'] ?? []));
        $this->newLine();

        $fb1 = $diag['fallback_1_local_table_inep_school_geos'] ?? [];
        $this->info('[1] Tabela local `inep_school_geos`');
        $this->line('    existe: '.(($fb1['exists'] ?? false) ? 'sim' : 'não'));
        if (! empty($fb1['error'])) {
            $this->warn('    erro: '.$fb1['error']);
        }
        foreach ($fb1['rows'] ?? [] as $ic => $r) {
            $this->line('    INEP '.$ic.': valid_coords='.($r['valid_coords'] ? 'sim' : 'não').' lat='.($r['lat'] ?? 'null').' lng='.($r['lng'] ?? 'null'));
        }
        if (($fb1['exists'] ?? false) && ($fb1['rows'] ?? []) === []) {
            $this->line('    (nenhuma linha para os códigos pedidos)');
        }
        $this->newLine();

        $this->info('[2] Cache Redis (chave inep_geo_v2_<INEP>)');
        foreach ($diag['fallback_2_redis_cache'] ?? [] as $code => $c) {
            $this->line('    '.$code.': present='.($c['present'] ? 'sim' : 'não')
                .' miss_marker='.($c['is_miss_marker'] ?? false ? 'sim' : 'não')
                .' valid_coords='.($c['valid_coords'] ?? false ? 'sim' : 'não')
                .' key='.$c['cache_key']);
        }
        $this->newLine();

        $fbCsv = $diag['fallback_2b_csv_local_scope'] ?? [];
        $this->info('[2b] CSV fallback (escopo: só INEPs em school_unit_geos + cidades forAnalytics)');
        $this->line('    ativo: '.(($fbCsv['enabled'] ?? false) ? 'sim' : 'não'));
        $this->line('    path: '.($fbCsv['path'] ?? ''));
        $this->line('    legível: '.(($fbCsv['readable'] ?? false) ? 'sim' : 'não'));
        $this->line('    hits para os códigos pedidos: '.($fbCsv['count'] ?? 0).' → '.implode(', ', $fbCsv['inep_hits'] ?? []));
        $this->newLine();

        $fb4 = $diag['fallback_4_school_unit_geos_by_inep'] ?? [];
        $this->info('[4] school_unit_geos (fallback por INEP em cache agregado)');
        $this->line('    ativo: '.(($fb4['enabled'] ?? false) ? 'sim' : 'não'));
        $this->line('    hits: '.($fb4['count'] ?? 0).' → '.implode(', ', $fb4['inep_hits'] ?? []));
        $this->newLine();

        $this->info('[3] ArcGIS (cada URL da config; cada tentativa de WHERE)');
        foreach ($diag['fallback_3_arcgis'] ?? [] as $arc) {
            $this->line('    URL #'.($arc['index'] ?? '?').': '.($arc['url'] ?? ''));
            foreach ($arc['where_attempts'] ?? [] as $wa) {
                if (isset($wa['exception'])) {
                    $this->warn('      WHERE '.$wa['where'].' → EXCEÇÃO: '.$wa['exception']);
                    continue;
                }
                $this->line('      WHERE '.$wa['where']);
                $this->line('        http='.$wa['http_status'].' ok='.($wa['ok'] ? 'sim' : 'não')
                    .' features='.$wa['feature_count']
                    .($wa['arcgis_error'] ? ' arcgis_error='.$wa['arcgis_error'] : ''));
                if (! empty($wa['sample_inep_from_features'])) {
                    $this->line('        amostra INEP em features: '.implode(', ', $wa['sample_inep_from_features']));
                }
            }
            $hits = $arc['fetch_parsed_hits'] ?? [];
            $this->line('    → parse final (fetchFromArcgis): '.count($hits).' hit(s) INEP: '.implode(', ', array_keys($hits)));
            $this->newLine();
        }

        $this->info('[Resumo] Onde cada INEP seria resolvido (ordem: local → CSV → Redis → ArcGIS → school_unit_geos)');
        foreach ($diag['merged_like_lookup']['would_resolve'] ?? [] as $ic => $src) {
            $this->line('    '.$ic.' → '.($src ?: 'none'));
        }

        $this->newLine();
        $this->comment('Dica: saída JSON completa com --json=1');

        return self::SUCCESS;
    }
}
