<?php

namespace App\Services\Fundeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Descobre recurso CKAN FNDE com VAAF/VAAT por município (datastore activo).
 */
final class FundebCkanVaafDiscovery
{
    /**
     * @return array{resource_id: string, package_title: string, resource_name: string}|null
     */
    public function discover(): ?array
    {
        $cached = Cache::get('fundeb_ckan_vaaf_resource');
        if (is_array($cached) && filled($cached['resource_id'] ?? null)) {
            return $cached;
        }

        $configured = trim((string) config('ieducar.fundeb.open_data.resource_id', ''));
        if ($configured !== '') {
            return [
                'resource_id' => $configured,
                'package_title' => __('Configurado (IEDUCAR_FUNDEB_CKAN_RESOURCE_ID)'),
                'resource_name' => $configured,
            ];
        }

        $base = rtrim((string) config('ieducar.fundeb.open_data.ckan_base_url', 'https://www.fnde.gov.br/dadosabertos'), '/');
        $timeout = max(8, (int) config('ieducar.fundeb.open_data.timeout', 30));
        $queries = array_unique([
            (string) config('ieducar.fundeb.open_data.search_query', 'fundeb vaaf municipio'),
            'vaaf municipio',
            'fundeb municipio',
        ]);

        foreach ($queries as $q) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->get($base.'/api/3/action/package_search', ['q' => $q, 'rows' => 10]);
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
                    if (! is_array($res) || ! ($res['datastore_active'] ?? false)) {
                        continue;
                    }
                    $blob = strtolower((string) (($res['name'] ?? '').' '.($res['description'] ?? '')));
                    if (! str_contains($blob, 'vaaf') && ! str_contains($blob, 'fundeb')) {
                        continue;
                    }
                    $id = (string) ($res['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    $found = [
                        'resource_id' => $id,
                        'package_title' => (string) ($pkg['title'] ?? ''),
                        'resource_name' => (string) ($res['name'] ?? $id),
                    ];
                    Cache::put('fundeb_ckan_vaaf_resource', $found, 86400);

                    return $found;
                }
            }
        }

        return null;
    }

    public function diagnostics(): array
    {
        $found = $this->discover();

        return [
            'configured_id' => trim((string) config('ieducar.fundeb.open_data.resource_id', '')),
            'discovered' => $found,
            'hint' => $found === null
                ? __('Defina IEDUCAR_FUNDEB_CKAN_RESOURCE_ID ou verifique conectividade com dadosabertos FNDE.')
                : __('Recurso CKAN: :name (:id)', [
                    'name' => Str::limit((string) ($found['resource_name'] ?? ''), 60),
                    'id' => $found['resource_id'] ?? '',
                ]),
        ];
    }
}
