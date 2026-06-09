<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Dashboard\AdminHomeMunicipalityMap;
use App\Support\Dashboard\AdminHomeMapCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        $this->assertArrayHasKey('reference_contact', $marker);
        $this->assertArrayHasKey('map_fill_key', $marker);
        $this->assertIsArray($marker['reference_contact']);
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
    public function markers_usam_cache_apos_primeira_construcao(): void
    {
        config([
            'cache.default' => 'array',
            'performance.home_map_cache_store' => 'redis',
            'performance.home_map_cache_ttl' => 3600,
            'performance.home_defer_map_rx_snapshot' => true,
        ]);
        Cache::flush();

        City::factory()->create([
            'name' => 'Cacheville',
            'uf' => 'SP',
            'is_active' => true,
            'db_host' => 'h',
            'db_database' => 'd',
            'db_username' => 'u',
        ]);

        $map = app(AdminHomeMunicipalityMap::class);
        $first = $map->markers();
        $second = $map->markers();

        $this->assertCount(1, $first);
        $this->assertSame($first, $second);
        $this->assertNotNull(AdminHomeMapCache::get(
            'admin_home_map_markers:v2:defer:'.(int) config('rx.vigente_year', (int) date('Y')).':'.$this->invokeFingerprint(),
        ));
    }

    private function invokeFingerprint(): string
    {
        $row = City::query()
            ->selectRaw('count(*) as aggregate_count, max(updated_at) as aggregate_updated')
            ->first();
        $count = (int) ($row->aggregate_count ?? 0);
        $updated = $row->aggregate_updated ?? null;
        $updatedTs = $updated instanceof \DateTimeInterface
            ? $updated->format('U')
            : (is_string($updated) ? strtotime($updated) : 0);

        return $count.':'.$updatedTs;
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

        $map = app(AdminHomeMunicipalityMap::class);
        $markers = $map->markers();
        $summary = $map->summary($markers);

        $this->assertCount(4, $summary['legend']);
        $this->assertSame(1, $summary['by_status']['ready'] ?? 0);
        $this->assertSame('#10b981', $summary['colors']['ready']);
        $readyLegend = collect($summary['legend'])->firstWhere('status', 'ready');
        $this->assertSame(1, $readyLegend['count']);
        $this->assertSame('#10b981', $readyLegend['color']);
        $this->assertArrayHasKey('cadastro_legend', $summary);
        $this->assertArrayHasKey('cadastro_snapshot_url', $summary);
    }
}
