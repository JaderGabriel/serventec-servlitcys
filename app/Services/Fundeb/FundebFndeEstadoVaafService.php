<?php

namespace App\Services\Fundeb;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * VAAF e receitas por UF/DF — PDF «Valor aluno/ano e receita anual prevista» (Consultas FNDE).
 *
 * @see https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas
 */
class FundebFndeEstadoVaafService
{
    private const CONSULTAS_PATH = 'consultas';

    /** @var list<string> */
    private const UF_CODES = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT',
        'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    /**
     * @return ?array{
     *   uf: string,
     *   vaaf: float,
     *   contribuicao_entes: ?float,
     *   complementacao_vaaf: ?float,
     *   total_receita_vaaf: ?float,
     *   ano_publicacao: int,
     *   pdf_url: string
     * }
     */
    public function rowForUf(string $uf, int $ano): ?array
    {
        $uf = strtoupper(trim($uf));
        if (! in_array($uf, self::UF_CODES, true)) {
            return null;
        }

        foreach ($this->candidatePublicationYears($ano) as $pubYear) {
            $index = $this->loadYearIndex($pubYear);
            if (isset($index[$uf])) {
                $row = $index[$uf];
                $row['ano_publicacao'] = $pubYear;

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

        $url = $this->discoverPdfUrl($publicationYear);
        if ($url === null) {
            return [];
        }

        $text = $this->fetchPdfAsText($url);
        if ($text === null || trim($text) === '') {
            return [];
        }

        $index = $this->parsePdfText($text, $url, $publicationYear);
        if ($index !== []) {
            $dir = dirname($cachePath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($cachePath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $index;
    }

    public function discoverPdfUrl(int $publicationYear): ?string
    {
        $configured = config('ieducar.fundeb.open_data.fnde_estado_vaaf_pdf_urls', []);
        if (is_array($configured)) {
            $direct = $configured[$publicationYear] ?? $configured[(string) $publicationYear] ?? null;
            if (is_string($direct) && $direct !== '') {
                return $direct;
            }
        }

        $slug = 'Valoralunoanoereceitaanualprevista'.$publicationYear.'.pdf';
        $base = 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/';

        foreach ([self::CONSULTAS_PATH, (string) $publicationYear, $publicationYear.'-1'] as $path) {
            $html = $this->fetchHtml($base.$path);
            if ($html === null) {
                continue;
            }
            $pattern = '/href="(https:\/\/www\.gov\.br\/fnde[^"]*'.preg_quote($slug, '/').')"/i';
            if (preg_match($pattern, $html, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            }
        }

        $directUrl = $base.$slug;

        return $this->headExists($directUrl) ? $directUrl : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function parsePdfText(string $text, string $pdfUrl, int $publicationYear): array
    {
        $index = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '' || ! preg_match('/^([A-Z]{2})\s+(.+)$/u', $line, $m)) {
                continue;
            }
            $uf = $m[1];
            if (! in_array($uf, self::UF_CODES, true)) {
                continue;
            }

            $parsed = $this->parseStateLineNumbers($m[2]);
            if ($parsed === null) {
                continue;
            }

            $index[$uf] = array_merge($parsed, [
                'uf' => $uf,
                'ano_publicacao' => $publicationYear,
                'pdf_url' => $pdfUrl,
            ]);
        }

        return $index;
    }

    /**
     * Últimos valores monetários (receitas) e o VAAF consolidado imediatamente anterior.
     *
     * @return ?array{vaaf: float, contribuicao_entes: ?float, complementacao_vaaf: ?float, total_receita_vaaf: ?float}
     */
    private function parseStateLineNumbers(string $tail): ?array
    {
        if (! preg_match_all('/\d[\d.,]*/', $tail, $matches)) {
            return null;
        }

        $values = [];
        foreach ($matches[0] as $raw) {
            $n = $this->parseBrNumber($raw);
            if ($n !== null) {
                $values[] = $n;
            }
        }

        if (count($values) < 4) {
            return null;
        }

        $firstReceitaIdx = null;
        foreach ($values as $i => $v) {
            if ($v >= 1_000_000) {
                $firstReceitaIdx = $i;
                break;
            }
        }

        if ($firstReceitaIdx === null || $firstReceitaIdx < 1) {
            return null;
        }

        $vaaf = (float) $values[$firstReceitaIdx - 1];
        $receitas = array_slice($values, $firstReceitaIdx);
        $contrib = count($receitas) >= 3 ? $receitas[0] : null;
        $compl = count($receitas) >= 2 ? $receitas[count($receitas) - 2] : null;
        $total = (float) end($receitas);

        if ($vaaf < 1_000 || $vaaf > 25_000) {
            return null;
        }

        return [
            'vaaf' => round($vaaf, 2),
            'contribuicao_entes' => $contrib,
            'complementacao_vaaf' => $compl,
            'total_receita_vaaf' => $total,
        ];
    }

    private function parseBrNumber(string $raw): ?float
    {
        $s = trim($raw);
        if ($s === '' || $s === '-') {
            return null;
        }
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
        }
        $s = str_replace(',', '.', $s);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    /**
     * @return list<int>
     */
    private function candidatePublicationYears(int $requestedAno): array
    {
        $years = [$requestedAno, $requestedAno + 1, $requestedAno - 1];

        return FundebOpenDataImportService::normalizeYearList($years);
    }

    private function yearCachePath(int $year): string
    {
        return storage_path('app/fundeb/fnde-estado-vaaf/'.$year.'.json');
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

    private function headExists(string $url): bool
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Servlitcys-FUNDEB/1.0'])
                ->head($url);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function fetchPdfAsText(string $url): ?string
    {
        $timeout = max(20, (int) config('ieducar.fundeb.open_data.timeout', 30));
        $response = Http::timeout($timeout)
            ->withHeaders(['User-Agent' => 'Servlitcys-FUNDEB/1.0'])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $tmpPdf = tempnam(sys_get_temp_dir(), 'fnde_vaaf_');
        if ($tmpPdf === false) {
            return null;
        }

        file_put_contents($tmpPdf, $response->body());

        try {
            return $this->pdftotext($tmpPdf);
        } finally {
            @unlink($tmpPdf);
        }
    }

    private function pdftotext(string $pdfPath): ?string
    {
        if (! $this->pdftotextAvailable()) {
            return null;
        }

        $out = tempnam(sys_get_temp_dir(), 'fnde_vaaf_txt_');
        if ($out === false) {
            return null;
        }

        try {
            $result = Process::run(['pdftotext', '-layout', $pdfPath, $out]);
            if (! $result->successful() || ! is_readable($out)) {
                return null;
            }

            $text = file_get_contents($out);

            return $text !== false && $text !== '' ? $text : null;
        } finally {
            @unlink($out);
        }
    }

    private function pdftotextAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        $which = Process::run(['which', 'pdftotext']);

        $available = $which->successful() && trim($which->output()) !== '';

        return $available;
    }
}
