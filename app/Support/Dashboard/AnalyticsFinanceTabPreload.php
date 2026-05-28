<?php

namespace App\Support\Dashboard;

/**
 * Pré-carga das abas de Finanças e Censo: um snapshot por aba e contexto municipal derivado.
 */
final class AnalyticsFinanceTabPreload
{
    public static function shouldReuseFundingContext(string $tab): bool
    {
        if (! self::financeTabsReuseEnabled() && ! self::isCensoGroupTab($tab)) {
            return false;
        }

        return match ($tab) {
            'municipality_health' => self::municipalityHealthReuseEnabled(),
            'discrepancies', 'fundeb', 'comparativo' => self::financeTabsReuseEnabled(),
            'other_funding' => self::financeStripContextEnabled(),
            'work_done' => true,
            default => false,
        };
    }

    public static function isCensoGroupTab(string $tab): bool
    {
        return AnalyticsTabCatalog::isCensoGroupTab($tab);
    }

    public static function isFinanceGroupTab(string $tab): bool
    {
        return AnalyticsTabCatalog::isFinanceGroupTab($tab);
    }

    public static function financeTabsReuseEnabled(): bool
    {
        return filter_var(config('analytics.finance_tabs_reuse_funding_context', true), FILTER_VALIDATE_BOOL);
    }

    public static function municipalityHealthReuseEnabled(): bool
    {
        if (filter_var(config('analytics.municipality_health_reuse_funding_context', true), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        return self::financeTabsReuseEnabled();
    }

    public static function financeStripContextEnabled(): bool
    {
        return filter_var(config('analytics.finance_tabs_strip_funding_context', true), FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>  $discrepanciesData
     * @return array<string, mixed>|null
     */
    public static function contextFromDiscrepancies(array $discrepanciesData): ?array
    {
        if ($discrepanciesData === []) {
            return null;
        }

        $summary = is_array($discrepanciesData['summary'] ?? null) ? $discrepanciesData['summary'] : [];
        $fundingSnapshot = [
            'summary' => $summary,
            'funding_reference' => is_array($discrepanciesData['funding_reference'] ?? null)
                ? $discrepanciesData['funding_reference']
                : null,
        ];
        $totalMat = $discrepanciesData['total_matriculas'] ?? null;
        $overviewData = [
            'kpis' => ['matriculas' => $totalMat],
            'total_matriculas' => $totalMat,
        ];

        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot($fundingSnapshot, $overviewData);
        if ($ctx === null) {
            return null;
        }

        $ctx['com_problema'] = (int) ($summary['com_problema'] ?? 0);

        return $ctx;
    }

    /**
     * @param  array{summary?: array<string, mixed>, funding_reference?: ?array<string, mixed>}|null  $fundingSnapshot
     * @param  array<string, mixed>  $overviewData
     * @return array<string, mixed>|null
     */
    public static function contextFromFundingSnapshot(?array $fundingSnapshot, array $overviewData = []): ?array
    {
        if ($fundingSnapshot === null) {
            return null;
        }

        if (! is_array($fundingSnapshot['funding_reference'] ?? null)) {
            $fundingSnapshot['funding_reference'] = null;
        }

        $ctx = AnalyticsMunicipalityContext::fromFundingSnapshot($fundingSnapshot, $overviewData);
        if ($ctx === null) {
            return null;
        }

        $summary = is_array($fundingSnapshot['summary'] ?? null) ? $fundingSnapshot['summary'] : [];
        $ctx['com_problema'] = (int) ($summary['com_problema'] ?? 0);

        return $ctx;
    }
}
