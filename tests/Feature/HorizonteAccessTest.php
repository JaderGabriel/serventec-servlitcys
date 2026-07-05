<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\InepCensoMunicipioMatricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['horizonte.enabled' => true]);
    }

    #[Test]
    public function admin_pode_abrir_horizonte(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.horizonte'))
            ->assertOk()
            ->assertSee('serv-horizonte-mobile', false)
            ->assertSee('layoutPreference', false);

        $this->actingAs($admin)
            ->get(route('dashboard.horizonte.map-data'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard.horizonte.map-geo'))
            ->assertOk();
    }

    #[Test]
    public function utilizador_pode_abrir_horizonte(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)
            ->get(route('dashboard.horizonte'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('dashboard.horizonte.map-data'))
            ->assertOk();
    }

    #[Test]
    public function municipal_nao_pode_abrir_horizonte(): void
    {
        $municipal = User::factory()->municipal()->create();

        $this->actingAs($municipal)
            ->get(route('dashboard.horizonte'))
            ->assertForbidden();

        $this->actingAs($municipal)
            ->get(route('dashboard.horizonte.map-data'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_obtem_serie_matriculas_para_municipio_sem_consultoria(): void
    {
        $admin = User::factory()->admin()->create();

        InepCensoMunicipioMatricula::query()->create([
            'ibge_municipio' => '2927408',
            'ano' => 2023,
            'matriculas_total' => 11000,
            'matriculas_regular' => 9000,
            'matriculas_eja' => 1000,
        ]);

        $this->actingAs($admin)
            ->getJson(route('dashboard.horizonte.enrollment-series', ['ibge' => '2927408']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ibge', '2927408')
            ->assertJsonStructure(['chart' => ['type', 'labels', 'datasets']]);
    }

    #[Test]
    public function serie_matriculas_bloqueia_municipio_com_consultoria_activa(): void
    {
        $admin = User::factory()->admin()->create();

        City::factory()->create([
            'ibge_municipio' => '2927408',
            'is_active' => true,
        ]);

        InepCensoMunicipioMatricula::query()->create([
            'ibge_municipio' => '2927408',
            'ano' => 2023,
            'matriculas_total' => 11000,
        ]);

        $this->actingAs($admin)
            ->getJson(route('dashboard.horizonte.enrollment-series', ['ibge' => '2927408']))
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }
}
