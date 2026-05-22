<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalTransferSnapshot extends Model
{
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
}
