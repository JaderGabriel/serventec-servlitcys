<?php

namespace App\Console\Commands;

use App\Services\Funding\FinanceRealtimeTransferRebuildService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('funding:rebuild-finance-realtime
    {--ano= : Ano de repasse (calendário)}
    {--from= : Ano inicial (com --to)}
    {--to= : Ano final}
    {--city= : ID da cidade}
    {--cities= : IDs separados por vírgula}
    {--all-cities : Todos os municípios com IBGE (forAnalytics)}
    {--purge-only : Apenas apaga snapshots, sem reimportar}
    {--no-purge : Não apaga antes de importar (acrescenta/atualiza)}
    {--dry-run : Mostra o plano sem alterar dados}
    {--confirm= : Slug anual obrigatório em production (ex.: rebuild-repasses-2025)}')]
#[Description('Apaga repasses municipais e reimporta fontes FUNDEB para Finanças → Tempo Real')]
class FundingRebuildFinanceRealtimeCommand extends Command
{
    public function handle(FinanceRealtimeTransferRebuildService $rebuild): int
    {
        $years = $this->resolveYears();
        if ($years === []) {
            $this->error(__('Indique --ano= ou --from=/--to=.'));

            return self::FAILURE;
        }

        $cityIds = $this->resolveCityIds();
        if ($cityIds === false) {
            $this->error(__('Indique --city=, --cities= ou --all-cities.'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $purgeOnly = (bool) $this->option('purge-only');
        $purgeBefore = ! (bool) $this->option('no-purge');

        if (! $this->confirmProduction($years, $dryRun, $purgeOnly)) {
            return self::FAILURE;
        }

        $this->info(__('Anos: :anos', ['anos' => implode(', ', array_map('strval', $years))]));
        $this->line(__('Municípios: :n', ['n' => $cityIds === null ? __('todos com IBGE') : count($cityIds)]));

        if ($dryRun) {
            $purge = $rebuild->purge($years, $cityIds);
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Registos a apagar', (string) $purge['total']],
                    ['…tesouro_publicacao (UF)', (string) $purge['uf_publicacao']],
                    ['Reimportações planeadas', (string) (count($cityIds ?? []) ?: 'N')],
                ],
            );
            $this->comment(__('Após rebuild, totais UF (tesouro_publicacao) deixam de contar no Tempo Real por município.'));

            return self::SUCCESS;
        }

        if ($purgeOnly) {
            $purge = $rebuild->purge($years, $cityIds);
            $this->info(__('Apagados :n registo(s) (:uf de publicação STN por UF).', [
                'n' => $purge['total'],
                'uf' => $purge['uf_publicacao'],
            ]));

            return self::SUCCESS;
        }

        $result = $rebuild->rebuild($years, $cityIds, $purgeBefore);

        $this->info(__('Purga: :n registo(s) (:uf publicação UF).', [
            'n' => $result['purged'],
            'uf' => $result['purged_uf_publicacao'],
        ]));
        $this->info(__('Cidades: :c · importações OK: :ok · falhas: :fail · linhas gravadas: :rows', [
            'c' => $result['cities'],
            'ok' => $result['imported'],
            'fail' => $result['failed'],
            'rows' => $result['rows_written'],
        ]));

        $rows = array_map(static fn (array $r): array => [
            $r['slug'] ?? '',
            $r['city'] ?? '',
            $r['uf'] ?? '',
            (string) ($r['year'] ?? ''),
            ($r['ok'] ?? false) ? 'OK' : 'FAIL',
            (string) ($r['rows'] ?? 0),
            mb_substr((string) ($r['message'] ?? ''), 0, 80),
        ], $result['results']);

        if ($rows !== []) {
            $this->table(['Slug anual', 'Município', 'UF', 'Ano', 'Estado', 'Linhas', 'Mensagem'], $rows);
        }

        return ($result['failed'] ?? 0) === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<int>
     */
    private function resolveYears(): array
    {
        if ($this->option('ano') !== null) {
            return [(int) $this->option('ano')];
        }

        $from = $this->option('from');
        $to = $this->option('to');
        if ($from !== null && $to !== null) {
            $a = (int) $from;
            $b = (int) $to;
            if ($a > $b) {
                [$a, $b] = [$b, $a];
            }

            return range($a, $b);
        }

        return [];
    }

    /**
     * @return list<int>|null|false
     */
    private function resolveCityIds(): array|null|false
    {
        if ($this->option('all-cities')) {
            return null;
        }

        if ($this->option('city') !== null) {
            return [(int) $this->option('city')];
        }

        $cities = trim((string) $this->option('cities'));
        if ($cities !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $cities))));
        }

        return false;
    }

    /**
     * @param  list<int>  $years
     */
    private function confirmProduction(array $years, bool $dryRun, bool $purgeOnly): bool
    {
        if ($dryRun || ! app()->environment('production')) {
            if (! $dryRun && ! $purgeOnly && ! $this->confirm(__('Confirma rebuild dos repasses para Tempo Real?'), false)) {
                return false;
            }

            return true;
        }

        $year = count($years) === 1 ? $years[0] : null;
        $template = (string) config('ieducar.finance_realtime.rebuild_confirm_slug', 'rebuild-repasses-{ano}');
        $expected = str_replace('{ano}', $year !== null ? (string) $year : 'all', $template);
        $confirm = trim((string) $this->option('confirm'));

        if ($confirm === '' || ! hash_equals($expected, $confirm)) {
            $this->error(__('Em production use o slug anual:'));
            $this->line('  php artisan funding:rebuild-finance-realtime --all-cities --ano='.$year.' --confirm='.$expected);

            return false;
        }

        return true;
    }
}
