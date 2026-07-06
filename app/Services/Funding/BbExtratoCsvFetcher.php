<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Funding\BbExtratoStoragePaths;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;

/**
 * Garante CSV de extrato BB em storage (descarrega de URL configurada ou usa arquivo já enviado).
 */
final class BbExtratoCsvFetcher
{
    /**
     * @return array{ok: bool, path: ?string, downloaded: bool, message: string, source_url: ?string}
     */
    public function ensureForCityYear(City $city, int $ano): array
    {
        $ibge = MunicipalTransferSnapshotRepository::normalizeIbge((string) $city->ibge_municipio);
        if ($ibge === null) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('IBGE do município inválido.'),
                'source_url' => null,
            ];
        }

        $path = BbExtratoStoragePaths::csvFile($ibge, $ano);
        if ($this->isFresh($path)) {
            return [
                'ok' => true,
                'path' => $path,
                'downloaded' => false,
                'message' => __('Extrato BB já em cache (:file).', ['file' => basename($path)]),
                'source_url' => null,
            ];
        }

        $url = $this->resolveDownloadUrl($city, $ibge, $ano);
        if ($url !== null) {
            return $this->downloadTo($url, $path);
        }

        if (is_readable($path) && filesize($path) >= 32) {
            return [
                'ok' => true,
                'path' => $path,
                'downloaded' => false,
                'message' => __('A usar arquivo manual em storage (:file).', ['file' => basename($path)]),
                'source_url' => null,
            ];
        }

        $hint = BbExtratoStoragePaths::csvFile($ibge, $ano);

        return [
            'ok' => false,
            'path' => null,
            'downloaded' => false,
            'message' => __('Configure IEDUCAR_BB_EXTRATO_URL_TEMPLATE ou IEDUCAR_BB_EXTRATO_EXPORT_URL, ou envie o CSV para :path', [
                'path' => $hint,
            ]),
            'source_url' => null,
        ];
    }

    public function resolveDownloadUrl(City $city, string $ibge, int $ano): ?string
    {
        $cfg = config('ieducar.funding.transfers.extrato_sources.bb_extrato', []);
        $template = trim((string) ($cfg['url_template'] ?? ''));
        if ($template !== '') {
            $url = $this->applyPlaceholders($template, $city, $ibge, $ano);

            return SafeOutboundUrl::isAllowedHttpUrl($url) ? $url : null;
        }

        $exportUrl = trim((string) ($cfg['export_url'] ?? ''));
        if ($exportUrl === '') {
            return null;
        }

        if (str_contains($exportUrl, '{ibge}') || str_contains($exportUrl, '{ano}') || str_contains($exportUrl, '{uf}')) {
            $url = $this->applyPlaceholders($exportUrl, $city, $ibge, $ano);

            return SafeOutboundUrl::isAllowedHttpUrl($url) ? $url : null;
        }

        return SafeOutboundUrl::isAllowedHttpUrl($exportUrl) ? $exportUrl : null;
    }

    private function applyPlaceholders(string $template, City $city, string $ibge, int $ano): string
    {
        return str_replace(
            ['{ibge}', '{ano}', '{uf}'],
            [$ibge, (string) $ano, strtoupper(trim((string) ($city->uf ?? '')))],
            $template,
        );
    }

    private function isFresh(string $path): bool
    {
        if (! is_readable($path) || filesize($path) < 32) {
            return false;
        }

        $maxAgeDays = max(1, (int) config('ieducar.funding.transfers.extrato_sources.bb_extrato.refresh_days', 7));
        $mtime = filemtime($path);

        return $mtime !== false && $mtime >= time() - ($maxAgeDays * 86400);
    }

    /**
     * @return array{ok: bool, path: ?string, downloaded: bool, message: string, source_url: ?string}
     */
    private function downloadTo(string $url, string $destPath): array
    {
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('URL do extrato BB inválida ou não permitida (SSRF).'),
                'source_url' => $url,
            ];
        }

        $timeout = max(10, (int) config('ieducar.funding.transfers.extrato_sources.bb_extrato.http_timeout', 30));
        $dir = dirname($destPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Não foi possível criar pasta de storage para extrato BB.'),
                'source_url' => $url,
            ];
        }

        try {
            $response = Http::timeout($timeout)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders(['User-Agent' => 'Servlitcys/1.0 (+bb-extrato)'])
                ->get($url);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Falha ao descarregar extrato BB: :msg', ['msg' => $e->getMessage()]),
                'source_url' => $url,
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Download extrato BB HTTP :status.', ['status' => (string) $response->status()]),
                'source_url' => $url,
            ];
        }

        $body = $response->body();
        if (strlen($body) < 32) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Resposta do extrato BB vazia ou inválida.'),
                'source_url' => $url,
            ];
        }

        file_put_contents($destPath, $body);

        return [
            'ok' => true,
            'path' => $destPath,
            'downloaded' => true,
            'message' => __('Extrato BB descarregado para :file.', ['file' => basename($destPath)]),
            'source_url' => $url,
        ];
    }
}
