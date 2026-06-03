<?php

namespace App\Services\Cadunico;

use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Cadunico\CadunicoMisocialIbgeNormalizer;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API Solr Misocial (MDS/SAGI) — fonte oficial agregada por município (sem servidor próprio).
 */
final class CadunicoSagiMisocialClient
{
    public function __construct(
        private CadunicoMunicipioSnapshotRepository $repository,
    ) {}

    public static function enabled(): bool
    {
        return filter_var(config('ieducar.cadunico.misocial.enabled', true), FILTER_VALIDATE_BOOL);
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('ieducar.cadunico.misocial.base_url', 'https://aplicacoes.mds.gov.br/sagi/servicos/misocial'), '/').'/';
    }

    /**
     * @return array{ok: bool, message: string, reachable: bool, sample_month: ?string, municipalities: ?int}
     */
    public function probe(): array
    {
        if (! self::enabled()) {
            return [
                'ok' => false,
                'message' => __('Misocial/SAGI desactivado na configuração.'),
                'reachable' => false,
                'sample_month' => null,
                'municipalities' => null,
            ];
        }

        $ano = CadunicoOpenDataImportService::suggestedImportYear();
        $month = self::resolveReferenceMonth($ano);
        try {
            $response = $this->http()->get(self::baseUrl(), [
                'q' => '*:*',
                'fq' => 'anomes_s:'.$month,
                'rows' => 0,
                'wt' => 'json',
            ]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'reachable' => false,
                'sample_month' => $month,
                'municipalities' => null,
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => __('Misocial HTTP :status', ['status' => (string) $response->status()]),
                'reachable' => false,
                'sample_month' => $month,
                'municipalities' => null,
            ];
        }

        $found = (int) ($response->json('response.numFound') ?? 0);

        return [
            'ok' => $found > 0,
            'message' => $found > 0
                ? __('Misocial acessível — :n municípios no mês :m.', ['n' => (string) $found, 'm' => $month])
                : __('Misocial sem registos para o mês :m.', ['m' => $month]),
            'reachable' => true,
            'sample_month' => $month,
            'municipalities' => $found > 0 ? $found : null,
        ];
    }

    /**
     * Importa todos os municípios com referência mensal (dezembro do ano ou último mês disponível).
     *
     * @return array{success: bool, message: string, imported: int, month: string, errors: list<string>}
     */
    public function importYear(int $ano): array
    {
        if (! self::enabled()) {
            return [
                'success' => false,
                'message' => __('Misocial/SAGI desactivado.'),
                'imported' => 0,
                'month' => '',
                'errors' => [],
            ];
        }

        $month = self::resolveReferenceMonth($ano);
        $monthYear = (int) substr($month, 0, 4);
        $fields = implode(',', $this->fieldList());
        $pageSize = max(500, min(15000, (int) config('ieducar.cadunico.misocial.page_size', 6000)));
        $start = 0;
        $imported = 0;
        $errors = [];
        $total = null;

        do {
            try {
                $response = $this->http()->get(self::baseUrl(), [
                    'q' => '*:*',
                    'fq' => 'anomes_s:'.$month,
                    'fl' => $fields,
                    'rows' => $pageSize,
                    'start' => $start,
                    'sort' => 'codigo_ibge asc',
                    'wt' => 'json',
                ]);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'imported' => $imported,
                    'month' => $month,
                    'errors' => [$e->getMessage()],
                ];
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => __('Misocial HTTP :status', ['status' => (string) $response->status()]),
                    'imported' => $imported,
                    'month' => $month,
                    'errors' => [__('Falha na página :start', ['start' => (string) $start])],
                ];
            }

            $total ??= (int) ($response->json('response.numFound') ?? 0);
            $docs = $response->json('response.docs');
            if (! is_array($docs) || $docs === []) {
                break;
            }

            foreach ($docs as $doc) {
                if ($this->persistMisocialDocument(is_array($doc) ? $doc : [], $ano, $month, $monthYear)) {
                    $imported++;
                }
            }

            $start += count($docs);
        } while ($total !== null && $start < $total);

        return [
            'success' => $imported > 0,
            'message' => $imported > 0
                ? ($monthYear !== $ano
                    ? __(':n município(s) via SAGI/Misocial — mês :m (ano civil :y); gravado como referência :ano.', [
                        'n' => (string) $imported,
                        'm' => $month,
                        'y' => (string) $monthYear,
                        'ano' => (string) $ano,
                    ])
                    : __(':n município(s) importado(s) via SAGI/Misocial (mês :m).', ['n' => (string) $imported, 'm' => $month]))
                : __('Misocial sem dados importáveis para :ano (último mês testado :m). Use --ano=2024 ou --ano=2025.', ['ano' => (string) $ano, 'm' => $month]),
            'imported' => $imported,
            'month' => $month,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{success: bool, message: string, source: string, attempts: list<string>, imported?: int}
     */
    public function importForIbge(string $ibge, int $ano, array &$attempts = []): array
    {
        if (! self::enabled()) {
            $attempts[] = __('Misocial: desactivado.');

            return ['success' => false, 'message' => '', 'source' => 'sagi_misocial', 'attempts' => $attempts];
        }

        $month = self::resolveReferenceMonth($ano);
        $fields = implode(',', $this->fieldList());

        try {
            $response = $this->http()->get(self::baseUrl(), [
                'q' => CadunicoMisocialIbgeNormalizer::solrQueryForOfficialIbge($ibge),
                'fq' => 'anomes_s:'.$month,
                'fl' => $fields,
                'rows' => 1,
                'wt' => 'json',
            ]);
        } catch (\Throwable $e) {
            $attempts[] = __('Misocial: :msg', ['msg' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'source' => 'sagi_misocial', 'attempts' => $attempts];
        }

        if (! $response->successful()) {
            $attempts[] = __('Misocial HTTP :status', ['status' => (string) $response->status()]);

            return ['success' => false, 'message' => '', 'source' => 'sagi_misocial', 'attempts' => $attempts];
        }

        $docs = $response->json('response.docs');
        $doc = is_array($docs[0] ?? null) ? $docs[0] : null;
        if ($doc === null) {
            $attempts[] = __('Misocial: sem registo IBGE :ibge em :m.', ['ibge' => $ibge, 'm' => $month]);

            return ['success' => false, 'message' => '', 'source' => 'sagi_misocial', 'attempts' => $attempts];
        }

        $monthYear = (int) substr($month, 0, 4);
        if (! $this->persistMisocialDocument($doc, $ano, $month, $monthYear, $ibge)) {
            $attempts[] = __('Misocial: documento sem população escolar estimada.');

            return ['success' => false, 'message' => '', 'source' => 'sagi_misocial', 'attempts' => $attempts];
        }

        $attempts[] = __('Misocial: importado (mês :m).', ['m' => $month]);

        return [
            'success' => true,
            'message' => __('Importado via SAGI/Misocial (MDS).'),
            'source' => 'sagi_misocial',
            'attempts' => $attempts,
            'imported' => 1,
        ];
    }

    public static function referenceMonthForYear(int $ano): string
    {
        return sprintf('%04d12', $ano);
    }

    /**
     * Último mês Misocial com agregados CadÚnico utilizáveis (evita 202612+ só com metadados).
     */
    public static function resolveReferenceMonth(int $ano): string
    {
        if (! self::enabled()) {
            return self::referenceMonthForYear($ano);
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $startYear = min($ano, $currentYear);
        $minYear = max(2000, $startYear - 3);

        for ($y = $startYear; $y >= $minYear; $y--) {
            $maxMonth = ($y === $currentYear) ? min(12, $currentMonth) : 12;
            for ($m = $maxMonth; $m >= 1; $m--) {
                $month = sprintf('%04d%02d', $y, $m);
                if (self::monthHasImportableCadunicoData($month)) {
                    return $month;
                }
            }
        }

        return self::referenceMonthForYear(min($ano, $currentYear - 1));
    }

    /**
     * O Solr pode devolver milhares de linhas só com codigo_ibge/anomes_s (ex.: projeção 202612).
     */
    public static function monthHasImportableCadunicoData(string $month): bool
    {
        try {
            $response = Http::timeout(10)->get(self::baseUrl(), [
                'q' => '*:*',
                'fq' => 'anomes_s:'.$month,
                'fl' => 'codigo_ibge,cadun_qtd_pessoas_cadastradas_i,qtd_pes_pbf_idade_7_a_15_sexo_feminino_i,igd_pbf_qtd_total_criancas_adolescentes_pbf_i',
                'rows' => 5,
                'wt' => 'json',
            ]);
        } catch (\Throwable) {
            return false;
        }

        if (! $response->successful() || (int) ($response->json('response.numFound') ?? 0) < 1000) {
            return false;
        }

        foreach ($response->json('response.docs') ?? [] as $doc) {
            if (! is_array($doc)) {
                continue;
            }
            if (CadunicoMisocialSnapshotMapper::toSnapshotPayload($doc, (int) substr($month, 0, 4)) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    private function persistMisocialDocument(
        array $doc,
        int $ano,
        string $month,
        int $monthYear,
        ?string $officialIbge = null,
    ): bool {
        $ibge = $officialIbge ?? CadunicoMisocialIbgeNormalizer::toOfficialSeven((string) ($doc['codigo_ibge'] ?? ''));
        if ($ibge === null) {
            return false;
        }

        $payload = CadunicoMisocialSnapshotMapper::toSnapshotPayload($doc, $ano);
        if ($payload === null) {
            return false;
        }

        $payload['metadados'] = array_merge(
            is_array($payload['metadados'] ?? null) ? $payload['metadados'] : [],
            [
                'anomes_referencia' => $month,
                'ano_misocial' => $monthYear,
                'ano_referencia_app' => $ano,
            ],
        );
        $this->repository->upsert($ibge, $ano, $payload);

        return true;
    }

    /**
     * @return list<string>
     */
    public static function defaultFieldList(): array
    {
        return [
            'codigo_ibge',
            'anomes_s',
            'cadun_qtd_pessoas_cadastradas_i',
            'cadun_qtd_familias_cadastradas_i',
            'qtd_pes_pbf_idade_0_e_4_sexo_feminino_i',
            'qtd_pes_pbf_idade_0_e_4_sexo_masculino_i',
            'qtd_pes_cad_nao_pbf_idade_0_e_4_sexo_feminino_i',
            'qtd_pes_cad_nao_pbf_idade_0_e_4_sexo_masculino_i',
            'qtd_pes_pbf_idade_5_a_6_sexo_feminino_i',
            'qtd_pes_pbf_idade_5_a_6_sexo_masculino_i',
            'qtd_pes_cad_nao_pbf_idade_5_a_6_sexo_feminino_i',
            'qtd_pes_cad_nao_pbf_idade_5_a_6_sexo_masculino_i',
            'qtd_pes_pbf_idade_7_a_15_sexo_feminino_i',
            'qtd_pes_pbf_idade_7_a_15_sexo_masculino_i',
            'qtd_pes_cad_nao_pbf_idade_7_a_15_sexo_feminino_i',
            'qtd_pes_cad_nao_pbf_idade_7_a_15_sexo_masculino_i',
            'qtd_pes_pbf_idade_16_a_17_sexo_feminino_i',
            'qtd_pes_pbf_idade_16_a_17_sexo_masculino_i',
            'qtd_pes_cad_nao_pbf_idade_16_a_17_sexo_feminino_i',
            'qtd_pes_cad_nao_pbf_idade_16_a_17_sexo_masculino_i',
            'igd_pbf_qtd_total_criancas_adolescentes_pbf_i',
            'igd_pab_qtd_total_criancas_adolescentes_pab_i',
        ];
    }

    /**
     * Lista compacta — fl muito longo faz o Solr devolver só codigo_ibge/anomes_s.
     *
     * @return list<string>
     */
    private function fieldList(): array
    {
        $configured = config('ieducar.cadunico.misocial.field_list');
        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return self::defaultFieldList();
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $timeout = max(10, (int) config('ieducar.cadunico.open_data.http_timeout', 45));

        return Http::timeout($timeout)->acceptJson()->withOptions(['allow_redirects' => true]);
    }
}
