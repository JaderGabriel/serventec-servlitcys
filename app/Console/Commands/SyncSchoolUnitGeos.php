<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\SchoolUnitGeo;
use App\Services\CityDataConnection;
use App\Support\Ieducar\IeducarColumnInspector;
use App\Support\Ieducar\IeducarSchema;
use Illuminate\Database\QueryException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:sync-school-unit-geos {--city= : ID da cidade (opcional; se omitido, sincroniza todas forAnalytics)} {--only-missing=0 : Se 1, só preenche quando não existir registro local}')]
#[Description('Sincroniza coordenadas locais das escolas a partir do iEducar (por cidade)')]
class SyncSchoolUnitGeos extends Command
{
    /**
     * @param  array<int, mixed>  $bindings
     */
    private function sqlWithBindings(string $sql, array $bindings): string
    {
        // Mostra bindings de forma legível (sem executar o "replace" de forma perigosa).
        $pretty = [];
        foreach ($bindings as $b) {
            if ($b === null) {
                $pretty[] = 'NULL';
            } elseif (is_bool($b)) {
                $pretty[] = $b ? 'true' : 'false';
            } elseif (is_numeric($b)) {
                $pretty[] = (string) $b;
            } else {
                $s = (string) $b;
                $s = mb_substr($s, 0, 160, 'UTF-8');
                $pretty[] = "'".str_replace("'", "''", $s)."'";
            }
        }

        return $sql.($pretty !== [] ? '  -- bindings: ['.implode(', ', $pretty).']' : '');
    }

