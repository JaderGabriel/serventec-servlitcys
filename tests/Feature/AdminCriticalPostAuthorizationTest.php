<?php

namespace Tests\Feature;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Jobs\ProcessAdminSyncTaskJob;
use App\Models\AdminSyncTask;
use App\Models\City;
use App\Models\User;
use App\Services\Fundeb\FundebImportMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AdminCriticalPostAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_user_cannot_run_geo_sync(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.geo-sync.run'), ['step' => 'ieducar'])
            ->assertForbidden();
    }

    public function test_admin_geo_sync_queues_task(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.geo-sync.run'), [
            'step' => 'ieducar',
        ]);

        $response->assertRedirect(route('admin.geo-sync.index'));
        $response->assertSessionHas('admin_sync_queued.task_id');
        Queue::assertPushed(ProcessAdminSyncTaskJob::class);
    }

    public function test_platform_user_cannot_run_pedagogical_sync(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.pedagogical-sync.run'), ['action' => 'import_urls'])
            ->assertForbidden();
    }

    public function test_admin_pedagogical_sync_queues_import_urls(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.pedagogical-sync.run'), [
            'action' => 'import_urls',
        ]);

        $response->assertRedirect(route('admin.pedagogical-sync.index'));
        $response->assertSessionHas('admin_sync_queued.task_id');
        Queue::assertPushed(ProcessAdminSyncTaskJob::class);
    }

    public function test_platform_user_cannot_import_fundeb(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.ieducar-compatibility.fundeb-import'), [
                'city_id' => $city->id,
                'ano' => 2024,
            ])
            ->assertForbidden();
    }

    public function test_admin_fundeb_import_queues_task(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $city = City::factory()->create(['ibge_municipio' => '2901106']);

        $response = $this->actingAs($admin)->post(route('admin.ieducar-compatibility.fundeb-import'), [
            'city_id' => $city->id,
            'ano' => 2024,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('admin_sync_queued.task_id');
        Queue::assertPushed(ProcessAdminSyncTaskJob::class);
    }

    public function test_admin_fundeb_sync_all_requires_cities_or_all_flag(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.ieducar-compatibility.fundeb-sync-all'), [
            'ano_from' => 2023,
            'ano_to' => 2024,
            'import_mode' => FundebImportMode::UPDATE,
            'all_cities' => false,
            'city_ids' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('fundeb_import_error');
        Queue::assertNothingPushed();
    }

    public function test_platform_user_cannot_upsert_horizonte_sge(): void
    {
        config(['horizonte.enabled' => true, 'horizonte.sge.enabled' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.horizonte.sge.upsert', ['ibge' => '2901106']), [
                'system' => 'i-Educar',
            ])
            ->assertForbidden();
    }

    public function test_admin_sge_upsert_rejects_invalid_ibge(): void
    {
        config(['horizonte.enabled' => true, 'horizonte.sge.enabled' => true]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson(route('admin.horizonte.sge.upsert', ['ibge' => 'abc']), [
                'system' => 'i-Educar',
            ])
            ->assertStatus(422);
    }

    public function test_platform_user_cannot_resume_sync_queue_task(): void
    {
        $user = User::factory()->create();
        $task = AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Fundeb->value,
            'task_key' => 'import_city_year',
            'label' => 'falhou',
            'status' => AdminSyncTaskStatus::Failed->value,
            'payload' => [],
        ]);

        $this->actingAs($user)
            ->post(route('admin.sync-queue.resume', $task))
            ->assertForbidden();
    }

    public function test_admin_cannot_resume_pending_task(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $task = AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Fundeb->value,
            'task_key' => 'import_city_year',
            'label' => 'pending',
            'status' => AdminSyncTaskStatus::Pending->value,
            'payload' => [],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sync-queue.resume', $task))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_admin_resume_failed_task_queues_again(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $task = AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Fundeb->value,
            'task_key' => 'import_city_year',
            'label' => 'falhou',
            'status' => AdminSyncTaskStatus::Failed->value,
            'payload' => ['city_id' => 1, 'ano' => 2024],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.sync-queue.resume', $task));

        $response->assertRedirect(route('admin.sync-queue.show', $task));
        $response->assertSessionHas('admin_sync_queued.task_id', $task->id);
        Queue::assertPushed(ProcessAdminSyncTaskJob::class);
    }
}
