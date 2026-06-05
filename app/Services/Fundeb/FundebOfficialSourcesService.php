<?php

namespace App\Services\Fundeb;

use App\Models\FundebMunicipioReference;
use App\Support\Fundeb\FundebFndePortariaCatalog;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;

/**
 * Portarias, fontes oficiais e verificação de actualizações FNDE/Tesouro.
 */
final class FundebOfficialSourcesService
{
    public function __construct(
        private FundebOpenDataImportService $import,
    ) {}

    /**
     * @return array{
     *   diagnostics: array<string, mixed>,
     *   portarias: list<array<string, mixed>>,
     *   fontes: list<array<string, mixed>>,
     *   updates: list<array<string, mixed>>,
     *   last_import_max: ?string
     * }
     */
    public function adminPanel(): array
    {
        $diag = $this->import->apiDiagnostics();
        $portarias = $this->portariasFromConfig();
        $updates = $this->probeUpdates();

        $lastImport = FundebMunicipioReference::query()->max('imported_at');

        return [
            'diagnostics' => $diag,
            'portarias' => $portarias,
            'fontes' => $this->fontesCatalog(),
            'updates' => $updates,
            'last_import_max' => $lastImport !== null ? (string) $lastImport : null,
        ];
    }

    /**
     * @return list<array{ano: int, label: string, url: string, configured: bool, numero?: ?string, data?: ?string, tipo?: string}>
     */
    private function portariasFromConfig(): array
    {
        $out = [];
        foreach (FundebFndePortariaCatalog::adminPortariaRows() as $row) {
            $receita = $row['csv']['receita'] ?? null;
            if (! is_string($receita) || $receita === '') {
                continue;
            }
            $out[] = [
                'ano' => (int) $row['exercicio'],
                'label' => $row['label'] !== ''
                    ? $row['label']
                    : __('Receita total FUNDEB por ente (:ano)', ['ano' => (string) $row['exercicio']]),
                'url' => $receita,
                'configured' => true,
                'numero' => $row['numero'] ?? null,
                'data' => $row['data'] ?? null,
                'tipo' => 'portaria_receita',
            ];
        }

        if ($out !== []) {
            return $out;
        }

        foreach (FundebFndePortariaCatalog::receitaCsvUrlsByYear() as $ano => $url) {
            $out[] = [
                'ano' => (int) $ano,
                'label' => __('Receita total FUNDEB por ente (:ano)', ['ano' => (string) $ano]),
                'url' => $url,
                'configured' => true,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function fontesCatalog(): array
    {
        return [
            ['key' => 'ckan', 'label' => __('CKAN FNDE'), 'url' => (string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos')],
            ['key' => 'consultas', 'label' => __('Consultas FUNDEB (gov.br)'), 'url' => 'https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas'],
            ['key' => 'tesouro', 'label' => __('Tesouro Transparente'), 'url' => 'https://www.tesourotransparente.gov.br/'],
            ['key' => 'lei', 'label' => __('Lei 14.113/2020'), 'url' => 'http://www.planalto.gov.br/ccivil_3/_ato2019-2022/2020/lei/L14113.htm'],
        ];
    }

    /**
     * @return list<array{source: string, status: string, message: string, action?: string}>
     */
    public function probeUpdates(): array
    {
        $items = [];
        $diag = $this->import->apiDiagnostics();
        $base = (string) ($diag['ckan_base_url'] ?? '');
        $reachable = (bool) ($diag['ckan_reachable'] ?? false);

        $items[] = [
            'source' => 'CKAN FNDE',
            'status' => $reachable ? 'ok' : 'warning',
            'message' => $reachable
                ? __('API acessível. Recurso efectivo: :id', ['id' => ($diag['effective_resource_id'] ?? '') ?: __('descoberta automática')])
                : __('CKAN inacessível — verifique rede ou configure IEDUCAR_FUNDEB_CKAN_RESOURCE_ID.'),
            'action' => $reachable ? null : __('Testar importação FUNDEB na fila'),
        ];

        foreach ($this->activePortariasForProbe() as $portaria) {
            $url = (string) ($portaria['url'] ?? '');
            $probe = $this->probeCsvUrl($url);
            $items[] = [
                'source' => (string) ($portaria['label'] ?? __('Portaria :ano', ['ano' => (string) $portaria['ano']])),
                'status' => $probe['ok'] ? 'ok' : 'warning',
                'message' => $probe['message'],
                'action' => $probe['ok'] ? __('CSV publicado — importar na rotina FUNDEB') : null,
            ];
        }

        $lastImport = FundebMunicipioReference::query()->max('imported_at');
        $items[] = [
            'source' => __('Base local'),
            'status' => $lastImport !== null ? 'ok' : 'neutral',
            'message' => $lastImport !== null
                ? __('Última importação gravada: :dt', ['dt' => (string) $lastImport])
                : __('Nenhum VAAF importado ainda em fundeb_municipio_references.'),
            'action' => __('Sincronizar todos os municípios'),
        ];

        return $items;
    }

    /**
     * Uma portaria por exercício (publicação vigente = maior «ordem» no catálogo).
     *
     * @return list<array{ano: int, label: string, url: string}>
     */
    private function activePortariasForProbe(): array
    {
        $portarias = config('ieducar.fundeb.open_data.portarias', []);
        if (! is_array($portarias)) {
            return [];
        }

        $out = [];
        foreach (array_keys($portarias) as $exercicio) {
            $year = (int) $exercicio;
            $pub = FundebFndePortariaCatalog::activePublication($year);
            if ($pub === null) {
                continue;
            }
            $csv = is_array($pub['csv'] ?? null) ? $pub['csv'] : [];
            $receita = $csv['receita'] ?? null;
            if (! is_string($receita) || trim($receita) === '') {
                continue;
            }
            $label = trim((string) ($pub['label'] ?? ''));
            $out[] = [
                'ano' => $year,
                'label' => $label !== '' ? $label : __('Portaria FUNDEB :ano', ['ano' => (string) $year]),
                'url' => trim($receita),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['ano'] <=> $a['ano']);

        return $out;
    }

    /**
     * gov.br/FNDE bloqueia HEAD (403); a importação usa GET com User-Agent.
     *
     * @return array{ok: bool, message: string}
     */
    private function probeCsvUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '' || ! str_starts_with($url, 'http')) {
            return ['ok' => false, 'message' => __('URL não configurada.')];
        }

        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return ['ok' => false, 'message' => __('URL não permitida para verificação de saída.')];
        }

        try {
            $timeout = max(8, (int) config('ieducar.fundeb.open_data.timeout', 30));
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'Servlitcys-FUNDEB/1.0',
                    'Range' => 'bytes=0-1023',
                    'Accept' => 'text/csv,text/plain,*/*',
                ])
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            $status = $response->status();
            if (in_array($status, [200, 206], true)) {
                $sample = strlen($response->body());
                $looksCsv = $sample > 0 && str_contains(mb_strtolower(substr($response->body(), 0, 200)), 'ibge');

                return [
                    'ok' => true,
                    'message' => $looksCsv
                        ? __('CSV acessível (HTTP :status) — amostra com colunas esperadas.', ['status' => (string) $status])
                        : __('CSV acessível (HTTP :status, :bytes bytes).', [
                            'status' => (string) $status,
                            'bytes' => number_format($sample),
                        ]),
                ];
            }

            if ($status === 403) {
                return [
                    'ok' => false,
                    'message' => __('HTTP 403 — o servidor pode bloquear o IP da aplicação; teste «fundeb:import-api» na fila ou abra o link no browser.'),
                ];
            }

            return ['ok' => false, 'message' => __('HTTP :status ao verificar CSV.', ['status' => (string) $status])];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
