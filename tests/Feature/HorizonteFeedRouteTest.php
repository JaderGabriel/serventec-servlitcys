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

        $response->assertRedirect(route('admin.horizonte-import.index'));
    }

    public function test_post_horizonte_feed_runs_feed_and_redirects_to_hub(): void
    {
        config([
            'horizonte.enabled' => true,
            'horizonte.fortnightly_feed.enabled' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $this->mock(HorizonteFortnightlyFeedService::class, function ($mock): void {
            $mock->shouldReceive('runStaged')
                ->once()
                ->withArgs(function (array $options): bool {
                    return ($options['reset'] ?? false) === true
                        && ($options['uf'] ?? '') === 'SP'
                        && ($options['skip_fundeb'] ?? true) === false;
                })
                ->andReturn([
                    'success' => true,
                    'message' => 'OK',
                    'phases' => [],
                ]);
        });

        $response = $this->actingAs($admin)->post(route('admin.horizonte-import.feed'), [
            'uf' => 'SP',
            'phases' => ['fundeb_receita', 'censo_matriculas'],
        ]);

        $response->assertRedirect(route('admin.horizonte-import.index'));
        $response->assertSessionHas('horizonte_feed.success', true);
    }

    public function test_post_horizonte_feed_rejects_invalid_uf(): void
    {
        config([
            'horizonte.enabled' => true,
            'horizonte.fortnightly_feed.enabled' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $this->mock(HorizonteFortnightlyFeedService::class, function ($mock): void {
            $mock->shouldNotReceive('runStaged');
            $mock->shouldNotReceive('run');
        });

        $response = $this->actingAs($admin)->post(route('admin.horizonte-import.feed'), [
            'uf' => 'XX',
            'phases' => ['fundeb_receita'],
        ]);

        $response->assertRedirect(route('admin.horizonte-import.index'));
        $response->assertSessionHas('public_data_error');
    }

    public function test_post_horizonte_feed_requires_at_least_one_phase(): void
    {
        config([
            'horizonte.enabled' => true,
            'horizonte.fortnightly_feed.enabled' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $this->mock(HorizonteFortnightlyFeedService::class, function ($mock): void {
            $mock->shouldNotReceive('runStaged');
            $mock->shouldNotReceive('run');
        });

        $response = $this->actingAs($admin)->post(route('admin.horizonte-import.feed'), [
            'phases' => [],
        ]);

        $response->assertRedirect(route('admin.horizonte-import.index'));
        $response->assertSessionHas('public_data_error');
    }

    public function test_public_data_horizonte_hub_redirects_to_dedicated_hub(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.public-data.index', ['hub' => 'horizonte']));

        $response->assertRedirect(route('admin.horizonte-import.index'));
    }
}
