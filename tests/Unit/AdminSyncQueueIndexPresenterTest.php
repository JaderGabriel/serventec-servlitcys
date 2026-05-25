<?php

namespace Tests\Unit;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Models\AdminSyncTask;
use App\Support\Admin\AdminSyncQueueIndexPresenter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminSyncQueueIndexPresenterTest extends TestCase
{
    public function test_sync_theme_cards_include_all_domains(): void
    {
        $cards = AdminSyncQueueIndexPresenter::syncThemeCards(collect(), 'admin-sync');

        $this->assertCount(6, $cards);
        $this->assertSame('fundeb', $cards[0]['id']);
        $this->assertSame(AdminSyncDomain::Fundeb, $cards[0]['domain']);
    }

    public function test_task_context_lines_include_summary(): void
    {
        $task = new AdminSyncTask([
            'domain' => AdminSyncDomain::Ieducar->value,
            'task_key' => 'inclusion_nee_export',
            'payload' => ['format' => 'xlsx', 'ano_letivo' => '2024'],
        ]);

        $lines = AdminSyncQueueIndexPresenter::taskContextLines($task);

        $this->assertNotEmpty($lines);
        $this->assertStringContainsString('NEE', $lines[0]);
    }

    public function test_export_downloadable_when_file_exists(): void
    {
        $path = storage_path('app/admin_sync/exports/test-unit.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, 'a;b');

        $task = new AdminSyncTask([
            'status' => AdminSyncTaskStatus::Completed->value,
            'result' => [
                'export_path' => $path,
                'export_filename' => 'nee.csv',
            ],
            'payload' => ['format' => 'csv'],
        ]);

        $this->assertTrue($task->isExportDownloadable());
        $this->assertSame('CSV', $task->exportFormatLabel());

        @unlink($path);
    }
}
