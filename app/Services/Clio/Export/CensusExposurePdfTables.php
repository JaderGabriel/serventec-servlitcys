<?php

namespace App\Services\Clio\Export;

/**
 * Converte a matriz «Exposição das matrículas — escolas ativas» (análise)
 * em tabelas planas para PDFs (células Urbana / Rural).
 */
final class CensusExposurePdfTables
{
    /**
     * @param  array<string, mixed>  $matrix
     * @return list<array{title: string, headers: list<string>, rows: list<list<string>>}>
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
                    $row[] = $this->fmtInt($u).' / '.$this->fmtInt($r);
                }
                $rows[] = $row;
            }

            $tables[] = [
                'title' => $prefix.' — '.(string) ($block['title'] ?? $blockKey),
                'headers' => $headers,
                'rows' => $rows,
            ];
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
                'title' => $prefix.' — '.(string) ($geral['title'] ?? __('Análise geral')),
                'headers' => $headers,
                'rows' => [$row],
            ];
        }

        return $tables;
    }

    private function fmtInt(mixed $n): string
    {
        return number_format((int) ($n ?? 0), 0, ',', '.');
    }
}
