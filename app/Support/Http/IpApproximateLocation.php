<?php

namespace App\Support\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geolocalização aproximada por IP (cacheada; sem API key).
 */
final class IpApproximateLocation
{
    public function label(?string $ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return null;
        }

        if ($this->isNonRoutable($ip)) {
            return __('Rede local ou servidor');
        }

        if (! (bool) config('session.ip_geo_lookup', true)) {
            return null;
        }

        return Cache::remember(
            'session-ip-geo:'.md5($ip),
            now()->addHours((int) config('session.ip_geo_cache_hours', 24)),
            fn (): ?string => $this->fetchLabel($ip),
        );
    }

    private function isNonRoutable(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function fetchLabel(string $ip): ?string
    {
        try {
            $response = Http::timeout(4)
                ->acceptJson()
                ->get('https://ipwho.is/'.$ip);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (! is_array($data) || ! ($data['success'] ?? false)) {
                return null;
            }

            $parts = array_values(array_filter([
                is_string($data['city'] ?? null) ? trim($data['city']) : null,
                is_string($data['region'] ?? null) ? trim($data['region']) : null,
                is_string($data['country'] ?? null) ? trim($data['country']) : null,
            ]));

            if ($parts === []) {
                return null;
            }

            return implode(', ', $parts);
        } catch (\Throwable $e) {
            Log::debug('session.ip_geo_failed', ['ip' => $ip, 'message' => $e->getMessage()]);

            return null;
        }
    }
}
