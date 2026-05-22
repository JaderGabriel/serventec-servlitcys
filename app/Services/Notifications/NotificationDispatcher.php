<?php

namespace App\Services\Notifications;

use App\Enums\AdminSyncTaskStatus;
use App\Enums\AnalyticsReportExportStatus;
use App\Enums\NotificationPriority;
use App\Enums\UserRole;
use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Models\User;
use App\Notifications\AppMessageNotification;
use App\Support\Notifications\NotificationKinds;
use App\Support\Notifications\NotificationPayload;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class NotificationDispatcher
{
    public function isEnabled(): bool
    {
        return (bool) config('notifications.enabled', true);
    }

    /**
     * @return Collection<int, User>
     */
    public function operationalRecipients(): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->get()
            ->filter(static fn (User $u): bool => $u->canImportOrConfigure());
    }

    public function pdfExportQueued(AnalyticsReportExport $export): void
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
        $queue = (string) config('analytics.pdf_report.queue', 'default');

        $this->notifyUser($user, [
            'title' => __('PDF enfileirado'),
            'body' => __('Relatório #:id de :city na fila :queue. Acompanhe em Filas de processamento.', [
                'id' => (string) $export->id,
                'city' => $cityName,
                'queue' => $queue,
            ]),
            'icon' => 'info',
            'priority' => NotificationPriority::Normal->value,
            'kind' => NotificationKinds::PDF_EXPORT,
            'action_url' => route('admin.sync-queue.index'),
            'dedupe_key' => 'pdf:queued:'.$export->id,
        ]);

        if ((string) config('queue.default') === 'sync') {
            $this->queueSyncModeWarning($user);
        }
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
                'priority' => NotificationPriority::Normal->value,
                'kind' => NotificationKinds::PDF_EXPORT,
                'action_url' => $export->isDownloadable()
                    ? route('dashboard.analytics.pdf.download', $export)
                    : $this->analyticsServentecUrl($export),
                'dedupe_key' => 'pdf:done:'.$export->id,
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
            'priority' => NotificationPriority::Critical->value,
            'kind' => NotificationKinds::PDF_EXPORT,
            'action_url' => $this->analyticsServentecUrl($export),
            'dedupe_key' => 'pdf:failed:'.$export->id,
        ]);
    }

    public function adminSyncQueued(AdminSyncTask $task): void
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
        $queue = (string) config('ieducar.admin_sync.queue', 'admin-sync');

        $payload = [
            'title' => __('Sincronização enfileirada'),
            'body' => __(':label — :city (fila :queue).', ['label' => $label, 'city' => $cityLabel, 'queue' => $queue]),
            'icon' => 'info',
            'priority' => NotificationPriority::Normal->value,
            'kind' => NotificationKinds::ADMIN_SYNC,
            'action_url' => route('admin.sync-queue.index'),
            'dedupe_key' => 'sync:queued:'.$task->id,
        ];

        foreach ($recipients as $user) {
            $this->notifyUser($user, $payload);
        }

        if ((string) config('queue.default') === 'sync') {
            foreach ($recipients as $user) {
                $this->queueSyncModeWarning($user);
            }
        }
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
                'priority' => NotificationPriority::Normal->value,
                'kind' => NotificationKinds::ADMIN_SYNC,
                'action_url' => route('admin.sync-queue.index'),
                'dedupe_key' => 'sync:done:'.$task->id,
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
                'priority' => NotificationPriority::Critical->value,
                'kind' => NotificationKinds::ADMIN_SYNC,
                'action_url' => route('admin.sync-queue.index'),
                'dedupe_key' => 'sync:failed:'.$task->id,
            ];
        }

        foreach ($recipients as $user) {
            $this->notifyUser($user, $payload);
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    public function analyticsTabPartialWarnings(User $user, City $city, string $tab, array $warnings): void
    {
        if (! $this->isEnabled() || ! (bool) config('notifications.analytics_partial_errors', true)) {
            return;
        }

        if (! $user->is_active || $warnings === []) {
            return;
        }

        $count = count($warnings);
        $preview = mb_substr(implode(' · ', array_slice($warnings, 0, 2)), 0, 220);

        $this->notifyUser($user, [
            'title' => __('Painel analítico com avisos'),
            'body' => __(':city — aba :tab: :count seção(ões) com dados incompletos. :preview', [
                'city' => $city->name,
                'tab' => $tab,
                'count' => $count,
                'preview' => $preview,
            ]),
            'icon' => 'warning',
            'priority' => NotificationPriority::High->value,
            'kind' => NotificationKinds::ANALYTICS,
            'action_url' => route('dashboard.analytics', [
                'city_id' => $city->id,
                'tab' => $tab,
            ]),
            'dedupe_key' => 'analytics:warnings:'.$city->id.':'.$tab.':'.md5(implode('|', $warnings)),
        ]);
    }

    public function accountCreated(User $user, User $actor): void
    {
        if (! $this->isEnabled() || ! $user->is_active) {
            return;
        }

        $this->notifyUser($user, [
            'title' => __('Conta criada'),
            'body' => __('A sua conta no :app foi criada. Complete o perfil se solicitado e utilize as credenciais definidas pela equipe.', [
                'app' => config('app.name', 'servlitcys'),
            ]),
            'icon' => 'info',
            'priority' => NotificationPriority::Normal->value,
            'kind' => NotificationKinds::ACCOUNT,
            'action_url' => route('profile.edit'),
            'dedupe_key' => 'account:created:'.$user->id,
        ]);
    }

    public function accountUpdated(User $user, User $actor, bool $deactivated, bool $profileChanged): void
    {
        if (! $this->isEnabled() || $user->id === $actor->id) {
            return;
        }

        if ($deactivated) {
            $this->notifyUser($user, [
                'title' => __('Conta desativada'),
                'body' => __('O seu acesso foi desativado por um administrador. Contacte a equipe se precisar de reactivação.'),
                'icon' => 'warning',
                'priority' => NotificationPriority::Critical->value,
                'kind' => NotificationKinds::ACCOUNT,
                'action_url' => null,
                'dedupe_key' => 'account:deactivated:'.$user->id,
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
                'priority' => NotificationPriority::High->value,
                'kind' => NotificationKinds::ACCOUNT,
                'action_url' => route('profile.edit'),
                'dedupe_key' => 'account:updated:'.$user->id.':'.now()->format('Y-m-d'),
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
            'priority' => NotificationPriority::Normal->value,
            'kind' => NotificationKinds::ACCOUNT,
            'action_url' => $user->homeUrl(),
            'dedupe_key' => 'account:reactivated:'.$user->id,
        ]);
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array{title: string, body: string, icon?: string, priority?: string, action_url?: ?string, kind?: ?string, dedupe_key?: ?string}  $payload
     */
    public function notifyOperational(Collection $recipients, array $payload): void
    {
        foreach ($recipients as $user) {
            $this->notifyUser($user, $payload);
        }
    }

    /**
     * @param  array{title: string, body: string, icon?: string, priority?: string, action_url?: ?string, kind?: ?string, dedupe_key?: ?string}  $payload
     */
    private function notifyUser(User $user, array $payload): void
    {
        $normalized = NotificationPayload::normalize($payload);
        $dedupeKey = $normalized['dedupe_key'];

        if ($dedupeKey !== null && $this->wasRecentlySent($user, $dedupeKey)) {
            return;
        }

        $user->notify(new AppMessageNotification($normalized));
    }

    private function wasRecentlySent(User $user, string $dedupeKey): bool
    {
        $ttl = max(5, (int) config('notifications.dedupe_ttl_minutes', 360));

        return $user->notifications()
            ->where('created_at', '>=', now()->subMinutes($ttl))
            ->get()
            ->contains(static function (DatabaseNotification $n) use ($dedupeKey): bool {
                $data = is_array($n->data) ? $n->data : [];

                return ($data['dedupe_key'] ?? null) === $dedupeKey;
            });
    }

    private function queueSyncModeWarning(User $user): void
    {
        $this->notifyUser($user, [
            'title' => __('Fila síncrona activa'),
            'body' => __('Os jobs correm na requisição HTTP (sync). Em produção use database/redis e um worker.'),
            'icon' => 'warning',
            'priority' => NotificationPriority::High->value,
            'kind' => NotificationKinds::OPERATIONS,
            'action_url' => route('admin.sync-queue.index'),
            'dedupe_key' => 'ops:queue_sync_user',
        ]);
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

        return $this->operationalRecipients();
    }

    private function analyticsServentecUrl(AnalyticsReportExport $export): string
    {
        $params = ['city_id' => $export->city_id, 'tab' => 'municipality_health'];
        $filters = is_array($export->filters) ? $export->filters : [];
        if (filled($filters['ano_letivo'] ?? null)) {
            $params['ano_letivo'] = $filters['ano_letivo'];
        }

        return route('dashboard.analytics', $params);
    }
}
