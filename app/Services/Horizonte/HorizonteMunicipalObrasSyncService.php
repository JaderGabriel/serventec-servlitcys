<?php

namespace App\Services\Horizonte;

use App\Models\EducationWorkFinanceSnapshot;
use App\Models\MunicipalEducationWork;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Obrasgov\ObrasgovClient;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteUfScope;
use App\Support\Horizonte\ObrasgovWorkFieldExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sincronização de obras de educação (FNDE/SIMEC) por UF via Obrasgov.
 */
final class HorizonteMunicipalObrasSyncService
{
    public function __construct(
        private readonly ObrasgovClient $client,
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, partial?: bool, uf?: string, skipped?: bool}
     */
    public function syncBatch(array $options = []): array
    {
        if (! filter_var(config('horizonte.obras.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('Obras (Obrasgov) desactivado (HORIZONTE_OBRAS_ENABLED=false).'),
            ];
        }

        $uf = HorizonteUfScope::normalize($options['uf'] ?? null);
        $reset = (bool) ($options['reset'] ?? false);
        $continueSync = (bool) ($options['continue'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $enrichFinance = (bool) ($options['enrich_finance'] ?? config('horizonte.obras.enrich_finance', true));

        if (isset($options['no_enrich_finance']) && (bool) $options['no_enrich_finance']) {
            $enrichFinance = false;
        }

        if ($reset) {
            $this->resetProgress();
        }

        $targetUf = $uf ?? $this->nextPendingUf($continueSync);

        if ($targetUf === null) {
            return [
                'success' => true,
                'message' => __('Obras: todas as UFs já sincronizadas no ciclo atual.'),
                'imported' => 0,
                'partial' => false,
            ];
        }

        Log::info('horizonte.obras_sync_start', ['uf' => $targetUf, 'enrich_finance' => $enrichFinance]);

        if ($dryRun) {
            return [
                'success' => true,
                'message' => __('Obras (dry-run): UF :uf seria sincronizada.', ['uf' => $targetUf]),
                'imported' => 0,
                'uf' => $targetUf,
            ];
        }

        $result = $this->syncUf($targetUf, $enrichFinance, $options);

        if ($uf === null) {
            $this->markUfDone($targetUf);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported: int, partial?: bool, uf: string}
     */
    private function syncUf(string $uf, bool $enrichFinance, array $options): array
    {
        $situacoes = is_array(config('horizonte.obras.situacoes'))
            ? config('horizonte.obras.situacoes')
            : ['Cadastrada', 'Cancelada', 'Em execução', 'Inacabada', 'Paralisada'];

        if (isset($options['situacao']) && is_string($options['situacao']) && trim($options['situacao']) !== '') {
            $situacoes = [trim($options['situacao'])];
        }

        $cnpjFnde = preg_replace('/\D/', '', (string) config('horizonte.obras.cnpj_fnde', '00378257000262')) ?: '00378257000262';

        $geometriaMap = $this->buildGeometriaMap($uf);

        $imported = 0;
        $limitPages = isset($options['limit_pages']) && is_numeric($options['limit_pages']) ? (int) $options['limit_pages'] : null;

        foreach ($situacoes as $situacao) {
            $page = 1;
            do {
                $response = $this->client->getProjetos([
                    'uf_principal' => $uf,
                    'situacao' => $situacao,
                    'cnpj_organizacao_resp' => $cnpjFnde,
                ], $page);

                if ($response === null || $response['data'] === []) {
                    break;
                }

                foreach ($response['data'] as $projeto) {
                    if (! is_array($projeto)) {
                        continue;
                    }

                    $idProjeto = trim((string) ($projeto['id_projeto_investimento'] ?? ''));
                    if ($idProjeto === '') {
                        continue;
                    }

                    $ibge = $this->resolveIbge($idProjeto, $projeto, $geometriaMap);

                    $data = [
                        'id_projeto_investimento' => $idProjeto,
                        'ibge_municipio' => $ibge['ibge'],
                        'ibge_confidence' => $ibge['confidence'],
                        'uf_principal' => trim((string) ($projeto['uf_principal'] ?? '')),
                        'situacao' => trim((string) ($projeto['situacao'] ?? '')),
                        'especie_intervencao' => trim((string) ($projeto['especie_intervencao'] ?? '')) ?: null,
                        'natureza_intervencao' => trim((string) ($projeto['natureza_intervencao'] ?? '')) ?: null,
                        'desc_nome' => mb_substr(trim((string) ($projeto['desc_nome'] ?? '')), 0, 512) ?: null,
                        'desc_meta_global' => mb_substr(trim((string) ($projeto['desc_meta_global'] ?? '')), 0, 255) ?: null,
                        'sistema_resp' => trim((string) ($projeto['sistema_resp'] ?? '')) ?: null,
                        'organizacao_resp' => mb_substr(trim((string) ($projeto['organizacao_resp'] ?? '')), 0, 255) ?: null,
                        'cnpj_organizacao_resp' => preg_replace('/\D/', '', (string) ($projeto['cnpj_organizacao_resp'] ?? '')) ?: null,
                        'latitude' => $this->parseCoordinate($projeto['pins'][0]['latitude'] ?? null),
                        'longitude' => $this->parseCoordinate($projeto['pins'][0]['longitude'] ?? null),
                        'percentual_execucao_fisica' => null,
                        'valor_empenhado' => null,
                        'valor_pago' => null,
                        'valor_previsto' => ObrasgovWorkFieldExtractor::valorPrevisto($projeto),
                        'data_inicio' => ObrasgovWorkFieldExtractor::dataInicio($projeto),
                        'data_paralisacao' => null,
                        'data_ultima_afericao' => null,
                        'historico_paralisacao' => null,
                        'meta_execucao' => null,
                        'meta' => is_array($projeto['meta'] ?? null) ? $projeto['meta'] : null,
                        'fonte' => 'obrasgov',
                        'imported_at' => now(),
                    ];

                    if ($enrichFinance) {
                        $enriched = $this->enrichFinance($idProjeto, $situacao, $projeto);
                        $data['percentual_execucao_fisica'] = $enriched['percentual'] ?? null;
                        $data['valor_empenhado'] = $enriched['valor_empenhado'] ?? null;
                        $data['valor_pago'] = $enriched['valor_pago'] ?? null;
                        $data['historico_paralisacao'] = $enriched['historico'] ?? null;
                        $data['meta_execucao'] = $enriched['meta_execucao'] ?? null;
                        $data['data_inicio'] = $enriched['data_inicio'] ?? $data['data_inicio'];
                        $data['data_paralisacao'] = $enriched['data_paralisacao'] ?? null;
                        $data['data_ultima_afericao'] = $enriched['data_ultima_afericao'] ?? null;
                    }

                    MunicipalEducationWork::query()->updateOrCreate(
                        ['id_projeto_investimento' => $idProjeto],
                        $data
                    );

                    $imported++;
                }

                $page++;
                if ($limitPages !== null && $page > $limitPages) {
                    break 2;
                }
            } while ($page <= $response['total_pages']);
        }

        Log::info('horizonte.obras_sync_uf_done', [
            'uf' => $uf,
            'imported' => $imported,
            'situacoes' => $situacoes,
        ]);

        return [
            'success' => true,
            'message' => __('Obras: :n obra(s) sincronizadas para UF :uf.', ['n' => (string) $imported, 'uf' => $uf]),
            'imported' => $imported,
            'uf' => $uf,
        ];
    }

    /**
     * @return array<string, array{cod_ibge: string, no_municipio: string}>
     */
    private function buildGeometriaMap(string $uf): array
    {
        $map = [];
        $page = 1;

        do {
            $response = $this->client->getGeometrias(['sg_uf' => $uf], $page);
            if ($response === null || $response['data'] === []) {
                break;
            }

            foreach ($response['data'] as $geo) {
                if (! is_array($geo)) {
                    continue;
                }

                $idProjeto = trim((string) ($geo['id_projeto_investimento'] ?? ''));
                $codIbge = preg_replace('/\D/', '', (string) ($geo['cod_ibge'] ?? ''));

                if ($idProjeto !== '' && $codIbge !== '' && strlen($codIbge) === 7) {
                    $map[$idProjeto] = [
                        'cod_ibge' => $codIbge,
                        'no_municipio' => trim((string) ($geo['no_municipio'] ?? '')),
                    ];
                }
            }

            $page++;
        } while ($page <= $response['total_pages'] && $page <= 50);

        return $map;
    }

    /**
     * @param  array<string, mixed>  $projeto
     * @param  array<string, array{cod_ibge: string, no_municipio: string}>  $geometriaMap
     * @return array{ibge: string|null, confidence: string}
     */
    private function resolveIbge(string $idProjeto, array $projeto, array $geometriaMap): array
    {
        if (isset($geometriaMap[$idProjeto])) {
            return [
                'ibge' => $geometriaMap[$idProjeto]['cod_ibge'],
                'confidence' => 'high',
            ];
        }

        $descNome = trim((string) ($projeto['desc_nome'] ?? ''));
        if ($descNome !== '' && preg_match('/\b([A-Za-záéíóúâêôãõçÁÉÍÓÚÂÊÔÃÕÇ\s]+)\s*-\s*([A-Z]{2})\b/', $descNome, $matches)) {
            $cityName = trim($matches[1]);
            $uf = trim($matches[2]);
            $normalized = FundebMunicipioReferenceRepository::normalizeIbge(
                $this->ibgeCatalog->findByName($cityName, $uf)
            );
            if ($normalized !== null) {
                return ['ibge' => $normalized, 'confidence' => 'low'];
            }
        }

        return ['ibge' => null, 'confidence' => 'none'];
    }

    /**
     * @param  array<string, mixed>  $projeto
     * @return array{
     *     percentual: float|null,
     *     valor_empenhado: float|null,
     *     valor_pago: float|null,
     *     historico: array|null,
     *     meta_execucao: array|null,
     *     data_inicio: string|null,
     *     data_paralisacao: string|null,
     *     data_ultima_afericao: string|null
     * }
     */
    private function enrichFinance(string $idProjeto, string $situacao, array $projeto = []): array
    {
        $historico = null;

        $execucao = $this->client->getExecucaoFisica($idProjeto);
        $percentual = ObrasgovWorkFieldExtractor::percentualExecucao(is_array($execucao) ? $execucao : null);

        $empenhos = $this->client->getEmpenhos($idProjeto);
        $totaisEmp = ObrasgovWorkFieldExtractor::totaisEmpenho($empenhos);
        $valorEmpenhado = $totaisEmp['valor_empenhado'];
        $valorPago = $totaisEmp['valor_pago'];

        if ($empenhos !== [] && is_array($empenhos[0] ?? null)) {
            EducationWorkFinanceSnapshot::query()->updateOrCreate(
                ['id_projeto_investimento' => $idProjeto],
                [
                    'fonte_orcamentaria' => $totaisEmp['fonte'],
                    'valor_empenho' => $valorEmpenhado,
                    'valor_liquidado' => $totaisEmp['valor_liquidado'],
                    'valor_pago' => $valorPago,
                    'meta' => is_array($empenhos[0]['meta'] ?? null) ? $empenhos[0]['meta'] : [
                        'empenhos_count' => count($empenhos),
                        'amostra' => array_intersect_key($empenhos[0], array_flip([
                            'nr_empenho',
                            'sistema_origem_empenho',
                            'bd_origem_empenho',
                            'programa_trabalho',
                            'ug_emitente',
                        ])),
                    ],
                    'imported_at' => now(),
                ]
            );
        }

        if (in_array($situacao, ['Paralisada', 'Cancelada'], true)) {
            $hist = $this->client->getHistoricoParalisacao($idProjeto);
            if ($hist !== []) {
                $historico = $hist;
            }
        }

        $execucaoArr = is_array($execucao) ? $execucao : null;

        return [
            'percentual' => $percentual,
            'valor_empenhado' => $valorEmpenhado,
            'valor_pago' => $valorPago,
            'historico' => $historico,
            'meta_execucao' => ObrasgovWorkFieldExtractor::metaExecucao($execucaoArr),
            'data_inicio' => ObrasgovWorkFieldExtractor::dataInicio($projeto, $execucaoArr),
            'data_paralisacao' => ObrasgovWorkFieldExtractor::dataParalisacao($historico),
            'data_ultima_afericao' => ObrasgovWorkFieldExtractor::dataUltimaAfericao($execucaoArr),
        ];
    }

    private function parseCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return abs($float) > 0.0001 ? $float : null;
    }

    private function nextPendingUf(bool $continueSync): ?string
    {
        if (! $continueSync) {
            return null;
        }

        $progressKey = 'horizonte.obras.sync.progress';
        $progress = Cache::get($progressKey);
        if (! is_array($progress) || ! isset($progress['pending_ufs']) || ! is_array($progress['pending_ufs'])) {
            $allUfs = IbgeMunicipalityCatalog::brazilianUfs();
            Cache::put($progressKey, ['pending_ufs' => $allUfs], now()->addDays(30));

            return $allUfs[0] ?? null;
        }

        return $progress['pending_ufs'][0] ?? null;
    }

    private function markUfDone(string $uf): void
    {
        $progressKey = 'horizonte.obras.sync.progress';
        $progress = Cache::get($progressKey);
        if (! is_array($progress) || ! isset($progress['pending_ufs']) || ! is_array($progress['pending_ufs'])) {
            return;
        }

        $pending = array_values(array_filter($progress['pending_ufs'], static fn ($u): bool => $u !== $uf));
        if ($pending === []) {
            Cache::forget($progressKey);
        } else {
            Cache::put($progressKey, ['pending_ufs' => $pending], now()->addDays(30));
        }
    }

    private function resetProgress(): void
    {
        $progressKey = 'horizonte.obras.sync.progress';
        $allUfs = IbgeMunicipalityCatalog::brazilianUfs();
        Cache::put($progressKey, ['pending_ufs' => $allUfs], now()->addDays(30));
        Log::info('horizonte.obras_sync_reset');
    }
}
