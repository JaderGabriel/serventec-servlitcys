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

    private function resolveColumnByProbe($db, string $table, array $candidates, string $alias = 'e'): ?string
    {
        $candidates = array_values(array_unique(array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : '',
            $candidates
        ))));

        foreach ($candidates as $col) {
            try {
                $db->table($table.' as '.$alias)->select($alias.'.'.$col)->limit(1)->get();

                return $col;
            } catch (QueryException $e) {
                $sqlState = (string) ($e->errorInfo[0] ?? '');
                if ($sqlState === '42703') { // undefined_column
                    continue;
                }
                throw $e;
            }
        }

        return null;
    }

    /**
     * Tenta descobrir onde está o Código INEP quando não existe na tabela escola.
     *
     * @return ?array{table: string, alias: string, fk: string, col: string}
     */
    private function resolveInepJoinSpecByProbe($db, City $city, string $escolaTable, string $eAlias, string $eIdCol): ?array
    {
        if ($db->getDriverName() !== 'pgsql') {
            return null;
        }

        $schema = IeducarSchema::effectiveSchema($city);
        if ($schema === '') {
            $schema = 'pmieducar';
        }

        $joinTables = [
            $schema.'.escola_complemento',
            $schema.'.escola_complementar',
            $schema.'.escola_inep',
            $schema.'.escola_dados_inep',
            $schema.'.escola_dados',
        ];
        $fkCandidates = [
            'ref_cod_escola',
            'cod_escola',
            'escola_id',
            'ref_escola',
        ];
        $colCandidates = array_values(array_unique(array_filter([
            (string) config('ieducar.columns.escola.inep'),
            'codigo_inep',
            'cod_inep',
            'inep',
            'inep_code',
            'cod_escola_inep',
            'codigo_escola_inep',
        ])));

        $joinAlias = 'ei';

        foreach ($joinTables as $jt) {
            foreach ($fkCandidates as $fk) {
                foreach ($colCandidates as $col) {
                    try {
                        $q = $db->table($escolaTable.' as '.$eAlias)
                            ->leftJoin($jt.' as '.$joinAlias, $eAlias.'.'.$eIdCol, '=', $joinAlias.'.'.$fk)
                            ->select([$eAlias.'.'.$eIdCol.' as eid', $joinAlias.'.'.$col.' as inep'])
                            ->limit(1);
                        $q->get();

                        return [
                            'table' => $jt,
                            'alias' => $joinAlias,
                            'fk' => $fk,
                            'col' => $col,
                        ];
                    } catch (QueryException $e) {
                        $sqlState = (string) ($e->errorInfo[0] ?? '');
                        if (in_array($sqlState, ['42703', '42P01'], true)) { // undefined_column / undefined_table
                            continue;
                        }
                        throw $e;
                    }
                }
            }
        }

        return null;
    }

    private function relatorioGetNomeEscolaExpr($db, string $alias, string $eId): ?string
    {
        try {
            if ($db->getDriverName() !== 'pgsql') {
                return null;
            }
            if (! filter_var(config('ieducar.pgsql_use_relatorio_escola_nome', true), FILTER_VALIDATE_BOOLEAN)) {
                return null;
            }
            $relSchema = (string) config('ieducar.pgsql_schema_relatorio', 'relatorio');
            $relSchema = $relSchema !== '' ? trim($relSchema) : 'relatorio';
            $g = $db->getQueryGrammar();
            $fn = $g->wrapTable($relSchema).'.get_nome_escola';

            return $fn.'('.$g->wrap($alias).'.'.$g->wrap($eId).')';
        } catch (\Throwable) {
            return null;
        }
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
                    $inepCol = $this->resolveColumnByProbe($db, $escolaT, array_filter([
                        (string) config('ieducar.columns.escola.inep'),
                        'codigo_inep',
                        'cod_escola_inep',
                        'inep',
                        'cod_inep',
                        'codigo_escola_inep',
                        'inep_escola',
                        'ref_cod_escola_inep',
                    ]), 'e');

                    $inepJoin = null;
                    if ($inepCol === null) {
                        // Quando o INEP não está na escola, tentar tabela complementar.
                        $inepJoin = $this->resolveInepJoinSpecByProbe($db, $city, $escolaT, 'e', $eId);
                    }

                    if ($inepCol === null) {
                        if ($inepJoin !== null) {
                            $this->info(' - Código INEP encontrado via JOIN: '.$inepJoin['table'].'.'.$inepJoin['col'].' (FK '.$inepJoin['fk'].')');
                        } else {
                            $this->warn(' - coluna de código INEP não encontrada na tabela escola (inep_code ficará vazio). Configure IEDUCAR_COL_ESCOLA_INEP se a base usar outro nome.');
                        }
                    }

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
                    $relNomeExpr = $this->relatorioGetNomeEscolaExpr($db, 'e', $eId);
                    if ($relNomeExpr !== null) {
                        $q->selectRaw($relNomeExpr.' as nome');
                    } elseif ($eName !== null) {
                        $q->addSelect('e.'.$eName.' as nome');
                    }

                    $q = $q
                        ->when($inepCol !== null, fn ($q) => $q->addSelect('e.'.$inepCol.' as inep'))
                        ->orderBy('e.'.$eId)
                        ->limit(5000);

                    if ($inepCol === null && $inepJoin !== null) {
                        $q->leftJoin($inepJoin['table'].' as '.$inepJoin['alias'], 'e.'.$eId, '=', $inepJoin['alias'].'.'.$inepJoin['fk']);
                        $q->addSelect($inepJoin['alias'].'.'.$inepJoin['col'].' as inep');
                    }

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

                    $batchCoords = [];
                    $batchMetaOnly = [];
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
                        $inepRaw = $a['inep'] ?? null;
                        $inep = is_numeric($inepRaw) ? (int) $inepRaw : null;
                        if ($inep !== null && $inep <= 0) {
                            $inep = null;
                        }

                        $validCoord = $la !== null && $ln !== null
                            && ! (abs($la) < 0.01 && abs($ln) < 0.01)
                            && abs($la) <= 90 && abs($ln) <= 180;

                        $base = [
                            'city_id' => (int) $city->id,
                            'escola_id' => $eid,
                            'inep_code' => $inep,
                            'ieducar_lat' => $validCoord ? $la : null,
                            'ieducar_lng' => $validCoord ? $ln : null,
                            'ieducar_seen_at' => $now,
                            'has_divergence' => false,
                            'meta' => json_encode([
                                'nome' => isset($a['nome']) ? (string) $a['nome'] : '',
                            ], JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if ($validCoord) {
                            $batchCoords[] = array_merge($base, [
                                'lat' => $la,
                                'lng' => $ln,
                            ]);
                        } else {
                            // Sem coordenadas na base iEducar: ainda assim salvar INEP/meta/seen_at.
                            $batchMetaOnly[] = array_merge($base, [
                                'lat' => null,
                                'lng' => null,
                            ]);
                        }
                    }

                    if ($batchCoords === [] && $batchMetaOnly === []) {
                        $this->info(' - nenhuma escola encontrada para processar.');

                        return;
                    }

                    $this->line(' - Registos para upsert (com coordenadas): '.count($batchCoords));
                    $this->line(' - Registos para upsert (sem coordenadas): '.count($batchMetaOnly));
                    $previewN = min(3, count($batchCoords));
                    if ($previewN > 0) {
                        $this->line(' - Preview (primeiros '.$previewN.' com coordenadas):');
                        for ($i = 0; $i < $previewN; $i++) {
                            $b = $batchCoords[$i];
                            $this->line('   • escola_id='.(string) ($b['escola_id'] ?? '')
                                .' inep_code='.(string) ($b['inep_code'] ?? '')
                                .' lat='.(string) ($b['lat'] ?? '')
                                .' lng='.(string) ($b['lng'] ?? '')
                            );
                        }
                    }

                    if ($batchCoords !== []) {
                        DB::table((new SchoolUnitGeo())->getTable())->upsert(
                            $batchCoords,
                            ['city_id', 'escola_id'],
                            ['inep_code', 'lat', 'lng', 'ieducar_lat', 'ieducar_lng', 'ieducar_seen_at', 'has_divergence', 'meta', 'updated_at'],
                        );
                    }
                    if ($batchMetaOnly !== []) {
                        DB::table((new SchoolUnitGeo())->getTable())->upsert(
                            $batchMetaOnly,
                            ['city_id', 'escola_id'],
                            ['inep_code', 'ieducar_lat', 'ieducar_lng', 'ieducar_seen_at', 'has_divergence', 'meta', 'updated_at'],
                        );
                    }

                    $totalUpserts += count($batchCoords) + count($batchMetaOnly);
                    $this->info(' - upsert: '.count($batchCoords).' com coordenadas; '.count($batchMetaOnly).' sem coordenadas.');
                });
            } catch (\Throwable $e) {
                $this->error(" - erro ao sincronizar: {$e->getMessage()}");
            }
        }

        $this->info("Concluído. Total upserts: {$totalUpserts}");

        return self::SUCCESS;
    }
}
