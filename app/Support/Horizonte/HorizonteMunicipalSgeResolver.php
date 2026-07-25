<?php

namespace App\Support\Horizonte;

/**
 * Resolve sistema de gestão educacional (SGE) por município IBGE para o mapa Horizonte.
 *
 * Prioridade: consultoria activa (iEducar/Serventec) → registo externo → sinais de mercado
 * (licitações/contratos Portal). Município só no catálogo, sem consultoria, não assume iEducar.
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
     * @param  ?array{system?: string, vendor?: string, notes?: string, source?: string, app_url?: string, cnpj?: string}  $registry
     * @param  ?array{
     *     samples?: list<array<string, mixed>>,
     *     top_vendors?: list<array{label?: string, count?: int, cnpj?: string}>,
     *     vendor_matched?: int
     * }  $market
     * @return array{
     *     found: bool,
     *     status: string,
     *     status_label: string,
     *     system: ?string,
     *     system_label: string,
     *     vendor: ?string,
     *     company: ?string,
     *     cnpj: ?string,
     *     detail: string,
     *     app_url: ?string,
     *     source: string,
     *     badge: ?string
     * }
     */
    public function resolve(string $ibge, ?array $city, ?array $registry = null, ?array $market = null): array
    {
        if ($city !== null && ($city['consultoria_active'] ?? false)) {
            $driver = strtoupper(trim((string) ($city['db_driver'] ?? 'pgsql')));

            return [
                'found' => true,
                'status' => 'consultoria_active',
                'status_label' => __('Consultoria activa'),
                'system' => 'iEducar',
                'system_label' => __('iEducar (Serventec)'),
                'vendor' => 'Serventec',
                'company' => 'Serventec',
                'cnpj' => null,
                'detail' => __('Base :driver ligada no ServLITCYS — painel analítico disponível.', [
                    'driver' => $driver === 'MYSQL' ? 'MySQL' : 'PostgreSQL',
                ]),
                'app_url' => filled($city['ieducar_app_url'] ?? null) ? (string) $city['ieducar_app_url'] : null,
                'source' => 'servlitcys_catalog',
                'badge' => 'ieducar',
            ];
        }

        if (is_array($registry) && trim((string) ($registry['system'] ?? '')) !== '') {
            $system = trim((string) $registry['system']);
            $vendor = trim((string) ($registry['vendor'] ?? ''));
            $notes = trim((string) ($registry['notes'] ?? ''));
            $cnpj = preg_replace('/\D/', '', (string) ($registry['cnpj'] ?? '')) ?: null;
            if ($cnpj !== null && strlen($cnpj) !== 14) {
                $cnpj = null;
            }
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
                'vendor' => $vendor !== '' ? $vendor : null,
                'company' => $vendor !== '' ? $vendor : null,
                'cnpj' => $cnpj,
                'detail' => $notes !== '' ? $notes : $defaultDetail,
                'app_url' => filled($registry['app_url'] ?? null) ? (string) $registry['app_url'] : null,
                'source' => $source,
                'badge' => null,
            ];
        }

        $fromMarket = $this->resolveFromMarket($ibge, $market);
        if ($fromMarket !== null) {
            return $fromMarket;
        }

        $inCatalog = $city !== null;

        return [
            'found' => false,
            'status' => 'not_found',
            'status_label' => __('SGE não identificado'),
            'system' => null,
            'system_label' => __('Desconhecido'),
            'vendor' => null,
            'company' => null,
            'cnpj' => null,
            'detail' => $inCatalog
                ? __('Município no catálogo sem consultoria activa — sem SGE no registo externo nem sinal claro em licitações/contratos públicos.')
                : __('Nenhum sistema de gestão educacional encontrado no registo externo nem em licitações/recursos públicos sincronizados.'),
            'app_url' => null,
            'source' => 'none',
            'badge' => null,
        ];
    }

    /**
     * @param  ?array{
     *     samples?: list<array<string, mixed>>,
     *     top_vendors?: list<array{label?: string, count?: int, cnpj?: string}>,
     *     vendor_matched?: int
     * }  $market
     * @return ?array{
     *     found: bool,
     *     status: string,
     *     status_label: string,
     *     system: ?string,
     *     system_label: string,
     *     vendor: ?string,
     *     company: ?string,
     *     cnpj: ?string,
     *     detail: string,
     *     app_url: ?string,
     *     source: string,
     *     badge: ?string
     * }
     */
    private function resolveFromMarket(string $ibge, ?array $market): ?array
    {
        if (! is_array($market)) {
            return null;
        }

        $samples = is_array($market['samples'] ?? null) ? $market['samples'] : [];
        foreach ($samples as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $soft = (bool) ($sample['itens_software'] ?? false)
                || (bool) ($sample['vendor_matched'] ?? false);
            $label = trim((string) ($sample['vendor_label'] ?? ''));
            $company = trim((string) ($sample['fornecedor_nome'] ?? $sample['company'] ?? ''));
            $cnpj = preg_replace('/\D/', '', (string) ($sample['fornecedor_cnpj'] ?? $sample['cnpj'] ?? '')) ?: null;
            if ($cnpj !== null && strlen($cnpj) !== 14) {
                $cnpj = null;
            }
            if (! $soft && $label === '' && $company === '') {
                continue;
            }
            $system = $label !== '' ? $label : ($company !== '' ? $company : __('Software (licitação)'));
            $vendor = $company !== '' ? $company : ($label !== '' ? $label : null);

            return [
                'found' => true,
                'status' => 'market',
                'status_label' => __('Sinal Portal / licitação'),
                'system' => $system,
                'system_label' => $vendor !== null && $vendor !== $system ? $system.' ('.$vendor.')' : $system,
                'vendor' => $vendor,
                'company' => $vendor,
                'cnpj' => $cnpj,
                'detail' => __('Inferido de licitação/contrato público (IBGE :ibge) — proxy, não confirma implantação.', [
                    'ibge' => $ibge,
                ]),
                'app_url' => null,
                'source' => 'portal_procurement',
                'badge' => null,
            ];
        }

        $top = is_array($market['top_vendors'] ?? null) ? $market['top_vendors'] : [];
        if ($top !== [] && (int) ($market['vendor_matched'] ?? 0) > 0) {
            $first = $top[0];
            if (is_array($first)) {
                $label = trim((string) ($first['label'] ?? ''));
                if ($label !== '') {
                    $cnpj = preg_replace('/\D/', '', (string) ($first['cnpj'] ?? '')) ?: null;
                    if ($cnpj !== null && strlen($cnpj) !== 14) {
                        $cnpj = null;
                    }

                    return [
                        'found' => true,
                        'status' => 'market_national',
                        'status_label' => __('Sinal nacional MEC/FNDE'),
                        'system' => $label,
                        'system_label' => $label,
                        'vendor' => $label,
                        'company' => $label,
                        'cnpj' => $cnpj,
                        'detail' => __('CNPJ curado com contratos MEC/FNDE (nível órgão) — sinal de mercado, não prova o SGE municipal.'),
                        'app_url' => null,
                        'source' => 'portal_procurement_national',
                        'badge' => null,
                    ];
                }
            }
        }

        return null;
    }
}
