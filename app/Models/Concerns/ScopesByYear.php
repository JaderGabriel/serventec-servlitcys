<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ScopesByYear
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where($this->yearColumn(), $year);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForYears(Builder $query, array $years): Builder
    {
        if ($years === []) {
            return $query;
        }

        return $query->whereIn($this->yearColumn(), $years);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBetweenYears(Builder $query, int $from, int $to): Builder
    {
        return $query->whereBetween($this->yearColumn(), [$from, $to]);
    }

    protected function yearColumn(): string
    {
        return 'ano';
    }
}
