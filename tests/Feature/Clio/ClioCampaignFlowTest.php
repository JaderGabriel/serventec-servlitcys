<?php

namespace Tests\Feature\Clio;

use App\Models\City;
use App\Models\Clio\ClioCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClioCampaignFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extensão pdo_sqlite necessária para RefreshDatabase neste ambiente.');
        }

        parent::setUp();
        config(['clio.enabled' => true]);
    }

    #[Test]
    public function admin_ve_menu_e_lista_clio(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('clio.campaigns.index'))
            ->assertOk()
            ->assertSee('Clio', false);
    }

    #[Test]
    public function municipal_nao_acede_clio(): void
    {
        $municipal = User::factory()->municipal()->create();

        $this->actingAs($municipal)
            ->get(route('clio.campaigns.index'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_cria_ficha_leve_campanha_e_faz_upload(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('clio.cities.store'), [
                'name' => 'Saubara Teste',
                'uf' => 'BA',
                'ibge_municipio' => '2929752',
            ])
            ->assertRedirect();

        $city = City::query()->where('name', 'Saubara Teste')->firstOrFail();
        $this->assertTrue($city->isClioCatalogOnly());
        $this->assertFalse(
            City::query()->forAnalytics()->whereKey($city->id)->exists()
        );

        $this->actingAs($admin)
            ->post(route('clio.campaigns.store'), [
                'city_id' => $city->id,
                'year' => 2026,
            ])
            ->assertRedirect();

        $campaign = ClioCampaign::query()->where('city_id', $city->id)->firstOrFail();
        $this->assertSame(ClioCampaign::PROFILE_ANALYSIS_ONLY, $campaign->profile);

        $file = UploadedFile::fake()->create(
            'Relatorio_Acomp_Coleta_1Etapa_20072026.csv',
            12,
            'text/csv'
        );

        $this->actingAs($admin)
            ->post(route('clio.campaigns.upload.store', $campaign), [
                'files' => [$file],
            ])
            ->assertRedirect(route('clio.campaigns.upload', $campaign));

        $campaign->refresh();
        $this->assertSame(1, $campaign->artifacts()->count());
        $this->assertSame('acomp_coleta_1etapa', $campaign->artifacts()->first()->kind);
        $this->assertSame(ClioCampaign::STATUS_INGESTING, $campaign->status);
    }
}
