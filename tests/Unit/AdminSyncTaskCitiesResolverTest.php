<?php

namespace Tests\Unit;

use App\Enums\AdminSyncDomain;
use App\Models\City;
use App\Services\AdminSync\AdminSyncTaskCitiesResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSyncTaskCitiesResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrich_payload_lists_all_fundeb_cities_with_ibge(): void
    {
        $withIbge = City::factory()->create(['name' => 'Alpha', 'ibge_municipio' => '2901106']);
        City::factory()->create(['name' => 'Beta', 'ibge_municipio' => null]);

        $payload = AdminSyncTaskCitiesResolver::enrichPayload(
            ['years' => [2024]],
            null,
            AdminSyncDomain::Fundeb,
            'sync_all_years',
        );

        $this->assertTrue($payload['all_cities'] ?? false);
        $this->assertContains($withIbge->id, $payload['city_ids']);
        $this->assertContains('Alpha', $payload['city_names']);
        $this->assertNotContains('Beta', $payload['city_names']);
    }

    public function test_enrich_payload_keeps_explicit_city_ids(): void
    {
        $a = City::factory()->create(['name' => 'Cidade A', 'ibge_municipio' => '2901106']);
        $b = City::factory()->create(['name' => 'Cidade B', 'ibge_municipio' => '2901107']);

        $payload = AdminSyncTaskCitiesResolver::enrichPayload(
            ['city_ids' => [$a->id, $b->id]],
            null,
            AdminSyncDomain::Fundeb,
            'sync_all_years',
        );

        $this->assertSame(['Cidade A', 'Cidade B'], $payload['city_names']);
        $this->assertFalse($payload['all_cities'] ?? true);
    }
}
