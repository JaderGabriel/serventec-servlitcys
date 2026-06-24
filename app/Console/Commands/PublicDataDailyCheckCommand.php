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
        $counts = PublicDataAvailabilityPresenter::counts($report);
        $this->renderGroupedTables($report);

        $this->newLine();
        if ($counts['action'] === 0) {
            $this->info(__('Tudo alinhado — :n fonte(s) verificada(s).', ['n' => $counts['total']]));
        } elseif ($counts['new'] > 0 && $counts['attention'] > 0) {
            $this->info(__('Resultado: :news novidade(s), :att atenção(ões), :aligned alinhada(s).', [
                'news' => $counts['new'],
                'att' => $counts['attention'],
                'aligned' => $counts['aligned'],
            ]));
        } elseif ($counts['new'] > 0) {
            $this->info(trans_choice(
                ':n publicação nova detectada.|:n publicações novas detectadas.',
                max(1, $counts['new']),
                ['n' => $counts['new']],
            ));
        } else {
            $this->info(trans_choice(
                ':n fonte requer atenção.|:n fontes requerem atenção.',
                max(1, $counts['attention']),
                ['n' => $counts['attention']],
            ));
            if ($counts['aligned'] > 0) {
                $this->line(trans_choice(
                    ':aligned fonte permanece sem alteração.|:aligned fontes permanecem sem alteração.',
                    $counts['aligned'],
                    ['aligned' => $counts['aligned']],
                ));
            }
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
     * @param  array{findings?: list<array<string, mixed>>, groups?: array{action?: list<array<string, mixed>>, aligned?: list<array<string, mixed>>}}  $report
     */
    private function renderGroupedTables(array $report): void
    {
        $groups = is_array($report['groups'] ?? null) ? $report['groups'] : PublicDataAvailabilityPresenter::groupFindings(
            is_array($report['findings'] ?? null) ? $report['findings'] : [],
        );

        if (($groups['action'] ?? []) !== []) {
            $this->comment(__('▶ REQUER ACÇÃO'));
            $this->renderTable($groups['action']);
        }

        if (($groups['aligned'] ?? []) !== []) {
            $this->newLine();
            $this->comment(__('✓ SEM ALTERAÇÃO'));
            $this->renderTable($groups['aligned']);
        }

        if (($groups['action'] ?? []) === [] && ($groups['aligned'] ?? []) === []) {
            $this->warn(__('Nenhuma fonte verificada.'));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function renderTable(array $findings): void
    {
        $rows = [];
        foreach ($findings as $finding) {
            $status = (string) ($finding['status'] ?? '');
            $meta = PublicDataAvailabilityPresenter::statusMeta($status);
            $rows[] = [
                (string) ($finding['source_title'] ?? $finding['source_id'] ?? '—'),
                $meta['label'],
                (string) ($finding['headline'] ?? ''),
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->table(
            [__('Fonte'), __('Estado'), __('Resumo')],
            $rows,
        );
    }
}
