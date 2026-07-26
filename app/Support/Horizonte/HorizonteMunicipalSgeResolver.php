<?php

namespace App\Support\Horizonte;

/**
 * Resolve sistema de gestão educacional (SGE) por município IBGE para o mapa Horizonte.
 *
 * Prioridade: consultoria activa (iEducar/Serventec) → registo externo → evidência
 * municipal no Portal (IBGE). Contratos nacionais MEC/FNDE NÃO definem incumbente municipal.
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
     *     badge: ?string,
     *     candidates: list<array<string, mixed>>,
     *     evidence_level: string
     * }
     */
    public function resolve(string $ibge, ?array $city, ?array $registry = null, ?array $market = null): array
    {
        if ($city !== null && ($city['consultoria_active'] ?? false)) {
            $driver = strtoupper(trim((string) ($city['db_driver'] ?? 'pgsql')));

            return $this->withDefaults([
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
                'candidates' => [],
                'evidence_level' => 'solid',
            ]);
        }

        if (is_array($registry) && trim((string) ($registry['system'] ?? '')) !== '') {
            $system = trim((string) ($registry['system']));
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

            return $this->withDefaults([
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
                'candidates' => [],
                'evidence_level' => 'solid',
            ]);
        }

        $fromMarket = $this->resolveFromMunicipalMarket($ibge, $market);
        if ($fromMarket !== null) {
            return $this->withDefaults($fromMarket);
        }

        $inCatalog = $city !== null;

        return $this->withDefaults([
            'found' => false,
            'status' => 'not_found',
            'status_label' => __('SGE não identificado'),
            'system' => null,
            'system_label' => __('Desconhecido'),
            'vendor' => null,
            'company' => null,
            'cnpj' => null,
            'detail' => $inCatalog
                ? __('Município no catálogo sem consultoria activa — sem SGE no registo externo nem evidência municipal (IBGE) em licitações/contratos.')
                : __('Sem evidência municipal sólida de SGE (registo ou Portal com IBGE). Contratos nacionais MEC/FNDE não atribuem sistema à cidade.'),
            'app_url' => null,
            'source' => 'none',
            'badge' => null,
            'candidates' => [],
            'evidence_level' => 'none',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withDefaults(array $payload): array
    {
        return array_merge([
            'candidates' => [],
            'evidence_level' => 'none',
        ], $payload);
    }

    /**
     * Apenas amostras com IBGE municipal. Nunca promove top vendors nacionais a incumbente.
     *
     * @param  ?array{samples?: list<array<string, mixed>>}  $market
     * @return ?array<string, mixed>
     */
    private function resolveFromMunicipalMarket(string $ibge, ?array $market): ?array
    {
        if (! is_array($market)) {
            return null;
        }

        $candidates = $this->buildMunicipalCandidates(
            is_array($market['samples'] ?? null) ? $market['samples'] : [],
        );

        if ($candidates === []) {
            return null;
        }

        if (count($candidates) === 1) {
            $c = $candidates[0];

            return [
                'found' => true,
                'status' => 'market',
                'status_label' => __('Evidência municipal (Portal)'),
                'system' => $c['system'],
                'system_label' => $c['system_label'],
                'vendor' => $c['company'],
                'company' => $c['company'],
                'cnpj' => $c['cnpj'],
                'detail' => __('Único fornecedor com evidência sólida em licitação/contrato com IBGE :ibge — proxy, não confirma implantação.', [
                    'ibge' => $ibge,
                ]),
                'app_url' => null,
                'source' => 'portal_procurement',
                'badge' => null,
                'candidates' => $candidates,
                'evidence_level' => 'municipal',
            ];
        }

        $labels = array_values(array_unique(array_filter(array_map(
            static fn (array $c): string => (string) ($c['system'] ?? ''),
            $candidates,
        ))));

        return [
            'found' => false,
            'status' => 'market_candidates',
            'status_label' => __('Vários indícios municipais'),
            'system' => null,
            'system_label' => __('Indícios múltiplos — sem incumbente único'),
            'vendor' => null,
            'company' => null,
            'cnpj' => null,
            'detail' => __('Há :n fornecedores distintos com evidência municipal (IBGE :ibge): :list. Não se elege um incumbente por amostragem.', [
                'n' => (string) count($candidates),
                'ibge' => $ibge,
                'list' => implode(', ', $labels),
            ]),
            'app_url' => null,
            'source' => 'portal_procurement',
            'badge' => null,
            'candidates' => $candidates,
            'evidence_level' => 'municipal_ambiguous',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $samples
     * @return list<array{
     *     system: string,
     *     system_label: string,
     *     company: ?string,
     *     cnpj: ?string,
     *     count: int,
     *     valor: ?float,
     *     valor_final: ?float,
     *     data_inicio_vigencia: ?string,
     *     data_fim_vigencia: ?string,
     *     data_publicacao: ?string,
     *     objeto: ?string,
     *     vendor_matched: bool,
     *     itens_software: bool
     * }>
     */
    private function buildMunicipalCandidates(array $samples): array
    {
        /** @var array<string, array<string, mixed>> $byKey */
        $byKey = [];

        foreach ($samples as $sample) {
            if (! is_array($sample) || ! $this->sampleIsSolidEvidence($sample)) {
                continue;
            }

            $cnpj = preg_replace('/\D/', '', (string) ($sample['fornecedor_cnpj'] ?? $sample['cnpj'] ?? '')) ?: null;
            if ($cnpj !== null && strlen($cnpj) !== 14) {
                $cnpj = null;
            }
            $label = trim((string) ($sample['vendor_label'] ?? ''));
            $company = trim((string) ($sample['fornecedor_nome'] ?? $sample['company'] ?? ''));
            $system = $label !== '' ? $label : ($company !== '' ? $company : __('Software (licitação)'));
            $key = $cnpj ?? mb_strtolower($label !== '' ? $label : $company);
            if ($key === '') {
                continue;
            }

            if (! isset($byKey[$key])) {
                $byKey[$key] = [
                    'system' => $system,
                    'system_label' => $company !== '' && $company !== $system ? $system.' ('.$company.')' : $system,
                    'company' => $company !== '' ? $company : ($label !== '' ? $label : null),
                    'cnpj' => $cnpj,
                    'count' => 0,
                    'valor' => null,
                    'valor_final' => null,
                    'data_inicio_vigencia' => null,
                    'data_fim_vigencia' => null,
                    'data_publicacao' => null,
                    'objeto' => null,
                    'vendor_matched' => false,
                    'itens_software' => false,
                ];
            }

            $byKey[$key]['count']++;
            if ((bool) ($sample['vendor_matched'] ?? false)) {
                $byKey[$key]['vendor_matched'] = true;
            }
            if ((bool) ($sample['itens_software'] ?? false)) {
                $byKey[$key]['itens_software'] = true;
            }

            $valor = is_numeric($sample['valor'] ?? null) ? (float) $sample['valor'] : null;
            $valorFinal = is_numeric($sample['valor_final'] ?? null) ? (float) $sample['valor_final'] : null;
            if ($valor !== null) {
                $byKey[$key]['valor'] = ($byKey[$key]['valor'] ?? 0.0) + $valor;
            }
            if ($valorFinal !== null) {
                $byKey[$key]['valor_final'] = ($byKey[$key]['valor_final'] ?? 0.0) + $valorFinal;
            }

            foreach (['data_inicio_vigencia', 'data_fim_vigencia', 'data_publicacao', 'objeto'] as $field) {
                if (($byKey[$key][$field] ?? null) === null && filled($sample[$field] ?? null)) {
                    $byKey[$key][$field] = is_string($sample[$field])
                        ? mb_substr((string) $sample[$field], 0, 160)
                        : $sample[$field];
                }
            }
        }

        $list = array_values($byKey);
        usort($list, static function (array $a, array $b): int {
            $va = (float) ($a['valor_final'] ?? $a['valor'] ?? 0);
            $vb = (float) ($b['valor_final'] ?? $b['valor'] ?? 0);
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }

            return $vb <=> $va;
        });

        foreach ($list as &$row) {
            if ($row['valor'] !== null) {
                $row['valor'] = round((float) $row['valor'], 2);
            }
            if ($row['valor_final'] !== null) {
                $row['valor_final'] = round((float) $row['valor_final'], 2);
            }
        }
        unset($row);

        return $list;
    }

    /**
     * Evidência sólida: CNPJ curado match, ou software + (CNPJ ou razão social).
     *
     * @param  array<string, mixed>  $sample
     */
    private function sampleIsSolidEvidence(array $sample): bool
    {
        if ((bool) ($sample['vendor_matched'] ?? false)) {
            return true;
        }

        $soft = (bool) ($sample['itens_software'] ?? false);
        $cnpj = preg_replace('/\D/', '', (string) ($sample['fornecedor_cnpj'] ?? $sample['cnpj'] ?? '')) ?: '';
        $company = trim((string) ($sample['fornecedor_nome'] ?? $sample['company'] ?? ''));
        $label = trim((string) ($sample['vendor_label'] ?? ''));

        if (! $soft) {
            return false;
        }

        return strlen($cnpj) === 14 || $company !== '' || $label !== '';
    }
}
