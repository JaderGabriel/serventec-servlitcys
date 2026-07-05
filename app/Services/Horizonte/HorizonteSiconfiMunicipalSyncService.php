<?php

namespace App\Services\Horizonte;

use App\Models\MunicipalFiscalSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\Horizonte\SiconfiApiClient;
use App\Support\Horizonte\SiconfiRreoParser;
use Illuminate\Support\Facades\Log;

/** Importa indicadores fiscais municipais via API SICONFI (RREO). */
final class HorizonteSiconfiMunicipalSyncService
{
    public function __construct(
        private readonly SiconfiApiClient $client,
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, partial?: bool}
     */
    public function syncBatch(array $options = []): array
    {
        if (! filter_var(config('horizonte.siconfi.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('SICONFI desactivado (HORIZONTE_SICONFI_ENABLED=false).'),
            ];
        }

        $year = (int) ($options['year'] ?? config('horizonte.reference_year', (int) date('Y') - 1));
        $period = max(1, min(6, (int) ($options['period'] ?? config('horizonte.siconfi.period', 6))));
        $scopedUf = HorizonteUfScope::normalize($options['uf'] ?? null);
        $perStep = max(1, min(50, (int) ($options['municipios_per_step'] ?? config('horizonte.siconfi.municipios_per_step', 8))));
        $batch = $this->resolveIbgeBatch($scopedUf, $perStep, $year, $options);
        $ibgeCodes = $batch['codes'];
        $pendingAfter = $batch['pending_after'];

        if ($ibgeCodes === []) {
            return [
                'success' => true,
                'message' => __('SICONFI: nenhum município pendente para o lote.'),
                'imported' => 0,
                'partial' => false,
            ];
        }

        $imported = 0;
        foreach ($ibgeCodes as $ibge) {
            $parsed = $this->fetchAndParse((int) $ibge, $year, $period);
            if ($parsed === null) {
                continue;
            }

            MunicipalFiscalSnapshot::query()->updateOrCreate(
                ['ibge_municipio' => $ibge, 'ano' => $year],
                array_merge($parsed, [
                    'periodo' => $period,
                    'fonte' => 'siconfi_rreo',
                    'imported_at' => now(),
                ]),
            );
            $imported++;
        }

        $partial = $pendingAfter > 0;

        return [
            'success' => $imported > 0 || ! $partial,
            'message' => __('SICONFI: :n município(s) actualizados (ano :ano).', [
                'n' => (string) $imported,
                'ano' => (string) $year,
            ]),
            'imported' => $imported,
            'partial' => $partial,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAndParse(int $ibge, int $year, int $period): ?array
    {
        try {
            $a01 = $this->client->fetchRreo($ibge, $year, $period, 'RREO-Anexo 01');
            $a02 = $this->client->fetchRreo($ibge, $year, $period, 'RREO-Anexo 02');
            $a06 = $this->client->fetchRreo($ibge, $year, $period, 'RREO-Anexo 06');
            $a14 = $this->client->fetchRreo($ibge, $year, $period, 'RREO-Anexo 14');
        } catch (\Throwable $e) {
            Log::warning('horizonte.siconfi_fetch_failed', ['ibge' => $ibge, 'message' => $e->getMessage()]);

            return null;
        }

        if ($a01 === [] && $a02 === [] && $a06 === [] && $a14 === []) {
            return null;
        }

        $parsed = SiconfiRreoParser::parse($a01, $a02, $a06, $a14);
        $parsed['metadados'] = [
            'annex_counts' => [
                '01' => count($a01),
                '02' => count($a02),
                '06' => count($a06),
                '14' => count($a14),
            ],
        ];

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    /**
     * @param  array<string, mixed>  $options
     * @return array{codes: list<string>, pending_after: int}
     */
    private function resolveIbgeBatch(?string $scopedUf, int $perStep, int $year, array $options): array
    {
        if (is_array($options['ibge_codes'] ?? null)) {
            $codes = [];
            foreach ($options['ibge_codes'] as $raw) {
                $norm = FundebMunicipioReferenceRepository::normalizeIbge((string) $raw);
                if ($norm !== null) {
                    $codes[] = $norm;
                }
            }
            $codes = array_slice(array_values(array_unique($codes)), 0, $perStep);

            return ['codes' => $codes, 'pending_after' => 0];
        }

        $all = HorizonteUfScope::ibgeCodesForUf($scopedUf, $this->ibgeCatalog)
            ?? HorizonteUfScope::nationalIbgeCodes($this->ibgeCatalog);
        $imported = MunicipalFiscalSnapshot::query()
            ->where('ano', $year)
            ->pluck('ibge_municipio')
            ->map(static fn ($v) => FundebMunicipioReferenceRepository::normalizeIbge((string) $v))
            ->filter()
            ->all();
        $importedSet = array_fill_keys($imported, true);
        $pending = array_values(array_filter($all, static fn (string $ibge): bool => ! isset($importedSet[$ibge])));
        $codes = array_slice($pending, 0, $perStep);

        return [
            'codes' => $codes,
            'pending_after' => max(0, count($pending) - count($codes)),
        ];
    }
}
