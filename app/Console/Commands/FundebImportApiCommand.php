<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Fundeb\FundebOpenDataImportService;
use App\Services\Fundeb\FundebImportProgress;
use Illuminate\Console\Command;

class FundebImportApiCommand extends Command
{
    protected $signature = 'fundeb:import-api
                            {city? : ID da cidade (omitir com --all ou --cities)}
                            {--ano= : Um único ano (ignorado se --years, --from/--to ou --new-city-years)}
                            {--years= : Anos separados por vírgula (ex.: 2024,2025)}
                            {--from= : Ano inicial do intervalo}
                            {--to= : Ano final do intervalo}
                            {--new-city-years : Apenas ano vigente e anterior (igual ao cadastro automático)}
                            {--nearest : Se o ano pedido não existir na API, gravar o mais recente disponível}
                            {--all : Todos os municípios com IBGE}
                            {--cities= : IDs de cidades separados por vírgula (ex.: 1,3,5)}';

    protected $description = 'Importa VAAF/VAAT via API (CKAN FNDE ou JSON) e grava em fundeb_municipio_references';

    public function __construct(
        private FundebOpenDataImportService $import,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $useNearest = (bool) $this->option('nearest');
        $years = $this->resolveYears();
        if ($years === []) {
            $this->error(__('Nenhum ano definido. Use --ano, --years, --from/--to ou --new-city-years.'));

            return self::FAILURE;
        }

        $cityIds = $this->resolveCityIds();
        if ($cityIds === false) {
            $this->error(__('Indique o ID da cidade, --cities= ou --all.'));

            return self::FAILURE;
        }

        $progress = $this->makeConsoleProgress();
        $this->printRunHeader($years, $cityIds);

        $singleCityId = is_array($cityIds) && count($cityIds) === 1 ? $cityIds[0] : null;
        if ($singleCityId !== null && count($years) === 1 && ! $this->option('all') && ! $this->option('cities')) {
            $city = City::query()->find($singleCityId);
            if ($city === null) {
                $this->error(__('Cidade não encontrada.'));

                return self::FAILURE;
            }

            $result = $this->import->importForCityYear($city, $years[0], $useNearest, $progress);
            $this->newLine();
            if ($result['success']) {
                $this->info($result['message']);

                return self::SUCCESS;
            }

            $this->error($result['message']);

            return self::FAILURE;
        }

        $result = $this->import->importBulkForYears($years, $useNearest, $cityIds, $progress);
        $this->newLine();
        $this->displayBulkResult($result);

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function makeConsoleProgress(): FundebImportProgress
    {
        return new FundebImportProgress(function (string $level, string $message): void {
            match ($level) {
                'success' => $this->info($message),
                'warn' => $this->warn($message),
                'error' => $this->error($message),
                default => $this->line($message),
            };
        });
    }

    /**
     * @param  list<int>  $years
     * @param  list<int>|null|false  $cityIds
     */
    private function printRunHeader(array $years, array|null|false $cityIds): void
    {
        $flags = [];
        if ($this->option('new-city-years')) {
            $flags[] = '--new-city-years';
        }
        if ($this->option('nearest')) {
            $flags[] = '--nearest';
        }
        if ($this->option('all')) {
            $flags[] = '--all';
        }
        if ($this->option('cities')) {
            $flags[] = '--cities='.$this->option('cities');
        }
        if ($this->option('years')) {
            $flags[] = '--years='.$this->option('years');
        }
        if ($this->option('from') || $this->option('to')) {
            $flags[] = '--from='.($this->option('from') ?: '…').' --to='.($this->option('to') ?: '…');
        }
        if ($this->option('ano')) {
            $flags[] = '--ano='.$this->option('ano');
        }

        $this->info(__('Comando fundeb:import-api'));
        if ($flags !== []) {
            $this->line(__('Opções: :opts', ['opts' => implode(' ', $flags)]));
        }
        $this->line(__('Anos: :anos', ['anos' => implode(', ', array_map('strval', $years))]));
        if ($cityIds === null) {
            $this->line(__('Municípios: todos com IBGE'));
        } elseif (is_array($cityIds)) {
            $this->line(__('Municípios (IDs): :ids', ['ids' => implode(', ', array_map('strval', $cityIds))]));
        }
        $this->newLine();
    }

    /**
     * @return list<int>
     */
    private function resolveYears(): array
    {
        if ($this->option('new-city-years')) {
            return FundebOpenDataImportService::yearsForNewCitySync();
        }

        $yearsOpt = trim((string) $this->option('years'));
        if ($yearsOpt !== '') {
            return FundebOpenDataImportService::normalizeYearList(
                array_map('intval', explode(',', $yearsOpt)),
            );
        }

        $from = $this->option('from');
        $to = $this->option('to');
        if ($from !== null && $from !== '' || $to !== null && $to !== '') {
            $fromInt = $from !== null && $from !== '' ? (int) $from : (int) config('ieducar.fundeb.open_data.sync_from_year', 2020);
            $toInt = $to !== null && $to !== '' ? (int) $to : (int) date('Y') - 1;

            return FundebOpenDataImportService::yearsInRange($fromInt, $toInt);
        }

        $ano = $this->option('ano');
        if ($ano !== null && $ano !== '') {
            return [(int) $ano];
        }

        return [FundebOpenDataImportService::suggestedImportYear()];
    }

    /**
     * @return list<int>|null|false
     */
    private function resolveCityIds(): array|null|false
    {
        if ($this->option('all')) {
            return null;
        }

        $citiesOpt = trim((string) $this->option('cities'));
        if ($citiesOpt !== '') {
            return array_values(array_unique(array_map('intval', explode(',', $citiesOpt))));
        }

        $cityArg = $this->argument('city');
        if ($cityArg !== null && $cityArg !== '') {
            return [(int) $cityArg];
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function displayBulkResult(array $result): void
    {
        $this->line($result['message'] ?? '');

        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        if ($summary !== []) {
            $this->table(
                [__('Métrica'), __('Valor')],
                [
                    [__('Anos'), implode(', ', array_map('strval', $result['anos'] ?? []))],
                    [__('Gravados'), (string) ($summary['ok_count'] ?? 0)],
                    [__('Falhas'), (string) ($summary['failed_count'] ?? 0)],
                    [__('Sem IBGE'), (string) ($summary['skipped_count'] ?? 0)],
                ],
            );
        }

        $logs = is_array($result['logs'] ?? null) ? $result['logs'] : [];
        if ($logs !== [] && $this->output->isVerbose()) {
            $this->newLine();
            $this->line(__('<comment>Log completo (--verbose):</comment>'));
            foreach ($logs as $entry) {
                $this->line(sprintf('[%s] %s', $entry['at'] ?? '', $entry['message'] ?? ''));
            }
        }
    }
}
