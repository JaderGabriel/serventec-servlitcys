<?php

namespace App\Services\Ibge;

use App\Repositories\MunicipalDemographySnapshotRepository;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteSidraImportProgress;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa população 4–17 anos por município via API SIDRA (Censo 2022, agregado 9514).
 */
final class IbgeSidraMunicipalDemographyService
{
    /** @var list<string> */
    private const AGE_CATEGORY_IDS = [
        '6561', '6562', '6563', '6564', '6565', '6566', '6567',
        '6568', '6569', '6570', '6571', '6572', '6573', '6574',
    ];

    public function __construct(
        private readonly MunicipalDemographySnapshotRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, partial?: bool, sidra_done?: int, sidra_total?: int}
     */
    public function importNextUfBatch(array $options = []): array
    {
        $cfg = config('horizonte.sidra', []);
        if (! (bool) ($cfg['enabled'] ?? true)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('SIDRA desactivado (HORIZONTE_SIDRA_ENABLED=false).'),
            ];
        }

        $scopedUf = HorizonteUfScope::normalize($options['uf'] ?? null);
        $remaining = $scopedUf !== null
            ? (in_array($scopedUf, HorizonteSidraImportProgress::remainingUfs(), true) ? [$scopedUf] : [])
            : HorizonteSidraImportProgress::remainingUfs();

        if ($remaining === []) {
            HorizonteSidraImportProgress::reset();

            return [
                'success' => true,
                'message' => __('SIDRA: catálogo demográfico já completo.'),
                'imported' => 0,
                'partial' => false,
            ];
        }

        $perStep = max(1, min(3, (int) ($cfg['ufs_per_step'] ?? config('horizonte.fortnightly_feed.ibge_ufs_per_step', 1))));
        $batch = array_slice($remaining, 0, $perStep);
        $ano = (int) ($cfg['periodo'] ?? config('horizonte.reference_year', (int) date('Y') - 1));
        $timeout = max(15, (int) ($cfg['http_timeout'] ?? 90));
        $imported = 0;

        foreach ($batch as $uf) {
            $rows = $this->fetchPop417ForUf($uf, $ano, $timeout);
            if ($rows === []) {
                Log::warning('horizonte.sidra_uf_empty', ['uf' => $uf, 'ano' => $ano]);

                continue;
            }
            $imported += $this->repository->upsertBatch($rows);
            HorizonteSidraImportProgress::markDone([$uf]);
        }

        $allUfs = IbgeMunicipalityCatalog::brazilianUfs();
        $doneCount = count(HorizonteSidraImportProgress::doneUfs());
        $total = $scopedUf !== null ? 1 : count($allUfs);
        $partial = $doneCount < $total;

        return [
            'success' => $imported > 0 || $partial,
            'partial' => $partial,
            'message' => $partial
                ? __('SIDRA: :n município(s) — :done/:total UF(s) concluída(s).', [
                    'n' => (string) $imported,
                    'done' => (string) min($doneCount, $total),
                    'total' => (string) $total,
                ])
                : __('SIDRA: população 4–17 importada — :n município(s).', ['n' => (string) $imported]),
            'imported' => $imported,
            'sidra_done' => min($doneCount, $total),
            'sidra_total' => $total,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPop417ForUf(string $uf, int $ano, int $timeout): array
    {
        $n3 = $this->ufToN3($uf);
        if ($n3 === null) {
            return [];
        }

        $cfg = config('horizonte.sidra', []);
        $agregado = (string) ($cfg['agregado'] ?? '9514');
        $variavel = (string) ($cfg['variavel'] ?? '93');
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://servicodados.ibge.gov.br/api/v3/agregados'), '/');
        $ageIds = implode(',', self::AGE_CATEGORY_IDS);

        $url = sprintf(
            '%s/%s/periodos/%d/variaveis/%s?localidades=N6[N3[%s]]&classificacao=287[%s]',
            $baseUrl,
            $agregado,
            $ano,
            $variavel,
            $n3,
            $ageIds,
        );

        try {
            $response = Http::timeout($timeout)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            Log::warning('horizonte.sidra_http_failed', ['uf' => $uf, 'message' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('horizonte.sidra_http_status', ['uf' => $uf, 'status' => $response->status()]);

            return [];
        }

        $decoded = $response->json();
        if (! is_array($decoded) || (isset($decoded['statusCode']) && (int) $decoded['statusCode'] >= 400)) {
            return [];
        }

        /** @var array<string, int> $byIbge */
        $byIbge = [];
        foreach ($decoded as $block) {
            if (! is_array($block)) {
                continue;
            }
            foreach ($block['resultados'] ?? [] as $result) {
                if (! is_array($result)) {
                    continue;
                }
                foreach ($result['series'] ?? [] as $series) {
                    if (! is_array($series)) {
                        continue;
                    }
                    $ibge = preg_replace('/\D/', '', (string) ($series['localidade']['id'] ?? ''));
                    if ($ibge === null || strlen($ibge) !== 7) {
                        continue;
                    }
                    $val = (int) preg_replace('/\D/', '', (string) ($series['serie'][(string) $ano] ?? $series['serie'][$ano] ?? '0'));
                    if ($val <= 0) {
                        continue;
                    }
                    $byIbge[$ibge] = ($byIbge[$ibge] ?? 0) + $val;
                }
            }
        }

        $rows = [];
        foreach ($byIbge as $ibge => $pop) {
            $rows[] = [
                'ibge_municipio' => $ibge,
                'ano_referencia' => $ano,
                'populacao_4_17' => $pop,
                'fonte' => 'ibge_sidra',
                'metadados' => ['uf' => strtoupper($uf), 'agregado' => $agregado],
            ];
        }

        return $rows;
    }

    private function ufToN3(string $uf): ?string
    {
        $map = config('horizonte.sidra.uf_n3_codes', []);

        return isset($map[strtoupper($uf)]) ? (string) $map[strtoupper($uf)] : null;
    }
}
