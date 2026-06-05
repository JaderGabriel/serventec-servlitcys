<?php

namespace App\Services\Fundeb;

use App\Support\Fundeb\FundebFndeCsvTableReader;
use App\Support\Fundeb\FundebFndePortariaCatalog;
use App\Support\Fundeb\FundebIbgeMatcher;
use Illuminate\Support\Facades\Http;

/**
 * CSV «VAAT, VAAT-MIN e complementação-VAAT por ente federado» (Portaria MEC/MF).
 */
class FundebFndeVaatCsvService
{
    /**
     * @return ?array{ibge: string, vaat: float, vaat_complementacao: ?float, csv_url: string, ano_publicacao: int}
     */
    public function rowForIbge(string $ibge, int $ano): ?array
    {
        $ibge = FundebIbgeMatcher::normalize($ibge) ?? $ibge;

        foreach ($this->candidatePublicationYears($ano) as $pubYear) {
            $index = $this->loadYearIndex($pubYear);
            if (isset($index[$ibge])) {
                $row = $index[$ibge];
                $row['exercicio'] = $pubYear;

                return $row;
            }
        }

        return null;
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
        return FundebFndePortariaCatalog::vaatCsvUrl($publicationYear);
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

        return $this->parseCsvBody($response->body(), $url, $publicationYear);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function parseCsvBody(string $body, string $csvUrl, int $publicationYear): array
    {
        $rows = FundebFndeCsvTableReader::rowsFromBody($body);
        $matchers = [
            'ibge' => ['codigo ibge', 'ibge'],
            'entidade' => ['ente federado', 'entidade'],
            'vaat' => ['vaat com a', 'vaat com complementacao', 'vaat com a complementacao'],
            'vaat_compl' => ['complementacao da uniao-vaat', 'complementacao vaat'],
        ];
        $table = FundebFndeCsvTableReader::locateTable($rows, $matchers);
        if ($table['data_start'] < 0) {
            return [];
        }

        $columns = $table['columns'];
        if (($columns['ibge'] ?? -1) < 0) {
            $columns = FundebFndeCsvTableReader::inferVaatColumns($rows[$table['data_start']]);
        }

        $index = [];
        for ($i = $table['data_start'], $n = count($rows); $i < $n; $i++) {
            $row = $rows[$i];
            if (! FundebFndeCsvTableReader::isDataRow($row)) {
                continue;
            }

            $ibgeIdx = $columns['ibge'] ?? 2;
            $ibge = FundebIbgeMatcher::normalize($row[$ibgeIdx] ?? null);
            if ($ibge === null) {
                continue;
            }

            $vaat = $this->parseMoney($row[$columns['vaat'] ?? 4] ?? null);
            if ($vaat === null || $vaat <= 0) {
                continue;
            }

            $index[$ibge] = [
                'ibge' => $ibge,
                'vaat' => $vaat,
                'vaat_complementacao' => $this->parseMoney($row[$columns['vaat_compl'] ?? 5] ?? null),
                'csv_url' => $csvUrl,
                'ano_publicacao' => $publicationYear,
            ];
        }

        return $index;
    }

    /**
     * @return list<int>
     */
    private function candidatePublicationYears(int $requestedAno): array
    {
        $years = [$requestedAno];
        if (FundebFndePortariaCatalog::vaatCsvUrl($requestedAno - 1) !== null) {
            $years[] = $requestedAno - 1;
        }

        return FundebOpenDataImportService::normalizeYearList($years);
    }

    private function yearCachePath(int $year): string
    {
        return storage_path('app/fundeb/fnde-vaat/'.$year.'.json');
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
