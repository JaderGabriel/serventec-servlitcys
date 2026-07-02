<?php

namespace App\Services\Horizonte;

use App\Repositories\MunicipalAreaSnapshotRepository;
use App\Support\Geo\GeoJsonFeatureAreaKm2;
use App\Support\Horizonte\HorizonteIbgeMunicipalGeoImportProgress;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa malha municipal IBGE (GeoJSON por UF) e persiste área territorial em km².
 */
final class HorizonteIbgeMunicipalGeoImportService
{
    public function __construct(
        private readonly HorizonteIbgeMalhaService $malha,
        private readonly MunicipalAreaSnapshotRepository $areas,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *   success: bool,
     *   message: string,
     *   imported?: int,
     *   features?: int,
     *   partial?: bool,
     *   complete?: bool,
     *   ibge_geo_done?: int,
     *   ibge_geo_total?: int,
     *   uf?: string,
     *   steps?: list<array<string, mixed>>
     * }
     */
    public function importNextUfBatch(array $options = []): array
    {
        if (! (bool) config('horizonte.municipal_geo.enabled', true)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('Malha municipal IBGE desactivada (HORIZONTE_MUNICIPAL_GEO_ENABLED=false).'),
                'steps' => [],
            ];
        }

        $scopedUf = HorizonteUfScope::normalize($options['uf'] ?? null);
        $force = (bool) ($options['force'] ?? false);
        $totalUfs = HorizonteIbgeMunicipalGeoImportProgress::totalUfs();

        if ($scopedUf !== null) {
            $remaining = ($force || in_array($scopedUf, HorizonteIbgeMunicipalGeoImportProgress::remainingUfs(), true))
                ? [$scopedUf]
                : [];
        } else {
            $remaining = HorizonteIbgeMunicipalGeoImportProgress::remainingUfs();
        }

        if ($remaining === []) {
            $done = HorizonteIbgeMunicipalGeoImportProgress::doneCount();

            return [
                'success' => true,
                'complete' => true,
                'message' => $scopedUf !== null
                    ? __('Malha municipal IBGE já importada para :uf.', ['uf' => (string) $scopedUf])
                    : __('Malha municipal IBGE nacional completa (:done/:total UF(s)).', [
                        'done' => (string) $done,
                        'total' => (string) $totalUfs,
                    ]),
                'imported' => 0,
                'partial' => false,
                'ibge_geo_done' => $done,
                'ibge_geo_total' => $totalUfs,
                'steps' => [],
            ];
        }

        $perStep = max(1, min(3, (int) ($options['ufs_per_step'] ?? config('horizonte.municipal_geo.ufs_per_step', 1))));
        $batch = array_slice($remaining, 0, $perStep);
        $refYear = (int) config('horizonte.municipal_geo.reference_year', (int) date('Y'));
        $importMalha = ! array_key_exists('malha', $options) || (bool) $options['malha'];
        $importArea = ! array_key_exists('area', $options) || (bool) $options['area'];
        $useMetadadosFallback = (bool) ($options['metadados_fallback'] ?? config('horizonte.municipal_geo.metadados_fallback', true));
        $imported = 0;
        $featuresTotal = 0;
        $steps = [];

        foreach ($batch as $uf) {
            $this->emitStep($options, __('[:uf] A descarregar malha municipal IBGE…', ['uf' => $uf]));

            $result = $this->importUf(
                $uf,
                $refYear,
                $importMalha,
                $importArea,
                $useMetadadosFallback,
                $force,
            );

            $step = [
                'uf' => $uf,
                'success' => (bool) ($result['success'] ?? false),
                'imported' => (int) ($result['imported'] ?? 0),
                'features' => (int) ($result['features'] ?? 0),
                'message' => $result['message'] ?? null,
            ];
            $steps[] = $step;

            if (! ($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'partial' => true,
                    'message' => (string) ($result['message'] ?? __('Falha ao importar malha municipal — :uf', ['uf' => $uf])),
                    'uf' => $uf,
                    'imported' => $imported,
                    'steps' => $steps,
                    'ibge_geo_done' => HorizonteIbgeMunicipalGeoImportProgress::doneCount(),
                    'ibge_geo_total' => $totalUfs,
                ];
            }

            HorizonteIbgeMunicipalGeoImportProgress::recordStep($step);
            $this->emitStep($options, __('[:uf] :features polígonos · :imported área(s) km² gravadas', [
                'uf' => $uf,
                'features' => (string) $step['features'],
                'imported' => (string) $step['imported'],
            ]));

            $imported += $step['imported'];
            $featuresTotal += $step['features'];
        }

