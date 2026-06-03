<?php

namespace App\Services\Cadunico;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Descobre recurso CKAN (dados.gov.br / FNDE) com agregados municipais CadÚnico.
 */
final class CadunicoCkanDiscovery
{
    /**
     * @return array{resource_id: string, base_url: string, package_title: string, resource_name: string}|null
     */
    public function discover(): ?array
    {
        $cached = Cache::get('cadunico_ckan_resource');
        if (is_array($cached) && filled($cached['resource_id'] ?? null)) {
            return $cached;
        }

        $configured = trim((string) config('ieducar.cadunico.open_data.resource_id', ''));
        if ($configured !== '') {
            $base = rtrim((string) config('ieducar.cadunico.open_data.ckan_base_url', 'https://dados.gov.br'), '/');

            return [
                'resource_id' => $configured,
                'base_url' => $base,
                'package_title' => __('Configurado (IEDUCAR_CADUNICO_CKAN_RESOURCE_ID)'),
                'resource_name' => $configured,
            ];
        }

        foreach ($this->ckanBases() as $base) {
            $found = $this->discoverOnBase($base);
            if ($found !== null) {
                Cache::put('cadunico_ckan_resource', $found, 86400);

                return $found;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function ckanBases(): array
    {
        $bases = config('ieducar.cadunico.open_data.ckan_bases', [
            'https://dados.gov.br',
            'https://catalogo.dados.gov.br',
        ]);

        if (! is_array($bases)) {
            return ['https://dados.gov.br'];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($u) => rtrim((string) $u, '/'),
            $bases,
        ))));
    }

    /**
     * @return array{resource_id: string, base_url: string, package_title: string, resource_name: string}|null
     */
    private function discoverOnBase(string $base): ?array
    {
        $timeout = max(8, (int) config('ieducar.cadunico.open_data.http_timeout', 30));
        $queries = array_unique([
            (string) config('ieducar.cadunico.open_data.search_query', 'cadastro unico municipio ibge'),
            'cadastro unico municipio',
            'cadunico municipio',
            'cecad municipio',
        ]);

        foreach ($queries as $q) {
            if (trim($q) === '') {
                continue;
            }
            try {
                $response = Http::timeout($timeout)->acceptJson()->get($base.'/api/3/action/package_search', [
                    'q' => $q,
                    'rows' => 8,
                ]);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful() || ! ($response->json('success') ?? false)) {
                continue;
            }

            foreach ($response->json('result.results') ?? [] as $pkg) {
                if (! is_array($pkg)) {
                    continue;
                }
                foreach ($pkg['resources'] ?? [] as $res) {
                    if (! is_array($res)) {
                        continue;
                    }
                    if (! ($res['datastore_active'] ?? false)) {
                        continue;
                    }
                    $blob = mb_strtolower((string) (($res['name'] ?? '').' '.($res['description'] ?? '').' '.($pkg['title'] ?? '')));
                    if (! $this->looksLikeCadunicoResource($blob)) {
                        continue;
                    }
                    $id = (string) ($res['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }

                    return [
                        'resource_id' => $id,
                        'base_url' => $base,
                        'package_title' => (string) ($pkg['title'] ?? ''),
                        'resource_name' => (string) ($res['name'] ?? $id),
                    ];
                }
            }
        }

        return null;
    }

    private function looksLikeCadunicoResource(string $blob): bool
    {
        $needles = ['cadastro unico', 'cadastro único', 'cadunico', 'cadúnico', 'cecad'];
        $hits = 0;
        foreach ($needles as $n) {
            if (str_contains($blob, $n)) {
                $hits++;
            }
        }

        return $hits > 0 && (str_contains($blob, 'municip') || str_contains($blob, 'ibge'));
    }
}
