<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MunicipalAreaSnapshot extends Model
{
    protected $table = 'municipal_area_snapshots';

    protected $fillable = [
        'ibge_municipio',
        'ano_referencia',
        'area_km2',
        'fonte',
        'metadados',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ano_referencia' => 'integer',
            'area_km2' => 'decimal:3',
            'metadados' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
