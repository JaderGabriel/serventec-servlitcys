<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ScopesByIbge
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForIbge(Builder $query, string $ibge): Builder
    {
        return $query->where('ibge_municipio', $ibge);
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<string>  $ibges
     * @return Builder<static>
     */
    public function scopeForIbges(Builder $query, array $ibges): Builder
    {
        if ($ibges === []) {
            return $query;
        }

        return $query->whereIn('ibge_municipio', $ibges);
    }
}
