<?php

namespace App\Support\Dashboard;

/**
 * Numeração dinâmica dos passos do fluxo de consultoria (omite secções vazias).
 */
final class ConsultoriaFlow
{
    /**
     * @param  list<array{label: string, anchor: string, visible?: bool}>  $sections
     * @return list<array{num: string, label: string, anchor: string}>
     */
    public static function numberedSteps(array $sections): array
    {
        $steps = [];
        $n = 1;
        foreach ($sections as $section) {
            if (($section['visible'] ?? true) === false) {
                continue;
            }
            $steps[] = [
                'num' => (string) $n,
                'label' => $section['label'],
                'anchor' => $section['anchor'],
            ];
            $n++;
        }

        return $steps;
    }

    /**
     * @param  list<array{num: string, label: string, anchor: string}>  $steps
     */
    public static function stepNum(array $steps, string $anchor): ?string
    {
        foreach ($steps as $step) {
            if (($step['anchor'] ?? '') === $anchor) {
                return $step['num'];
            }
        }

        return null;
    }

    /**
     * @param  list<array{num: string, label: string, anchor: string}>  $steps
     * @return array<string, string>
     */
    public static function stepMap(array $steps): array
    {
        $map = [];
        foreach ($steps as $step) {
            $anchor = (string) ($step['anchor'] ?? '');
            if ($anchor !== '') {
                $map[$anchor] = (string) ($step['num'] ?? '');
            }
        }

        return $map;
    }
}
