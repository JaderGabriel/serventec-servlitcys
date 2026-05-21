<?php

namespace App\Services\Notifications;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Enums\UserRole;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\User;
use App\Notifications\AppMessageNotification;
use Illuminate\Support\Collection;

final class NotificationDispatcher
{
    public function isEnabled(): bool
    {
        return (bool) config('notifications.enabled', true);
    }

    public function pdfExportFinished(AnalyticsReportExport $export): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $export->loadMissing(['user', 'city']);
        $user = $export->user;
        if ($user === null || ! $user->is_active) {
            return;
        }

        $cityName = (string) ($export->city?->name ?? __('Município'));
        $success = $export->statusEnum() === AnalyticsReportExportStatus::Completed;

        if ($success) {
            $this->notifyUser($user, [
                'title' => __('Relatório PDF pronto'),
                'body' => __('O PDF de análise de :city está disponível para descarregar.', ['city' => $cityName]),
                'icon' => 'success',
                'kind' => 'pdf_export',
                'action_url' => $export->isDownloadable()
                    ? route('dashboard.analytics.pdf.download', $export)
                    : $this->analyticsServentecUrl($export),
            ]);

            return;
        }

        if ($export->statusEnum() !== AnalyticsReportExportStatus::Failed) {
            return;
        }

        $err = filled($export->error_message) ? mb_substr((string) $export->error_message, 0, 200) : __('Erro desconhecido.');

        $this->notifyUser($user, [
            'title' => __('Falha na geração do PDF'),
            'body' => __(':city — :erro', ['city' => $cityName, 'erro' => $err]),
            'icon' => 'error',
            'kind' => 'pdf_export',
            'action_url' => $this->analyticsServentecUrl($export),
        ]);
    }

    public function adminSyncFinished(AdminSyncTask $task): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $task->loadMissing(['queuedBy', 'city']);
        $recipients = $this->adminSyncRecipients($task);
        if ($recipients->isEmpty()) {
            return;
        }

        $label = filled($task->label) ? (string) $task->label : $task->domainEnum()->label();
        $cityLabel = $task->cityLabel();
        $success = $task->statusEnum() === AdminSyncTaskStatus::Completed;

        if ($success) {
            $payload = [
                'title' => __('Sincronização concluída'),
                'body' => __(':label — :city', ['label' => $label, 'city' => $cityLabel]),
                'icon' => 'success',
                'kind' => 'admin_sync',
                'action_url' => route('admin.sync-queue.index'),
            ];
        } else {
            if ($task->statusEnum() !== AdminSyncTaskStatus::Failed) {
                return;
            }
            $err = filled($task->error_message) ? mb_substr((string) $task->error_message, 0, 200) : __('Erro desconhecido.');
            $payload = [
                'title' => __('Sincronização falhou'),
                'body' => __(':label — :city. :erro', ['label' => $label, 'city' => $cityLabel, 'erro' => $err]),
                'icon' => 'error',
                'kind' => 'admin_sync',
                'action_url' => route('admin.sync-queue.index'),
            ];
        }

        foreach ($recipients as $user) {
            $this->notifyUser($user, $payload);
        }
    }

    public function accountCreated(User $user, User $actor): void
    {
        if (! $this->isEnabled() || ! $user->is_active) {
            return;
        }

        $this->notifyUser($user, [
            'title' => __('Conta criada'),
            'body' => __('A sua conta no :app foi criada. Complete o perfil se solicitado e utilize as credenciais definidas pela equipa.', [
                'app' => config('app.name', 'servlitcys'),
            ]),
            'icon' => 'info',
            'kind' => 'account',
            'action_url' => route('profile.edit'),
        ]);
    }

    public function accountUpdated(User $user, User $actor, bool $deactivated, bool $profileChanged): void
    {
        if (! $this->isEnabled() || $user->id === $actor->id) {
            return;
        }

        if ($deactivated) {
            $this->notifyUser($user, [
                'title' => __('Conta desactivada'),
                'body' => __('O seu acesso foi desactivado por um administrador. Contacte a equipa se precisar de reactivação.'),
                'icon' => 'warning',
                'kind' => 'account',
                'action_url' => null,
            ]);

            return;
        }

        if (! $user->is_active) {
            return;
        }

        if ($profileChanged) {
            $this->notifyUser($user, [
                'title' => __('Perfil actualizado'),
                'body' => __('Os seus dados de acesso ou municípios associados foram alterados. Verifique o perfil e as permissões.'),
                'icon' => 'info',
                'kind' => 'account',
                'action_url' => route('profile.edit'),
            ]);
        }
    }

    public function accountReactivated(User $user): void
    {
        if (! $this->isEnabled() || ! $user->is_active) {
            return;
        }

        $this->notifyUser($user, [
            'title' => __('Conta reactivada'),
            'body' => __('O seu acesso foi reactivado. Pode voltar a utilizar o painel.'),
            'icon' => 'success',
            'kind' => 'account',
            'action_url' => $user->homeUrl(),
        ]);
    }

    /**
     * @param  array{title: string, body: string, icon?: string, action_url?: ?string, kind?: ?string}  $payload
     */
    private function notifyUser(User $user, array $payload): void
    {
        $user->notify(new AppMessageNotification($payload));
    }

    /**
     * @return Collection<int, User>
     */
    private function adminSyncRecipients(AdminSyncTask $task): Collection
    {
        if ($task->queued_by !== null) {
            $queued = $task->queuedBy;
            if ($queued !== null && $queued->is_active && $queued->canImportOrConfigure()) {
                return collect([$queued]);
            }

            return collect();
        }

        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->get()
            ->filter(static fn (User $u): bool => $u->canImportOrConfigure());
    }

    private function analyticsPdfUrl(AnalyticsReportExport $export): string
    {
        $params = ['city_id' => $export->city_id, 'tab' => 'municipality_health'];
        $filters = is_array($export->filters) ? $export->filters : [];
        if (filled($filters['ano_letivo'] ?? null)) {
            $params['ano_letivo'] = $filters['ano_letivo'];
        }

        return route('dashboard.analytics', $params);
    }

    private function analyticsServentecUrl(AnalyticsReportExport $export): string
    {
        return $this->analyticsPdfUrl($export);
    }
}
