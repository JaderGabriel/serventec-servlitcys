<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\Fundeb\FundebImportBenchmark;
use App\Services\Fundeb\FundebOpenDataImportService;
use Illuminate\Console\Command;

class FundebBenchmarkImportCommand extends Command
{
    protected $signature = 'fundeb:benchmark
                            {--cities= : IDs separados por vírgula (default: até 5 com IBGE)}
                            {--years= : Anos (ex.: 2024,2025); default anos de nova cidade}
                            {--iterations=3 : Repetições por modo (fluxo completo limitado a 3)}
                            {--warm-cache : Pré-grava JSON em cache via HTTP fake antes do benchmark}';

    protected $description = 'Compara tempo: fluxo completo (API/cache) vs gravação directa na base';

    public function handle(FundebImportBenchmark $benchmark): int
    {
        $cityIds = $this->parseCityIds();
        $yearsOpt = trim((string) $this->option('years'));
        $anos = $yearsOpt !== ''
            ? FundebOpenDataImportService::normalizeYearList(array_map('intval', explode(',', $yearsOpt)))
            : FundebOpenDataImportService::yearsForNewCitySync();

        $iterations = max(1, (int) $this->option('iterations'));

        if ($this->option('warm-cache') && $cityIds !== []) {
            $this->info(__('A aquecer cache JSON (:n municípios × :y anos)...', [
                'n' => (string) count($cityIds),
                'y' => (string) count($anos),
            ]));
            $written = $benchmark->warmFakeCache($cityIds, $anos);
            $this->line(__('  :n registos gravados em cache.', ['n' => (string) $written]));
        }

        $this->info(__('Benchmark FUNDEB — :c município(s), anos :y, :i iteração(ões) por modo', [
            'c' => $cityIds !== null ? (string) count($cityIds) : 'auto',
            'y' => implode(', ', array_map('strval', $anos)),
            'i' => (string) $iterations,
        ]));

        $result = $benchmark->run($cityIds, $anos, $iterations);

        if (isset($result['comparison']['error'])) {
            $this->error((string) $result['comparison']['error']);

            return self::FAILURE;
        }

        $rows = [];
        foreach ($result['modes'] as $mode) {
            $rows[] = [
                $mode['label'],
                $mode['operations'],
                $mode['total_ms'].' ms',
                $mode['ms_per_op'].' ms',
                $mode['ops_per_sec'] ?? '—',
                isset($mode['vs_db_ratio']) ? $mode['vs_db_ratio'].'×' : '—',
            ];
        }

        $this->table(
            [__('Modo'), __('Ops'), __('Total'), __('ms/op'), __('ops/s'), __('vs só BD')],
            $rows,
        );

        $cmp = $result['comparison'];
        $this->newLine();
        $this->line(__('Gravação só na base: :ms ms/op', ['ms' => (string) ($cmp['db_only_ms'] ?? '—')]));
        $this->line(__('Fluxo completo: :ms ms/op (:ratio mais lento; overhead ~:oh ms/op)', [
            'ms' => (string) ($cmp['full_import_ms'] ?? '—'),
            'ratio' => (string) ($cmp['full_is_slower_by'] ?? '—'),
            'oh' => (string) round((float) ($cmp['full_overhead_ms'] ?? 0), 2),
        ]));
        if (! empty($cmp['note'])) {
            $this->comment((string) $cmp['note']);
        }

        $ops = (int) ($result['operations'] ?? 0);
        $fullPerOp = (float) ($cmp['full_import_ms'] ?? 0);
        if ($fullPerOp > 0 && $ops > 0) {
            $estAll = City::query()
                ->whereNotNull('ibge_municipio')
                ->where('ibge_municipio', '!=', '')
                ->count() * count($anos);
            $estSec = ($estAll * $fullPerOp) / 1000;
            $this->newLine();
            $this->line(__('Estimativa lote nacional (~:n ops × :ms ms): ~:min min', [
                'n' => (string) $estAll,
                'ms' => (string) round($fullPerOp, 1),
                'min' => (string) round($estSec / 60, 1),
            ]));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>|null
     */
    private function parseCityIds(): ?array
    {
        $raw = trim((string) $this->option('cities'));
        if ($raw !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $raw))));
        }

        $ids = City::query()
            ->whereNotNull('ibge_municipio')
            ->where('ibge_municipio', '!=', '')
            ->orderBy('name')
            ->limit(5)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return $ids !== [] ? $ids : null;
    }
}
