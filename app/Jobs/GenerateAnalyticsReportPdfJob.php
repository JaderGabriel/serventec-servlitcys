<?php

namespace App\Jobs;

use App\Enums\AnalyticsReportExportStatus;
use App\Models\AnalyticsReportExport;
use App\Services\Analytics\AnalyticsReportPdfService;
use App\Support\Pulse\PulseOperationRecorder;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Request;
use Throwable;

class GenerateAnalyticsReportPdfJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public int $tries;

    public function __construct(
        public int $analyticsReportExportId,
    ) {
        $this->timeout = max(120, (int) config('analytics.pdf_report.job_timeout', 900));
        $this->tries = max(1, (int) config('analytics.pdf_report.tries', 2));
        $this->onQueue((string) config('analytics.pdf_report.queue', 'default'));
        $connection = config('analytics.pdf_report.connection');
        if ($connection !== null && $connection !== '') {
            $this->onConnection((string) $connection);
        }
    }

    public function handle(AnalyticsReportPdfService $pdfService, NotificationDispatcher $notifications): void
    {
        $export = AnalyticsReportExport::query()->with('city')->find($this->analyticsReportExportId);
        if ($export === null) {
            return;
        }

        if ($export->status === AnalyticsReportExportStatus::Completed->value) {
            return;
        }

        $export->update([
            'status' => AnalyticsReportExportStatus::Processing->value,
            'started_at' => $export->started_at ?? now(),
            'error_message' => null,
        ]);

        $city = $export->city;
        if ($city === null) {
            $export->update([
                'status' => AnalyticsReportExportStatus::Failed->value,
                'error_message' => __('Cidade não encontrada.'),
                'completed_at' => now(),
            ]);
            $notifications->pdfExportFinished($export->fresh());

            return;
        }

        $filters = $this->filtersFromExport($export);

        try {
            $pdfKey = 'pdf:analytics|cid:'.(int) $city->id;
            $result = PulseOperationRecorder::measure(
                $pdfKey,
                fn (): array => $pdfService->generate($export, $city, $filters),
            );
            $export->update([
                'status' => AnalyticsReportExportStatus::Completed->value,
                'file_path' => $result['path'],
                'file_disk' => $result['disk'],
                'page_count' => $result['page_count'],
                'completed_at' => now(),
            ]);
            $notifications->pdfExportFinished($export->fresh());
        } catch (Throwable $e) {
            PulseOperationRecorder::recordFailure('pdf:analytics|cid:'.(int) $city->id);
            $export->update([
                'status' => AnalyticsReportExportStatus::Failed->value,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $notifications->pdfExportFinished($export->fresh());

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $export = AnalyticsReportExport::query()->find($this->analyticsReportExportId);
        if ($export === null || $export->status === AnalyticsReportExportStatus::Completed->value) {
            return;
        }

        $export->update([
            'status' => AnalyticsReportExportStatus::Failed->value,
            'error_message' => $exception?->getMessage() ?? __('Falha na geração do PDF.'),
            'completed_at' => $export->completed_at ?? now(),
        ]);

        app(NotificationDispatcher::class)->pdfExportFinished($export->fresh());
    }

    private function filtersFromExport(AnalyticsReportExport $export): IeducarFilterState
    {
        $payload = is_array($export->filters) ? $export->filters : [];
        $request = Request::create('/', 'GET', $payload);

        return IeducarFilterState::fromRequest($request);
    }
}
