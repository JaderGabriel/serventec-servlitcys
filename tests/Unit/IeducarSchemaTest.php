<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Ieducar\IeducarSchema;
use Tests\TestCase;

class IeducarSchemaTest extends TestCase
{
    public function test_mysql_city_without_env_uses_unqualified_table_name(): void
    {
        config(['ieducar.schema' => '']);
        config(['ieducar.tables.escola' => 'escola']);

        $city = new City([
            'db_driver' => City::DRIVER_MYSQL,
            'ieducar_schema' => null,
        ]);

        $this->assertSame('escola', IeducarSchema::resolveTable('escola', $city));
    }

    public function test_pgsql_city_gets_default_pmieducar_schema(): void
    {
        config(['ieducar.schema' => '']);
        config(['ieducar.pgsql_default_schema' => 'pmieducar']);
        config(['ieducar.tables.escola' => 'escola']);

        $city = new City([
            'db_driver' => City::DRIVER_PGSQL,
            'ieducar_schema' => null,
        ]);

        $this->assertSame('pmieducar.escola', IeducarSchema::resolveTable('escola', $city));
    }

    public function test_city_ieducar_schema_overrides_global(): void
    {
        config(['ieducar.schema' => 'global_schema']);
        config(['ieducar.tables.escola' => 'escola']);

        $city = new City([
            'db_driver' => City::DRIVER_PGSQL,
            'ieducar_schema' => 'custom_schema',
        ]);

        $this->assertSame('custom_schema.escola', IeducarSchema::resolveTable('escola', $city));
    }

    public function test_fully_qualified_table_bypasses_schema(): void
    {
        config(['ieducar.schema' => 'pmieducar']);
        config(['ieducar.tables.turno' => 'cadastro.turno']);

        $city = new City(['db_driver' => City::DRIVER_PGSQL]);

        $this->assertSame('cadastro.turno', IeducarSchema::resolveTable('turno', $city));
    }

    public function test_port_5432_mysql_driver_still_resolves_pmieducar_schema(): void
    {
        config(['ieducar.schema' => '']);
        config(['ieducar.pgsql_default_schema' => 'pmieducar']);
        config(['ieducar.tables.matricula' => 'matricula']);

        $city = new City([
            'db_driver' => City::DRIVER_MYSQL,
            'db_port' => 5432,
        ]);

        $this->assertSame('pmieducar.matricula', IeducarSchema::resolveTable('matricula', $city));
    }
}
