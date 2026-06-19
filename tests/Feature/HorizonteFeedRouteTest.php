<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Horizonte\HorizonteFortnightlyFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HorizonteFeedRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_horizonte_feed_redirects_to_hub_instead_of_405(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.public-data.horizonte-feed'));

        $response->assertRedirect(route('admin.public-data.index', ['hub' => 'horizonte']).'#horizonte-hub');
    }

    public function test_post_horizonte_feed_runs_feed_and_redirects_to_hub(): void
    {
        config([
            'horizonte.enabled' => true,
            'horizonte.fortnightly_feed.enabled' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $this->mock(HorizonteFortnightlyFeedService::class, function ($mock): void {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'OK',
                    'phases' => [],
                ]);
        });

        $response = $this->actingAs($admin)->post(route('admin.public-data.horizonte-feed'));

        $response->assertRedirect(route('admin.public-data.index', ['hub' => 'horizonte']).'#horizonte-hub');
        $response->assertSessionHas('horizonte_feed.success', true);
    }
}
