<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicDataImportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_public_data_hub(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.public-data.index'))
            ->assertOk();
    }

    public function test_municipal_user_cannot_view_public_data_hub(): void
    {
        $user = User::factory()->municipal()->create();

        $this->actingAs($user)
            ->get(route('admin.public-data.index'))
            ->assertForbidden();
    }

    public function test_platform_user_cannot_run_public_data_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.public-data.run'), [
                'source_id' => 'fundeb_fnde',
                'action_key' => 'import',
                'ano' => 2024,
            ])
            ->assertForbidden();
    }

    public function test_admin_run_validates_required_fields(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.public-data.run'), [])
            ->assertSessionHasErrors(['source_id', 'action_key']);
    }
}
