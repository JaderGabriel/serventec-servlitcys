<?php

namespace App\Support\Horizonte;

use Illuminate\Support\Facades\Storage;

/**
 * Segmentos e playbooks de prospecção para gestores comerciais.
 */
final class HorizonteManagerInsights
{
    /**
     * @param  list<array<string, mixed>>  $markers
     * @return array<string, mixed>
     */
    public static function dataCoverage(array $markers): array
    {
        $withPublic = 0;
        $withFundeb = 0;
        $withCenso = 0;
        $withSaeb = 0;
        $withFullTriad = 0;
        $prospectMatriculas = 0;

        foreach ($markers as $m) {
            $hasFundeb = (bool) ($m['has_fundeb'] ?? false);
            $hasCenso = (bool) ($m['has_censo'] ?? false);
            $hasSaeb = (bool) ($m['has_saeb'] ?? false);
            $isProspect = str_starts_with((string) ($m['tier'] ?? ''), 'prospect_');

            if ($hasFundeb || $hasCenso || $hasSaeb) {
                $withPublic++;
            }
            if ($hasFundeb) {
                $withFundeb++;
            }
            if ($hasCenso) {
                $withCenso++;
            }
            if ($hasSaeb) {
                $withSaeb++;
            }
            if ($hasFundeb && $hasCenso && $hasSaeb) {
                $withFullTriad++;
            }
            if ($isProspect && ! ($m['consultoria_active'] ?? false)) {
                $prospectMatriculas += (int) ($m['matriculas_censo'] ?? 0);
            }
        }

        $total = count($markers);

        return [
            'with_public_data' => $withPublic,
            'with_fundeb' => $withFundeb,
            'with_censo' => $withCenso,
            'with_saeb' => $withSaeb,
            'with_full_triad' => $withFullTriad,
            'prospect_matriculas_censo' => $prospectMatriculas,
            'public_data_pct' => $total > 0 ? (int) round(100 * $withPublic / $total) : 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return array<string, mixed>
     */
    public static function sgeSummary(array $markers): array
    {
        $withSge = 0;
        $consultoria = 0;
        $catalog = 0;
        $registry = 0;
        $notFound = 0;
        $bySystem = [];

        foreach ($markers as $m) {
            $sge = is_array($m['sge'] ?? null) ? $m['sge'] : [];
            $found = (bool) ($sge['found'] ?? $m['sge_found'] ?? false);
            if ($found) {
                $withSge++;
            } else {
                $notFound++;
            }

            $status = (string) ($sge['status'] ?? $m['sge_status'] ?? 'not_found');
            match ($status) {
                'consultoria_active' => $consultoria++,
                'catalog_pending', 'catalog_configured' => $catalog++,
                'registry' => $registry++,
                default => null,
            };

            $system = trim((string) ($sge['system'] ?? $m['sge_system'] ?? ''));
            if ($system !== '') {
                $bySystem[$system] = ($bySystem[$system] ?? 0) + 1;
            }
        }

        arsort($bySystem);

        return [
            'total' => count($markers),
            'with_sge' => $withSge,
            'not_found' => $notFound,
            'consultoria_active' => $consultoria,
            'catalog' => $catalog,
            'registry' => $registry,
            'by_system' => $bySystem,
            'registry_configured' => self::sgeRegistryConfigured(),
        ];
    }

    private static function sgeRegistryConfigured(): bool
    {
        $url = trim((string) config('horizonte.sge.registry_url', ''));
        if ($url !== '') {
            return true;
        }

        $rel = trim((string) config('horizonte.sge.registry_path', ''));
        if ($rel === '') {
            return false;
        }

        try {
            return Storage::disk('local')->exists($rel);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    public static function focusSegments(array $markers): array
    {
        $highFinancial = 0;
        $pedagogicalGap = 0;
        $largeScale = 0;
        $readyToPitch = 0;

        foreach ($markers as $m) {
            if ($m['consultoria_active'] ?? false) {
                continue;
            }
            if (! str_starts_with((string) ($m['tier'] ?? ''), 'prospect_')) {
                continue;
            }

            if ((int) ($m['financial_pressure'] ?? 0) >= 60) {
                $highFinancial++;
            }
            if ((int) ($m['pedagogical_gap'] ?? 0) >= 60) {
                $pedagogicalGap++;
            }
            if ((int) ($m['matriculas_censo'] ?? 0) >= 15000) {
                $largeScale++;
            }
            if ((int) ($m['data_readiness'] ?? 0) >= 66 && (int) ($m['success_score'] ?? 0) >= 55) {
                $readyToPitch++;
            }
        }

        return [
            [
                'key' => 'ready_to_pitch',
                'label' => __('Prontos para abordagem'),
                'description' => __('Propensão ≥ 55 com FUNDEB + Censo ou SAEB — dados suficientes para argumento comercial.'),
                'count' => $readyToPitch,
                'filter' => ['tier' => 'prospects', 'min_success' => 55, 'min_readiness' => 66],
            ],
            [
                'key' => 'fundeb_pressure',
                'label' => __('Pressão FUNDEB'),
                'description' => __('Complementação elevada vs. mediana — argumento financeiro forte.'),
                'count' => $highFinancial,
                'filter' => ['tier' => 'prospects', 'min_financial' => 60, 'require_fundeb' => true],
            ],
            [
                'key' => 'pedagogical_gap',
                'label' => __('Déficit SAEB'),
                'description' => __('Desempenho abaixo do p25 nacional — oportunidade pedagógica.'),
                'count' => $pedagogicalGap,
                'filter' => ['tier' => 'prospects', 'min_pedagogical' => 60, 'require_saeb' => true],
            ],
            [
                'key' => 'large_scale',
                'label' => __('Grande escala'),
                'description' => __('≥ 15 mil matrículas Censo — impacto de rede e receita.'),
                'count' => $largeScale,
                'filter' => ['tier' => 'prospects', 'min_matriculas' => 15000],
            ],
        ];
    }
}
