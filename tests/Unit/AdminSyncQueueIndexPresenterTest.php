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

        $this->assertCount(7, $cards);
        $this->assertSame('fundeb', $cards[0]['id']);
        $this->assertSame(AdminSyncDomain::Fundeb, $cards[0]['domain']);
        $cadastro = collect($cards)->firstWhere('id', 'cadastro');
        $this->assertNotNull($cadastro);
        $this->assertSame(AdminSyncDomain::Cadastro, $cadastro['domain']);
        $this->assertSame('admin.cadunico-sync.index', $cadastro['admin_route'] ?? null);
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

    public function test_horizonte_theme_card_summarizes_hub_coverage(): void
    {
        $card = AdminSyncQueueIndexPresenter::horizonteThemeCard([
            'enabled' => true,
            'feed_enabled' => true,
            'coverage' => [
                'universe_municipios' => 1200,
                'with_full_triad' => 450,
            ],
            'phases' => [
                ['ok' => true],
                ['ok' => false],
            ],
            'last_feed' => [
                'success' => true,
                'finished_at' => now()->toIso8601String(),
            ],
        ]);

        $this->assertSame('horizonte', $card['id']);
        $this->assertSame(1200, $card['universe']);
        $this->assertSame(450, $card['triad']);
        $this->assertSame(1, $card['status_ok']);
        $this->assertSame(1, $card['status_alert']);
        $this->assertTrue($card['last_feed_success']);
    }
}
