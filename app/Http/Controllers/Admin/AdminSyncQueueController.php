<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminSyncQueueController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->input('status', ''));
        $domain = trim((string) $request->input('domain', ''));

        $query = AdminSyncTask::query()
            ->with(['city:id,name,uf', 'queuedBy:id,name'])
            ->orderByDesc('id');

        if ($status !== '' && AdminSyncTaskStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }
        if ($domain !== '') {
            $query->where('domain', $domain);
        }

        $tasks = $query->paginate(25)->withQueryString();

        $counts = AdminSyncTask::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pdfStatus = trim((string) $request->input('pdf_status', ''));
        $pdfQuery = AnalyticsReportExport::query()
            ->with(['city:id,name,uf', 'user:id,name'])
            ->orderByDesc('id');
        if ($pdfStatus !== '' && AnalyticsReportExportStatus::tryFrom($pdfStatus) !== null) {
            $pdfQuery->where('status', $pdfStatus);
        }
        $pdfExports = $pdfQuery->paginate(15, ['*'], 'pdf_page')->withQueryString();

        $pdfCounts = AnalyticsReportExport::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $queueDefault = (string) config('queue.default', 'database');

        return view('admin.sync-queue.index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'filterStatus' => $status,
            'filterDomain' => $domain,
            'syncQueueName' => (string) config('ieducar.admin_sync.queue', 'admin-sync'),
            'syncQueueConnection' => config('ieducar.admin_sync.connection') ?? $queueDefault,
            'pdfExports' => $pdfExports,
            'pdfCounts' => $pdfCounts,
            'filterPdfStatus' => $pdfStatus,
            'pdfQueueName' => (string) config('analytics.pdf_report.queue', 'default'),
            'pdfQueueConnection' => config('analytics.pdf_report.connection') ?? $queueDefault,
            'queueDefault' => $queueDefault,
            'queueIsSync' => $queueDefault === 'sync',
        ]);
    }

    public function show(AdminSyncTask $task): View
    {
        $task->load(['city:id,name,uf,ibge_municipio', 'queuedBy:id,name']);

        return view('admin.sync-queue.show', [
            'task' => $task,
        ]);
    }

    public function download(AdminSyncTask $task): BinaryFileResponse
    {
        if ($task->status !== AdminSyncTaskStatus::Completed->value) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $path = (string) ($task->result['export_path'] ?? '');
        if ($path === '' || ! is_readable($path)) {
            abort(Response::HTTP_NOT_FOUND, __('Ficheiro de exportação não disponível.'));
        }

        $filename = (string) ($task->result['export_filename'] ?? basename($path));

        return response()->download($path, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
