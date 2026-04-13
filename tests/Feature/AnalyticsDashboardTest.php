<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_analytics(): void
    {
        $this->get(route('dashboard.analytics'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_analytics_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.analytics'))
            ->assertOk();
    }

    public function test_filter_options_requires_city_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('dashboard.analytics.filter-options', [
                'kind' => 'turno',
            ]))
            ->assertUnprocessable();
    }
}
