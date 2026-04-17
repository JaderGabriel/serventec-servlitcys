<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ponto bruto SAEB (série) por importação — alimenta os gráficos Desempenho após normalização.
 */
class SaebIndicatorPoint extends Model
{
    protected $table = 'saeb_indicator_points';

    protected $fillable = [
        'city_id',
        'ibge_municipio',
        'ano',
        'disciplina',
        'etapa',
        'valor',
        'fonte',
        'payload',
        'dedupe_key',
        'raw_point',
        'series_key',
        'is_final',
        'status',
        'escola_id',
        'escola_ids',
        'city_ids',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'valor' => 'decimal:4',
            'payload' => 'array',
            'raw_point' => 'array',
            'escola_ids' => 'array',
            'city_ids' => 'array',
            'is_final' => 'boolean',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
