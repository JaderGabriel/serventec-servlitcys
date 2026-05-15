<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundebMunicipioReference extends Model
{
    protected $fillable = [
        'city_id',
        'ibge_municipio',
        'ano',
        'vaaf',
        'vaat',
        'complementacao_vaar',
        'fonte',
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
}
