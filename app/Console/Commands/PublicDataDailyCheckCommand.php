<?php

namespace App\Console\Commands;

use App\Services\Notifications\PublicDataDailyCheckNotifier;
use Illuminate\Console\Command;

class PublicDataDailyCheckCommand extends Command
{
    protected $signature = 'public-data:check-official
                            {--notify : Envia notificação aos administradores (predefinido)}';

    protected $description = 'Verifica fontes oficiais de dados públicos (existência) e notifica admins com a rotina de importação';

    public function handle(PublicDataDailyCheckNotifier $notifier): int
    {
        if (! (bool) config('public_data_availability.enabled', true)) {
            $this->comment(__('Verificação diária de dados públicos desactivada (PUBLIC_DATA_DAILY_CHECK_ENABLED).'));

            return self::SUCCESS;
        }

        $result = $notifier->notifyAdminsDaily();

        if ($result['skipped'] ?? false) {
            $this->comment(__('Verificação ignorada: :reason', ['reason' => (string) ($result['reason'] ?? '?')]));

            return self::SUCCESS;
        }

        if ($result['has_news'] ?? false) {
            $this->info(__('Novidades detectadas: :n área(s). Notificação enviada.', [
                'n' => (int) ($result['news_count'] ?? 0),
            ]));
        } else {
            $this->info(__('Sem novidades. Resumo diário enviado (:n fontes verificadas).', [
                'n' => (int) ($result['findings'] ?? 0),
            ]));
        }

        return self::SUCCESS;
    }
}
