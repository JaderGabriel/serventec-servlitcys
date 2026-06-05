<?php

namespace App\Services\Fundeb;

use App\Support\Fundeb\FundebFndeCsvTableReader;
use App\Support\Fundeb\FundebFndePortariaCatalog;
use App\Support\Fundeb\FundebIbgeMatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lê o CSV oficial «Receita total do Fundeb por ente federado» (gov.br/FNDE).
 */
class FundebFndeReceitaCsvService
{
    /**
     * @return ?array{
     *   ibge: string,
     *   entidade: string,
     *   uf: string,
     *   total_receita: float,
     *   complementacao_vaaf: ?float,
     *   complementacao_vaat: ?float,
     *   complementacao_vaar: ?float,
     *   ano_publicacao: int,
     *   csv_url: string
     * }
     */
    public function rowForIbge(string $ibge, int $ano): ?array
    {
        $ibge = FundebIbgeMatcher::normalize($ibge) ?? $ibge;

        foreach ($this->candidatePublicationYears($ano) as $pubYear) {
            $index = $this->loadYearIndex($pubYear);
            if (! isset($index[$ibge])) {
                continue;
            }
            $row = $index[$ibge];
            $row['ano_publicacao'] = $pubYear;
            $row['exercicio'] = $pubYear;

            return $row;
        }

        return null;
    }

