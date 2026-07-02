<?php

namespace App\Console\Commands;

use App\Services\Horizonte\HorizonteTesouroRepassesSyncService;
use App\Support\Horizonte\HorizonteTesouroRepassesSyncProgress;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Console\Command;

class HorizonteSyncRepassesTesouroCommand extends Command
{
    protected $signature = 'horizonte:sync-repasses-tesouro
                            {--year= : Exercício civil a importar (predefinição: ano vigente)}
                            {--with-ref : Importar também o ano de referência Horizonte (HORIZONTE_REFERENCE_YEAR)}
                            {--ref-only : Importar apenas o ano de referência Horizonte}
                            {--uf= : Restringir a uma UF (ex.: BA)}
                            {--continue : Retomar lote pendente de UFs (ignorado com --uf)}
                            {--reset : Reiniciar progresso por UF}
                            {--ufs-per-step= : UFs processadas por invocação (predefinição: 1)}
                            {--dry-run : Listar anos/UFs sem gravar na base}';

    protected $description = 'Importa repasses FUNDEB do Tesouro Transparente (CKAN) para o ano vigente e/ou referência Horizonte';

    public function handle(HorizonteTesouroRepassesSyncService $sync): int
    {
        $memory = trim((string) config('horizonte.fortnightly_feed.memory_limit', '512M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $ufRaw = trim((string) $this->option('uf'));
        if ($ufRaw !== '' && HorizonteUfScope::normalize($ufRaw) === null) {
            $this->error(__('UF inválida: :uf — use sigla de estado (ex.: BA).', ['uf' => $ufRaw]));

            return self::FAILURE;
        }

        if ((bool) $this->option('with-ref') && (bool) $this->option('ref-only')) {
            $this->error(__('Use apenas um de --with-ref ou --ref-only.'));

            return self::FAILURE;
        }

        $refYear = (int) config('horizonte.reference_year', (int) date('Y') - 1);
        $currentYear = (int) date('Y');
        $yearRaw = trim((string) $this->option('year'));
        $targetYear = $yearRaw !== '' && is_numeric($yearRaw) ? (int) $yearRaw : $currentYear;

        $years = (bool) $this->option('ref-only')
            ? [$refYear]
            : ((bool) $this->option('with-ref')
                ? array_values(array_unique([$refYear, $targetYear]))
                : [$targetYear]);
        sort($years);

        $this->info(__('Horizonte — repasses Tesouro (CKAN FUNDEB)'));
        $this->line(__('Anos alvo: :anos · referência Horizonte: :ref', [
            'anos' => implode(', ', array_map('strval', $years)),
            'ref' => (string) $refYear,
        ]));

        if ($ufRaw === '') {
            $this->renderProgressHeader($years);
        } else {
            $this->line(__('Âmbito: UF :uf', ['uf' => (string) HorizonteUfScope::normalize($ufRaw)]));
        }

        if ((bool) $this->option('reset')) {
            if ($ufRaw !== '') {
                $this->warn(__('Progresso da UF :uf reiniciado (demais UFs mantidas).', [
                    'uf' => (string) HorizonteUfScope::normalize($ufRaw),
                ]));
            } else {
                $this->warn(__('Progresso nacional por UF reiniciado.'));
            }
        }

        $ufsPerStepOption = $this->option('ufs-per-step');
        $ufsPerStep = $ufsPerStepOption !== null && $ufsPerStepOption !== ''
            ? max(1, (int) $ufsPerStepOption)
            : (int) config('horizonte.tesouro_repasses_sync.ufs_per_step', 1);

        $result = $sync->run([
            'year' => $targetYear,
            'with_ref' => (bool) $this->option('with-ref'),
            'ref_only' => (bool) $this->option('ref-only'),
            'uf' => $ufRaw !== '' ? $ufRaw : null,
            'reset' => (bool) $this->option('reset'),
            'continue' => (bool) $this->option('continue'),
            'ufs_per_step' => $ufsPerStep,
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        if (is_array($result['imported_by_year'] ?? null) && ($result['imported_by_year'] ?? []) !== []) {
            foreach ($result['imported_by_year'] as $year => $count) {
                $this->line(__('  · :ano: :n município(s)', [
                    'ano' => (string) $year,
                    'n' => (string) $count,
                ]));
            }
        }

        if (is_array($result['ufs'] ?? null) && ($result['ufs'] ?? []) !== []) {
            $this->line(__('  · UFs: :ufs', ['ufs' => implode(', ', $result['ufs'])]));
        }

        $this->newLine();
        if ($result['complete'] ?? false) {
            $this->info((string) ($result['message'] ?? ''));
        } elseif ($result['success'] ?? false) {
            $this->info((string) ($result['message'] ?? ''));
            if (! ($result['dry_run'] ?? false) && $ufRaw === '' && ($result['remaining_ufs'] ?? []) !== []) {
                $this->line(__('Retomar: php artisan horizonte:sync-repasses-tesouro --continue'));
            }
        } else {
            $this->error((string) ($result['message'] ?? ''));
            if (! ($result['dry_run'] ?? false) && (int) ($result['imported'] ?? 0) === 0) {
                $this->line(__('Nenhum município gravado — o progresso por UF não foi avançado.'));
                $this->line(__('Diagnóstico: php artisan horizonte:sync-repasses-tesouro --dry-run'.($ufRaw !== '' ? ' --uf='.$ufRaw : '')));
            }
        }

        $this->line(__('Mapa: :url', ['url' => route('dashboard.horizonte')]));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<int>  $years
     */
    private function renderProgressHeader(array $years): void
    {
        $done = HorizonteTesouroRepassesSyncProgress::doneUfs($years);
        $remaining = HorizonteTesouroRepassesSyncProgress::remainingUfs($years);

        if ($done === []) {
            $this->line(__('Nenhum progresso anterior — serão processadas :n UFs.', [
                'n' => (string) count($remaining),
            ]));

            return;
        }

        $this->line(__('Progresso: :done/:total UFs concluídas.', [
            'done' => (string) count($done),
            'total' => (string) (count($done) + count($remaining)),
        ]));

        if ($remaining !== []) {
            $this->line(__('Próxima(s) UF(s): :ufs', ['ufs' => implode(', ', array_slice($remaining, 0, 5))]));
        }
    }
}
