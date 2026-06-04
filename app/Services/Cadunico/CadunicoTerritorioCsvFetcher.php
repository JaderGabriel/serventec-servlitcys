<?php

namespace App\Services\Cadunico;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Cadunico\CadunicoStoragePaths;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;

/**
 * Descarrega CSV territorial (bairro/setor/CRAS) de URL configurada para importação em produção.
 */
final class CadunicoTerritorioCsvFetcher
{
    /**
     * Garante CSV territorial em storage (descarrega se URL configurada e ficheiro ausente/antigo).
     *
     * @return array{ok: bool, path: ?string, downloaded: bool, message: string, url: ?string}
     */
    public function ensureForCity(City $city, int $ano, ?string $urlOverride = null, bool $force = false): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Município sem IBGE.'),
                'url' => null,
            ];
        }

        $path = $this->expectedPath($ibge, $ano);

        if (! $force && $this->isFresh($path)) {
            return [
                'ok' => true,
                'path' => $path,
                'downloaded' => false,
                'message' => __('CSV territorial já disponível (:file).', ['file' => basename($path)]),
                'url' => null,
            ];
        }

        $url = $this->resolveUrl($city, $ibge, $ano, $urlOverride);
        if ($url === null) {
            return [
                'ok' => is_readable($path),
                'path' => is_readable($path) ? $path : null,
                'downloaded' => false,
                'message' => __('URL não configurada (IEDUCAR_CADUNICO_TERRITORIO_CSV_URL) nem --url=.'),
                'url' => null,
            ];
        }

        $result = $this->downloadTo($url, $path);

        return array_merge($result, ['url' => $url]);
    }

    public function expectedPath(string $ibge, int $ano): string
    {
        $ibge = str_pad(preg_replace('/\D/', '', $ibge) ?? '', 7, '0', STR_PAD_LEFT);
        $root = CadunicoStoragePaths::territorioRoot();
        if (! is_dir($root)) {
            mkdir($root, 0755, true);
        }

        return $root.'/territorio_'.$ibge.'_'.$ano.'.csv';
    }

    private function resolveUrl(City $city, string $ibge, int $ano, ?string $urlOverride): ?string
    {
        $raw = trim((string) ($urlOverride ?? ''));
        if ($raw !== '') {
            return $this->expandUrlTemplate($raw, $city, $ibge, $ano);
        }

        $template = trim((string) config('ieducar.cadunico.territorio.csv_url_template', ''));
        if ($template === '') {
            return null;
        }

        return $this->expandUrlTemplate($template, $city, $ibge, $ano);
    }

    private function expandUrlTemplate(string $template, City $city, string $ibge, int $ano): string
    {
        return str_replace(
            ['{ibge}', '{ano}', '{city_id}', '{city}'],
            [$ibge, (string) $ano, (string) $city->id, rawurlencode((string) $city->name)],
            $template,
        );
    }

    private function isFresh(string $path): bool
    {
        if (! is_readable($path) || filesize($path) < 16) {
            return false;
        }

        $maxAgeDays = max(1, (int) config('ieducar.cadunico.territorio.csv_cache_days', 7));
        $mtime = filemtime($path);

        return $mtime !== false && $mtime >= time() - ($maxAgeDays * 86400);
    }

    /**
     * @return array{ok: bool, path: ?string, downloaded: bool, message: string}
     */
    private function downloadTo(string $url, string $destPath): array
    {
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('URL inválida ou não permitida (política de saída HTTP).'),
            ];
        }

        $timeout = max(10, (int) config('ieducar.cadunico.territorio.csv_http_timeout', 120));
        $dir = dirname($destPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [
                'ok' => false,
                'path' => null,
                'downloaded' => false,
                'message' => __('Não foi possível criar pasta de storage.'),
            ];
        }

        try {
            $response = Http::timeout($timeout)->withOptions(['allow_redirects' => true])->get($url);
            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'path' => is_readable($destPath) ? $destPath : null,
                    'downloaded' => false,
                    'message' => __('Download falhou HTTP :status', ['status' => (string) $response->status()]),
                ];
            }

            $body = $response->body();
            if (strlen($body) < 16) {
                return [
                    'ok' => false,
                    'path' => null,
                    'downloaded' => false,
                    'message' => __('Resposta vazia ou inválida.'),
                ];
            }

            file_put_contents($destPath, $body);

            return [
                'ok' => true,
                'path' => $destPath,
                'downloaded' => true,
                'message' => __('CSV descarregado (:size KB).', [
                    'size' => (string) round(strlen($body) / 1024, 1),
                ]),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'path' => is_readable($destPath) ? $destPath : null,
                'downloaded' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
