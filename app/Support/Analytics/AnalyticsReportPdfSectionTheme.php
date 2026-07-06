<?php

namespace App\Support\Analytics;

/**
 * Cores e metadados visuais das seções ATM no PDF.
 */
final class AnalyticsReportPdfSectionTheme
{
    /**
     * @return array<string, array{label: string, header_bg: string, header_text: string, accent: string, border: string}>
     */
    public static function groups(): array
    {
        return [
            'diagnostico' => [
                'label' => __('Diagnóstico'),
                'header_bg' => '#0f766e',
                'header_text' => '#ffffff',
                'accent' => '#ccfbf1',
                'border' => '#5eead4',
            ],
            'financiamento' => [
                'label' => __('Financiamento'),
                'header_bg' => '#4338ca',
                'header_text' => '#ffffff',
                'accent' => '#e0e7ff',
                'border' => '#a5b4fc',
            ],
            'programas' => [
                'label' => __('Programas'),
                'header_bg' => '#b45309',
                'header_text' => '#ffffff',
                'accent' => '#fef3c7',
                'border' => '#fcd34d',
            ],
            'gestao' => [
                'label' => __('Gestão'),
                'header_bg' => '#334155',
                'header_text' => '#ffffff',
                'accent' => '#f1f5f9',
                'border' => '#94a3b8',
            ],
            'meta' => [
                'label' => __('Publicação'),
                'header_bg' => '#6d28d9',
                'header_text' => '#ffffff',
                'accent' => '#ede9fe',
                'border' => '#c4b5fd',
            ],
        ];
    }

    /**
     * @return array{label: string, header_bg: string, header_text: string, accent: string, border: string}
     */
    public static function forSectionId(string $sectionId): array
    {
        foreach (AnalyticsReportAtmCatalog::sections() as $def) {
            if (($def['id'] ?? '') === $sectionId) {
                $group = (string) ($def['group'] ?? 'gestao');

                return self::groups()[$group] ?? self::groups()['gestao'];
            }
        }

        return self::groups()['gestao'];
    }
}
