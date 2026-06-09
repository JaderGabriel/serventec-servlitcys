<?php

namespace App\Http\Controllers;

use App\Enums\AdminSyncDomain;
use App\Models\City;
use App\Services\AdminSync\AdminSyncQueueService;
use App\Services\Ieducar\InclusionNeeExportService;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Pulse\PulseOperationRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InclusionNeeExportController extends Controller
{
    public function __construct(
        private InclusionNeeExportService $exportService,
        private AdminSyncQueueService $syncQueue,
    ) {}

    public function queue(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || ! $user->canExportInclusionNee(), 403);

        $validated = $request->validate([
            'format' => 'required|string|in:csv,xlsx',
            'city_id' => 'required|integer|exists:cities,id',
            'ano_letivo' => 'nullable|string',
            'escola_id' => 'nullable|integer',
            'curso_id' => 'nullable|integer',
            'turno_id' => 'nullable|integer',
            'inclusion_scope' => 'nullable|string|in:all,nee,inconsistencias',
        ]);

        $city = City::query()->findOrFail((int) $validated['city_id']);
        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);
        if (! $filters->hasYearSelected()) {
            return redirect()
                ->back()
                ->with('error', __('Selecione o ano letivo antes de exportar.'));
        }

        $format = (string) $validated['format'];
        $task = $this->syncQueue->dispatch(
            AdminSyncDomain::Ieducar,
            'inclusion_nee_export',
            __('Inclusão NEE — exportação :fmt (:city)', [
                'fmt' => strtoupper($format),
                'city' => $city->name,
            ]),
            [
                'city_id' => $city->id,
                'format' => $format,
                'ano_letivo' => $filters->ano_letivo,
                'escola_id' => $filters->escola_id,
                'curso_id' => $filters->curso_id,
                'turno_id' => $filters->turno_id,
                'inclusion_scope' => $request->input('inclusion_scope', 'all'),
            ],
            $city->id,
            (int) $user->id,
        );

        $message = AdminSyncQueueService::flashQueuedMessage($task);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'task_id' => $task->id,
            ]);
        }

        return redirect()
            ->back()
            ->with('admin_sync_queued', [
                'task_id' => $task->id,
                'message' => $message,
            ]);
    }

    public function download(Request $request): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();
        abort_if($user === null || ! $user->canExportInclusionNee(), 403);

        $validated = $request->validate([
            'format' => 'nullable|string|in:csv,xlsx',
            'city_id' => 'required|integer|exists:cities,id',
        ]);

        $city = City::query()->findOrFail((int) $validated['city_id']);
        $this->authorize('viewAnalytics', $city);

        $filters = IeducarFilterState::fromRequest($request);
        if (! $filters->hasYearSelected()) {
            abort(422, __('Selecione o ano letivo antes de exportar.'));
        }

        $format = ($validated['format'] ?? 'csv') === 'xlsx' ? 'xlsx' : 'csv';

        $result = PulseOperationRecorder::measure(
            'export:inclusion-nee|cid:'.(int) $city->id,
            fn () => $this->exportService->generate($city, $filters, $format),
        );

        $path = (string) ($result['export_path'] ?? '');
        if ($path === '' || ! is_readable($path)) {
            abort(404, __('Nenhum registo NEE para exportar neste filtro.'));
        }

        $filename = (string) ($result['export_filename'] ?? basename($path));
        $mime = (string) ($result['export_mime'] ?? 'text/csv; charset=UTF-8');

        return response()->download($path, $filename, ['Content-Type' => $mime]);
    }
}
