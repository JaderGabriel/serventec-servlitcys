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
        $this->assertArrayHasKey('ieducar_url', $marker);
    }

    #[Test]
    public function marker_inclui_ieducar_url_quando_cadastrada(): void
    {
        $city = City::factory()->create([
            'is_active' => true,
            'db_host' => 'h',
            'db_database' => 'd',
            'db_username' => 'u',
            'ieducar_app_url' => 'https://municipio.test/ieducar',
        ]);

        $marker = collect(app(AdminHomeMunicipalityMap::class)->markers())->firstWhere('id', $city->id);

        $this->assertSame('https://municipio.test/ieducar', $marker['ieducar_url']);
    }

    #[Test]
    public function marcadores_na_mesma_uf_sem_ibge_nao_ficam_sobrepostos(): void
    {
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            City::factory()->create([
                'name' => $name,
                'uf' => 'BA',
                'is_active' => true,
                'ibge_municipio' => null,
                'db_host' => 'h',
                'db_database' => 'd',
                'db_username' => 'u',
            ]);
        }

        $markers = app(AdminHomeMunicipalityMap::class)->markers();
        $this->assertCount(3, $markers);

        foreach ($markers as $a) {
            foreach ($markers as $b) {
                if ($a['id'] === $b['id']) {
                    continue;
                }
                $dLat = abs((float) $a['lat'] - (float) $b['lat']);
                $dLng = abs((float) $a['lng'] - (float) $b['lng']);
                $this->assertTrue($dLat >= 0.1 || $dLng >= 0.1, 'Marcadores BA devem estar separados');
            }
        }
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
