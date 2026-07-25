<?php

namespace Tests\Unit\Services\Horizonte;

use App\Models\City;
use App\Models\MunicipalEducationWork;
use App\Support\Horizonte\HorizonteCanteiroAlertsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteCanteiroAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extensão pdo_sqlite necessária para RefreshDatabase neste ambiente.');
        }

        parent::setUp();

        Storage::fake('local');
        config([
            'horizonte.obras.enabled' => true,
            'horizonte.obras.alerts.enabled' => true,
            'horizonte.obras.alerts.snapshot_path' => 'horizonte/canteiro_alerts_snapshot.json',
        ]);
    }

    #[Test]
    public function alerts_include_only_consultoria_cities_with_relevant_works(): void
    {
        $withSetup = City::factory()->create([
            'name' => 'Com Consultoria',
            'uf' => 'BA',
            'ibge_municipio' => '2905701',
            'is_active' => true,
        ]);

        City::factory()->create([
            'name' => 'Sem Setup',
            'uf' => 'BA',
            'ibge_municipio' => '2927408',
            'is_active' => true,
            'db_host' => null,
            'db_database' => null,
            'db_username' => null,
        ]);

        MunicipalEducationWork::query()->create([
            'id_projeto_investimento' => 'OBRA-CONS-1',
            'ibge_municipio' => '2905701',
            'ibge_confidence' => 'high',
            'uf_principal' => 'BA',
            'situacao' => 'Paralisada',
            'desc_nome' => 'Creche paralisada',
            'sistema_resp' => 'SIMEC-FNDE',
            'fonte' => 'obrasgov',
            'imported_at' => now(),
        ]);

        MunicipalEducationWork::query()->create([
            'id_projeto_investimento' => 'OBRA-SEM-1',
            'ibge_municipio' => '2927408',
            'ibge_confidence' => 'high',
            'uf_principal' => 'BA',
            'situacao' => 'Paralisada',
            'desc_nome' => 'Escola sem consultoria',
            'sistema_resp' => 'SIMEC-FNDE',
            'fonte' => 'obrasgov',
            'imported_at' => now(),
        ]);

        $this->artisan('horizonte:canteiro-alerts')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->exists('horizonte/canteiro_alerts_snapshot.json'));

        $raw = Storage::disk('local')->get('horizonte/canteiro_alerts_snapshot.json');
        $data = json_decode((string) $raw, true);
        $this->assertIsArray($data);
        $this->assertSame(1, $data['total_cities'] ?? 0);
        $this->assertSame(1, $data['skipped_no_setup'] ?? null);
        $this->assertArrayHasKey('2905701', $data['snapshot']);
        $this->assertArrayNotHasKey('2927408', $data['snapshot']);
        $this->assertSame((int) $withSetup->id, $data['snapshot']['2905701']['city_id']);

        $cache = HorizonteCanteiroAlertsCache::load();
        $this->assertArrayHasKey('2905701', $cache['by_ibge']);
        $this->assertSame(1, $cache['by_ibge']['2905701']['paralisadas']);
    }

    #[Test]
    public function dry_run_does_not_write_snapshot(): void
    {
        City::factory()->create([
            'ibge_municipio' => '2905701',
            'is_active' => true,
        ]);

        MunicipalEducationWork::query()->create([
            'id_projeto_investimento' => 'OBRA-DRY-1',
            'ibge_municipio' => '2905701',
            'ibge_confidence' => 'high',
            'uf_principal' => 'BA',
            'situacao' => 'Em execução',
            'desc_nome' => 'Obra dry',
            'fonte' => 'obrasgov',
            'imported_at' => now(),
        ]);

        $this->artisan('horizonte:canteiro-alerts', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists('horizonte/canteiro_alerts_snapshot.json'));
    }
}
