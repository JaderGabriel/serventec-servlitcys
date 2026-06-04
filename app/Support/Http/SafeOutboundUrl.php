<?php

namespace App\Support\Http;

/**
 * Valida URLs HTTP(S) de saída para reduzir risco de SSRF em downloads configuráveis (CSV, CKAN).
 */
final class SafeOutboundUrl
{
    private const BLOCKED_HOSTS = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '[::1]',
        'metadata.google.internal',
    ];

    public static function isAllowedHttpUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || self::isBlockedHost($host)) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        $resolved = @gethostbyname($host);
        if ($resolved === '' || $resolved === $host) {
            return true;
        }

        return self::isPublicIp($resolved);
    }

    private static function isBlockedHost(string $host): bool
    {
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return true;
        }

        return str_starts_with($host, '127.') || str_starts_with($host, '10.')
            || str_starts_with($host, '192.168.') || str_starts_with($host, '169.254.');
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
