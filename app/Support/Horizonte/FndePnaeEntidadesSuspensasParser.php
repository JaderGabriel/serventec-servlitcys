<?php

namespace App\Support\Horizonte;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Brazil\MunicipalityNomeUfKey;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Relação oficial FNDE de Entidades Executoras (EEx) com repasse do PNAE suspenso.
 *
 * A planilha não traz código IBGE — apenas «UF» e «ENTIDADE» (ex.: "PREF MUN DE CUTIAS").
 * O município é resolvido por nome + UF contra o catálogo IBGE. Secretarias estaduais
 * (sem município) são ignoradas.
 */
final class FndePnaeEntidadesSuspensasParser
{
    private const MUNICIPAL_PREFIXES = [
        'prefeitura municipal de ',
        'pref municipal de ',
        'pref mun de ',
        'p m de ',
        'pm de ',
        'municipio de ',
    ];

    /**
     * @return array{
     *     entries: array<string, array{ibge: string, uf: string, name: string, detail: string, items: list<array<string, mixed>>}>,
     *     matched: int,
     *     unmatched: int
     * }
     */
    public static function parse(string $binary, int $defaultYear, string $detailUrl): array
    {
        $rows = self::readRows($binary);
        if ($rows === []) {
            return ['entries' => [], 'matched' => 0, 'unmatched' => 0];
        }

        $header = self::locateHeader($rows);
        if ($header === null) {
            return ['entries' => [], 'matched' => 0, 'unmatched' => 0];
        }

        [$dataStart, $cols] = $header;
        $nomeUfIndex = self::nomeUfIndex();

        $entries = [];
        $matched = 0;
        $unmatched = 0;

        for ($i = $dataStart, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i];
            $uf = strtoupper(trim((string) ($row[$cols['uf']] ?? '')));
            $entidade = trim((string) ($row[$cols['entidade']] ?? ''));
            if (! preg_match('/^[A-Z]{2}$/', $uf) || $entidade === '') {
                continue;
            }

            $municipio = self::municipioFromEntidade($entidade);
            if ($municipio === null) {
                continue;
            }

            $key = MunicipalityNomeUfKey::key($municipio, $uf);
            $ibge = $key !== '' ? ($nomeUfIndex[$key] ?? null) : null;
            if ($ibge === null) {
                $unmatched++;

                continue;
            }

            $motivo = trim((string) ($row[$cols['motivo']] ?? ''));
            $inicio = self::formatDate($row[$cols['inicio']] ?? null);
            $ano = (int) ($cols['ano'] >= 0 ? (int) ($row[$cols['ano']] ?? 0) : 0);
            if ($ano < 2007) {
                $ano = $defaultYear;
            }

            $matched++;
            $entries[$ibge] = self::entry($ibge, $uf, $municipio, $motivo, $inicio, $ano, $detailUrl);
        }

        return ['entries' => $entries, 'matched' => $matched, 'unmatched' => $unmatched];
    }

    /**
     * @return list<list<string>>
     */
    private static function readRows(string $binary): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pnae_');
        if ($tmp === false) {
            return [];
        }

        try {
            file_put_contents($tmp, $binary);
            $reader = IOFactory::createReaderForFile($tmp);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmp);

            return $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable) {
            return [];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{0: int, 1: array{uf: int, entidade: int, ano: int, inicio: int, motivo: int}}|null
     */
    private static function locateHeader(array $rows): ?array
    {
        foreach ($rows as $i => $row) {
            $labels = array_map(static fn ($c): string => Str::lower(Str::ascii(trim((string) $c))), $row);
            $ufCol = self::columnIndex($labels, ['uf']);
            $entidadeCol = self::columnIndex($labels, ['entidade']);
            if ($ufCol < 0 || $entidadeCol < 0) {
                continue;
            }

            return [
                $i + 1,
                [
                    'uf' => $ufCol,
                    'entidade' => $entidadeCol,
                    'ano' => self::columnIndex($labels, ['ano']),
                    'inicio' => self::columnIndex($labels, ['data inicio', 'inicio']),
                    'motivo' => self::columnIndex($labels, ['motivo']),
                ],
            ];
        }

        return null;
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $needles
     */
    private static function columnIndex(array $labels, array $needles): int
    {
        foreach ($labels as $idx => $label) {
            foreach ($needles as $needle) {
                if ($label !== '' && str_contains($label, $needle)) {
                    return (int) $idx;
                }
            }
        }

        return -1;
    }

    private static function municipioFromEntidade(string $entidade): ?string
    {
        $norm = Str::lower(Str::ascii($entidade));
        $norm = trim(preg_replace('/\s+/', ' ', $norm) ?? $norm);

        foreach (self::MUNICIPAL_PREFIXES as $prefix) {
            if (str_starts_with($norm, $prefix)) {
                $name = trim(substr($entidade, strlen($prefix)));

                return $name !== '' ? $name : null;
            }
        }

        return null;
    }

    private static function formatDate(mixed $raw): string
    {
        if (is_numeric($raw) && (float) $raw > 0) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('d/m/Y');
            } catch (\Throwable) {
                return '';
            }
        }

        return trim((string) $raw);
    }

    /**
     * @return array<string, string>
     */
    private static function nomeUfIndex(): array
    {
        try {
            return app(IbgeMunicipalityCatalog::class)->nationalNomeUfToIbgeIndex();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{ibge: string, uf: string, name: string, detail: string, items: list<array<string, mixed>>}
     */
    private static function entry(
        string $ibge,
        string $uf,
        string $name,
        string $motivo,
        string $inicio,
        int $exerciseYear,
        string $detailUrl,
    ): array {
        $motivoLabel = $motivo !== '' ? Str::title(Str::lower($motivo)) : __('Repasse suspenso');
        $detail = $inicio !== ''
            ? __(':motivo — suspenso desde :data', ['motivo' => $motivoLabel, 'data' => $inicio])
            : $motivoLabel;

        return [
            'ibge' => $ibge,
            'uf' => $uf,
            'name' => $name,
            'detail' => $detail,
            'items' => [
                [
                    'kind' => 'pnae_suspenso',
                    'severity' => 'danger',
                    'title' => __('Repasse PNAE suspenso'),
                    'detail' => $detail,
                    'exercise_year' => $exerciseYear,
                    'source' => 'pnae_entidades_suspensas',
                    'detail_url' => $detailUrl,
                ],
            ],
        ];
    }
}
