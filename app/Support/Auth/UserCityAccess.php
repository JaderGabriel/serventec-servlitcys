<?php

namespace App\Support\Auth;

use App\Enums\UserRole;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class UserCityAccess
{
    /**
     * @return Builder<City>
     */
    public static function citiesQuery(User $user): Builder
    {
        $query = City::query()->forAnalytics()->orderBy('name');

        if ($user->role() === UserRole::Municipal) {
            $ids = $user->cityIds();
            if ($ids === []) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn('id', $ids);
        }

        return $query;
    }

    /**
     * @param  list<int>  $cityIds
     * @return list<int>
     */
    public static function sanitizeCityIdsForActor(User $actor, array $cityIds): array
    {
        $cityIds = array_values(array_unique(array_map('intval', $cityIds)));
        $cityIds = array_filter($cityIds, static fn (int $id) => $id > 0);

        if ($actor->role() === UserRole::Admin) {
            return $cityIds;
        }

        if ($actor->role() === UserRole::Municipal) {
            $allowed = $actor->cityIds();

            return array_values(array_intersect($cityIds, $allowed));
        }

        return [];
    }
}
