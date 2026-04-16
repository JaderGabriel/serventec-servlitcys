<?php

namespace App\Services\Inep;

use App\Models\City;
use App\Support\Inep\SaebOfficialPayloadParser;
use Illuminate\Support\Facades\Http;

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
        $template = trim((string) config('ieducar.saeb.official_url_template', ''));

        if ($template === '' || ! str_contains($template, '{ibge}')) {
            return [
                'ok' => false,
                'message' => __(
                    'Configure IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE no .env com o placeholder {ibge} (código de 7 dígitos do município).'
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

        $allPontos = [];
        $errors = [];
        $urlsTentadas = [];

        foreach ($cities as $city) {
            /** @var City $city */
            $ibge = (string) $city->ibge_municipio;
            $url = $this->expandTemplate($template, $city, $ibge);
            $urlsTentadas[] = $url;

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
            } catch (\Throwable $e) {
                $errors[] = __(':nome (IBGE :ibge): :msg', [
                    'nome' => $city->name,
                    'ibge' => $ibge,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        if ($allPontos === []) {
            $msg = __('Nenhum município devolveu dados SAEB válidos.')."\n".implode("\n", $errors);

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
}
