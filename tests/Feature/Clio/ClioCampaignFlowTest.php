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
        config([
            'clio.enabled' => true,
            'legal.require_authenticated_consent' => false,
        ]);
    }

    #[Test]
    public function admin_ve_menu_e_home_clio(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('clio.home'))
            ->assertOk()
            ->assertSee('Clio', false)
            ->assertSee('Relatórios por município', false);
    }

    #[Test]
    public function municipal_nao_acede_clio(): void
    {
        $municipal = User::factory()->municipal()->create();

        $this->actingAs($municipal)
            ->get(route('clio.home'))
            ->assertForbidden();
    }

    #[Test]
    public function usuario_ve_clio_mas_nao_cria_nem_faz_upload(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('clio.home'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('clio.cities.store'), [
                'name' => 'Bloqueado',
                'uf' => 'BA',
                'ibge_municipio' => '2929752',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('clio.cities.store'), [
                'name' => 'Saubara User Test',
                'uf' => 'BA',
                'ibge_municipio' => '2929752',
            ])
            ->assertRedirect();

        $city = City::query()->where('name', 'Saubara User Test')->firstOrFail();

        $this->actingAs($user)
            ->post(route('clio.campaigns.store'), [
                'city_id' => $city->id,
                'year' => 2026,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('clio.campaigns.store'), [
                'city_id' => $city->id,
                'year' => 2026,
            ])
            ->assertRedirect();

        $campaign = ClioCampaign::query()->where('city_id', $city->id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('clio.campaigns.show', $campaign))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('clio.campaigns.upload', $campaign))
            ->assertOk();

        $file = UploadedFile::fake()->create(
            'Relatorio_Acomp_Coleta_1Etapa_20072026.csv',
            12,
            'text/csv'
        );

        $this->actingAs($user)
            ->post(route('clio.campaigns.upload.store', $campaign), [
                'files' => [$file],
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('clio.campaigns.analyze', $campaign))
            ->assertForbidden();
    }

    #[Test]
    public function admin_cria_ficha_leve_campanha_e_faz_upload(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('clio.cities.store'), [
                'setup_mode' => 'catalog',
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

    #[Test]
    public function admin_cadastra_municipio_consultoria_e_coleta_usa_perfil_consultancy(): void
    {
        $this->mock(\App\Services\CityDataConnection::class, function ($mock) {
            $mock->shouldReceive('connectionStatus')
                ->once()
                ->andReturn(['status' => 'ok', 'message' => null, 'ms' => 1]);
        });

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('clio.cities.store'), [
                'setup_mode' => 'consultancy',
                'name' => 'Consultoria Alpha',
                'uf' => 'BA',
                'ibge_municipio' => '2910800',
                'db_driver' => 'pgsql',
                'db_host' => '127.0.0.1',
                'db_port' => 5432,
                'db_database' => 'ieducar_teste',
                'db_username' => 'ieducar',
                'db_password' => 'secret',
                'ieducar_schema' => 'pmieducar',
            ])
            ->assertRedirect();

        $city = City::query()->where('name', 'Consultoria Alpha')->firstOrFail();
        $this->assertTrue($city->hasDataSetup());
        $this->assertFalse($city->isClioCatalogOnly());

        $this->actingAs($admin)
            ->post(route('clio.campaigns.store'), [
                'city_id' => $city->id,
                'year' => 2026,
            ])
            ->assertRedirect();

        $campaign = ClioCampaign::query()->where('city_id', $city->id)->firstOrFail();
        $this->assertSame(ClioCampaign::PROFILE_CONSULTANCY, $campaign->profile);
    }

    #[Test]
    public function home_lista_municipio_e_abre_relatorio_quando_analisado(): void
    {
        $admin = User::factory()->admin()->create();
        $city = City::query()->create([
            'name' => 'Home Relatorio Mun',
            'uf' => 'BA',
            'ibge_municipio' => '2905701',
            'country' => 'Brasil',
            'db_driver' => City::DRIVER_MYSQL,
            'db_password' => '',
            'is_active' => true,
        ]);
        $campaign = ClioCampaign::query()->create([
            'city_id' => $city->id,
            'municipality_name' => $city->name,
            'uf' => 'BA',
            'ibge_municipio' => $city->ibge_municipio,
            'year' => 2026,
            'stage' => ClioCampaign::STAGE_1,
            'profile' => ClioCampaign::PROFILE_ANALYSIS_ONLY,
            'status' => ClioCampaign::STATUS_ANALYZED,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('clio.home', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Home Relatorio Mun', false)
            ->assertSee('Abrir relatório', false)
            ->assertSee(route('clio.campaigns.analysis', $campaign), false);
    }

    #[Test]
    public function admin_cadastra_municipio_com_drive_e_verifica_pasta(): void
    {
        config(['clio.drive.api_key' => 'test-key']);

        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/drive/v3/files*' => \Illuminate\Support\Facades\Http::response([
                'files' => [
                    [
                        'id' => 'f1',
                        'name' => 'Relatorio_Acomp_Coleta_1Etapa_20072026.csv',
                        'mimeType' => 'text/csv',
                        'size' => '800',
                    ],
                ],
            ]),
        ]);

        $admin = User::factory()->admin()->create();
        $driveUrl = 'https://drive.google.com/drive/folders/1vxN6vZysR8I-ySLUZEgZo8d-5tc08PDO';

        $this->actingAs($admin)
            ->post(route('clio.cities.store'), [
                'setup_mode' => 'catalog',
                'name' => 'Saubara Drive',
                'uf' => 'BA',
                'ibge_municipio' => '2929752',
                'clio_drive_url' => $driveUrl,
            ])
            ->assertRedirect();

        $city = City::query()->where('name', 'Saubara Drive')->firstOrFail();
        $this->assertSame($driveUrl, $city->clio_drive_url);

        $this->actingAs($admin)
            ->post(route('clio.campaigns.store'), [
                'city_id' => $city->id,
                'year' => 2026,
            ])
            ->assertRedirect();

        $campaign = ClioCampaign::query()->where('city_id', $city->id)->firstOrFail();
        $this->assertSame('drive_upload', $campaign->source);
        $this->assertSame($driveUrl, $campaign->meta['drive_folder_url'] ?? null);

        $this->actingAs($admin)
            ->from(route('clio.campaigns.show', $campaign))
            ->post(route('clio.campaigns.drive.verify', $campaign), [
                'clio_drive_url' => $driveUrl,
            ])
            ->assertRedirect(route('clio.campaigns.show', $campaign))
            ->assertSessionHas('success')
            ->assertSessionHas('clio_drive_verify');
    }
}
