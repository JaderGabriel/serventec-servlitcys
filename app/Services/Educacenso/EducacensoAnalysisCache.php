<?php

namespace App\Services\Educacenso;

use App\Models\City;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class EducacensoAnalysisCache
{
    public static function key(User $user, City $city): string
    {
        return 'educacenso:analysis:'.$user->getKey().':'.$city->getKey();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(User $user, City $city): ?array
    {
        $cached = Cache::get(self::key($user, $city));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function put(User $user, City $city, array $report): void
    {
        $hours = max(1, (int) config('educacenso.cache_ttl_hours', 24));
        Cache::put(self::key($user, $city), $report, now()->addHours($hours));
    }

    public static function forget(User $user, City $city): void
    {
        Cache::forget(self::key($user, $city));
    }
}
