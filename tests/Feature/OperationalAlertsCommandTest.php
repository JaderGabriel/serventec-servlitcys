<?php

namespace Tests\Feature;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\UserRole;
use App\Models\AdminSyncTask;
use App\Models\User;
use App\Notifications\AppMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OperationalAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_notifies_admins_on_sync_failures_without_dashboard_visit(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        AdminSyncTask::query()->create([
            'type' => 'geo',
            'status' => AdminSyncTaskStatus::Failed->value,
            'payload' => [],
            'output_log' => 'erro',
        ]);

        $this->artisan('notifications:operational-alerts')
            ->assertSuccessful();

        Notification::assertSentTo($admin, AppMessageNotification::class, function (AppMessageNotification $n): bool {
            return ($n->payload['dedupe_key'] ?? '') === 'ops:sync_failed_24h';
        });
    }

    #[Test]
    public function command_exits_when_operational_alerts_disabled(): void
    {
        config(['notifications.operational_alerts.enabled' => false]);

        $this->artisan('notifications:operational-alerts')
            ->expectsOutputToContain('desactivados')
            ->assertSuccessful();
    }
}
