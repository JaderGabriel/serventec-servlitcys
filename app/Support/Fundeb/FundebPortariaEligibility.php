<?php

namespace App\Support\Fundeb;

use App\Models\FundebMunicipioReference;

/**
 * Elegibilidade VAAF / VAAT / VAAR conforme complementações da portaria FNDE vigente.
 */
final class FundebPortariaEligibility
{
    /**
     * @return array{
     *   vaaf: bool,
     *   vaat: bool,
     *   vaar: bool,
     *   labels: array{vaaf: string, vaat: string, vaar: string}
     * }
     */
    public static function badges(FundebMunicipioReference|array|null $reference): array
    {
        $complVaaf = self::money($reference, 'complementacao_vaaf');
        $complVaat = self::money($reference, 'complementacao_vaat');
        $complVaar = self::money($reference, 'complementacao_vaar');

        $vaaf = $complVaaf !== null && $complVaaf > 0;
        $vaat = $complVaat !== null && $complVaat > 0;
        $vaar = $complVaar !== null && $complVaar > 0;

        return [
            'vaaf' => $vaaf,
            'vaat' => $vaat,
            'vaar' => $vaar,
            'labels' => [
                'vaaf' => $vaaf ? __('Compl. VAAF') : __('Sem compl. VAAF'),
                'vaat' => $vaat ? __('Compl. VAAT') : __('Sem compl. VAAT'),
                'vaar' => $vaar ? __('Compl. VAAR') : __('Sem compl. VAAR'),
            ],
        ];
    }

    /**
     * @param  FundebMunicipioReference|array<string, mixed>|null  $reference
     */
    private static function money(FundebMunicipioReference|array|null $reference, string $field): ?float
    {
        if ($reference === null) {
            return null;
        }

        $raw = $reference instanceof FundebMunicipioReference
            ? $reference->{$field}
            : ($reference[$field] ?? null);

        return is_numeric($raw) ? (float) $raw : null;
    }
}
