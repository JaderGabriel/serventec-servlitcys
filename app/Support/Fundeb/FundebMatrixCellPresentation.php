<?php

namespace App\Support\Fundeb;

/**
 * Apresentação de células VAAF/VAAT (cores, rótulos) na matriz admin FUNDEB.
 *
 * @see \App\Support\Ieducar\FundebReferenceDisplay Comparação VAAF no painel municipal (analytics).
 */
final class FundebMatrixCellPresentation
{
    public const KIND_EMPTY = 'empty';

    public const KIND_NATIONAL = 'national';

    /** Valor municipal oficial / importado (CKAN, CSV FNDE com VAAF explícito, etc.). */
    public const KIND_CONSOLIDATED = 'consolidated';

    /** Estimativa (ex.: receita FNDE ÷ matrículas i-Educar). */
    public const KIND_PREVIEW = 'preview';

    /**
     * @return array{
     *     kind: string,
     *     label: string,
     *     short: string,
     *     title: string,
     *     cell_class: string,
     *     swatch_class: string,
     *     icon: string
     * }
     */
    public static function forFonte(?string $fonte, bool $hasReference = true): array
    {
        if (! $hasReference) {
            return self::meta(self::KIND_EMPTY);
        }

        $fonte = trim((string) $fonte);

        if (FundebReferenceSource::isPlaceholder($fonte)) {
            return self::meta(self::KIND_NATIONAL);
        }

        if ($fonte === FundebReferenceSource::FONTE_FNDE_RECEITA_IEDUCAR) {
            return self::meta(self::KIND_PREVIEW);
        }

        if ($fonte !== '' && FundebReferenceSource::isMunicipalOfficial($fonte)) {
            return self::meta(self::KIND_CONSOLIDATED);
        }

        return self::meta(self::KIND_EMPTY);
    }

    /**
     * @return list<array{kind: string, label: string, short: string, swatch_class: string, icon: string}>
     */
    public static function legendItems(): array
    {
        return [
            self::meta(self::KIND_CONSOLIDATED),
            self::meta(self::KIND_PREVIEW),
            self::meta(self::KIND_NATIONAL),
            self::meta(self::KIND_EMPTY),
        ];
    }

    /**
     * @return array{kind: string, label: string, short: string, title: string, cell_class: string, swatch_class: string, icon: string}
     */
    private static function meta(string $kind): array
    {
        return match ($kind) {
            self::KIND_NATIONAL => [
                'kind' => self::KIND_NATIONAL,
                'label' => __('fundeb.matrix.national_label'),
                'short' => __('fundeb.matrix.national_short'),
                'title' => __('fundeb.matrix.national_title'),
                'cell_class' => 'bg-amber-50 dark:bg-amber-950/30 text-amber-950 dark:text-amber-100',
                'swatch_class' => 'bg-amber-400 ring-amber-600/40',
                'icon' => '○',
            ],
            self::KIND_CONSOLIDATED => [
                'kind' => self::KIND_CONSOLIDATED,
                'label' => __('fundeb.matrix.consolidated_label'),
                'short' => __('fundeb.matrix.consolidated_short'),
                'title' => __('fundeb.matrix.consolidated_title'),
                'cell_class' => 'bg-emerald-50 dark:bg-emerald-950/25 text-emerald-950 dark:text-emerald-100',
                'swatch_class' => 'bg-emerald-500 ring-emerald-600/40',
                'icon' => '●',
            ],
            self::KIND_PREVIEW => [
                'kind' => self::KIND_PREVIEW,
                'label' => __('fundeb.matrix.preview_label'),
                'short' => __('fundeb.matrix.preview_short'),
                'title' => __('fundeb.matrix.preview_title'),
                'cell_class' => 'bg-indigo-50 dark:bg-indigo-950/25 text-indigo-950 dark:text-indigo-100',
                'swatch_class' => 'bg-indigo-400 ring-indigo-500/40 ring-2 ring-dashed',
                'icon' => '◆',
            ],
            default => [
                'kind' => self::KIND_EMPTY,
                'label' => __('fundeb.matrix.empty_label'),
                'short' => '—',
                'title' => __('fundeb.matrix.empty_title'),
                'cell_class' => 'text-slate-400 dark:text-slate-500',
                'swatch_class' => 'bg-slate-200 dark:bg-slate-600',
                'icon' => '·',
            ],
        };
    }
}
