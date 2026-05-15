<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_users_index(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_utilizador_can_access_users_index(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)->get(route('users.index'))->assertOk();
    }

    public function test_utilizador_can_create_another_utilizador(): void
    {
        $user = User::factory()->utilizador()->create();

        $response = $this->actingAs($user)->post(route('users.store'), [
            'name' => 'Novo Utilizador',
            'username' => 'novo_utilizador',
            'email' => 'novo@example.com',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
            'role' => UserRole::User->value,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'role' => UserRole::User->value,
        ]);
    }

    public function test_utilizador_cannot_create_admin(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)->post(route('users.store'), [
            'name' => 'Admin Falso',
            'username' => 'admin_falso',
            'email' => 'admin@example.com',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
            'role' => UserRole::Admin->value,
        ])->assertSessionHasErrors('role');
    }

    public function test_municipal_can_create_municipal_with_linked_cities(): void
    {
        $city = City::factory()->create();
        $municipal = User::factory()->municipal()->create();
        $municipal->cities()->attach($city->id);

        $response = $this->actingAs($municipal)->post(route('users.store'), [
            'name' => 'Municipal Secundário',
            'username' => 'municipal_sec',
            'email' => 'municipal2@example.com',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
            'role' => UserRole::Municipal->value,
            'city_ids' => [$city->id],
        ]);

        $response->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'municipal2@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->isMunicipal());
        $this->assertTrue($created->cities()->whereKey($city->id)->exists());
    }

    public function test_municipal_cannot_assign_city_outside_scope(): void
    {
        $cityA = City::factory()->create(['name' => 'Cidade A']);
        $cityB = City::factory()->create(['name' => 'Cidade B']);
        $municipal = User::factory()->municipal()->create();
        $municipal->cities()->attach($cityA->id);

        $this->actingAs($municipal)->post(route('users.store'), [
            'name' => 'Municipal Inválido',
            'username' => 'municipal_inv',
            'email' => 'inv@example.com',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
            'role' => UserRole::Municipal->value,
            'city_ids' => [$cityB->id],
        ])->assertSessionHasErrors('city_ids');
    }

    public function test_admin_can_access_users_index(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Novo Utilizador',
            'username' => 'novo_utilizador',
            'email' => 'novo@example.com',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
            'role' => UserRole::User->value,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'role' => UserRole::User->value,
        ]);

        $created = User::query()->where('email', 'novo@example.com')->first();
        $this->assertNotNull($created);

        $this->assertDatabaseHas('admin_user_logs', [
            'actor_id' => $admin->id,
            'subject_user_id' => $created->id,
            'action' => 'user_created',
        ]);
    }

    public function test_utilizador_cannot_access_sessions_index(): void
    {
        $user = User::factory()->utilizador()->create();

        $this->actingAs($user)->get(route('users.sessions.index'))->assertForbidden();
    }

    public function test_municipal_cannot_access_admin_geo_sync(): void
    {
        $municipal = User::factory()->municipal()->create();

        $this->actingAs($municipal)->get(route('admin.geo-sync.index'))->assertForbidden();
    }
}
