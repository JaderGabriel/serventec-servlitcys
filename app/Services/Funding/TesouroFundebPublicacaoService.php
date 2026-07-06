<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Publicação anual FUNDEB no Tesouro Transparente (planilha .xls em thot-arquivos).
 *
 * @see https://www.tesourotransparente.gov.br/publicacoes/transferencias-ao-fundo-de-manutencao-e-desenvolvimento-da-educacao-basica-fundeb/
 */
final class TesouroFundebPublicacaoService
{
    private const SHEET_TOTAL_MUNICIPIOS = 'M_TOTAL';

    /**
     * @return array{rows: list<array<string, mixed>>, attempt: array<string, mixed>}
     */
    public function fetchForCityYear(City $city, int $year, int $timeout): array
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.tesouro_publicacao', []);
        if (! (bool) ($cfg['enabled'] ?? true)) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('skipped', __('Fonte desactivada na configuração.')),
            ];
        }

        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);
        $uf = strtoupper(trim((string) ($city->uf ?? '')));
        if ($ibge === null || strlen($uf) !== 2) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('failed', __('IBGE ou UF do município inválidos.')),
            ];
        }

        $downloadUrl = $this->resolveDownloadUrl($year, $timeout);
        if ($downloadUrl === null) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('failed', __('Não foi possível resolver o arquivo da publicação FUNDEB :ano.', ['ano' => $year])),
            ];
        }

        $path = $this->ensureWorkbook($downloadUrl, $year, $timeout);
        if ($path === null) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('failed', __('Falha ao descarregar a planilha FUNDEB do Tesouro Transparente.')),
            ];
        }

        try {
            $valor = $this->sumUfAnnualFromSheet($path, self::SHEET_TOTAL_MUNICIPIOS, $uf);
        } catch (\Throwable $e) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('failed', __('Erro ao ler planilha FUNDEB: :msg', ['msg' => $e->getMessage()])),
            ];
        }

        if ($valor === null || $valor <= 0) {
            return [
                'rows' => [],
                'attempt' => $this->attempt('empty', __('Sem total FUNDEB na planilha para UF :uf / :ano.', ['uf' => $uf, 'ano' => $year])),
            ];
        }

        return [
            'rows' => [[
                'ibge_municipio' => $ibge,
                'ano' => $year,
                'fonte' => 'tesouro_publicacao',
                'programa_id' => 'fundeb',
                'programa_label' => 'FUNDEB (publicação STN)',
                'valor' => round($valor, 2),
                'meta' => [
                    'agregacao' => 'uf',
                    'uf' => $uf,
                    'sheet' => self::SHEET_TOTAL_MUNICIPIOS,
                    'download_url' => $downloadUrl,
                    'portal_url' => $this->pageUrl($year),
                ],
            ]],
            'attempt' => $this->attempt('ok', __('Planilha FUNDEB importada (total UF :uf).', ['uf' => $uf]), 1),
        ];
    }

    public function resolveDownloadUrl(int $year, int $timeout): ?string
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.tesouro_publicacao', []);
        $ids = is_array($cfg['arquivo_ids'] ?? null) ? $cfg['arquivo_ids'] : [];
        $id = trim((string) ($ids[(string) $year] ?? $ids[$year] ?? ''));
        if ($id !== '' && ctype_digit($id)) {
            $url = 'https://thot-arquivos.tesouro.gov.br/publicacao/'.$id;

            return SafeOutboundUrl::isAllowedHttpUrl($url) ? $url : null;
        }

        $pageUrl = $this->pageUrl($year);
        if (! SafeOutboundUrl::isAllowedHttpUrl($pageUrl)) {
            return null;
        }

        $cacheKey = 'tesouro_fundeb_publicacao_url:'.$year;

        return Cache::remember($cacheKey, 86400, function () use ($pageUrl, $timeout): ?string {
            try {
                $response = Http::timeout(min($timeout, 25))
                    ->withOptions(['allow_redirects' => true])
                    ->withHeaders(['User-Agent' => 'Servlitcys/1.0 (+https://github.com/servlitcys)'])
                    ->get($pageUrl);
            } catch (\Throwable) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $html = (string) $response->body();
            if (preg_match('#https://thot-arquivos\.tesouro\.gov\.br/publicacao/(\d+)#i', $html, $m)) {
                $url = 'https://thot-arquivos.tesouro.gov.br/publicacao/'.$m[1];

                return SafeOutboundUrl::isAllowedHttpUrl($url) ? $url : null;
            }

            return null;
        });
    }

    private function pageUrl(int $year): string
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.tesouro_publicacao', []);
        $template = (string) ($cfg['page_url_template'] ?? '');
        if ($template === '') {
            $template = 'https://www.tesourotransparente.gov.br/publicacoes/transferencias-ao-fundo-de-manutencao-e-desenvolvimento-da-educacao-basica-fundeb/{ano}/{slug}?ano_selecionado={ano}';
        }

        $slugs = is_array($cfg['page_slugs'] ?? null) ? $cfg['page_slugs'] : [];
        $slug = trim((string) ($slugs[(string) $year] ?? $slugs[$year] ?? '114'));

        return str_replace(['{ano}', '{slug}'], [(string) $year, $slug], $template);
    }

    private function ensureWorkbook(string $url, int $year, int $timeout): ?string
    {
        $dir = storage_path('app/funding');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $path = $dir.'/fundeb_publicacao_'.$year.'.xls';
        $ttl = max(3600, (int) config('ieducar.funding.transfers.extrato_sources.tesouro_publicacao.cache_ttl_seconds', 86400));

        if (is_readable($path) && filemtime($path) >= time() - $ttl) {
            return $path;
        }

        try {
            $response = Http::timeout(max($timeout, 30))
                ->withOptions(['allow_redirects' => true])
                ->withHeaders(['User-Agent' => 'Servlitcys/1.0'])
                ->get($url);
        } catch (\Throwable) {
            return is_readable($path) ? $path : null;
        }

        if (! $response->successful()) {
            return is_readable($path) ? $path : null;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) < 1024) {
            return is_readable($path) ? $path : null;
        }

        file_put_contents($path, $body);

        return is_readable($path) ? $path : null;
    }

    private function sumUfAnnualFromSheet(string $path, string $sheetName, string $uf): ?float
    {
        $reader = IOFactory::createReader(IOFactory::identify($path));
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (! $sheet instanceof Worksheet) {
            return null;
        }

        $headerRow = $this->findHeaderRow($sheet);
        if ($headerRow === null) {
            return null;
        }

        $monthCols = $this->monthColumns($sheet, $headerRow);
        if ($monthCols === []) {
            return null;
        }

        $ufCol = 'B';
        $total = 0.0;
        $found = false;
        $highest = $sheet->getHighestRow();

        for ($r = $headerRow + 1; $r <= $highest; $r++) {
            $rowUf = strtoupper(trim((string) $sheet->getCell($ufCol.$r)->getCalculatedValue()));
            if ($rowUf !== $uf) {
                continue;
            }
            $found = true;
            foreach ($monthCols as $col) {
                $v = $sheet->getCell($col.$r)->getCalculatedValue();
                if (is_numeric($v) && (float) $v > 0) {
                    $total += (float) $v;
                }
            }
            break;
        }

        return $found ? $total : null;
    }

    private function findHeaderRow(Worksheet $sheet): ?int
    {
        $limit = min(20, $sheet->getHighestRow());
        for ($r = 1; $r <= $limit; $r++) {
            $a = strtoupper(trim((string) $sheet->getCell('A'.$r)->getCalculatedValue()));
            $b = strtoupper(trim((string) $sheet->getCell('B'.$r)->getCalculatedValue()));
            if ($a === 'ESTADOS' && $b === 'UF') {
                return $r;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function monthColumns(Worksheet $sheet, int $headerRow): array
    {
        $cols = [];
        $highestCol = $sheet->getHighestColumn();
        $highestIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        for ($i = 3; $i <= $highestIndex; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $label = strtoupper(trim((string) $sheet->getCell($col.$headerRow)->getCalculatedValue()));
            if ($label === '' || $label === 'TOTAL') {
                continue;
            }
            $cols[] = $col;
        }

        return $cols;
    }

    /**
     * @return array{source: string, status: string, message: string, rows: int, url?: string}
     */
    private function attempt(string $status, string $message, int $rows = 0): array
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.tesouro_publicacao', []);

        return [
            'source' => 'tesouro_publicacao',
            'status' => $status,
            'message' => $message,
            'rows' => $rows,
            'url' => (string) ($cfg['portal_url'] ?? 'https://www.tesourotransparente.gov.br/publicacoes/transferencias-ao-fundo-de-manutencao-e-desenvolvimento-da-educacao-basica-fundeb/'),
        ];
    }
}
