<?php

namespace App\Support\Horizonte;

use App\Repositories\FundebMunicipioReferenceRepository;

/**
 * Extrai municípios da lista preliminar/definitiva de inabilitados VAAT (Portaria FUNDEB / Lei 14.113).
 *
 * Formato típico do PDF FNDE: «UF Nome IBGE Inobservância…».
 */
final class FndeVaatInabilitadosParser
{
    /**
     * @return array<string, array{ibge: string, uf: string, name: string, detail: string, items: list<array<string, mixed>>}>
     */
    public static function parse(string $text, int $exerciseYear, string $detailUrl): array
    {
        $normalized = preg_replace("/\r\n?|\n/u", "\n", $text) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';
        $normalized = trim($normalized);

        if ($normalized === '') {
            return [];
        }

        $index = [];
        $pattern = '/([A-Z]{2})\s+(.+?)\s+(\d{7})\s+(Inobservância.+?)(?=(?:\s[A-Z]{2}\s+[A-ZÀ-Úa-zà-ú\'’\-]+(?:\s+[A-ZÀ-Úa-zà-ú\'’\-]+)*\s+\d{7}\s+Inobservância)|$)/u';

        if (! preg_match_all($pattern, $normalized, $matches, PREG_SET_ORDER)) {
            return self::parseFallback($normalized, $exerciseYear, $detailUrl);
        }

        foreach ($matches as $match) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($match[3] ?? '');
            if ($ibge === null) {
                continue;
            }

            $detail = trim((string) ($match[4] ?? ''));
            $index[$ibge] = self::entry(
                $ibge,
                strtoupper(trim((string) ($match[1] ?? ''))),
                trim((string) ($match[2] ?? '')),
                $detail,
                $exerciseYear,
                $detailUrl,
            );
        }

        return $index;
    }

    /**
     * @return array{ibge: string, uf: string, name: string, detail: string, items: list<array<string, mixed>>}
     */
    public static function entryForMunicipality(
        string $ibge,
        string $uf,
        string $name,
        string $detail,
        int $exerciseYear,
        string $detailUrl,
    ): array {
        return self::entry($ibge, $uf, $name, $detail, $exerciseYear, $detailUrl);
    }

    /**
     * @return array<string, array{ibge: string, uf: string, name: string, detail: string, items: list<array<string, mixed>>}>
     */
    private static function parseFallback(string $text, int $exerciseYear, string $detailUrl): array
    {
        $index = [];
        if (! preg_match_all('/(\d{7})\s+(Inobservância.+?)(?=(?:\s\d{7}\s+Inobservância)|$)/u', $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($match[1] ?? '');
            if ($ibge === null) {
                continue;
            }

            $detail = trim((string) ($match[2] ?? ''));
            $index[$ibge] = self::entry($ibge, '', '', $detail, $exerciseYear, $detailUrl);
        }

        return $index;
    }

    /**
     * @return array{ibge: string, uf: string, name: string, detail: string, items: list<array<string, mixed>>}
     */
    private static function entry(
        string $ibge,
        string $uf,
        string $name,
        string $detail,
        int $exerciseYear,
        string $detailUrl,
    ): array {
        $title = __('Inabilitado à complementação VAAT');
        $kinds = [];
        if (stripos($detail, 'SIOPE') !== false) {
            $kinds[] = 'siope';
        }
        if (stripos($detail, 'MSC') !== false || stripos($detail, 'Siconfi') !== false) {
            $kinds[] = 'siconfi';
        }

        return [
            'ibge' => $ibge,
            'uf' => $uf,
            'name' => $name,
            'detail' => $detail,
            'items' => [
                [
                    'kind' => 'vaat_inabilitado',
                    'kinds' => $kinds,
                    'severity' => 'danger',
                    'title' => $title,
                    'detail' => $detail,
                    'exercise_year' => $exerciseYear,
                    'source' => 'fnde_vaat_inabilitados',
                    'detail_url' => $detailUrl,
                ],
            ],
        ];
    }
}
