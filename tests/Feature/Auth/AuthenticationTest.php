<?php

namespace Tests\Feature\Auth;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard.analytics', absolute: false));
    }

    public function test_admin_is_redirected_to_dashboard_after_login(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/login', [
            'username' => $admin->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_municipal_is_redirected_to_analytics_with_city_after_login(): void
    {
        $city = City::factory()->create();
        $municipal = User::factory()->municipal()->create();
        $municipal->cities()->attach($city->id);

        $response = $this->post('/login', [
            'username' => $municipal->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard.analytics', ['city_id' => $city->id], absolute: false));
    }

    public function test_municipal_cannot_access_admin_dashboard(): void
    {
        $municipal = User::factory()->municipal()->create();

        $this->actingAs($municipal)
            ->get(route('dashboard'))
            ->assertRedirect(route('dashboard.analytics'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
