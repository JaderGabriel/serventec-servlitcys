<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\City;
use App\Models\User;
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
            ->assertSee('RX', false)
            ->assertSee(__('Painel operacional'), false);
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
