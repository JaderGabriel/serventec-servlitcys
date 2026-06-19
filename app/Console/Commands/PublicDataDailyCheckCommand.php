<?php

namespace App\Console\Commands;

use App\Services\Notifications\PublicDataDailyCheckNotifier;
use App\Support\Admin\PublicDataAvailabilityPresenter;
use Illuminate\Console\Command;

class PublicDataDailyCheckCommand extends Command
{
    protected $signature = 'public-data:check-official
                            {--no-notify : Apenas verifica e regista (sem notificação)}';

    protected $description = 'Verifica fontes oficiais de dados públicos (existência) e notifica admins com a rotina de importação';

    public function handle(PublicDataDailyCheckNotifier $notifier): int
    {
        if (! (bool) config('public_data_availability.enabled', true)) {
            $this->comment(__('Verificação diária de dados públicos desactivada (PUBLIC_DATA_DAILY_CHECK_ENABLED).'));

            return self::SUCCESS;
        }

        $notify = ! $this->option('no-notify');
        $result = $notifier->run($notify);

        if ($result['skipped'] ?? false) {
            $this->comment(__('Verificação ignorada: :reason', ['reason' => (string) ($result['reason'] ?? '?')]));

            return self::SUCCESS;
        }

        $report = is_array($result['report'] ?? null) ? $result['report'] : [];
        $this->renderTable($report);

        if ($result['has_news'] ?? false) {
            $this->newLine();
            $this->info(__('Novidades detectadas: :n área(s).', [
                'n' => (int) ($result['news_count'] ?? 0),
            ]));
        } else {
            $this->newLine();
            $this->info(__('Sem novidades. :n fontes verificadas.', [
                'n' => (int) ($result['findings'] ?? 0),
            ]));
        }

        if ($notify) {
            $this->line($result['notified'] ?? false
                ? __('Notificação enviada aos administradores.')
                : __('Notificação não enviada (centro de notificações desactivado ou sem destinatários).'));
        } else {
            $this->comment(__('Modo --no-notify: resultado guardado em cache para o hub admin.'));
        }

        $this->line(__('Hub: :url', ['url' => route('admin.public-data.index').'#verificacao-oficial']));

        return self::SUCCESS;
    }

    /**
     * @param  array{findings?: list<array<string, mixed>>}  $report
     */
    private function renderTable(array $report): void
    {
        $rows = [];
        foreach ($report['findings'] ?? [] as $finding) {
            $status = (string) ($finding['status'] ?? '');
            $meta = PublicDataAvailabilityPresenter::statusMeta($status);
            $rows[] = [
                (string) ($finding['source_title'] ?? $finding['source_id'] ?? '—'),
                $meta['label'],
                (string) ($finding['headline'] ?? ''),
            ];
        }

        if ($rows === []) {
            $this->warn(__('Nenhuma fonte verificada.'));

            return;
        }

        $this->table(
            [__('Fonte'), __('Estado'), __('Resumo')],
            $rows,
        );
    }
}
