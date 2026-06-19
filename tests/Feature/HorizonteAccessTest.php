<?php

namespace Tests\Feature;

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
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard.horizonte.map-data'))
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
}
