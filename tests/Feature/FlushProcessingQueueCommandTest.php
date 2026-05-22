<?php

namespace Tests\Feature;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Jobs\ProcessAdminSyncTaskJob;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class FlushProcessingQueueCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_nao_apaga_tarefas(): void
    {
        AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Geo->value,
            'task_key' => 'pipeline',
            'label' => 'Teste',
            'status' => AdminSyncTaskStatus::Pending->value,
        ]);

        $this->artisan('app:flush-processing-queue', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(1, AdminSyncTask::query()->count());
    }

    public function test_flush_sync_remove_tarefas_pendentes_em_local(): void
    {
        AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Geo->value,
            'task_key' => 'pipeline',
            'label' => 'Teste',
            'status' => AdminSyncTaskStatus::Pending->value,
        ]);

        $this->artisan('app:flush-processing-queue', ['--only-sync' => true, '--no-interaction' => true])
            ->expectsConfirmation(__('Confirma esvaziar a fila de processamento?'), 'yes')
            ->assertSuccessful();

        $this->assertSame(0, AdminSyncTask::query()->count());
    }

    public function test_production_exige_confirm_slug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('app:flush-processing-queue')
            ->assertFailed();

        $this->artisan('app:flush-processing-queue', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_production_aceita_slug_configurado(): void
    {
        config(['ieducar.admin_sync.flush_confirm_slug' => 'teste-slug-seguro']);
        $this->app->detectEnvironment(fn () => 'production');

        AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Fundeb->value,
            'task_key' => 'x',
            'label' => 'FUNDEB',
            'status' => AdminSyncTaskStatus::Failed->value,
        ]);

        $this->artisan('app:flush-processing-queue', [
            '--confirm' => 'teste-slug-seguro',
            '--include-failed' => true,
            '--only-sync' => true,
        ])->assertSuccessful();

        $this->assertSame(0, AdminSyncTask::query()->count());
    }

    public function test_flush_pdf_remove_exportacoes_pendentes(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create();

        AnalyticsReportExport::query()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => AnalyticsReportExportStatus::Pending->value,
            'filters' => [],
        ]);

        $this->artisan('app:flush-processing-queue', [
            '--only-pdf' => true,
            '--no-interaction' => true,
        ])
            ->expectsConfirmation(__('Confirma esvaziar a fila de processamento?'), 'yes')
            ->assertSuccessful();

        $this->assertSame(0, AnalyticsReportExport::query()->count());
    }

    public function test_nao_remove_concluidas_sem_flag(): void
    {
        AdminSyncTask::query()->create([
            'domain' => AdminSyncDomain::Geo->value,
            'task_key' => 'x',
            'label' => 'OK',
            'status' => AdminSyncTaskStatus::Completed->value,
        ]);

        $this->artisan('app:flush-processing-queue', ['--only-sync' => true])
            ->expectsConfirmation(__('Confirma esvaziar a fila de processamento?'), 'yes')
            ->assertSuccessful();

        $this->assertSame(1, AdminSyncTask::query()->count());
    }
}
