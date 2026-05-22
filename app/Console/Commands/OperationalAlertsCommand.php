<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OperationalAlertsNotifier;
use Illuminate\Console\Command;

class OperationalAlertsCommand extends Command
{
    protected $signature = 'notifications:operational-alerts';

    protected $description = 'Avalia filas, sync e PDFs e envia alertas operacionais aos administradores (sem depender de visita ao painel)';

    public function handle(OperationalAlertsNotifier $notifier, NotificationDispatcher $dispatcher): int
    {
        if (! (bool) config('notifications.operational_alerts.enabled', true)) {
            $this->comment(__('Alertas operacionais desativados (APP_NOTIFICATIONS_OPERATIONAL).'));

            return self::SUCCESS;
        }

        if (! $dispatcher->isEnabled()) {
            $this->comment(__('Centro de notificações desativado (APP_NOTIFICATIONS_ENABLED).'));

            return self::SUCCESS;
        }

        $notifier->notifyAdminsIfNeeded();

        $this->info(__('Verificação de alertas operacionais concluída.'));

        return self::SUCCESS;
    }
}
