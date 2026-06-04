<?php

namespace Tests\Unit;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Models\AdminSyncTask;
use App\Support\Notifications\NotificationKinds;
use App\Support\Notifications\NotificationPresenter;
use App\Support\Notifications\NotificationQueuePresentation;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationQueuePresentationTest extends TestCase
{
    #[Test]
    public function sincronizacao_aponta_para_detalhe_da_tarefa(): void
    {
        $task = new AdminSyncTask([
            'domain' => AdminSyncDomain::Fundeb->value,
            'status' => AdminSyncTaskStatus::Pending->value,
            'label' => 'Import FUNDEB',
        ]);
        $task->id = 99;

        $presentation = NotificationQueuePresentation::forSyncTask($task);

        $this->assertSame(99, $presentation['sync_task_id']);
        $this->assertSame('banknotes', $presentation['queue_icon']);
        $this->assertSame('amber', $presentation['queue_accent']);
        $this->assertStringContainsString('/admin/sync-queue/99', $presentation['action_url']);
    }

    #[Test]
    public function pdf_enfileirado_aponta_para_ancora_do_export(): void
    {
        $presentation = NotificationQueuePresentation::forPdf(42);

        $this->assertSame(42, $presentation['pdf_export_id']);
        $this->assertSame('document-text', $presentation['queue_icon']);
        $this->assertSame('rose', $presentation['queue_accent']);
        $this->assertStringEndsWith('#export-42', $presentation['action_url']);
    }

    #[Test]
    public function presenter_expoe_tema_quando_dados_ja_estao_no_payload(): void
    {
        $notification = new DatabaseNotification([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'title' => 'Sync',
                'body' => 'Done',
                'icon' => 'success',
                'priority' => 'normal',
                'kind' => NotificationKinds::ADMIN_SYNC,
                'queue_domain' => 'geo',
                'queue_icon' => 'map-pin',
                'queue_accent' => 'sky',
                'queue_label' => 'Georreferenciamento',
                'action_url' => 'https://example.test/admin/sync-queue/7',
            ],
            'read_at' => null,
            'created_at' => now(),
        ]);

        $item = NotificationPresenter::fromDatabaseNotification($notification);

        $this->assertSame('map-pin', $item['queue_icon']);
        $this->assertSame('sky', $item['queue_accent']);
        $this->assertNotNull($item['queue_icon_html']);
        $this->assertStringContainsString('<svg', (string) $item['queue_icon_html']);
        $this->assertSame('Georreferenciamento', $item['queue_label']);
    }
}
