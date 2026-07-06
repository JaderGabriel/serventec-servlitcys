<?php

namespace App\Services\Horizonte;

use App\Models\MunicipalTransparencySnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteUfScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Cache municipal de convênios e empenhos do Portal da Transparência. */
final class HorizonteMunicipalTransparencySyncService
{
    public function __construct(
        private readonly IbgeMunicipalityCatalog $ibgeCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, imported?: int, partial?: bool, skipped?: bool}
     */
    public function syncBatch(array $options = []): array
    {
        $portal = config('ieducar.other_funding.public_queries.portal_transparencia', []);
        if (! (bool) ($portal['enabled'] ?? true)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('Portal da Transparência desactivado na configuração.'),
            ];
        }

        $apiKey = trim((string) ($portal['api_key'] ?? ''));
        if ($apiKey === '') {
            return [
                'success' => true,
                'skipped' => true,
                'message' => __('PORTAL_TRANSPARENCIA_API_KEY não definida — snapshot ignorado.'),
            ];
        }

        $year = (int) ($options['year'] ?? config('horizonte.reference_year', (int) date('Y') - 1));
        $perStep = max(1, min(30, (int) ($options['municipios_per_step'] ?? config('horizonte.transparency.municipios_per_step', 5))));
        $scopedUf = HorizonteUfScope::normalize($options['uf'] ?? null);
        $batch = $this->resolveIbgeBatch($scopedUf, $perStep, $year, $options);
        $ibgeCodes = $batch['codes'];
        $pendingAfter = $batch['pending_after'];

        if ($ibgeCodes === []) {
            return [
                'success' => true,
                'message' => __('Transparência: nenhum município pendente para o lote.'),
                'imported' => 0,
                'partial' => false,
            ];
        }
        $timeout = max(10, (int) config('horizonte.transparency.http_timeout', 25));
        $baseUrl = rtrim((string) ($portal['base_url'] ?? 'https://api.portaldatransparencia.gov.br'), '/');
        $educationKeywords = is_array($portal['education_keywords'] ?? null)
            ? $portal['education_keywords']
            : ['educacao', 'educação', 'fnde', 'pnae', 'pnate', 'pdde', 'fundeb', 'escolar'];
        $techKeywords = ['tecnolog', 'software', 'sistema', 'informatica', 'informática', 'ti ', 'digital'];

        $imported = 0;
        foreach ($ibgeCodes as $ibge) {
            $snapshot = $this->fetchSnapshot(
                $ibge,
                $year,
                $baseUrl,
                $apiKey,
                $timeout,
                $educationKeywords,
                $techKeywords,
            );
            if ($snapshot === null) {
                continue;
            }

            MunicipalTransparencySnapshot::query()->updateOrCreate(
                ['ibge_municipio' => $ibge, 'ano' => $year],
                array_merge($snapshot, [
                    'fonte' => 'portal_transparencia',
                    'imported_at' => now(),
                ]),
            );
            $imported++;
        }

