<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Funding\ConsultoriaFinanciamentosEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultoriaFinanciamentosEnrichmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultoria_cities_requires_active_setup_and_ibge(): void
    {
        $ok = City::factory()->create([
            'name' => 'Alpha',
            'is_active' => true,
            'ibge_municipio' => '2927408',
            'db_host' => '127.0.0.1',
            'db_database' => 'ieducar',
            'db_username' => 'user',
        ]);
        City::factory()->create([
            'name' => 'Sem IBGE',
            'is_active' => true,
            'ibge_municipio' => null,
            'db_host' => '127.0.0.1',
            'db_database' => 'ieducar',
            'db_username' => 'user',
        ]);
        City::factory()->create([
            'name' => 'Inactiva',
            'is_active' => false,
            'ibge_municipio' => '2910800',
            'db_host' => '127.0.0.1',
            'db_database' => 'ieducar',
            'db_username' => 'user',
        ]);
        City::factory()->create([
            'name' => 'Sem setup',
            'is_active' => true,
            'ibge_municipio' => '2919200',
            'db_host' => null,
            'db_database' => null,
            'db_username' => null,
        ]);

        $service = app(ConsultoriaFinanciamentosEnrichmentService::class);
        $cities = $service->consultoriaCities();

        $this->assertCount(1, $cities);
        $this->assertSame($ok->id, $cities->first()->id);
    }

    public function test_dry_run_does_not_import_rows(): void
    {
        City::factory()->create([
            'is_active' => true,
            'ibge_municipio' => '2927408',
            'db_host' => '127.0.0.1',
            'db_database' => 'ieducar',
            'db_username' => 'user',
        ]);

        $service = app(ConsultoriaFinanciamentosEnrichmentService::class);
        $result = $service->enrich(2025, null, true, false, false);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['cities']);
        $this->assertSame(0, $result['imported_rows']);
        $this->assertSame(0, $result['warmed']);
        $this->assertSame(0, $result['failed']);
        $this->assertStringContainsString('importaria', (string) ($result['results'][0]['import'] ?? ''));
    }
}
