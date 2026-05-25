<?php

namespace App\Support\Product;

use Illuminate\Support\Carbon;

final class ProductVersion
{
    /**
     * Dados para o selo de versão no rodapé e documentação.
     *
     * @return array{
     *   version: string,
     *   release_tag: string,
     *   revision_date: string,
     *   revision_label: string,
     *   in_production: bool,
     *   production_label: string,
     *   display_label: string,
     *   title: string,
     *   tone: string
     * }
     */
    public static function badge(): array
    {
        $product = config('documentation.product', []);
        $version = trim((string) ($product['version'] ?? ''));
        $tag = trim((string) ($product['release_tag'] ?? ''));
        $revision = trim((string) ($product['revision_date'] ?? ''));
        $inProduction = (bool) ($product['in_production'] ?? false);
        $productionLabel = trim((string) ($product['production_label'] ?? __('Em produção')));

        $revisionLabel = self::formatRevisionDate($revision);

        $displayLabel = $version !== ''
            ? ($tag !== '' ? $version.' · '.$tag : 'v'.$version)
            : ($tag !== '' ? $tag : '—');

        $titleParts = array_filter([
            $version !== '' ? __('Versão :v', ['v' => $version]) : null,
            $tag !== '' ? __('Release :t', ['t' => $tag]) : null,
            $revisionLabel !== '' ? __('Lançamento :d', ['d' => $revisionLabel]) : null,
            $inProduction ? $productionLabel : null,
        ]);

        return [
            'version' => $version,
            'release_tag' => $tag,
            'revision_date' => $revision,
            'revision_label' => $revisionLabel,
            'in_production' => $inProduction,
            'production_label' => $productionLabel,
            'display_label' => $displayLabel,
            'title' => implode(' · ', $titleParts),
            'tone' => $inProduction ? 'production' : 'preview',
        ];
    }

    private static function formatRevisionDate(string $iso): string
    {
        if ($iso === '') {
            return '';
        }

        try {
            return Carbon::parse($iso)->locale(app()->getLocale())->isoFormat('D MMM YYYY');
        } catch (\Throwable) {
            return $iso;
        }
    }
}
