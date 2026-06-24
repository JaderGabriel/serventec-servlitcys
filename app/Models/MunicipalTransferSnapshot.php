<?php

namespace App\Models;

use App\Models\Concerns\ScopesByIbge;
use App\Models\Concerns\ScopesByYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class MunicipalTransferSnapshot extends Model
{
    use ScopesByIbge;
    use ScopesByYear;

    protected $fillable = [
        'city_id',
        'ibge_municipio',
        'ano',
        'fonte',
        'programa_id',
        'programa_label',
        'valor',
        'moeda',
        'meta',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'valor' => 'float',
            'meta' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForFonte(Builder $query, string $fonte): Builder
    {
        return $query->where('fonte', $fonte);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPrograma(Builder $query, string $programaId): Builder
    {
        return $query->where('programa_id', $programaId);
    }
}
