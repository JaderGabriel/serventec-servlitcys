<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Services\Admin\HorizonteImportHubStatusService;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Notifications\OperationalAlertsNotifier;
use App\Support\Admin\AdminSyncQueueIndexPresenter;
use App\Support\Admin\ImportHubThemeCatalog;
use App\Support\Admin\ExternalImportImpact;
use App\Support\SyncQueue\SyncQueueUserScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminSyncQueueController extends Controller
{
    public function index(Request $request, OperationalAlertsNotifier $operationalAlerts, HorizonteImportHubStatusService $horizonteHub): View
    {
        $user = $request->user();
        abort_if($user === null || ! $user->canViewSyncQueue(), 403);

        if ($user->canImportOrConfigure()) {
            $operationalAlerts->notifyAdminsIfNeeded($user);
        }

        $status = trim((string) $request->input('status', ''));
        $domain = trim((string) $request->input('domain', ''));

        $query = SyncQueueUserScope::applyToTasks(
            AdminSyncTask::query()
                ->with(['city:id,name,uf', 'queuedBy:id,name'])
                ->orderByDesc('id'),
            $user,
        );

        if ($status !== '' && AdminSyncTaskStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }
        if ($domain !== '') {
            $query->where('domain', $domain);
        }

        $tasks = $query->paginate(25)->withQueryString();

        $counts = SyncQueueUserScope::applyToTasks(AdminSyncTask::query(), $user)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $countsByDomainStatus = SyncQueueUserScope::applyToTasks(AdminSyncTask::query(), $user)
            ->selectRaw('domain, status, count(*) as aggregate')
            ->groupBy('domain', 'status')
            ->get()
            ->groupBy('domain');

        $pdfStatus = trim((string) $request->input('pdf_status', ''));
        $pdfQuery = SyncQueueUserScope::applyToPdfExports(
            AnalyticsReportExport::query()
                ->with(['city:id,name,uf', 'user:id,name'])
                ->orderByDesc('id'),
            $user,
        );
        if ($pdfStatus !== '' && AnalyticsReportExportStatus::tryFrom($pdfStatus) !== null) {
            $pdfQuery->where('status', $pdfStatus);
        }
        $pdfExports = $pdfQuery->paginate(15, ['*'], 'pdf_page')->withQueryString();

        $pdfCounts = SyncQueueUserScope::applyToPdfExports(AnalyticsReportExport::query(), $user)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $queueDefault = (string) config('queue.default', 'database');
        $syncQueueName = (string) config('ieducar.admin_sync.queue', 'admin-sync');
        $pdfQueueName = (string) config('analytics.pdf_report.queue', 'default');

        $horizonteHubData = $horizonteHub->build();
        $horizonteThemeCard = AdminSyncQueueIndexPresenter::horizonteThemeCard($horizonteHubData);

        $domainEnum = $domain !== '' ? AdminSyncDomain::tryFrom($domain) : null;
        $activeTheme = $domainEnum !== null
            ? AdminSyncQueueIndexPresenter::themeForDomain($domainEnum, $syncQueueName)
            : null;

        $activeThemeSection = null;
        if ($domainEnum !== null && $activeTheme !== null) {
            $statusCounts = $countsByDomainStatus->get($domainEnum->value, collect());
            $domainCounts = [];
            foreach (AdminSyncTaskStatus::cases() as $statusCase) {
                $domainCounts[$statusCase->value] = (int) ($statusCounts->firstWhere('status', $statusCase->value)?->aggregate ?? 0);
            }

            $activeThemeSection = [
                'theme' => array_merge($activeTheme, [
                    'domain' => $domainEnum,
                    'counts' => $domainCounts,
                    'total' => (int) $tasks->total(),
                    'active' => ($domainCounts[AdminSyncTaskStatus::Pending->value] ?? 0)
                        + ($domainCounts[AdminSyncTaskStatus::Processing->value] ?? 0),
                    'failed' => $domainCounts[AdminSyncTaskStatus::Failed->value] ?? 0,
                ]),
                'tasks' => $tasks,
                'total' => (int) $tasks->total(),
            ];
        }

        return view('admin.sync-queue.index', [
            'syncQueueRoutePrefix' => SyncQueueUserScope::routePrefix($user),
            'isAdminSyncQueue' => $user->isAdmin(),
            'counts' => $counts,
            'filterStatus' => $status,
            'filterDomain' => $domain,
            'activeThemeSection' => $activeThemeSection,
            'syncThemeCards' => AdminSyncQueueIndexPresenter::syncThemeCards($countsByDomainStatus, $syncQueueName),
            'syncThemeSections' => $domain === ''
                ? AdminSyncQueueIndexPresenter::syncThemeSections($countsByDomainStatus, $syncQueueName)
                : [],
            'pdfThemeCard' => AdminSyncQueueIndexPresenter::pdfThemeCard($pdfCounts, $pdfQueueName),
            'horizonteHub' => $horizonteHubData,
            'horizonteThemeCard' => $horizonteThemeCard,
            'syncQueueName' => $syncQueueName,
            'syncQueueConnection' => config('ieducar.admin_sync.connection') ?? $queueDefault,
            'pdfExports' => $pdfExports,
            'pdfCounts' => $pdfCounts,
            'filterPdfStatus' => $pdfStatus,
            'pdfQueueName' => $pdfQueueName,
            'pdfQueueConnection' => config('analytics.pdf_report.connection') ?? $queueDefault,
            'queueDefault' => $queueDefault,
            'queueIsSync' => $queueDefault === 'sync',
        ]);
    }

    public function show(AdminSyncTask $task): View
    {
        $this->authorize('view', $task);

        $task->load(['city:id,name,uf,ibge_municipio', 'queuedBy:id,name']);
        $user = request()->user();

        return view('admin.sync-queue.show', [
            'task' => $task,
            'taskTheme' => ImportHubThemeCatalog::themeForDomainValue($task->domain),
            'outcomeHint' => ExternalImportImpact::taskOutcomeHint($task),
            'canResume' => $user !== null && $user->can('resume', $task),
            'syncQueueRoutePrefix' => SyncQueueUserScope::routePrefix($user),
        ]);
    }

    public function resume(AdminSyncTask $task, AdminSyncQueueService $syncQueue): RedirectResponse
    {
        $this->authorize('resume', $task);

        if (! $task->isResumable()) {
            return redirect()
                ->route('admin.sync-queue.show', $task)
                ->with('geo_sync_error', __('Esta tarefa não pode ser retomada.'));
        }

        $syncQueue->resume($task);

        return redirect()
            ->route('admin.sync-queue.show', $task)
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => AdminSyncQueueService::flashQueuedMessage($task->fresh()),
            ]);
    }

    public function download(AdminSyncTask $task): BinaryFileResponse
    {
        $this->authorize('download', $task);

        if ($task->status !== AdminSyncTaskStatus::Completed->value) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $path = (string) ($task->result['export_path'] ?? '');
        if ($path === '' || ! is_readable($path)) {
            abort(Response::HTTP_NOT_FOUND, __('Ficheiro de exportação não disponível.'));
        }

        $filename = (string) ($task->result['export_filename'] ?? basename($path));
        $mime = (string) ($task->result['export_mime'] ?? '');
        if ($mime === '') {
            $mime = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
                'csv' => 'text/csv; charset=UTF-8',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'application/json',
            };
        }

        return response()->download($path, $filename, [
            'Content-Type' => $mime,
        ]);
    }
}
