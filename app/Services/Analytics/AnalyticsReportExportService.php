<?php

namespace App\Services\Analytics;

use App\Enums\AnalyticsReportExportStatus;
use App\Jobs\GenerateAnalyticsReportPdfJob;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Models\User;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Support\Facades\Storage;

final class AnalyticsReportExportService
{
    /**
     * @return array{export: AnalyticsReportExport, message: string}
     */
    public function dispatch(User $user, City $city, IeducarFilterState $filters): array
    {
        $this->pruneOldExports($user);

        $export = AnalyticsReportExport::query()->create([
            'user_id' => $user->id,
            'city_id' => $city->id,
            'status' => AnalyticsReportExportStatus::Pending->value,
            'filters' => $filters->toQueryParamsWithCity((int) $city->id),
            'file_disk' => (string) config('analytics.pdf_report.disk', 'local'),
        ]);

        $queue = (string) config('analytics.pdf_report.queue', 'default');
        $connection = config('analytics.pdf_report.connection');

        $pending = GenerateAnalyticsReportPdfJob::dispatch($export->id)->onQueue($queue);
        if ($connection !== null && $connection !== '') {
            $pending->onConnection((string) $connection);
        }
        $pending->afterResponse();

        return [
            'export' => $export,
            'message' => __('Relatório PDF em geração (fila :queue). Actualize esta página em alguns minutos para descarregar.', [
                'queue' => $queue,
            ]),
        ];
    }

    /**
     * @return list<AnalyticsReportExport>
     */
    public function recentForUserCity(User $user, City $city, int $limit = 5): array
    {
        return AnalyticsReportExport::query()
            ->where('user_id', $user->id)
            ->where('city_id', $city->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    private function pruneOldExports(User $user): void
    {
        $max = max(1, (int) config('analytics.pdf_report.max_exports_per_user', 10));

        $keepIds = AnalyticsReportExport::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit($max)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return;
        }

        $exports = AnalyticsReportExport::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds->all())
            ->get();
        foreach ($exports as $export) {
            if (filled($export->file_path)) {
                Storage::disk($export->file_disk)->delete($export->file_path);
            }
            $export->delete();
        }
    }
}