    /**
     * VAAF municipal estimado = receita total prevista FNDE ÷ matrículas activas i-Educar (mesmo ano).
     */
    public function estimateVaafFromReceitaAndMatriculas(float $totalReceita, int $matriculas): ?float
    {
        if ($totalReceita <= 0 || $matriculas <= 0) {
            return null;
        }

        $vaaf = round($totalReceita / $matriculas, 2);
        $min = (float) config('ieducar.fundeb.open_data.vaaf_estimate_min', 2500);
        $max = (float) config('ieducar.fundeb.open_data.vaaf_estimate_max', 18000);

        if ($vaaf < $min || $vaaf > $max) {
            return null;
        }

        return $vaaf;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadYearIndex(int $publicationYear): array
    {
        $cachePath = $this->yearCachePath($publicationYear);
        if (is_readable($cachePath)) {
            $decoded = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $url = $this->discoverCsvUrl($publicationYear);
        if ($url === null) {
            return [];
        }

        $index = $this->parseCsvFromUrl($url, $publicationYear);
        if ($index !== []) {
            $dir = dirname($cachePath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($cachePath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $index;
    }

    public function discoverCsvUrl(int $publicationYear): ?string
    {
        $fromCatalog = FundebFndePortariaCatalog::receitaCsvUrl($publicationYear);
        if ($fromCatalog !== null) {
            return $fromCatalog;
        }

        $base = 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/';

        foreach ($this->pagePathsForYear($publicationYear) as $path) {
            $url = $this->discoverReceitaCsvInListing($base.$path);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function discoverReceitaCsvInListing(string $listingUrl): ?string
    {
        foreach ([0, 20, 40, 60, 80, 100, 120, 140, 160] as $offset) {
            $pageUrl = $offset === 0
                ? $listingUrl
                : $listingUrl.(str_contains($listingUrl, '?') ? '&' : '?').'b_start:int='.$offset;
            $html = $this->fetchHtml($pageUrl);
            if ($html === null) {
                continue;
            }
            $url = $this->extractReceitaCsvUrlFromHtml($html);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function extractReceitaCsvUrlFromHtml(string $html): ?string
    {
        $patterns = [
            '/href="(https:\/\/www\.gov\.br\/fnde[^"]*ReceitatotaldoFundebporentefederado\.csv[^"]*)"/i',
            '/href="(https:\/\/www\.gov\.br\/fnde[^"]*receita-total-do-fundeb-por-ente-federado\.csv[^"]*)"/i',
            '/href="(https:\/\/www\.gov\.br\/fnde[^"]*1-receita-total-do-fundeb-por-ente-federado\.csv[^"]*)"/i',
        ];

        $candidates = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                    $url = preg_replace('#/view$#i', '', $url) ?? $url;
                    $candidates[] = $url;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (string $a, string $b): int {
            $score = static function (string $url): int {
                $s = 0;
                if (str_contains($url, '2-publicacao')) {
                    $s += 100;
                } elseif (str_contains($url, '6-publicacao')) {
                    $s += 80;
                } elseif (str_contains($url, '1-publicacao')) {
                    $s += 40;
                }
                if (str_contains($url, 'receita-total') || str_contains($url, 'Receitatotal')) {
                    $s += 20;
                }

                return $s;
            };

            return $score($b) <=> $score($a);
        });

        return $candidates[0];
    }

    /**
     * @return list<int>
     */
    private function candidatePublicationYears(int $requestedAno): array
    {
        $years = [$requestedAno];
        if (FundebFndePortariaCatalog::receitaCsvUrl($requestedAno - 1) !== null) {
            $years[] = $requestedAno - 1;
        }

        return FundebOpenDataImportService::normalizeYearList($years);
    }

    /**
     * @return list<string>
     */
    private function pagePathsForYear(int $year): array
    {
        $paths = [
            (string) $year,
            $year.'-1',
            $year.'/'.($year + 1),
        ];
        if ($year >= 2025) {
            $paths[] = (string) ($year + 1);
            $paths[] = ($year + 1).'-1';
        }
        if ($year >= 2024) {
            $paths[] = (string) ($year - 1).'-1';
        }

        return $paths;
    }

    private function yearCachePath(int $year): string
    {
        return storage_path('app/fundeb/fnde-receita/'.$year.'.json');
    }

    private function fetchHtml(string $url): ?string
    {
        $timeout = max(10, (int) config('ieducar.fundeb.open_data.timeout', 30));
        $response = Http::timeout($timeout)
            ->withHeaders(['User-Agent' => 'Servlitcys-FUNDEB/1.0'])
            ->withOptions(['allow_redirects' => true])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        return $body !== '' ? $body : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function parseCsvFromUrl(string $url, int $publicationYear): array
    {
        $timeout = max(15, (int) config('ieducar.fundeb.open_data.timeout', 30));
        $response = Http::timeout($timeout)
            ->withHeaders(['User-Agent' => 'Servlitcys-FUNDEB/1.0'])
            ->get($url);

        if (! $response->successful()) {
            return [];
        }

        $body = $response->body();
        $encoding = mb_detect_encoding($body, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'ISO-8859-1';
        if ($encoding !== 'UTF-8') {
            $body = mb_convert_encoding($body, 'UTF-8', $encoding);
        }

        return $this->parseCsvBody($body, $url, $publicationYear);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function parseCsvBody(string $body, string $csvUrl, int $publicationYear): array
    {
        $rows = FundebFndeCsvTableReader::rowsFromBody($body);
        $matchers = [
            'uf' => ['uf'],
            'ibge' => ['codigo ibge', 'ibge'],
            'entidade' => ['entidade', 'ente federado'],
            'vaaf' => ['complementacao vaaf'],
            'vaat_compl' => ['complementacao vaat'],
            'vaar' => ['complementacao vaar'],
            'total' => ['total das receitas', 'total receita', 'receitas previstas'],
        ];
        $table = FundebFndeCsvTableReader::locateTable($rows, $matchers);

        if ($table['data_start'] < 0) {
            return $this->parseCsvBodyLegacy($body, $csvUrl, $publicationYear);
        }

        $columns = $table['columns'];
        if (($columns['ibge'] ?? -1) < 0 || ($columns['total'] ?? -1) < 0) {
            $columns = array_merge(
                FundebFndeCsvTableReader::inferReceitaColumns($rows[$table['data_start']]),
                array_filter($columns, static fn (int $v): bool => $v >= 0),
            );
        }

        $index = [];
        for ($i = $table['data_start'], $n = count($rows); $i < $n; $i++) {
            $row = $rows[$i];
            if (! FundebFndeCsvTableReader::isDataRow($row)) {
                continue;
            }

            $ibge = FundebIbgeMatcher::normalize($row[$columns['ibge'] ?? 1] ?? null);
            if ($ibge === null) {
                continue;
            }

            $totalReceita = $this->parseMoney($row[$columns['total'] ?? -1] ?? null);
            if ($totalReceita === null || $totalReceita <= 0) {
                continue;
            }

            $index[$ibge] = [
                'ibge' => $ibge,
                'uf' => trim((string) ($row[$columns['uf'] ?? 0] ?? '')),
                'entidade' => trim((string) ($row[$columns['entidade'] ?? 2] ?? '')),
                'total_receita' => $totalReceita,
                'complementacao_vaaf' => $this->parseMoney($row[$columns['vaaf'] ?? -1] ?? null),
                'complementacao_vaat' => $this->parseMoney($row[$columns['vaat_compl'] ?? -1] ?? null),
                'complementacao_vaar' => $this->parseMoney($row[$columns['vaar'] ?? -1] ?? null),
                'ano_publicacao' => $publicationYear,
                'csv_url' => $csvUrl,
                'portaria' => FundebFndePortariaCatalog::metaForExercicio($publicationYear),
            ];
        }

        return $index;
    }

    /**
     * Formato antigo (cabeçalho numa linha).
     *
     * @return array<string, array<string, mixed>>
     */
    private function parseCsvBodyLegacy(string $body, string $csvUrl, int $publicationYear): array
    {
        $index = [];
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $body);
        rewind($handle);

        $headerMap = null;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $joined = strtolower(implode(' ', array_map('strval', $row)));
            if ($headerMap === null) {
                if (str_contains($joined, 'ibge') && (str_contains($joined, 'entidade') || str_contains($joined, 'munic'))) {
                    $headerMap = $this->mapHeader($row);
                }

                continue;
            }

            if ($headerMap === []) {
                continue;
            }

            $ibge = FundebIbgeMatcher::normalize($row[$headerMap['ibge']] ?? null);
            if ($ibge === null) {
                continue;
            }

            $totalReceita = $this->parseMoney($row[$headerMap['total']] ?? null);
            if ($totalReceita === null || $totalReceita <= 0) {
                continue;
            }

            $index[$ibge] = [
                'ibge' => $ibge,
                'uf' => trim((string) ($row[$headerMap['uf']] ?? '')),
                'entidade' => trim((string) ($row[$headerMap['entidade']] ?? '')),
                'total_receita' => $totalReceita,
                'complementacao_vaaf' => $this->parseMoney($row[$headerMap['vaaf']] ?? null),
                'complementacao_vaat' => $this->parseMoney($row[$headerMap['vaat']] ?? null),
                'complementacao_vaar' => $this->parseMoney($row[$headerMap['vaar']] ?? null),
                'ano_publicacao' => $publicationYear,
                'csv_url' => $csvUrl,
                'portaria' => FundebFndePortariaCatalog::metaForExercicio($publicationYear),
            ];
        }

        fclose($handle);

        return $index;
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array{uf: int, ibge: int, entidade: int, vaaf: int, vaat: int, vaar: int, total: int}
     */
    private function mapHeader(array $headerRow): array
    {
        $map = ['uf' => 0, 'ibge' => 1, 'entidade' => 2, 'vaaf' => -1, 'vaat' => -1, 'vaar' => -1, 'total' => -1];

        foreach ($headerRow as $i => $cell) {
            $h = Str::lower(Str::ascii((string) $cell));
            if ($h === 'uf') {
                $map['uf'] = $i;
            } elseif (str_contains($h, 'ibge')) {
                $map['ibge'] = $i;
            } elseif (str_contains($h, 'entidade')) {
                $map['entidade'] = $i;
            } elseif (str_contains($h, 'complementacao vaaf') || $h === 'complementacao vaaf') {
                $map['vaaf'] = $i;
            } elseif (str_contains($h, 'complementacao vaat') || $h === 'complementacao vaat') {
                $map['vaat'] = $i;
            } elseif (str_contains($h, 'complementacao vaar') || $h === 'complementacao vaar') {
                $map['vaar'] = $i;
            } elseif (str_contains($h, 'total') && str_contains($h, 'receita')) {
                $map['total'] = $i;
            }
        }

        if ($map['total'] < 0) {
            $map['total'] = max(array_keys($headerRow));
        }

        return $map;
    }

    private function parseMoney(mixed $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $s = trim((string) $raw);
        if ($s === '' || $s === '-' || str_contains(strtolower($s), ' - ')) {
            return null;
        }

        $s = str_replace(['R$', ' '], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
        }
        $s = str_replace(',', '.', $s);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
