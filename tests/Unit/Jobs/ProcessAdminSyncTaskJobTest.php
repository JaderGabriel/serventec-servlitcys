<?php

namespace Tests\Unit\Jobs;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Jobs\ProcessAdminSyncTaskJob;
use App\Models\AdminSyncTask;
use App\Services\AdminSync\AdminSyncTaskRunner;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProcessAdminSyncTaskJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function configura_fila_timeout_e_tries_por_defeito(): void
    {
        config([
            'ieducar.admin_sync.queue' => 'admin-sync',
            'ieducar.admin_sync.job_timeout' => 3600,
            'ieducar.admin_sync.tries' => 1,
        ]);

        $job = new ProcessAdminSyncTaskJob(999_999);

        $this->assertSame('admin-sync', $job->queue);
        $this->assertSame(3600, $job->timeout);
        $this->assertSame(1, $job->tries);
    }

    #[Test]
    public function handle_sem_tarefa_e_noop(): void
    {
        $job = new ProcessAdminSyncTaskJob(999_999);

        $job->handle(
            app(AdminSyncTaskRunner::class),
            app(NotificationDispatcher::class),
        );

        $this->assertTrue(true);
    }

    #[Test]
    public function handle_tarefa_ja_completed_e_noop(): void
    {
        $task = AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Fundeb->value,
            'task_key' => 'import_city_year',
            'label' => 'já feito',
            'status' => AdminSyncTaskStatus::Completed->value,
            'payload' => [],
        ]);

        $before = $task->updated_at?->toIso8601String();

        $job = new ProcessAdminSyncTaskJob($task->id);
        $job->handle(
            app(AdminSyncTaskRunner::class),
            app(NotificationDispatcher::class),
        );

        $task->refresh();
        $this->assertSame(AdminSyncTaskStatus::Completed->value, $task->status);
        $this->assertSame($before, $task->updated_at?->toIso8601String());
    }
}
