<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_connections_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.connections.index'))
            ->assertOk()
            ->assertSee(__('Ligações i-Educar'), false);
    }

    public function test_non_admin_cannot_access_connections_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.connections.index'))
            ->assertForbidden();
    }

    public function test_municipal_user_sees_new_home_dashboard_redirect(): void
    {
        $municipal = User::factory()->municipal()->create();

        $this->actingAs($municipal)
            ->get(route('dashboard'))
            ->assertRedirect(route('dashboard.analytics'));
    }

    public function test_admin_home_dashboard_renders(): void
    {
        $admin = User::factory()->admin()->create();
        City::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Consultoria municipal'), false)
            ->assertSee(__('Fluxo de dados'), false)
            ->assertSee(__('Acesso rápido'), false);
    }
}
