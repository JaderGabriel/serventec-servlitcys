<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_user_creation_form(): void
    {
        $this->get(route('users.create'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_user_creation_form(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('users.create'))->assertForbidden();
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
            'birth_date' => '1995-06-15',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
            'is_admin' => false,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'username' => 'novo_utilizador',
            'is_admin' => false,
        ]);
    }

    public function test_non_admin_cannot_create_user_via_post(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('users.store'), [
            'name' => 'X',
            'username' => 'user_x',
            'email' => 'x@example.com',
            'birth_date' => '2000-01-01',
            'password' => 'PasswordSegura1!',
            'password_confirmation' => 'PasswordSegura1!',
        ])->assertForbidden();
    }
}
