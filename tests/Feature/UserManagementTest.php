<?php

namespace Tests\Feature;

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

    public function test_guest_cannot_access_user_creation_form(): void
    {
        $this->get(route('users.create'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_users_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_non_admin_cannot_access_user_creation_form(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('users.create'))->assertForbidden();
    }

    public function test_admin_can_access_users_index(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
    }

    public function test_admin_can_access_user_creation_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('users.create'))->assertOk();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Novo Utilizador',
            'username' => 'novo_utilizador',
            'email' => 'novo@example.com',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
            'is_admin' => false,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'username' => 'novo_utilizador',
            'is_admin' => false,
        ]);

        $created = User::query()->where('email', 'novo@example.com')->first();
        $this->assertNotNull($created);
        $this->assertNull($created->birth_date);
        $this->assertNull($created->cpf);

        $this->assertDatabaseHas('admin_user_logs', [
            'actor_id' => $admin->id,
            'subject_user_id' => $created->id,
            'action' => 'user_created',
        ]);
    }

    public function test_non_admin_cannot_create_user_via_post(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('users.store'), [
            'name' => 'X',
            'username' => 'user_x',
            'email' => 'x@example.com',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
        ])->assertForbidden();
    }
}
