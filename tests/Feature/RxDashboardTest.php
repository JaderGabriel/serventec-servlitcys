<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Models\User;
use App\Support\Rx\RxSemaphore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RxDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_acessa_rx(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard.rx'))
            ->assertOk()
            ->assertSee(__('Painel RX'), false)
            ->assertSee(__('Legendas e cores'), false)
            ->assertSee(__('Guia completo das colunas'), false)
            ->assertSee(__('Tons'), false)
            ->assertSee(__('Atenção ao prazo'), false)
            ->assertSee(__('Indicador meta'), false)
            ->assertSee(__('Leitura dos dados'), false);
    }

    #[Test]
    public function municipal_ve_apenas_municipios_vinculados_na_lista(): void
    {
        $linked = City::factory()->create(['name' => 'Cidade Vinculada RX', 'is_active' => true]);
        City::factory()->create(['name' => 'Cidade Outra RX', 'is_active' => true]);

        $municipal = User::factory()->municipal()->create(['is_active' => true]);
        $municipal->cities()->attach($linked->id);

        $this->actingAs($municipal)
            ->get(route('dashboard.rx'))
            ->assertOk()
            ->assertSee('Cidade Vinculada RX', false)
            ->assertDontSee('Cidade Outra RX', false);
    }

    #[Test]
    public function semaforo_verde_quando_meta_cumprida(): void
    {
        $sem = RxSemaphore::fromRow([
            'ok' => true,
            'meta_encontrou_referencia' => true,
            'meta_matriculas_alvo' => 100,
            'progresso_cadastro_pct' => 100.0,
            'registros_restantes' => 0,
        ]);

        $this->assertSame('green', $sem['status']);
    }

    #[Test]
    public function exibe_grafico_fundeb_portaria_quando_ha_dados(): void
    {
        config(['rx.vigente_year' => 2026, 'rx.fundeb_portaria_exercicio' => 0]);

        $city = City::factory()->create([
            'name' => 'Município RX FUNDEB',
            'ibge_municipio' => '2913309',
            'is_active' => true,
        ]);

        FundebMunicipioReference::query()->create([
            'city_id' => $city->id,
            'ibge_municipio' => '2913309',
            'ano' => 2026,
            'complementacao_vaaf' => 1_000_000,
            'complementacao_vaat' => 500_000,
            'complementacao_vaar' => 250_000,
            'fonte' => 'test',
        ]);

        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard.rx'))
            ->assertOk()
            ->assertSee(__('FUNDEB — complementações da portaria (:ano)', ['ano' => '2026']), false)
            ->assertSee(__('IBGE dos municípios cadastrados'), false)
            ->assertSee(__('Complementações previstas por município'), false)
            ->assertSee('data-chart-panel-root="1"', false);
    }

    #[Test]
    public function utilizador_acessa_rx(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.rx'))
            ->assertOk();
    }
}
