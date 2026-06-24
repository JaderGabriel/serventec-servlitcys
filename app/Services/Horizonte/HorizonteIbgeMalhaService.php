<?php

namespace App\Services\Horizonte;

use App\Support\Brazil\IbgeUfFromCode;
use App\Support\Http\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Malhas geográficas IBGE (UF e mesorregião) com cache local para o mapa Horizonte.
 */
final class HorizonteIbgeMalhaService
{
    /**
     * @return array<string, mixed>
     */
    public function brazilUfGeoJson(): array
    {
        $url = (string) config('horizonte.geo_malha.brazil_uf_url');

        return $this->loadGeoJson('brazil-uf', $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function stateMesoGeoJson(string $uf): array
    {
        $uf = strtoupper(trim($uf));
        $stateId = config("horizonte.sidra.uf_n3_codes.{$uf}");
        if (! is_string($stateId) || $stateId === '') {
            $stateId = IbgeUfFromCode::ibgePrefixForUf($uf);
        }
        if ($stateId === null || $stateId === '') {
            throw new RuntimeException("UF inválida para malha IBGE: {$uf}");
        }

        $template = (string) config('horizonte.geo_malha.state_meso_url_template');
        $url = str_replace('{id}', $stateId, $template);

        return $this->loadGeoJson('meso-'.$uf, $url);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadGeoJson(string $cacheKey, string $url): array
    {
        if (! SafeOutboundUrl::isAllowedHttpUrl($url)) {
            throw new RuntimeException('URL de malha IBGE não permitida.');
        }

        $dir = trim((string) config('horizonte.geo_malha.cache_dir', 'horizonte/geo'), '/');
        $path = $dir.'/'.$cacheKey.'.json';
        $disk = Storage::disk('local');
        $ttl = max(86400, (int) config('horizonte.geo_malha.cache_seconds', 604800));

        if ($disk->exists($path)) {
            $mtime = $disk->lastModified($path);
            if (time() - $mtime < $ttl) {
                $cached = $this->decodeGeoJson((string) $disk->get($path));
                if ($cached !== null) {
                    return $cached;
                }
            }
        }

        $timeout = max(15, (int) config('horizonte.geo_malha.http_timeout', 60));
        $response = Http::timeout($timeout)
            ->retry(2, 500)
            ->accept('application/vnd.geo+json, application/json')
            ->get($url);

        if (! $response->successful()) {
            if ($disk->exists($path)) {
                $stale = $this->decodeGeoJson((string) $disk->get($path));
                if ($stale !== null) {
                    return $stale;
                }
            }

            throw new RuntimeException('Falha ao obter malha IBGE (HTTP '.$response->status().').');
        }

        $decoded = $this->decodeGeoJson((string) $response->body());
        if ($decoded === null) {
            throw new RuntimeException('Resposta de malha IBGE inválida.');
        }

        $disk->makeDirectory($dir);
        $disk->put($path, json_encode($decoded, JSON_UNESCAPED_UNICODE));

        return $decoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeGeoJson(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['type'])) {
            return null;
        }

        return $decoded;
    }
}
