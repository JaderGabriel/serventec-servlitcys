<?php

namespace App\Support\Notifications;

use App\Models\AdminSyncTask;
use App\Support\Admin\ImportHubThemeCatalog;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/** Ícones, cores e URLs de notificações ligadas às filas admin/sync-queue. */
final class NotificationQueuePresentation
{
    /**
     * @return array{
     *   sync_task_id?: int,
     *   pdf_export_id?: int,
     *   queue_domain: string,
     *   queue_icon: string,
     *   queue_accent: string,
     *   queue_label: string,
     *   queue_icon_box_class: string,
     *   action_url: string
     * }
     */
    public static function forSyncTask(AdminSyncTask $task): array
    {
        $theme = ImportHubThemeCatalog::themeForDomainValue($task->domain);

        return self::build([
            'sync_task_id' => $task->id,
            'queue_domain' => $task->domain,
            'queue_icon' => (string) $theme['icon'],
            'queue_accent' => (string) $theme['accent'],
            'queue_label' => (string) $theme['label'],
            'action_url' => route('admin.sync-queue.show', $task),
        ]);
    }

    public static function forPdf(?int $exportId = null, ?string $actionUrl = null): array
    {
        $url = $actionUrl ?? route('admin.sync-queue.index');
        $fragment = $exportId !== null ? 'export-'.$exportId : 'fila-pdf';
        $separator = str_contains($url, '#') ? '' : '#';

        $fields = [
            'queue_domain' => 'pdf',
            'queue_icon' => 'document-text',
            'queue_accent' => 'rose',
            'queue_label' => __('Relatórios PDF (Diagnóstico)'),
            'action_url' => $url.$separator.$fragment,
        ];

        if ($exportId !== null) {
            $fields['pdf_export_id'] = $exportId;
        }

        return self::build($fields);
    }

    /**
     * @return array{
     *   queue_domain: string,
     *   queue_icon: string,
     *   queue_accent: string,
     *   queue_label: string,
     *   queue_icon_box_class: string,
     *   action_url: string
     * }
     */
    public static function forHorizonte(?string $actionUrl = null): array
    {
        $url = $actionUrl ?? route('admin.sync-queue.index').'#fila-horizonte';

        return self::build([
            'queue_domain' => 'horizonte',
            'queue_icon' => 'map',
            'queue_accent' => 'indigo',
            'queue_label' => __('Horizonte — abastecimento'),
            'action_url' => $url,
        ]);
    }

    /**
     * @return array{
     *   queue_domain: string,
     *   queue_icon: string,
     *   queue_accent: string,
     *   queue_label: string,
     *   queue_icon_box_class: string
     * }
     */
    public static function forOperations(string $variant): array
    {
        return match ($variant) {
            'pdf_stale' => self::buildMeta('pdf', 'document-text', 'rose', __('Relatórios PDF (Diagnóstico)')),
            'sync_failed' => self::buildMeta('system', 'command-line', 'slate', __('Sincronização (sistema)')),
            'queue_backlog' => self::buildMeta('system', 'queue-list', 'slate', __('Fila de jobs')),
            default => self::buildMeta('system', 'queue-list', 'slate', __('Filas de processamento')),
        };
    }

