<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Dashboard\AdminHomeMunicipalityMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminHomeMunicipalityMapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function markers_include_all_cities_not_only_active(): void
    {
        City::factory()->create(['name' => 'Activa', 'uf' => 'SP', 'is_active' => true, 'db_host' => 'h', 'db_database' => 'd', 'db_username' => 'u']);
        City::factory()->create(['name' => 'Inactiva', 'uf' => 'RJ', 'is_active' => false]);

        $markers = app(AdminHomeMunicipalityMap::class)->markers();

        $this->assertCount(2, $markers);
        $names = array_column($markers, 'name');
        $this->assertContains('Activa', $names);
        $this->assertContains('Inactiva', $names);
    }

    #[Test]
    public function marker_includes_implementation_date_and_school_years_url(): void
    {
        $city = City::factory()->create([
            'is_active' => true,
            'db_host' => '127.0.0.1',
            'db_database' => 'ieducar',
            'db_username' => 'user',
        ]);

        $markers = app(AdminHomeMunicipalityMap::class)->markers();
        $marker = collect($markers)->firstWhere('id', $city->id);

        $this->assertNotNull($marker);
        $this->assertNotNull($marker['implemented_at_label']);
        $this->assertStringContainsString('/dashboard/municipality-map/'.$city->id.'/school-years', $marker['school_years_url']);
        $this->assertSame('ready', $marker['status']);
    }

    #[Test]
    public function summary_legend_alinha_cores_e_contagens_com_marcadores(): void
    {
        City::factory()->create([
            'name' => 'Pronta',
            'uf' => 'BA',
            'is_active' => true,
            'db_host' => 'h',
            'db_database' => 'd',
            'db_username' => 'u',
        ]);
        City::factory()->create(['name' => 'Incompleta', 'uf' => 'BA', 'is_active' => true]);
        City::factory()->create(['name' => 'Off', 'uf' => 'BA', 'is_active' => false]);

        $summary = app(AdminHomeMunicipalityMap::class)->summary();

        $this->assertCount(4, $summary['legend']);
        $this->assertSame(1, $summary['by_status']['ready'] ?? 0);
        $this->assertSame('#10b981', $summary['colors']['ready']);
        $readyLegend = collect($summary['legend'])->firstWhere('status', 'ready');
        $this->assertSame(1, $readyLegend['count']);
        $this->assertSame('#10b981', $readyLegend['color']);
    }
}
