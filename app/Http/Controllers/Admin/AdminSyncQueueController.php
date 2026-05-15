<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminSyncTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminSyncTask;
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

        return view('admin.sync-queue.index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'filterStatus' => $status,
            'filterDomain' => $domain,
            'queueName' => (string) config('ieducar.admin_sync.queue', 'admin-sync'),
            'queueConnection' => config('ieducar.admin_sync.connection') ?? config('queue.default'),
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
