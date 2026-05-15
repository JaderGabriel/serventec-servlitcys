<?php

namespace Tests\Feature;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Jobs\ProcessAdminSyncTaskJob;
use App\Models\AdminSyncTask;
use App\Models\City;
use App\Models\User;
use App\Services\AdminSync\AdminSyncQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminSyncQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_creates_pending_task(): void
    {
        Queue::fake();

        $city = City::factory()->create(['ibge_municipio' => '2901106']);

        $task = app(AdminSyncQueueService::class)->dispatch(
            AdminSyncDomain::Fundeb,
            'new_city_auto',
            'Teste FUNDEB',
            ['city_id' => $city->id, 'years' => [2024, 2025]],
            $city->id,
        );

        $this->assertDatabaseHas('admin_sync_tasks', [
            'id' => $task->id,
            'domain' => 'fundeb',
            'status' => AdminSyncTaskStatus::Pending->value,
            'city_id' => $city->id,
        ]);

        Queue::assertPushed(ProcessAdminSyncTaskJob::class, function (ProcessAdminSyncTaskJob $job) use ($task) {
            return $job->adminSyncTaskId === $task->id;
        });
    }

    public function test_admin_can_view_sync_queue_index(): void
    {
        $admin = User::factory()->admin()->create();
        AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Geo->value,
            'task_key' => 'ieducar',
            'label' => 'Geo teste',
            'status' => AdminSyncTaskStatus::Pending->value,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.sync-queue.index'));

        $response->assertOk();
        $response->assertSee('Geo teste');
    }

    public function test_job_processes_fundeb_task(): void
    {
        config([
            'ieducar.fundeb.open_data.json_url' => 'https://benchmark.test/fundeb/{ibge}/{ano}',
            'ieducar.fundeb.open_data.resource_id' => '',
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
        ]);

        Http::fake([
            'benchmark.test/*' => Http::response([
                ['codigo_ibge' => '2901106', 'ano' => 2024, 'vaaf' => 5200.0],
            ], 200),
        ]);

        $city = City::factory()->create(['ibge_municipio' => '2901106']);

        $task = AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Fundeb->value,
            'task_key' => 'import_city_year',
            'label' => 'Import teste',
            'city_id' => $city->id,
            'status' => AdminSyncTaskStatus::Pending->value,
            'payload' => [
                'city_id' => $city->id,
                'ano' => 2024,
                'use_nearest_year' => false,
            ],
        ]);

        $job = new ProcessAdminSyncTaskJob($task->id);
        $job->handle(app(\App\Services\AdminSync\AdminSyncTaskRunner::class));

        $task->refresh();
        $this->assertSame(AdminSyncTaskStatus::Completed->value, $task->status);
        $this->assertTrue((bool) ($task->result['success'] ?? false));
    }
}
