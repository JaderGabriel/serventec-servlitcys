<?php

namespace Tests\Unit\Jobs;

use App\Enums\AnalyticsReportExportStatus;
use App\Jobs\GenerateAnalyticsReportPdfJob;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Models\User;
use App\Services\Analytics\AnalyticsReportPdfService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GenerateAnalyticsReportPdfJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function configura_fila_timeout_e_tries(): void
    {
        config([
            'analytics.pdf_report.queue' => 'default',
            'analytics.pdf_report.job_timeout' => 900,
            'analytics.pdf_report.tries' => 2,
        ]);

        $job = new GenerateAnalyticsReportPdfJob(999_999);

        $this->assertSame('default', $job->queue);
        $this->assertSame(900, $job->timeout);
        $this->assertSame(2, $job->tries);
    }

    #[Test]
    public function handle_export_inexistente_e_noop(): void
    {
        $job = new GenerateAnalyticsReportPdfJob(999_999);

        $job->handle(
            app(AnalyticsReportPdfService::class),
            app(NotificationDispatcher::class),
        );

        $this->assertTrue(true);
    }

    #[Test]
    public function handle_export_ja_completed_e_noop(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create();

        $export = AnalyticsReportExport::query()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => AnalyticsReportExportStatus::Completed->value,
            'filters' => [],
            'file_disk' => 'local',
        ]);

        $job = new GenerateAnalyticsReportPdfJob($export->id);
        $job->handle(
            app(AnalyticsReportPdfService::class),
            app(NotificationDispatcher::class),
        );

        $export->refresh();
        $this->assertSame(AnalyticsReportExportStatus::Completed->value, $export->status);
    }
}
