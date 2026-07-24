<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_post_horizonte_feed_when_disabled_flashes_error(): void
    {
        config([
            'horizonte.enabled' => true,
            'horizonte.fortnightly_feed.enabled' => false,
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.horizonte-import.feed'), [
            'uf' => 'SP',
            'phases' => ['fundeb_receita', 'censo_matriculas'],
        ]);

        $response->assertRedirect(route('admin.horizonte-import.index'));
        $response->assertSessionHas('public_data_error');
    }

    public function test_post_horizonte_feed_rejects_invalid_uf(): void
    {
        config([
            'horizonte.enabled' => true,
            'horizonte.fortnightly_feed.enabled' => true,
        ]);

        $admin = User::factory()->admin()->create();

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

    public function test_platform_user_cannot_post_horizonte_feed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.horizonte-import.feed'), [
                'phases' => ['fundeb_receita'],
            ])
            ->assertForbidden();
    }

    public function test_municipal_user_cannot_post_horizonte_educacenso_sync(): void
    {
        $user = User::factory()->municipal()->create();

        $this->actingAs($user)
            ->post(route('admin.horizonte-import.educacenso-sync'), [
                'steps' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_post_educacenso_sync_when_disabled_flashes_error(): void
    {
        config(['horizonte.enabled' => false]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.horizonte-import.educacenso-sync'), [
            'steps' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('public_data_error');
    }

    public function test_admin_post_municipal_geo_sync_when_disabled_flashes_error(): void
    {
        config(['horizonte.enabled' => false]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.horizonte-import.municipal-geo-sync'), [
            'mode' => 'step',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('public_data_error');
    }

    public function test_platform_user_cannot_post_bundle_export(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.horizonte-import.bundle-export'))
            ->assertForbidden();
    }

    public function test_municipal_user_cannot_post_bundle_import(): void
    {
        $user = User::factory()->municipal()->create();

        $this->actingAs($user)
            ->post(route('admin.horizonte-import.bundle-import'))
            ->assertForbidden();
    }

    public function test_admin_post_bundle_import_validates_bundle_file(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.horizonte-import.bundle-import'), [])
            ->assertSessionHasErrors(['bundle']);
    }
}
