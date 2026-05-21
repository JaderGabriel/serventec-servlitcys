<?php

namespace App\Services\Inep;

/**
 * Cadeia TLS do download.inep.gov.br (RNP ICPEdu — CA recente, por vezes ausente no ca-certificates do SO).
 */
final class SaebInepDownloadCaBundle
{
    public static function bundledChainPath(): string
    {
        return resource_path('certs/inep-download-chain.pem');
    }

    public static function storageMergedPath(): string
    {
        return storage_path('app/certs/saeb-ca-bundle.pem');
    }

    /**
     * @return list<string> Caminhos PEM legíveis, por ordem de prioridade.
     */
    public static function candidatePaths(): array
    {
        $paths = [];

        $configured = trim((string) config('ieducar.saeb.microdados_http_ca_bundle', ''));
        if ($configured !== '' && is_readable($configured)) {
            $paths[] = $configured;
        }

        foreach ([self::storageMergedPath(), self::bundledChainPath()] as $path) {
            if (is_readable($path)) {
                $paths[] = $path;
            }
        }

        foreach (SaebMicrodadosInepDownloader::collectSystemCaBundleCandidates() as $path) {
            $paths[] = $path;
        }

        /** @var list<string> */
        return array_values(array_unique($paths));
    }

    /**
     * Obtém a cadeia servida pelo host INEP e grava bundle merged (SO + cadeia) em storage.
     *
     * @throws \RuntimeException
     */
    public static function refreshFromHost(string $host = 'download.inep.gov.br', int $port = 443): string
    {
        $chain = self::fetchPemChainFromHost($host, $port);
        $dir = dirname(self::storageMergedPath());
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException(__('Não foi possível criar :dir.', ['dir' => $dir]));
        }

        $parts = [];
        foreach (SaebMicrodadosInepDownloader::collectSystemCaBundleCandidates() as $systemCa) {
            $content = @file_get_contents($systemCa);
            if (is_string($content) && trim($content) !== '') {
                $parts[] = trim($content);
            }
            break;
        }
        $parts[] = trim($chain);

        $merged = implode("\n", $parts)."\n";
        if (file_put_contents(self::storageMergedPath(), $merged) === false) {
            throw new \RuntimeException(__('Não foi possível gravar o bundle CA em storage.'));
        }

        return self::storageMergedPath();
    }

    /**
     * @throws \RuntimeException
     */
    public static function fetchPemChainFromHost(string $host, int $port = 443): string
    {
        $cmd = sprintf(
            'openssl s_client -connect %s:%d -servername %s -showcerts </dev/null 2>/dev/null',
            escapeshellarg($host),
            $port,
            escapeshellarg($host)
        );

        $output = shell_exec($cmd);
        if (! is_string($output) || $output === '') {
            throw new \RuntimeException(__('openssl s_client não devolveu certificados para :host.', ['host' => $host]));
        }

        if (preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $output, $matches) < 1) {
            throw new \RuntimeException(__('Nenhum certificado PEM encontrado na resposta de :host.', ['host' => $host]));
        }

        return implode("\n", $matches[0])."\n";
    }

    public static function sslFailureHint(): string
    {
        $hints = [
            __('O INEP (download.inep.gov.br) usa certificados RNP recentes; o pacote ca-certificates do servidor pode estar desactualizado.'),
            __('Soluções: (1) php artisan saeb:refresh-ca-bundle — (2) IEDUCAR_SAEB_HTTP_CA_BUNDLE=/caminho/para.pem — (3) actualizar ca-certificates no SO — (4) só em dev: IEDUCAR_SAEB_HTTP_INSECURE_FALLBACK=true.'),
        ];

        return implode(' ', $hints);
    }
}
