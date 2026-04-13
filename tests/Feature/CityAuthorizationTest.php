<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_cities_index(): void
    {
        $this->get(route('cities.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_cities_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('cities.index'))->assertForbidden();
    }

    public function test_admin_can_access_cities_index(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('cities.index'))->assertOk();
    }

    public function test_non_admin_cannot_update_city(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $city = City::factory()->create([
            'name' => 'Cidade Teste',
            'uf' => 'SP',
        ]);

        $this->actingAs($user)->put(route('cities.update', $city), [
            'name' => 'Cidade Teste',
            'uf' => 'SP',
            'db_driver' => 'mysql',
            'db_host' => '127.0.0.1',
            'db_database' => 'db',
            'db_username' => 'u',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_admin_can_update_city(): void
    {
        $admin = User::factory()->admin()->create();
        $city = City::factory()->create([
            'name' => 'Cidade Teste',
            'uf' => 'SP',
        ]);

        $this->actingAs($admin)->put(route('cities.update', $city), [
            'name' => 'Cidade Teste',
            'uf' => 'SP',
            'db_driver' => 'mysql',
            'db_host' => '127.0.0.1',
            'db_database' => 'db',
            'db_username' => 'u',
            'is_active' => true,
        ])->assertRedirect(route('cities.index'));
    }
}
