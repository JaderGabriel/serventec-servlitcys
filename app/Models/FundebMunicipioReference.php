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
        'tipo_valor',
        'receita_total',
        'complementacao_vaaf',
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
}
