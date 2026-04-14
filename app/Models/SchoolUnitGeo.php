<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolUnitGeo extends Model
{
    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    protected $fillable = [
        'city_id',
        'escola_id',
        'inep_code',
        'lat',
        'lng',
        'ieducar_lat',
        'ieducar_lng',
        'ieducar_seen_at',
        'official_lat',
        'official_lng',
        'official_source',
        'official_seen_at',
        'has_divergence',
        'divergence_meters',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'city_id' => 'integer',
            'escola_id' => 'integer',
            'inep_code' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'ieducar_lat' => 'float',
            'ieducar_lng' => 'float',
            'ieducar_seen_at' => 'datetime',
            'official_lat' => 'float',
            'official_lng' => 'float',
            'official_seen_at' => 'datetime',
            'has_divergence' => 'boolean',
            'divergence_meters' => 'float',
            'meta' => 'array',
        ];
    }
}
