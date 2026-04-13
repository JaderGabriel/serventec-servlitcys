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

    public function test_mysql_maps_cadastro_turno_to_short_table_in_single_database(): void
    {
        config(['ieducar.schema' => '']);
        config(['ieducar.tables.turno' => 'cadastro.turno']);

        $city = new City([
            'db_driver' => City::DRIVER_MYSQL,
            'db_port' => 3306,
        ]);

        $this->assertSame('turno', IeducarSchema::resolveTable('turno', $city));
    }

    public function test_mysql_tables_mysql_turno_override(): void
    {
        config(['ieducar.schema' => '']);
        config(['ieducar.tables.turno' => 'cadastro.turno']);
        config(['ieducar.tables_mysql.turno' => 'cadastro_turno']);

        $city = new City([
            'db_driver' => City::DRIVER_MYSQL,
            'db_port' => 3306,
        ]);

        $this->assertSame('cadastro_turno', IeducarSchema::resolveTable('turno', $city));
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

    public function test_turno_table_candidates_for_pgsql_include_fallbacks(): void
    {
        config([
            'ieducar.schema' => '',
            'ieducar.pgsql_default_schema' => 'pmieducar',
            'ieducar.pgsql_schema_cadastro' => 'cadastro',
            'ieducar.tables.turno' => 'cadastro.turno',
            'ieducar.tables.turno_fallbacks' => '',
        ]);
        $city = new City(['db_driver' => City::DRIVER_PGSQL]);

        $c = IeducarSchema::turnoTableCandidates($city);

        $this->assertContains('cadastro.turno', $c);
        $this->assertContains('public.turno', $c);
        $this->assertContains('pmieducar.turno', $c);
    }

    public function test_turno_table_candidates_mysql_only_resolved_primary(): void
    {
        config(['ieducar.tables.turno' => 'cadastro.turno']);

        $city = new City([
            'db_driver' => City::DRIVER_MYSQL,
            'db_port' => 3306,
        ]);

        $this->assertSame(['turno'], IeducarSchema::turnoTableCandidates($city));
    }

    public function test_raca_table_candidates_for_pgsql_include_fallbacks(): void
    {
        config([
            'ieducar.schema' => '',
            'ieducar.pgsql_default_schema' => 'pmieducar',
            'ieducar.pgsql_schema_cadastro' => 'cadastro',
            'ieducar.tables.raca' => 'cadastro.raca',
            'ieducar.tables.raca_fallbacks' => '',
        ]);
        $city = new City(['db_driver' => City::DRIVER_PGSQL]);

        $c = IeducarSchema::racaTableCandidates($city);

        $this->assertContains('cadastro.raca', $c);
        $this->assertContains('public.raca', $c);
        $this->assertContains('pmieducar.raca', $c);
    }

    public function test_raca_table_candidates_mysql_only_resolved_primary(): void
    {
        config(['ieducar.tables.raca' => 'cadastro.raca']);

        $city = new City([
            'db_driver' => City::DRIVER_MYSQL,
            'db_port' => 3306,
        ]);

        $this->assertSame(['raca'], IeducarSchema::racaTableCandidates($city));
    }
}
