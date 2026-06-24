<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CadunicoSyncAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_cadunico_sync_index(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.cadunico-sync.index'))
            ->assertOk();
    }

    public function test_municipal_user_cannot_view_cadunico_sync_index(): void
    {
        $user = User::factory()->municipal()->create();

        $this->actingAs($user)
            ->get(route('admin.cadunico-sync.index'))
            ->assertForbidden();
    }

    public function test_platform_user_cannot_run_cadunico_sync(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.cadunico-sync.run'), [
                'action' => 'import_city_year',
                'city_id' => $city->id,
                'ano' => 2024,
            ])
            ->assertForbidden();
    }

    public function test_admin_run_requires_valid_action(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $city = City::factory()->create(['ibge_municipio' => '2901106']);

        $this->actingAs($admin)
            ->post(route('admin.cadunico-sync.run'), [
                'action' => 'import_city_year',
                'city_id' => $city->id,
                'ano' => 2024,
            ])
            ->assertRedirect();
    }
}
