<?php

namespace App\Services\Inep;

use App\Support\Inep\SaebExplicacaoModalBuilder;
use Illuminate\Support\Facades\Http;

/**
 * Grava séries SAEB na base de dados (tabela saeb_indicator_points) — importação por URL ou payload já montado.
 */
class SaebPedagogicalImportService
{
    public function __construct(
        private SaebHistoricoDatabase $historicoDb,
    ) {}

    /**
     * Tenta cada URL em IEDUCAR_SAEB_IMPORT_URLS até obter JSON com «pontos» não vazio. Não utiliza dados de demonstração.
     *
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string}
     */
    public function importFromConfiguredSources(): array
    {
        $attempts = [];
        $urls = $this->importUrlList();

        if ($urls === []) {
            return [
                'ok' => false,
                'message' => __(
                    'Nenhuma URL configurada (IEDUCAR_SAEB_IMPORT_URLS). Use a sincronização oficial por município ou defina uma URL que devolva JSON com a chave «pontos».'
                ),
                'fonte_efetiva' => null,
                'path' => SaebHistoricoDatabase::STORAGE_LABEL,
            ];
        }

        foreach ($urls as $url) {
            $attempts[] = $url;
            try {
                $resp = Http::timeout($this->httpTimeoutSeconds())
                    ->withHeaders([
                        'User-Agent' => 'ServLitcys-SAEB-Import/1.0',
                        'Accept' => 'application/json, text/plain;q=0.9, */*;q=0.8',
                    ])
                    ->acceptJson()
                    ->get($url);
                if ($resp->successful()) {
                    $decoded = json_decode($resp->body(), true);
                    if ($this->isValidPayload($decoded)) {
                        return $this->writePayloadToDisk($decoded, $url, $attempts, null);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [
            'ok' => false,
            'message' => __(
                'Nenhuma URL devolveu JSON válido (chave «pontos» ou «points» com pelo menos um item). Verifique a rede e o formato.'
            ),
            'fonte_efetiva' => null,
            'path' => SaebHistoricoDatabase::STORAGE_LABEL,
        ];
    }

    /**
     * Persiste um payload já validado (usado pela importação oficial por IBGE).
     *
     * @param  array<string, mixed>  $decoded
     * @param  list<string>  $attempts
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string}
     */
    public function persistHistoricoJson(array $decoded, string $fonteEfetiva, array $attempts = [], ?string $extraMessage = null): array
    {
        if (! $this->isValidPayload($decoded)) {
            return [
                'ok' => false,
                'message' => __('O JSON não contém «pontos» válidos.'),
                'fonte_efetiva' => null,
                'path' => SaebHistoricoDatabase::STORAGE_LABEL,
            ];
        }

        return $this->writePayloadToDisk($decoded, $fonteEfetiva, $attempts, $extraMessage);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<string>  $attempts
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string}
     */
    private function writePayloadToDisk(array $decoded, string $fonteEfetiva, array $attempts, ?string $extraMessage = null): array
    {
        $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
        $meta['fonte_efetiva'] = $fonteEfetiva;
        $meta['fonte_tentativas'] = $attempts;
        $meta['importado_em'] = now()->toIso8601String();
        $meta['observacao'] = trim((string) ($meta['observacao'] ?? ''));

        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? [];
        $pontos = is_array($pontos) ? $pontos : [];
        $newHash = SaebExplicacaoModalBuilder::hashConteudo($pontos);
        $prev = $meta['explicacao_modal'] ?? null;
        $prevHash = is_array($prev) ? ($prev['hash_conteudo'] ?? null) : null;

        if ($newHash !== $prevHash || ! is_array($prev) || empty($prev['secoes'])) {
            $meta['explicacao_modal'] = SaebExplicacaoModalBuilder::build($decoded, $fonteEfetiva, $attempts);
        } else {
            $meta['explicacao_modal'] = array_merge($prev, [
                'ultima_sincronizacao_em' => now()->toIso8601String(),
                'fonte_efetiva_ultima_sync' => $fonteEfetiva,
            ]);
        }

        $decoded['meta'] = $meta;

        $this->historicoDb->persistFullPayload($decoded);

        SaebMunicipioFilesWriter::syncFromDecodedPayload($decoded);

        $msg = $extraMessage ?? __('Importação concluída. Dados gravados na base (tabela saeb_indicator_points).');

        return [
            'ok' => true,
            'message' => $msg,
            'fonte_efetiva' => $fonteEfetiva,
            'path' => SaebHistoricoDatabase::STORAGE_LABEL,
        ];
    }

    private function isValidPayload(mixed $decoded): bool
    {
        if (! is_array($decoded)) {
            return false;
        }
        $pontos = $decoded['pontos'] ?? $decoded['points'] ?? null;
        if (! is_array($pontos) || $pontos === []) {
            return false;
        }

        return true;
    }

    /**
     * Apenas URLs explícitas em .env e, opcionalmente, import_url_defaults em config (sem APP_URL nem ficheiros de exemplo).
     *
     * @return list<string>
     */
    private function importUrlList(): array
    {
        $raw = trim((string) config('ieducar.saeb.import_urls', ''));
        if ($raw !== '') {
            $parts = array_map('trim', explode(',', $raw));

            return array_values(array_filter($parts, static fn (string $u): bool => $u !== '' && str_starts_with($u, 'http')));
        }

        $urls = [];
        foreach (config('ieducar.saeb.import_url_defaults', []) as $u) {
            if (is_string($u) && trim($u) !== '' && str_starts_with(trim($u), 'http')) {
                $urls[] = trim($u);
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Por tentativa: não exceder import_attempt_timeout_seconds nem o teto import_timeout_seconds.
     */
    private function httpTimeoutSeconds(): int
    {
        $teto = max(10, min(180, (int) config('ieducar.saeb.import_timeout_seconds', 45)));
        $porTentativa = max(5, min(60, (int) config('ieducar.saeb.import_attempt_timeout_seconds', 12)));

        return min($teto, $porTentativa);
    }
}