        return [
            'success' => $imported > 0 || ! $pendingAfter,
            'message' => __('Transparência: :n município(s) atualizados.', ['n' => (string) $imported]),
            'imported' => $imported,
            'partial' => $pendingAfter > 0,
        ];
    }

    /**
     * @param  list<string>  $educationKeywords
     * @param  list<string>  $techKeywords
     * @return array<string, mixed>|null
     */
    private function fetchSnapshot(
        string $ibge,
        int $year,
        string $baseUrl,
        string $apiKey,
        int $timeout,
        array $educationKeywords,
        array $techKeywords,
    ): ?array {
        try {
            $headers = ['chave-api-dados' => $apiKey];
            $despesas = Http::timeout($timeout)->acceptJson()->withHeaders($headers)
                ->get($baseUrl.'/api-de-dados/despesas', ['codigoMunicipio' => $ibge, 'pagina' => 1])->json();
            $convenios = Http::timeout($timeout)->acceptJson()->withHeaders($headers)
                ->get($baseUrl.'/api-de-dados/convenios', ['codigoMunicipioIbge' => $ibge, 'pagina' => 1])->json();
        } catch (\Throwable $e) {
            Log::warning('horizonte.transparency_fetch_failed', ['ibge' => $ibge, 'message' => $e->getMessage()]);

            return null;
        }

        $despesaItems = is_array($despesas) ? $despesas : [];
        $convenioItems = is_array($convenios) ? $convenios : [];

        $empenhosEduc = 0.0;
        $empenhosTech = 0.0;
        $softwareContracts = 0;
        /** @var list<array<string, string>> $highlights */
        $highlights = [];

        foreach ($despesaItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $anoItem = (int) preg_replace('/\D/', '', (string) ($item['ano'] ?? $item['exercicio'] ?? ''));
            if ($anoItem >= 2000 && $anoItem !== $year) {
                continue;
            }
            $blob = strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: '');
            $valor = $this->numericValue($item);
            if ($this->matchesKeywords($blob, $educationKeywords)) {
                $empenhosEduc += $valor;
            }
            if ($this->matchesKeywords($blob, $techKeywords)) {
                $empenhosTech += $valor;
                if (str_contains($blob, 'software') || str_contains($blob, 'sistema')) {
                    $softwareContracts++;
                }
            }
            if (count($highlights) < 6 && ($this->matchesKeywords($blob, $educationKeywords) || $this->matchesKeywords($blob, $techKeywords))) {
                $highlights[] = [
                    'label' => mb_substr((string) ($item['nomeOrgao'] ?? $item['orgao'] ?? 'Despesa'), 0, 72),
                    'value' => $valor > 0 ? number_format($valor, 2, ',', '.') : '—',
                ];
            }
        }

        $conveniosAtivos = 0;
        foreach ($convenioItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $blob = strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: '');
            if (str_contains($blob, 'mec') || str_contains($blob, 'fnde') || str_contains($blob, 'educ')) {
                $conveniosAtivos++;
            }
        }

        if ($despesaItems === [] && $convenioItems === []) {
            return null;
        }

        return [
            'convenios_ativos' => $conveniosAtivos,
            'empenhos_educacao' => $empenhosEduc > 0 ? round($empenhosEduc, 2) : null,
            'empenhos_tecnologia' => $empenhosTech > 0 ? round($empenhosTech, 2) : null,
            'contratos_software' => $softwareContracts,
            'highlights' => $highlights,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function numericValue(array $item): float
    {
        foreach (['valor', 'valorEmpenhado', 'valorPago', 'valorConvenio'] as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                return (float) $item[$key];
            }
        }

        return 0.0;
    }

    /**
     * @param  list<string>  $keywords
     */
    private function matchesKeywords(string $blob, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            $kw = strtolower(trim($kw));
            if ($kw !== '' && str_contains($blob, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{codes: list<string>, pending_after: int}
     */
    private function resolveIbgeBatch(?string $scopedUf, int $perStep, int $year, array $options): array
    {
        if (is_array($options['ibge_codes'] ?? null)) {
            $codes = [];
            foreach ($options['ibge_codes'] as $raw) {
                $norm = FundebMunicipioReferenceRepository::normalizeIbge((string) $raw);
                if ($norm !== null) {
                    $codes[] = $norm;
                }
            }
            $codes = array_slice(array_values(array_unique($codes)), 0, $perStep);

            return ['codes' => $codes, 'pending_after' => 0];
        }

        $all = HorizonteUfScope::ibgeCodesForUf($scopedUf, $this->ibgeCatalog)
            ?? HorizonteUfScope::nationalIbgeCodes($this->ibgeCatalog);
        $imported = MunicipalTransparencySnapshot::query()
            ->where('ano', $year)
            ->pluck('ibge_municipio')
            ->map(static fn ($v) => FundebMunicipioReferenceRepository::normalizeIbge((string) $v))
            ->filter()
            ->all();
        $importedSet = array_fill_keys($imported, true);
        $pending = array_values(array_filter($all, static fn (string $ibge): bool => ! isset($importedSet[$ibge])));
        $codes = array_slice($pending, 0, $perStep);

        return [
            'codes' => $codes,
            'pending_after' => max(0, count($pending) - count($codes)),
        ];
    }
}
