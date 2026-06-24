<?php

namespace App\Models;

use App\Models\Concerns\ScopesByIbge;
use App\Models\Concerns\ScopesByYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundebMunicipioReference extends Model
{
    use ScopesByIbge;
    use ScopesByYear;

    protected $fillable = [
        'city_id',
        'ibge_municipio',
        'ano',
        'vaaf',
        'vaat',
        'complementacao_vaar',
        'fonte',
        'tipo_valor',
        'receita_total',
        'complementacao_vaaf',
        'complementacao_vaat',
        'matriculas_base',
        'matriculas_fonte',
        'url_portaria',
        'meta',
        'notas',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'vaaf' => 'float',
            'vaat' => 'float',
            'complementacao_vaar' => 'float',
            'receita_total' => 'float',
            'complementacao_vaaf' => 'float',
            'complementacao_vaat' => 'float',
            'matriculas_base' => 'integer',
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
    public function scopeForCity(Builder $query, City $city): Builder
    {
        return $query->where('city_id', $city->id);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestYear(Builder $query): Builder
    {
        return $query->orderByDesc('ano');
    }
}
