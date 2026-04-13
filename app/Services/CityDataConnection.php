<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class CityDataConnection
{
    public const CONNECTION_PREFIX = 'city_data_';

    /** Resposta em ms acima disto = estado «lento» (amarelo). */
    public const SLOW_THRESHOLD_MS = 1500.0;

    public function connectionName(City $city): string
    {
        return self::CONNECTION_PREFIX.$city->getKey();
    }

    /**
     * Regista uma conexão dinâmica (MySQL ou PostgreSQL) com base nos dados da cidade.
     */
    public function configure(City $city): void
    {
        if (! $city->hasDataSetup()) {
            throw new \InvalidArgumentException(__('A cidade não tem configuração de banco de dados completa.'));
        }

        $name = $this->connectionName($city);
        $driver = $city->effectiveIeducarDriver();
        $templateKey = $driver === City::DRIVER_PGSQL ? 'pgsql' : 'mysql';
        $base = config("database.connections.{$templateKey}");

        $port = (int) ($city->db_port ?: ($driver === City::DRIVER_PGSQL ? 5432 : 3306));

        $connection = array_merge($base, [
            'driver' => $driver,
            'host' => $city->db_host,
            'port' => $port,
            'database' => $city->db_database,
            'username' => $city->db_username,
            'password' => $city->db_password ?? '',
        ]);

        if ($driver === City::DRIVER_PGSQL) {
            $connection['search_path'] = $this->buildPgsqlSearchPathForCity($city);
        }

        Config::set("database.connections.{$name}", $connection);
    }

    public function purge(City $city): void
    {
        DB::purge($this->connectionName($city));
    }

    /**
     * Monta o search_path do PostgreSQL para iEducar (ex.: 2.x Portabilis): o schema principal
     * da cidade vem primeiro (pmieducar ou o definido em cities.ieducar_schema), depois os
     * restantes de config (cadastro, public, …), sem duplicar — importante quando há vários
     * schemas na mesma base e tabelas qualificadas como cadastro.pessoa.
     */
    private function buildPgsqlSearchPathForCity(City $city): string
    {
        $configured = trim((string) config('ieducar.pgsql_search_path', 'pmieducar,cadastro,public'));
        $fromConfig = array_values(array_filter(array_map('trim', explode(',', $configured)), static fn (string $s): bool => $s !== ''));

        $main = trim((string) ($city->ieducar_schema ?? ''));
        if ($main === '') {
            $main = trim((string) config('ieducar.schema', ''));
        }
        if ($main === '') {
            $main = trim((string) config('ieducar.pgsql_default_schema', '')) ?: 'pmieducar';
        }

        $ordered = [];
        if ($main !== '') {
            $ordered[] = $main;
        }
        foreach ($fromConfig as $schema) {
            if (! in_array($schema, $ordered, true)) {
                $ordered[] = $schema;
            }
        }

        return $ordered !== [] ? implode(',', $ordered) : 'public';
    }

    /**
     * Executa um callback com a ligação à base da cidade e liberta-a no fim.
     *
     * @template T
     *
     * @param  callable(Connection): T  $callback
     * @return T
     */
    public function run(City $city, callable $callback): mixed
    {
        $this->configure($city);
        $name = $this->connectionName($city);

        try {
            return $callback(DB::connection($name));
        } finally {
            $this->purge($city);
        }
    }

    /**
     * Testa a ligação e devolve métricas genéricas para o painel.
     *
     * @return array{ok: bool, message: ?string, driver: string, mysql_version: ?string, table_count: ?int}
     */
    public function probe(City $city): array
    {
        $driver = $city->effectiveIeducarDriver();

        try {
            $this->configure($city);
            $name = $this->connectionName($city);
            $conn = DB::connection($name);
            $pdo = $conn->getPdo();
            $serverVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?: null;

            $tableCount = $driver === City::DRIVER_PGSQL
                ? $this->tableCountPostgres($conn)
                : $this->tableCountMysql($conn, $city->db_database);

            $this->purge($city);

            return [
                'ok' => true,
                'message' => null,
                'driver' => $driver,
                'mysql_version' => $serverVersion,
                'table_count' => $tableCount,
            ];
        } catch (\Throwable $e) {
            $this->purge($city);

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'driver' => $driver,
                'mysql_version' => null,
                'table_count' => null,
            ];
        }
    }

    private function tableCountMysql(Connection $conn, ?string $schema): ?int
    {
        if ($schema === null || $schema === '') {
            return null;
        }

        $tablesRow = $conn->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ?',
            [$schema]
        );

        return is_object($tablesRow) ? (int) ($tablesRow->c ?? 0) : null;
    }

    private function tableCountPostgres(Connection $conn): ?int
    {
        $tablesRow = $conn->selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS c
            FROM information_schema.tables
            WHERE table_catalog = current_database()
              AND table_schema NOT IN ('pg_catalog', 'information_schema')
              AND table_type = 'BASE TABLE'
            SQL
        );

        return is_object($tablesRow) ? (int) ($tablesRow->c ?? 0) : null;
    }

    /**
     * Testa a ligação com medição de tempo para indicador (verde / amarelo / vermelho).
     *
     * @return array{status: 'setup_missing'|'ok'|'slow'|'error', ms: ?float, message: ?string}
     */
    public function connectionStatus(City $city): array
    {
        if (! $city->hasDataSetup()) {
            return [
                'status' => 'setup_missing',
                'ms' => null,
                'message' => null,
            ];
        }

        $driver = $city->effectiveIeducarDriver();
        $t0 = microtime(true);

        try {
            $this->configure($city);
            $name = $this->connectionName($city);
            $conn = DB::connection($name);
            $conn->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

            if ($driver === City::DRIVER_PGSQL) {
                $this->tableCountPostgres($conn);
            } else {
                $this->tableCountMysql($conn, $city->db_database);
            }

            $this->purge($city);

            $ms = (microtime(true) - $t0) * 1000;

            return [
                'status' => $ms >= self::SLOW_THRESHOLD_MS ? 'slow' : 'ok',
                'ms' => round($ms, 0),
                'message' => null,
            ];
        } catch (\Throwable $e) {
            $this->purge($city);

            return [
                'status' => 'error',
                'ms' => null,
                'message' => $e->getMessage(),
            ];
        }
    }
}
