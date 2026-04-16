<?php

namespace App\Services\Inep;

use App\Support\Inep\SaebExplicacaoModalBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Importa séries SAEB (JSON) para storage/app/public, com URL primária e fallbacks,
 * e cópia de recurso modelo em database/data/saeb_historico.example.json.
 */
class SaebPedagogicalImportService
{
    /**
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string}
     */
    public function importFromConfiguredSources(): array
    {
        $rel = $this->relativePath();
        $attempts = [];
        $urls = $this->importUrlList();

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
                        return $this->savePayload($decoded, $url, $attempts, $rel);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->copyExamplePayload(
            __('Nenhuma URL devolveu JSON válido; a usar o ficheiro modelo do repositório.'),
            $attempts,
            $rel
        );
    }

    /**
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string}
     */
    public function copyExampleOnly(): array
    {
        return $this->copyExamplePayload(__('Modelo copiado a partir de database/data/saeb_historico.example.json.'), [], $this->relativePath());
    }

    /**
     * @param  list<string>  $attempts
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string}
     */
    private function copyExamplePayload(string $message, array $attempts, string $rel): array
    {
        $examplePath = base_path('database/data/saeb_historico.example.json');
        if (! is_readable($examplePath)) {
            return [
                'ok' => false,
                'message' => __('Ficheiro modelo não encontrado: :path', ['path' => $examplePath]),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }

        $raw = file_get_contents($examplePath);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded) || ! $this->isValidPayload($decoded)) {
            return [
                'ok' => false,
                'message' => __('O ficheiro modelo não tem um formato válido (pontos).'),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }

        $label = 'database/data/saeb_historico.example.json';

        return $this->savePayload($decoded, $label, $attempts, $rel, $message);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<string>  $attempts
     * @return array{ok: bool, message: string, fonte_efetiva: ?string, path: string}
     */
    private function savePayload(array $decoded, string $fonteEfetiva, array $attempts, string $rel, ?string $extraMessage = null): array
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

        $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return [
                'ok' => false,
                'message' => __('Não foi possível serializar o JSON (codificação).'),
                'fonte_efetiva' => null,
                'path' => $rel,
            ];
        }
        Storage::disk('public')->put($rel, $json);

        $abs = storage_path('app/public/'.$rel);
        $msg = $extraMessage ?? __('Importação concluída. Dados gravados em :path.', ['path' => $abs]);

        return [
            'ok' => true,
            'message' => $msg,
            'fonte_efetiva' => $fonteEfetiva,
            'path' => $rel,
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
        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl !== '' && str_starts_with($appUrl, 'http')) {
            $urls[] = $appUrl.'/saeb/historico.example.json';
        }

        foreach (config('ieducar.saeb.import_url_defaults', []) as $u) {
            if (is_string($u) && trim($u) !== '' && str_starts_with(trim($u), 'http')) {
                $urls[] = trim($u);
            }
        }

        return array_values(array_unique($urls));
    }

    private function relativePath(): string
    {
        return trim((string) config('ieducar.saeb.json_path', 'saeb/historico.json')) ?: 'saeb/historico.json';
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
