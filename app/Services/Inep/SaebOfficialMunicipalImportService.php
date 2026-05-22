<?php

namespace App\Services\Inep;

use App\Models\City;
use App\Support\Inep\SaebMunicipioPayloadReader;
use App\Support\Inep\SaebOfficialPayloadParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Obtém séries SAEB por município (código IBGE) para cada cidade cadastrada e grava na base (saeb_indicator_points).
 * Sem dados prévios e com template na API interna, tenta microdados INEP (INEP→cod_escola) antes de falhar.
 */
class SaebOfficialMunicipalImportService
{
    public function __construct(
        private SaebPedagogicalImportService $writer,
        private SaebMicrodadosOpenDataImportService $microdados,
        private SaebHistoricoDatabase $historicoDb,
        private SaebInepToEscolaIdResolver $inepResolver,
    ) {}

    /**
     * @param  string|null  $templateOverride  URL com {ibge} (e opcionalmente {uf}, {city_id}); sobrescreve IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE quando preenchida.
     * @param  array{city_id?: int|null, year?: int|null, auto_microdados?: bool|null, resolve_inep?: bool|null}  $options
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string, detalhes?: array<string, mixed>}
     */
    public function importFromOfficialTemplate(?string $templateOverride = null, array $options = []): array
    {
        $rel = SaebHistoricoDatabase::STORAGE_LABEL;
        $template = null;
        $trimOverride = $templateOverride !== null ? trim($templateOverride) : '';
        if ($trimOverride !== '') {
            $template = $trimOverride;
        } else {
            $template = $this->resolveOfficialUrlTemplate();
        }

        if ($template === '' || ! str_contains($template, '{ibge}')) {
            return [
                'ok' => false,
                'message' => __(
                    'Defina APP_URL com a URL pública da aplicação (https://…) ou IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE com o placeholder {ibge}. O IBGE nas cidades não substitui a URL de origem dos dados.'
                ),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }

        $query = City::query()
            ->forAnalytics()
            ->whereNotNull('ibge_municipio')
            ->orderBy('id');

        $onlyCityId = isset($options['city_id']) ? (int) $options['city_id'] : 0;
        if ($onlyCityId > 0) {
            $query->where('id', $onlyCityId);
        }

        $cities = $query->get();

        if ($cities->isEmpty()) {
            return [
                'ok' => false,
                'message' => __(
                    'Nenhuma cidade activa com base de dados configurada e código IBGE (7 dígitos) preenchido. Edite o cadastro das cidades.'
                ),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }

        $resolveInep = array_key_exists('resolve_inep', $options)
            ? (bool) $options['resolve_inep']
            : filter_var(config('ieducar.saeb.official_resolve_inep', true), FILTER_VALIDATE_BOOLEAN);

        $autoMicrodados = array_key_exists('auto_microdados', $options)
            ? (bool) $options['auto_microdados']
            : filter_var(config('ieducar.saeb.official_auto_microdados_fallback', true), FILTER_VALIDATE_BOOLEAN);

        $preferYear = isset($options['year']) && is_numeric($options['year'])
            ? max(2000, min(2100, (int) $options['year']))
            : $this->defaultPreferYear();

        $useInternalFirst = filter_var(config('ieducar.saeb.official_use_internal_storage_first', true), FILTER_VALIDATE_BOOLEAN);
        $usesInternalApiTemplate = $this->usesDefaultAppUrlTemplate($template, $trimOverride === '')
            || $this->templatePointsToInternalApi($template);

        $bootstrapNotes = [];
        if ($autoMicrodados && ($usesInternalApiTemplate || $useInternalFirst)) {
            $missing = $this->citiesWithoutSaebPoints($cities);
            if ($missing->isNotEmpty()) {
                $bootstrapNotes[] = __('A importar microdados INEP (:year) para :n município(s) sem dados SAEB…', [
                    'year' => (string) $preferYear,
                    'n' => (string) $missing->count(),
                ]);
                $md = $this->microdados->syncFromInepZipForCities(
                    $missing,
                    $preferYear,
                    true,
                    $resolveInep,
                    true,
                    null,
                );
                if ($md['ok']) {
                    $bootstrapNotes[] = __('Microdados: :msg', ['msg' => (string) ($md['message'] ?? '')]);
                } else {
                    $bootstrapNotes[] = __('Microdados (aviso): :msg', ['msg' => (string) ($md['message'] ?? __('falha desconhecida'))]);
                }
            }
        }

        $allPontos = [];
        $errors = [];
        $urlsTentadas = [];

        foreach ($cities as $city) {
            /** @var City $city */
            $ibge = (string) $city->ibge_municipio;
            $decoded = null;

            if ($useInternalFirst) {
                $decoded = SaebMunicipioPayloadReader::loadForIbge($ibge);
            }

            if ($decoded === null) {
                $url = $this->expandTemplate($template, $city, $ibge);
                $urlsTentadas[] = $url;

                if ($this->isSameApplicationSaebEndpointUrl($url)) {
                    if ($useInternalFirst) {
                        $decoded = SaebMunicipioPayloadReader::loadForIbge($ibge);
                    }
                    if ($decoded === null) {
                        $errors[] = __(':nome (IBGE :ibge): sem dados SAEB após tentativa de microdados/CSV. Confira IEDUCAR_SAEB_MICRODADOS_ENABLED, ano (:year) e colunas do ZIP, ou defina IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE com URL externa.', [
                            'nome' => $city->name,
                            'ibge' => $ibge,
                            'year' => (string) $preferYear,
                        ]);

                        continue;
                    }
                } else {
                    try {
                        $resp = Http::timeout($this->httpTimeoutSeconds())
                            ->withHeaders([
                                'User-Agent' => 'ServLitcys-SAEB-Official/1.0',
                                'Accept' => 'application/json, text/plain;q=0.9, */*;q=0.8',
                            ])
                            ->acceptJson()
                            ->get($url);

                        if (! $resp->successful()) {
                            $errors[] = __(':nome (IBGE :ibge): HTTP :code', [
                                'nome' => $city->name,
                                'ibge' => $ibge,
                                'code' => (string) $resp->status(),
                            ]);

                            continue;
                        }

                        $decoded = json_decode($resp->body(), true);
                        if (! is_array($decoded)) {
                            $errors[] = __(':nome (IBGE :ibge): resposta não é JSON.', [
                                'nome' => $city->name,
                                'ibge' => $ibge,
                            ]);

                            continue;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = __(':nome (IBGE :ibge): :msg', [
                            'nome' => $city->name,
                            'ibge' => $ibge,
                            'msg' => $e->getMessage(),
                        ]);

                        continue;
                    }
                }
            } else {
                $urlsTentadas[] = __('Leitura interna (base) para IBGE :ibge', ['ibge' => $ibge]);
            }

            $pontos = SaebOfficialPayloadParser::pontosForCity($decoded, $city);
            if ($pontos === []) {
                $errors[] = __(':nome (IBGE :ibge): JSON sem pontos reconhecíveis (chaves «pontos» ou «resultados»).', [
                    'nome' => $city->name,
                    'ibge' => $ibge,
                ]);

                continue;
            }

            foreach ($this->resolveEscolaIdsOnPontos($pontos, $city, $resolveInep) as $p) {
                $allPontos[] = $p;
            }
        }

        if ($allPontos === []) {
            $hint = '';
            if ($usesInternalApiTemplate) {
                $hint = "\n\n".__(
                    'Nota: com template na API interna, o sistema tenta microdados INEP automaticamente (IEDUCAR_SAEB_OFFICIAL_AUTO_MICRODADOS). Se falhou, execute o Passo 4 manualmente ou configure URL externa.'
                );
            }
            $prefix = $bootstrapNotes !== [] ? implode("\n", $bootstrapNotes)."\n\n" : '';
            $msg = $prefix.__('Nenhum município devolveu dados SAEB válidos.').$hint."\n".implode("\n", $errors);

            return [
                'ok' => false,
                'message' => $msg,
                'fonte_efetiva' => null,
                'path' => $rel,
                'detalhes' => ['erros' => $errors, 'urls' => $urlsTentadas, 'bootstrap' => $bootstrapNotes],
            ];
        }

        $meta = [
            'descricao' => __('Séries SAEB agregadas por município (código IBGE) para as cidades cadastradas.'),
            'fonte' => __('Fontes oficiais (INEP / dados abertos) conforme URL configurada por município.'),
            'importacao_oficial' => [
                'cidades_processadas' => $cities->count(),
                'pontos_gravados' => count($allPontos),
                'erros_parciais' => $errors,
                'microdados_bootstrap' => $bootstrapNotes,
                'resolve_inep' => $resolveInep,
                'ano_microdados' => $preferYear,
            ],
        ];

        $payload = [
            'meta' => $meta,
            'pontos' => $allPontos,
        ];

        $extraParts = $bootstrapNotes;
        if ($errors !== []) {
            $extraParts[] = __('Gravado com avisos:')."\n".implode("\n", $errors);
        }
        $extra = $extraParts !== [] ? implode("\n\n", $extraParts) : null;

        return $this->writer->persistHistoricoJson(
            $payload,
            'saeb:official-municipal',
            $urlsTentadas,
            $extra
        );
    }

    /**
     * @param  Collection<int, City>  $cities
     * @return Collection<int, City>
     */
    private function citiesWithoutSaebPoints(Collection $cities): Collection
    {
        return $cities->filter(function (City $city): bool {
            $ibge = preg_replace('/\D/', '', (string) ($city->ibge_municipio ?? '')) ?? '';

            return strlen($ibge) === 7 && ! $this->historicoDb->hasPointsForIbge($ibge);
        })->values();
    }

    /**
     * @param  list<array<string, mixed>>  $pontos
     * @return list<array<string, mixed>>
     */
    private function resolveEscolaIdsOnPontos(array $pontos, City $city, bool $resolveInep): array
    {
        if (! $resolveInep) {
            return $pontos;
        }

        $out = [];
        foreach ($pontos as $p) {
            if (! is_array($p)) {
                continue;
            }
            $eid = isset($p['escola_id']) && is_numeric($p['escola_id']) ? (int) $p['escola_id'] : 0;
            if ($eid <= 0) {
                $inep = isset($p['inep']) && is_numeric($p['inep'])
                    ? (int) $p['inep']
                    : (isset($p['inep_escola']) && is_numeric($p['inep_escola']) ? (int) $p['inep_escola'] : 0);
                if ($inep > 0) {
                    $cod = $this->inepResolver->resolve($city, $inep);
                    if ($cod !== null) {
                        $p['escola_id'] = $cod;
                    }
                }
            }
            $out[] = $p;
        }

        return $out;
    }

    private function defaultPreferYear(): int
    {
        $cfg = config('ieducar.saeb.official_prefer_year');
        if (is_int($cfg) && $cfg >= 2000 && $cfg <= 2100) {
            return $cfg;
        }

        return max(2000, (int) date('Y') - 1);
    }

    /**
     * Se IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE estiver vazio, usa a API desta aplicação (requer APP_URL correcto em produção).
     */
    private function resolveOfficialUrlTemplate(): string
    {
        $t = trim((string) config('ieducar.saeb.official_url_template', ''));
        if ($t !== '' && str_contains($t, '{ibge}')) {
            return $t;
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl === '' || ! str_starts_with($appUrl, 'http')) {
            return '';
        }

        return $appUrl.'/api/saeb/municipio/{ibge}.json';
    }

    private function expandTemplate(string $template, City $city, string $ibge): string
    {
        return str_replace(
            ['{ibge}', '{uf}', '{city_id}'],
            [$ibge, (string) $city->uf, (string) $city->getKey()],
            $template
        );
    }

    private function httpTimeoutSeconds(): int
    {
        $teto = max(15, min(180, (int) config('ieducar.saeb.official_timeout_seconds', 60)));

        return $teto;
    }

    private function templatePointsToInternalApi(string $template): bool
    {
        if (! str_contains($template, '/api/saeb/municipio/')) {
            return false;
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl === '' || ! str_starts_with($appUrl, 'http')) {
            return false;
        }

        $sample = str_replace('{ibge}', '0000000', $template);

        return $this->isSameApplicationSaebEndpointUrl($sample);
    }

    /**
     * URL resolvida aponta para o endpoint SAEB desta mesma aplicação (importação circular sem dados prévios em storage).
     */
    private function isSameApplicationSaebEndpointUrl(string $url): bool
    {
        if ($url === '' || ! str_contains($url, '/api/saeb/municipio/')) {
            return false;
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl === '' || ! str_starts_with($appUrl, 'http')) {
            return false;
        }

        $parsedApp = parse_url($appUrl);
        $parsedUrl = parse_url($url);
        $hostApp = strtolower((string) ($parsedApp['host'] ?? ''));
        $hostUrl = strtolower((string) ($parsedUrl['host'] ?? ''));
        if ($hostApp === '' || $hostUrl === '') {
            return false;
        }

        $hostsMatch = $hostApp === $hostUrl
            || $this->normalizeHostForCompare($hostApp) === $this->normalizeHostForCompare($hostUrl);

        return $hostsMatch;
    }

    private function normalizeHostForCompare(string $host): string
    {
        $host = strtolower($host);

        return Str::startsWith($host, 'www.') ? substr($host, 4) : $host;
    }

    private function usesDefaultAppUrlTemplate(string $template, bool $fromConfigOnly): bool
    {
        if (! $fromConfigOnly) {
            return false;
        }

        $t = trim((string) config('ieducar.saeb.official_url_template', ''));

        return $t === '' && $template === $this->defaultOfficialTemplateFromAppUrl();
    }

    private function defaultOfficialTemplateFromAppUrl(): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl === '' || ! str_starts_with($appUrl, 'http')) {
            return '';
        }

        return $appUrl.'/api/saeb/municipio/{ibge}.json';
    }
}
