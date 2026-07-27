<?php

namespace App\Services\Clio\Export;

/**
 * Converte a matriz «Exposição das matrículas — escolas ativas» (análise)
 * em tabelas planas para PDFs (células Urbana / Rural coloridas).
 */
final class CensusExposurePdfTables
{
    public const KIND_EXPOSURE = 'census_exposure';

    public const KIND_LEGEND = 'census_exposure_legend';

    /**
     * @param  array<string, mixed>  $matrix
     * @return list<array<string, mixed>>
     */
    public function format(array $matrix): array
    {
        if (empty($matrix['available'])) {
            return [];
        }

        $year = (string) ($matrix['year'] ?? '');
        $prefix = __('Exposição das matrículas — escolas ativas (:ano)', ['ano' => $year]);
        $tables = [];

        foreach (['infantil', 'fundamental', 'eja'] as $blockKey) {
            $block = $matrix[$blockKey] ?? null;
            if (! is_array($block) || empty($block['columns']) || empty($block['rows'])) {
                continue;
            }

            $headers = [__('Matrícula')];
            foreach ($block['columns'] as $col) {
                $headers[] = (string) ($col['label'] ?? '');
            }

            $rows = [];
            foreach ($block['rows'] as $modKey => $modLabel) {
                $row = [(string) $modLabel];
                foreach ($block['columns'] as $col) {
                    $vals = $block['values'][$col['key']] ?? [];
                    $u = (int) ($vals['Urbana'][$modKey] ?? 0);
                    $r = (int) ($vals['Rural'][$modKey] ?? 0);
                    $row[] = $this->locationCell($u, $r);
                }
                $rows[] = $row;
            }

            $tables[] = [
                'kind' => self::KIND_EXPOSURE,
                'title' => $prefix.' — '.(string) ($block['title'] ?? $blockKey),
                'headers' => $headers,
                'rows' => $rows,
            ];
        }

        if ($tables !== []) {
            $tables[] = $this->legendTable();
        }

        $geral = is_array($matrix['geral'] ?? null) ? $matrix['geral'] : [];
        if (! empty($geral['columns'])) {
            $headers = [];
            $row = [];
            foreach ($geral['columns'] as $col) {
                $headers[] = (string) ($col['label'] ?? '');
                $row[] = $this->fmtInt($geral['values'][$col['key']] ?? 0);
            }
            $tables[] = [
                'kind' => self::KIND_EXPOSURE,
                'title' => $prefix.' — '.(string) ($geral['title'] ?? __('Análise geral')),
                'headers' => $headers,
                'rows' => [$row],
            ];
        }

        return $tables;
    }

    /**
     * @return array{kind: string, title: null, note: string, items: list<array{tone: string, label: string, sample: string, hint: string}>}
     */
    public function legendTable(): array
    {
        return [
            'kind' => self::KIND_LEGEND,
            'title' => null,
            'note' => __('Em cada célula, o par x / y indica a localização da escola:'),
            'items' => [
                [
                    'tone' => 'urbana',
                    'label' => __('Urbana'),
                    'sample' => 'x',
                    'hint' => __('primeiro número — matrículas em escolas urbanas'),
                ],
                [
                    'tone' => 'rural',
                    'label' => __('Rural'),
                    'sample' => 'y',
                    'hint' => __('segundo número — matrículas em escolas rurais'),
                ],
            ],
        ];
    }

    /**
     * @return array{text: string, parts: list<array{text: string, tone: string|null}>}
     */
    private function locationCell(int $u, int $r): array
    {
        $uText = $this->fmtInt($u);
        $rText = $this->fmtInt($r);

        return [
            'text' => $uText.' / '.$rText,
            'parts' => [
                ['text' => $uText, 'tone' => 'urbana'],
                ['text' => ' / ', 'tone' => 'sep'],
                ['text' => $rText, 'tone' => 'rural'],
            ],
        ];
    }

    private function fmtInt(mixed $n): string
    {
        return number_format((int) ($n ?? 0), 0, ',', '.');
    }
}
