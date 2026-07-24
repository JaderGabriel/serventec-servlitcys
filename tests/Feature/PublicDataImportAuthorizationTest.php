<?php

namespace Tests\Feature;

use App\Jobs\ProcessAdminSyncTaskJob;
use App\Models\City;
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

    public function test_admin_run_queues_fundeb_city_year_import(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $city = City::factory()->create(['ibge_municipio' => '2901106']);

        $response = $this->actingAs($admin)->post(route('admin.public-data.run'), [
            'source_id' => 'fundeb_fnde',
            'action_key' => 'import_city_year',
            'city_id' => $city->id,
            'ano' => 2024,
        ]);

        $response->assertRedirect(route('admin.public-data.index'));
        $response->assertSessionHas('admin_sync_queued.task_id');

        Queue::assertPushed(ProcessAdminSyncTaskJob::class);
    }

    public function test_platform_user_cannot_check_official(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.public-data.check-official'))
            ->assertForbidden();
    }

    public function test_admin_check_official_when_disabled_flashes_error(): void
    {
        config(['public_data_availability.enabled' => false]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.public-data.check-official'));

        $response->assertRedirect(route('admin.public-data.index'));
        $response->assertSessionHas('public_data_error');
    }
}
