<?php

namespace App\Services\Admin;

use App\Support\Admin\PublicDataAvailabilityPresenter;
use Illuminate\Support\Facades\Cache;

final class PublicDataOfficialCheckCache
{
    private const CACHE_KEY = 'public_data_official_check:last';

    /**
     * @param  array<string, mixed>  $report
     */
    public static function put(array $report): void
    {
        $ttl = max(60, (int) config('public_data_availability.cache_ttl', 86400));

        Cache::put(self::CACHE_KEY, $report, now()->addSeconds($ttl));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (! is_array($cached)) {
            return null;
        }

        return PublicDataAvailabilityPresenter::enrichReport($cached);
    }
}
