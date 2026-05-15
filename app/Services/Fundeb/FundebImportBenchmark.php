<?php

namespace App\Services\Fundeb;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Support\Facades\Http;

/**
 * Compara tempo do fluxo completo (API/cache) vs gravação direta na base.
 */
final class FundebImportBenchmark
{
    public function __construct(
        private FundebOpenDataImportService $import,
        private FundebMunicipioReferenceRepository $references,
    ) {}

    /**
     * @param  list<int>  $anos
     * @return array{
     *     iterations: int,
     *     cities: int,
     *     anos: list<int>,
     *     operations: int,
     *     modes: list<array<string, mixed>>,
     *     comparison: array<string, mixed>
     * }
     */
    public function run(?array $cityIds, array $anos, int $iterations = 1): array
    {
        $anos = FundebOpenDataImportService::normalizeYearList($anos);
        if ($anos === []) {
            $anos = FundebOpenDataImportService::yearsForNewCitySync();
        }

        $query = City::query()->orderBy('name');
        if ($cityIds !== null && $cityIds !== []) {
            $query->whereIn('id', $cityIds);
        }

        /** @var \Illuminate\Support\Collection<int, City> $cities */
        $cities = $query->get()->filter(
            static fn (City $c): bool => FundebMunicipioReferenceRepository::normalizeIbge($c->ibge_municipio) !== null,
        );

        if ($cities->isEmpty()) {
            return [
                'iterations' => $iterations,
                'cities' => 0,
                'anos' => $anos,
                'operations' => 0,
                'modes' => [],
                'comparison' => ['error' => __('Nenhum município com IBGE para benchmark.')],
            ];
        }

        $sampleRow = [
            'vaaf' => (float) config('ieducar.discrepancies.vaa_referencia_anual', 5500),
            'vaat' => null,
            'complementacao_vaar' => null,
            'fonte' => 'benchmark_db_only',
            'notas' => 'benchmark',
        ];

        $modes = [];

        $modes[] = $this->measureMode(
            'db_only',
            __('Só gravação na base (upsert)'),
            $iterations,
            $cities,
            $anos,
            function (City $city, int $ano) use ($sampleRow): void {
                $this->references->upsert($city, $ano, $sampleRow);
            },
        );

        $modes[] = $this->measureMode(
            'cache_read',
            __('Leitura JSON em cache (sem gravar na base)'),
            $iterations,
            $cities,
            $anos,
            function (City $city, int $ano): void {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
                if ($ibge === null) {
                    return;
                }
                $this->import->readCachedRowOnly($ibge, $ano);
            },
        );

        $modes[] = $this->measureMode(
            'cache_and_db',
            __('Cache local + gravação na base (sem HTTP)'),
            $iterations,
            $cities,
            $anos,
            function (City $city, int $ano) use ($sampleRow): void {
                $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
                if ($ibge === null) {
                    return;
                }
                $row = $this->import->readCachedRowOnly($ibge, $ano) ?? $sampleRow;
                $this->references->upsert($city, $ano, $row);
            },
        );

        $modes[] = $this->measureMode(
            'full_import',
            __('Fluxo atual (cache → API/CKAN → cache + base)'),
            max(1, min($iterations, 3)),
            $cities,
            $anos,
            function (City $city, int $ano): void {
                $this->import->importForCityYear($city, $ano, false, null);
            },
        );

        $dbMs = (float) ($modes[0]['ms_per_op'] ?? 1);
        $fullIdx = count($modes) - 1;
        $fullMs = (float) ($modes[$fullIdx]['ms_per_op'] ?? 0);

        foreach ($modes as &$mode) {
            $mode['vs_db_ratio'] = $dbMs > 0 ? round($mode['ms_per_op'] / $dbMs, 1) : null;
            $mode['vs_db_pct'] = $dbMs > 0
                ? round((($mode['ms_per_op'] - $dbMs) / $dbMs) * 100, 0)
                : null;
        }
        unset($mode);

        return [
            'iterations' => $iterations,
            'cities' => $cities->count(),
            'anos' => $anos,
            'operations' => $cities->count() * count($anos),
            'modes' => $modes,
            'comparison' => [
                'db_only_ms' => $dbMs,
                'full_import_ms' => $fullMs,
                'full_is_slower_by' => $fullMs > 0 && $dbMs > 0
                    ? round($fullMs / $dbMs, 1).'×'
                    : null,
                'full_overhead_ms' => max(0, $fullMs - $dbMs),
                'note' => $fullMs > 500
                    ? __('O gargalo costuma ser HTTP CKAN/timeout ou tentativas sem cache. Com cache quente, o fluxo completo aproxima-se da leitura de ficheiro + upsert.')
                    : __('Tempos medidos no ambiente actual; use --warm-cache após uma importação real.'),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, City>  $cities
     * @param  list<int>  $anos
     */
    private function measureMode(
        string $id,
        string $label,
        int $iterations,
        $cities,
        array $anos,
        callable $callback,
    ): array {
        $iterations = max(1, $iterations);
        $ops = 0;
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            foreach ($cities as $city) {
                foreach ($anos as $ano) {
                    $callback($city, $ano);
                    $ops++;
                }
            }
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $perOp = $ops > 0 ? $elapsedMs / $ops : 0;

        return [
            'id' => $id,
            'label' => $label,
            'iterations' => $iterations,
            'operations' => $ops,
            'total_ms' => round($elapsedMs, 2),
            'ms_per_op' => round($perOp, 2),
            'ops_per_sec' => $perOp > 0 ? round(1000 / $perOp, 1) : null,
        ];
    }

    /**
     * Pré-aquece cache JSON via HTTP fake (benchmark reprodutível sem rede).
     *
     * @param  list<int>  $cityIds
     * @param  list<int>  $anos
     */
    public function warmFakeCache(array $cityIds, array $anos): int
    {
        config([
            'ieducar.fundeb.open_data.json_url' => 'https://benchmark.test/fundeb/{ibge}/{ano}',
            'ieducar.fundeb.open_data.resource_id' => '',
            'ieducar.fundeb.open_data.national_floor.enabled' => false,
        ]);

        Http::fake([
            'benchmark.test/*' => function (\Illuminate\Http\Client\Request $request) {
                if (preg_match('#/(\d{7})/(\d{4})#', $request->url(), $m)) {
                    return Http::response([
                        ['codigo_ibge' => $m[1], 'ano' => (int) $m[2], 'vaaf' => 5200.0],
                    ], 200);
                }

                return Http::response([], 404);
            },
        ]);

        $written = 0;
        $cities = City::query()->whereIn('id', $cityIds)->get();
        foreach ($cities as $city) {
            foreach ($anos as $ano) {
                $result = $this->import->importForCityYear($city, $ano, false, null);
                if ($result['success'] ?? false) {
                    $written++;
                }
            }
        }

        return $written;
    }
}
