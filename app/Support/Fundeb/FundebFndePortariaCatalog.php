<?php

namespace App\Support\Fundeb;

/**
 * Catálogo de portarias FNDE (receita, VAAT, VAAR) por exercício e publicação.
 */
final class FundebFndePortariaCatalog
{
    /**
     * Publicação vigente (maior ordem) para o exercício.
     *
     * @return array<string, mixed>|null
     */
    public static function activePublication(int $exercicio): ?array
    {
        $pubs = self::publicationsForExercicio($exercicio);
        if ($pubs === []) {
            return null;
        }

        usort($pubs, static fn (array $a, array $b): int => (int) ($b['ordem'] ?? 0) <=> (int) ($a['ordem'] ?? 0));

        return $pubs[0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function publicationsForExercicio(int $exercicio): array
    {
        $all = config('ieducar.fundeb.open_data.portarias', []);
        if (! is_array($all)) {
            return [];
        }
        $block = $all[$exercicio] ?? $all[(string) $exercicio] ?? null;
        if (! is_array($block)) {
            return [];
        }
        $pubs = $block['publicacoes'] ?? [];
        if (! is_array($pubs)) {
            return [];
        }

        return array_values(array_filter($pubs, static fn (mixed $p): bool => is_array($p)));
    }

    public static function receitaCsvUrl(int $exercicio): ?string
    {
        $legacy = config('ieducar.fundeb.open_data.fnde_receita_csv_urls', []);
        if (is_array($legacy)) {
            $direct = $legacy[$exercicio] ?? $legacy[(string) $exercicio] ?? null;
            if (is_string($direct) && trim($direct) !== '') {
                return trim($direct);
            }
        }

        $pub = self::activePublication($exercicio);
        $csv = is_array($pub['csv'] ?? null) ? $pub['csv'] : [];
        $url = $csv['receita'] ?? null;

        return is_string($url) && trim($url) !== '' ? trim($url) : null;
    }

    public static function vaatCsvUrl(int $exercicio): ?string
    {
        $legacy = config('ieducar.fundeb.open_data.fnde_vaat_csv_urls', []);
        if (is_array($legacy)) {
            $direct = $legacy[$exercicio] ?? $legacy[(string) $exercicio] ?? null;
            if (is_string($direct) && trim($direct) !== '') {
                return trim($direct);
            }
        }

        $pub = self::activePublication($exercicio);
        $csv = is_array($pub['csv'] ?? null) ? $pub['csv'] : [];
        $url = $csv['vaat'] ?? null;

        return is_string($url) && trim($url) !== '' ? trim($url) : null;
    }

    public static function vaarCsvUrl(int $exercicio): ?string
    {
        $pub = self::activePublication($exercicio);
        $csv = is_array($pub['csv'] ?? null) ? $pub['csv'] : [];
        $url = $csv['vaar'] ?? null;

        return is_string($url) && trim($url) !== '' ? trim($url) : null;
    }

    /**
     * @return array{vaaf_min: ?float, vaat_min: ?float}
     */
    public static function nationalFloors(int $exercicio): array
    {
        $pub = self::activePublication($exercicio);
        $pisos = is_array($pub['pisos_nacionais'] ?? null) ? $pub['pisos_nacionais'] : [];

        return [
            'vaaf_min' => self::positiveFloat($pisos['vaaf_min'] ?? null),
            'vaat_min' => self::positiveFloat($pisos['vaat_min'] ?? null),
        ];
    }

    /**
     * @return array{receita_vinculada: ?float, complementacao_uniao: ?float}
     */
    public static function nationalTotals(int $exercicio): array
    {
        $pub = self::activePublication($exercicio);
        $totais = is_array($pub['totais_nacionais'] ?? null) ? $pub['totais_nacionais'] : [];

        return [
            'receita_vinculada' => self::positiveFloat($totais['receita_vinculada'] ?? null),
            'complementacao_uniao' => self::positiveFloat($totais['complementacao_uniao'] ?? null),
        ];
    }

    /**
     * Metadados da portaria para gravar em fundeb_municipio_references.meta.
     *
     * @return array<string, mixed>
     */
    public static function metaForExercicio(int $exercicio): array
    {
        $pub = self::activePublication($exercicio);
        if ($pub === null) {
            return ['ano_publicacao' => $exercicio];
        }

        return array_filter([
            'ano_publicacao' => $exercicio,
            'exercicio' => (int) ($pub['exercicio'] ?? $exercicio),
            'portaria_numero' => $pub['numero'] ?? null,
            'portaria_data' => $pub['data'] ?? null,
            'portaria_label' => $pub['label'] ?? null,
            'portaria_ordem' => $pub['ordem'] ?? null,
            'portaria_listing_url' => $pub['listing_url'] ?? null,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');
    }

    /**
     * @return list<array{exercicio: int, ordem: int, label: string, numero: ?string, data: ?string, csv: array<string, string>}>
     */
    public static function adminPortariaRows(): array
    {
        $all = config('ieducar.fundeb.open_data.portarias', []);
        if (! is_array($all)) {
            return [];
        }

        $rows = [];
        foreach ($all as $exercicio => $block) {
            if (! is_array($block)) {
                continue;
            }
            foreach ($block['publicacoes'] ?? [] as $pub) {
                if (! is_array($pub)) {
                    continue;
                }
                $csv = is_array($pub['csv'] ?? null) ? $pub['csv'] : [];
                $rows[] = [
                    'exercicio' => (int) $exercicio,
                    'ordem' => (int) ($pub['ordem'] ?? 0),
                    'label' => (string) ($pub['label'] ?? ''),
                    'numero' => isset($pub['numero']) ? (string) $pub['numero'] : null,
                    'data' => isset($pub['data']) ? (string) $pub['data'] : null,
                    'csv' => array_filter(array_map(
                        static fn (mixed $u): ?string => is_string($u) && trim($u) !== '' ? trim($u) : null,
                        $csv,
                    )),
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = $b['exercicio'] <=> $a['exercicio'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b['ordem'] <=> $a['ordem'];
        });

        return $rows;
    }

    /**
     * Alias legado — mesma chave usada em painéis antigos.
     *
     * @return array<int|string, string>
     */
    public static function receitaCsvUrlsByYear(): array
    {
        $out = [];
        $legacy = config('ieducar.fundeb.open_data.fnde_receita_csv_urls', []);
        if (is_array($legacy)) {
            foreach ($legacy as $year => $url) {
                if (is_string($url) && trim($url) !== '') {
                    $out[(int) $year] = trim($url);
                }
            }
        }

        $all = config('ieducar.fundeb.open_data.portarias', []);
        if (is_array($all)) {
            foreach (array_keys($all) as $exercicio) {
                $year = (int) $exercicio;
                $url = self::receitaCsvUrl($year);
                if ($url !== null) {
                    $out[$year] = $url;
                }
            }
        }

        krsort($out);

        return $out;
    }

    private static function positiveFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $f = (float) $value;

        return $f > 0 ? $f : null;
    }
}
