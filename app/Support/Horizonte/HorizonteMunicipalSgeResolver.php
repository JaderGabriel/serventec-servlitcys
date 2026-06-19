<?php

namespace App\Support\Horizonte;

/**
 * Resolve sistema de gestão educacional (SGE) por município IBGE para o mapa Horizonte.
 */
final class HorizonteMunicipalSgeResolver
{
    /**
     * @param  ?array{
     *     id?: int,
     *     consultoria_active?: bool,
     *     has_data_setup?: bool,
     *     is_active?: bool,
     *     ieducar_app_url?: ?string,
     *     db_driver?: ?string
     * }  $city
     * @param  ?array{system?: string, vendor?: string, notes?: string, source?: string}  $registry
     * @return array{
     *     found: bool,
     *     status: string,
     *     status_label: string,
     *     system: ?string,
     *     system_label: string,
     *     detail: string,
     *     app_url: ?string,
     *     source: string
     * }
     */
    public function resolve(string $ibge, ?array $city, ?array $registry = null): array
    {
        if ($city !== null && ($city['consultoria_active'] ?? false)) {
            $driver = strtoupper(trim((string) ($city['db_driver'] ?? 'pgsql')));

            return [
                'found' => true,
                'status' => 'consultoria_active',
                'status_label' => __('Consultoria activa'),
                'system' => 'i-Educar',
                'system_label' => __('i-Educar (Portabilis)'),
                'detail' => __('Base :driver ligada no ServLITCYS — painel analítico disponível.', [
                    'driver' => $driver === 'MYSQL' ? 'MySQL' : 'PostgreSQL',
                ]),
                'app_url' => filled($city['ieducar_app_url'] ?? null) ? (string) $city['ieducar_app_url'] : null,
                'source' => 'servlitcys_catalog',
            ];
        }

        if ($city !== null) {
            $hasSetup = (bool) ($city['has_data_setup'] ?? false);
            $isActive = (bool) ($city['is_active'] ?? false);

            return [
                'found' => true,
                'status' => $hasSetup ? 'catalog_configured' : 'catalog_pending',
                'status_label' => $hasSetup
                    ? __('Catálogo · base configurada')
                    : __('Catálogo · pendente'),
                'system' => 'i-Educar',
                'system_label' => __('i-Educar (Portabilis)'),
                'detail' => $hasSetup
                    ? __('Município no catálogo com credenciais — Consultoria ainda não activa.')
                    : __('Município cadastrado — falta configurar conexão i-Educar.'),
                'app_url' => filled($city['ieducar_app_url'] ?? null) ? (string) $city['ieducar_app_url'] : null,
                'source' => 'servlitcys_catalog',
            ];
        }

        if (is_array($registry) && trim((string) ($registry['system'] ?? '')) !== '') {
            $system = trim((string) $registry['system']);
            $vendor = trim((string) ($registry['vendor'] ?? ''));
            $notes = trim((string) ($registry['notes'] ?? ''));
            $source = trim((string) ($registry['source'] ?? 'external_registry')) ?: 'external_registry';
            $defaultDetail = $source === 'manual_admin'
                ? __('Registo manual Horizonte — inteligência de concorrência (não abre Consultoria).')
                : __('Sistema identificado na base SGE configurada (IBGE :ibge).', ['ibge' => $ibge]);

            return [
                'found' => true,
                'status' => 'registry',
                'status_label' => __('Registo externo'),
                'system' => $system,
                'system_label' => $vendor !== '' ? $system.' ('.$vendor.')' : $system,
                'detail' => $notes !== '' ? $notes : $defaultDetail,
                'app_url' => filled($registry['app_url'] ?? null) ? (string) $registry['app_url'] : null,
                'source' => $source,
            ];
        }

        return [
            'found' => false,
            'status' => 'not_found',
            'status_label' => __('SGE não identificado'),
            'system' => null,
            'system_label' => __('Desconhecido'),
            'detail' => __('Nenhum sistema de gestão educacional encontrado no catálogo ServLITCYS nem no registo externo.'),
            'app_url' => null,
            'source' => 'none',
        ];
    }
}