    /**
     * Completa metadados de fila em notificações antigas ou incompletas.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enrichStoredData(array $data): array
    {
        if (filled($data['queue_icon'] ?? null) && filled($data['queue_accent'] ?? null)) {
            return self::ensurePresentationFields($data);
        }

        $taskId = isset($data['sync_task_id']) ? (int) $data['sync_task_id'] : self::syncTaskIdFromDedupeKey($data['dedupe_key'] ?? null);
        if ($taskId > 0) {
            $task = AdminSyncTask::query()->find($taskId);
            if ($task !== null) {
                return self::ensurePresentationFields(array_merge($data, self::forSyncTask($task)));
            }
        }

        $exportId = isset($data['pdf_export_id']) ? (int) $data['pdf_export_id'] : self::pdfExportIdFromDedupeKey($data['dedupe_key'] ?? null);
        $kind = (string) ($data['kind'] ?? '');
        if ($exportId > 0 || $kind === NotificationKinds::PDF_EXPORT) {
            $pdf = self::forPdf($exportId > 0 ? $exportId : null);
            $theme = array_diff_key($pdf, ['action_url' => true]);
            $merged = array_merge($data, $theme);
            $existingUrl = filled($data['action_url'] ?? null) ? (string) $data['action_url'] : null;
            if ($existingUrl === null || self::isSyncQueueIndexUrl($existingUrl)) {
                $merged['action_url'] = $pdf['action_url'];
            }

            return self::ensurePresentationFields($merged);
        }

        if ($kind === NotificationKinds::OPERATIONS) {
            $variant = self::operationsVariantFromDedupeKey($data['dedupe_key'] ?? null);

            return self::ensurePresentationFields(array_merge($data, self::forOperations($variant)));
        }

        $dedupe = (string) ($data['dedupe_key'] ?? '');
        if (str_starts_with($dedupe, 'horizonte:')) {
            return self::ensurePresentationFields(array_merge($data, self::forHorizonte(
                filled($data['action_url'] ?? null) ? (string) $data['action_url'] : null,
            )));
        }

        return self::ensurePresentationFields($data);
    }

    public static function iconBoxClass(string $accent): string
    {
        $base = 'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg';

        $tone = match ($accent) {
            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
            'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
            'sky' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
            'violet' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/50 dark:text-violet-200',
            'indigo' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
            'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        };

        return $base.' '.$tone;
    }

    public static function iconHtml(?string $iconName): ?string
    {
        if (! filled($iconName)) {
            return null;
        }

        return View::make('components.ui.icon', [
            'name' => $iconName,
            'class' => 'h-4 w-4',
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function build(array $fields): array
    {
        return self::ensurePresentationFields($fields);
    }

    /**
     * @return array{
     *   queue_domain: string,
     *   queue_icon: string,
     *   queue_accent: string,
     *   queue_label: string,
     *   queue_icon_box_class: string
     * }
     */
    private static function buildMeta(string $domain, string $icon, string $accent, string $label): array
    {
        return self::ensurePresentationFields([
            'queue_domain' => $domain,
            'queue_icon' => $icon,
            'queue_accent' => $accent,
            'queue_label' => $label,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function ensurePresentationFields(array $data): array
    {
        $accent = (string) ($data['queue_accent'] ?? '');
        if ($accent !== '' && ! isset($data['queue_icon_box_class'])) {
            $data['queue_icon_box_class'] = self::iconBoxClass($accent);
        }

        return $data;
    }

    private static function syncTaskIdFromDedupeKey(mixed $dedupeKey): int
    {
        if (! is_string($dedupeKey) || $dedupeKey === '') {
            return 0;
        }

        if (preg_match('/^sync:(?:queued|done|failed):(\d+)$/', $dedupeKey, $m) !== 1) {
            return 0;
        }

        return (int) $m[1];
    }

    private static function pdfExportIdFromDedupeKey(mixed $dedupeKey): int
    {
        if (! is_string($dedupeKey) || $dedupeKey === '') {
            return 0;
        }

        if (preg_match('/^pdf:(?:queued|done|failed):(\d+)$/', $dedupeKey, $m) !== 1) {
            return 0;
        }

        return (int) $m[1];
    }

    private static function operationsVariantFromDedupeKey(mixed $dedupeKey): string
    {
        return match ((string) $dedupeKey) {
            'ops:pdf_stale' => 'pdf_stale',
            'ops:sync_failed_24h' => 'sync_failed',
            'ops:queue_backlog' => 'queue_backlog',
            default => 'generic',
        };
    }

    private static function isSyncQueueIndexUrl(string $url): bool
    {
        if (! Route::has('admin.sync-queue.index')) {
            return false;
        }

        $indexPath = parse_url(route('admin.sync-queue.index'), PHP_URL_PATH) ?? '';
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return $indexPath !== '' && $path === $indexPath;
    }
}
