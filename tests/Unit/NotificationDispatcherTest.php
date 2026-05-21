<?php

namespace Tests\Unit;

use App\Enums\AnalyticsReportExportStatus;
use App\Enums\NotificationPriority;
use App\Enums\UserRole;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Models\User;
use App\Notifications\AppMessageNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pdf_enfileirado_notifica_utilizador(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
        $city = City::factory()->create();

        $export = AnalyticsReportExport::query()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => AnalyticsReportExportStatus::Pending->value,
            'filters' => [],
            'file_disk' => 'local',
        ]);

        app(NotificationDispatcher::class)->pdfExportQueued($export);

        Notification::assertSentTo($user, AppMessageNotification::class, function (AppMessageNotification $n): bool {
            return ($n->payload['kind'] ?? '') === 'pdf_export'
                && str_contains((string) ($n->payload['title'] ?? ''), 'enfileirado');
        });
    }

    #[Test]
    public function falha_pdf_e_critica_e_deduplica(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Municipal,
            'is_active' => true,
        ]);
        $city = City::factory()->create();

        $export = AnalyticsReportExport::query()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => AnalyticsReportExportStatus::Failed->value,
            'error_message' => 'Timeout',
            'filters' => [],
            'file_disk' => 'local',
        ]);

        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->pdfExportFinished($export);
        $dispatcher->pdfExportFinished($export->fresh());

        $this->assertSame(1, $user->notifications()->count());
        $data = $user->notifications()->first()->data;
        $this->assertSame(NotificationPriority::Critical->value, $data['priority'] ?? null);
    }

    #[Test]
    public function conta_desactivada_e_critica(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $actor = User::factory()->admin()->create();

        app(NotificationDispatcher::class)->accountUpdated($user, $actor, true, false);

        $data = $user->fresh()->notifications()->first()->data;
        $this->assertSame(NotificationPriority::Critical->value, $data['priority'] ?? null);
    }
}