    private function resolveEscolaNameColumnByProbe($db, string $escolaT, array $candidates): ?string
    {
        $candidates = array_values(array_unique(array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : '',
            $candidates
        ))));

        foreach ($candidates as $col) {
            try {
                // Query mínima: se a coluna não existir, Postgres responde rápido com 42703.
                $db->table($escolaT.' as e')->select('e.'.$col)->limit(1)->get();

                return $col;
            } catch (QueryException $e) {
                $sqlState = (string) ($e->errorInfo[0] ?? '');
                if ($sqlState === '42703') { // undefined_column
                    continue;
                }
                // Outro erro (conexão, schema, permissões etc.) deve propagar.
                throw $e;
            }
        }

        return null;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cityOpt = $this->option('city');
        $onlyMissing = (string) $this->option('only-missing') === '1';

        $citiesQ = City::query()->forAnalytics();
        if ($cityOpt !== null && $cityOpt !== '') {
            $citiesQ->whereKey((int) $cityOpt);
        }
        $cities = $citiesQ->orderBy('id')->get();
        if ($cities->isEmpty()) {
            $this->warn('Nenhuma cidade encontrada para sincronizar.');

            return self::SUCCESS;
        }

        /** @var CityDataConnection $cityData */
        $cityData = app(CityDataConnection::class);

        $totalUpserts = 0;

        foreach ($cities as $cityModel) {
            /** @var City $city */
            $city = $cityModel;

            $this->info("Cidade {$city->id} — {$city->name}: sincronizando escolas…");

            try {
                $cityData->run($city, function ($db) use ($city, $onlyMissing, &$totalUpserts) {
                    $escolaT = IeducarSchema::resolveTable('escola', $city);
                    if (! IeducarColumnInspector::tableExists($db, $escolaT, $city)) {
                        $this->warn(' - tabela escola não encontrada na base iEducar.');

                        return;
                    }

                    $eId = (string) config('ieducar.columns.escola.id');
                    $eName = $this->resolveEscolaNameColumnByProbe($db, $escolaT, [
                        (string) config('ieducar.columns.escola.name'),
                        'nm_escola',
                        'nome_escola',
                        'fantasia',
                        'nm_fantasia',
                        'razao_social',
                        'descricao',
                        'nome',
                    ]);
                    if ($eName === null) {
                        // Não bloquear a sincronização por falta de nome.
                        $this->warn(' - coluna de nome da escola não encontrada na tabela escola (ignorando para sincronizar coordenadas).');
                    }

                    $latCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                        'latitude', 'lat', 'geo_lat', 'latitude_graus',
                    ], $city);
                    $lngCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                        'longitude', 'lng', 'lon', 'geo_lng', 'longitude_graus',
                    ], $city);
                    $inepCol = IeducarColumnInspector::firstExistingColumn($db, $escolaT, [
                        'codigo_inep', 'cod_escola_inep', 'inep', 'cod_inep', 'codigo_escola_inep', 'inep_escola', 'ref_cod_escola_inep',
                    ], $city);

                    if ($latCol === null || $lngCol === null) {
                        $this->warn(' - colunas latitude/longitude não encontradas na tabela escola.');

                        return;
                    }

                    $q = $db->table($escolaT.' as e')
                        ->select([
                            'e.'.$eId.' as eid',
                            'e.'.$latCol.' as la',
                            'e.'.$lngCol.' as ln',
                        ]);
                    if ($eName !== null) {
                        $q->addSelect('e.'.$eName.' as nome');
                    }

                    $q = $q
                        ->when($inepCol !== null, fn ($q) => $q->addSelect('e.'.$inepCol.' as inep'))
                        ->orderBy('e.'.$eId)
                        ->limit(5000);

                    $this->line(' - SQL (select escolas): '.$this->sqlWithBindings($q->toSql(), $q->getBindings()));

                    $rows = $q->get();

                    $this->line(' - Linhas obtidas (bruto): '.count($rows));
                    $sampleN = min(5, count($rows));
                    if ($sampleN > 0) {
                        $this->line(' - Amostra (primeiras '.$sampleN.'):');
                        for ($i = 0; $i < $sampleN; $i++) {
                            $a = (array) $rows[$i];
                            $this->line('   • eid='.(string) ($a['eid'] ?? '')
                                .' la='.(string) ($a['la'] ?? '')
                                .' ln='.(string) ($a['ln'] ?? '')
                                .' inep='.(string) ($a['inep'] ?? '')
                                .' nome='.(isset($a['nome']) ? (string) $a['nome'] : '')
                            );
                        }
                    }

                    $batch = [];
                    $now = now();

                    // Para only-missing, buscamos quais escola_ids já existem localmente.
                    $existing = [];
                    if ($onlyMissing) {
                        $existing = SchoolUnitGeo::query()
                            ->where('city_id', $city->id)
                            ->pluck('escola_id')
                            ->map(fn ($v) => (int) $v)
                            ->flip()
                            ->all();
                    }

                    foreach ($rows as $r) {
                        $a = (array) $r;
                        $eid = (int) ($a['eid'] ?? 0);
                        if ($eid <= 0) {
                            continue;
                        }
                        if ($onlyMissing && isset($existing[$eid])) {
                            continue;
                        }
                        $la = isset($a['la']) && is_numeric($a['la']) ? (float) $a['la'] : null;
                        $ln = isset($a['ln']) && is_numeric($a['ln']) ? (float) $a['ln'] : null;
                        if ($la === null || $ln === null || abs($la) > 90 || abs($ln) > 180) {
                            continue;
                        }
                        if (abs($la) < 0.01 && abs($ln) < 0.01) {
                            continue;
                        }
                        $inepRaw = $a['inep'] ?? null;
                        $inep = is_numeric($inepRaw) ? (int) $inepRaw : null;
                        if ($inep !== null && $inep <= 0) {
                            $inep = null;
                        }

                        $batch[] = [
                            'city_id' => (int) $city->id,
                            'escola_id' => $eid,
                            'inep_code' => $inep,
                            'lat' => $la,
                            'lng' => $ln,
                            'ieducar_lat' => $la,
                            'ieducar_lng' => $ln,
                            'ieducar_seen_at' => $now,
                            'has_divergence' => false,
                            'meta' => json_encode([
                                'nome' => isset($a['nome']) ? (string) $a['nome'] : '',
                            ], JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($batch === []) {
                        $this->info(' - nenhuma coordenada válida encontrada na escola.');

                        return;
                    }

                    $this->line(' - Registos válidos para upsert: '.count($batch));
                    $previewN = min(3, count($batch));
                    if ($previewN > 0) {
                        $this->line(' - Preview (primeiros '.$previewN.' para upsert):');
                        for ($i = 0; $i < $previewN; $i++) {
                            $b = $batch[$i];
                            $this->line('   • escola_id='.(string) ($b['escola_id'] ?? '')
                                .' inep_code='.(string) ($b['inep_code'] ?? '')
                                .' lat='.(string) ($b['lat'] ?? '')
                                .' lng='.(string) ($b['lng'] ?? '')
                            );
                        }
                    }

                    DB::table((new SchoolUnitGeo())->getTable())->upsert(
                        $batch,
                        ['city_id', 'escola_id'],
                        ['inep_code', 'lat', 'lng', 'ieducar_lat', 'ieducar_lng', 'ieducar_seen_at', 'has_divergence', 'meta', 'updated_at'],
                    );

                    $totalUpserts += count($batch);
                    $this->info(' - upsert: '.count($batch).' escolas com coordenadas.');
                });
            } catch (\Throwable $e) {
                $this->error(" - erro ao sincronizar: {$e->getMessage()}");
            }
        }

        $this->info("Concluído. Total upserts: {$totalUpserts}");

        return self::SUCCESS;
    }
}