        $doneCount = HorizonteIbgeMunicipalGeoImportProgress::doneCount();
        $partial = $scopedUf === null && ! HorizonteIbgeMunicipalGeoImportProgress::isComplete();
        $complete = HorizonteIbgeMunicipalGeoImportProgress::isComplete();

        return [
            'success' => $imported > 0 || $featuresTotal > 0 || $complete,
            'partial' => $partial,
            'complete' => $complete,
            'message' => $complete
                ? __('Malha municipal IBGE nacional completa (:done/:total UF(s)).', [
                    'done' => (string) $doneCount,
                    'total' => (string) $totalUfs,
                ])
                : ($partial
                    ? __('Malha municipal IBGE: :n área(s) · :done/:total UF(s).', [
                        'n' => (string) $imported,
                        'done' => (string) $doneCount,
                        'total' => (string) $totalUfs,
                    ])
                    : __('Malha municipal IBGE — :uf: :n área(s) importada(s).', [
                        'uf' => (string) ($batch[0] ?? $scopedUf ?? ''),
                        'n' => (string) $imported,
                    ])),
            'imported' => $imported,
            'features' => $featuresTotal,
            'ibge_geo_done' => $doneCount,
            'ibge_geo_total' => $totalUfs,
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function emitStep(array $options, string $message): void
    {
        $callback = $options['on_step'] ?? null;
        if (is_callable($callback)) {
            $callback($message);
        }
    }

    /**
     * @return array{success: bool, message?: string, imported?: int, features?: int}
     */
    public function importUf(
        string $uf,
        ?int $refYear = null,
        bool $importMalha = true,
        bool $importArea = true,
        bool $useMetadadosFallback = true,
        bool $force = false,
    ): array {
        $uf = strtoupper(trim($uf));
        $refYear ??= (int) config('horizonte.municipal_geo.reference_year', (int) date('Y'));

        try {
            $geo = $importMalha
                ? $this->malha->stateMunicipalGeoJson($uf, $force)
                : $this->malha->cachedStateMunicipalGeoJson($uf);
        } catch (\Throwable $e) {
            Log::warning('horizonte.municipal_geo_malha_failed', ['uf' => $uf, 'message' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => __('Malha municipal indisponível para :uf — :msg', ['uf' => $uf, 'msg' => $e->getMessage()]),
            ];
        }

        $features = is_array($geo['features'] ?? null) ? $geo['features'] : [];
        if ($features === []) {
            return [
                'success' => false,
                'message' => __('Malha municipal vazia para :uf.', ['uf' => $uf]),
            ];
        }

        if (! $importArea) {
            return [
                'success' => true,
                'imported' => 0,
                'features' => count($features),
            ];
        }

        $rows = [];
        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $ibge = preg_replace('/\D/', '', (string) ($props['codarea'] ?? ''));
            if ($ibge === null || strlen($ibge) !== 7) {
                continue;
            }

            $area = GeoJsonFeatureAreaKm2::fromFeature($feature);
            $areaSource = 'ibge_malha_geom';
            if (($area === null || $area <= 0) && $useMetadadosFallback) {
                $fromMeta = $this->fetchAreaFromMetadados($ibge);
                if ($fromMeta !== null) {
                    $area = $fromMeta;
                    $areaSource = 'ibge_malha_metadados';
                }
            }
            if ($area === null || $area <= 0) {
                continue;
            }

            $rows[] = [
                'ibge_municipio' => $ibge,
                'ano_referencia' => $refYear,
                'area_km2' => $area,
                'fonte' => 'ibge_malha',
                'metadados' => [
                    'uf' => $uf,
                    'area_source' => $areaSource,
                ],
            ];
        }

        $count = $this->areas->upsertBatch($rows);

        return [
            'success' => true,
            'imported' => $count,
            'features' => count($features),
        ];
    }

    private function fetchAreaFromMetadados(string $ibge): ?float
    {
        $delayMs = max(0, (int) config('horizonte.municipal_geo.metadados_delay_ms', 40));
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get('https://servicodados.ibge.gov.br/api/v4/malhas/municipios/'.$ibge.'/metadados');
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $items = $response->json();
        if (! is_array($items) || ! isset($items[0]) || ! is_array($items[0])) {
            return null;
        }

        $dim = $items[0]['area']['dimensao'] ?? null;
        if (! is_numeric($dim)) {
            return null;
        }

        $area = (float) $dim;

        return $area > 0 ? round($area, 3) : null;
    }
}
