<?php

namespace App\Services\Inep;

use App\Models\City;
use App\Support\Inep\SaebMunicipioPayloadReader;
use App\Support\Inep\SaebOfficialPayloadParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Obtém séries SAEB por município (código IBGE) para cada cidade cadastrada e grava um único JSON agregado.
 */
class SaebOfficialMunicipalImportService
{
    public function __construct(
        private SaebPedagogicalImportService $writer,
    ) {}

    /**
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string, detalhes?: array<string, mixed>}
     */
    public function importFromOfficialTemplate(): array
    {
        $rel = $this->relativePath();
        $template = $this->resolveOfficialUrlTemplate();

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

        $cities = City::query()
            ->forAnalytics()
            ->whereNotNull('ibge_municipio')
            ->orderBy('id')
            ->get();

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

        $useInternalFirst = filter_var(config('ieducar.saeb.official_use_internal_storage_first', true), FILTER_VALIDATE_BOOLEAN);

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
                    $errors[] = __(':nome (IBGE :ibge): sem dados SAEB em storage para este código; o endpoint da própria aplicação (:url) só devolve JSON já importado (HTTP 404 até lá). Coloque ficheiros em storage/app/public/saeb/municipio/:ibge.json ou pontos em :hist com municipio_ibge ou city_ids, ou use primeiro «Importar de IEDUCAR_SAEB_IMPORT_URLS» / defina IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE com uma URL externa.', [
                        'nome' => $city->name,
                        'ibge' => $ibge,
                        'url' => $url,
                        'hist' => $this->relativePath(),
                    ]);

                    continue;
                }

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
            } else {
                $urlsTentadas[] = __('Leitura interna (storage) para IBGE :ibge', ['ibge' => $ibge]);
            }

            $pontos = SaebOfficialPayloadParser::pontosForCity($decoded, $city);
            if ($pontos === []) {
                $errors[] = __(':nome (IBGE :ibge): JSON sem pontos reconhecíveis (chaves «pontos» ou «resultados»).', [
                    'nome' => $city->name,
                    'ibge' => $ibge,
                ]);

                continue;
            }

            foreach ($pontos as $p) {
                $allPontos[] = $p;
            }
        }

        if ($allPontos === []) {
            $hint = '';
            if ($this->usesDefaultAppUrlTemplate($template)) {
                $hint = "\n\n".__('Nota: com IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE vazio, a importação usa APP_URL + /api/saeb/municipio/{ibge}.json — isso só funciona depois de existir JSON em storage (ou use uma URL externa real no .env).');
            }
            $msg = __('Nenhum município devolveu dados SAEB válidos.').$hint."\n".implode("\n", $errors);

            return [
                'ok' => false,
                'message' => $msg,
                'fonte_efetiva' => null,
                'path' => $rel,
                'detalhes' => ['erros' => $errors, 'urls' => $urlsTentadas],
            ];
        }

        $meta = [
            'descricao' => __('Séries SAEB agregadas por município (código IBGE) para as cidades cadastradas.'),
            'fonte' => __('Fontes oficiais (INEP / dados abertos) conforme URL configurada por município.'),
            'importacao_oficial' => [
                'cidades_processadas' => $cities->count(),
                'pontos_gravados' => count($allPontos),
                'erros_parciais' => $errors,
            ],
        ];

        $payload = [
            'meta' => $meta,
            'pontos' => $allPontos,
        ];

        $extra = $errors !== []
            ? __('Gravado com avisos:')."\n".implode("\n", $errors)
            : null;

        return $this->writer->persistHistoricoJson(
            $payload,
            'saeb:official-municipal',
            $urlsTentadas,
            $extra
        );
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

    private function relativePath(): string
    {
        return trim((string) config('ieducar.saeb.json_path', 'saeb/historico.json')) ?: 'saeb/historico.json';
    }

    private function httpTimeoutSeconds(): int
    {
        $teto = max(15, min(180, (int) config('ieducar.saeb.official_timeout_seconds', 60)));

        return $teto;
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

    private function usesDefaultAppUrlTemplate(string $template): bool
    {
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
