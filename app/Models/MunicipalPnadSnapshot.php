<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MunicipalPnadSnapshot extends Model
{
    protected $fillable = [
        'ibge_municipio',
        'ano_referencia',
        'escolaridade_media',
        'pct_neet_jovem',
        'fonte',
        'metadados',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'ano_referencia' => 'integer',
            'escolaridade_media' => 'float',
            'pct_neet_jovem' => 'float',
            'metadados' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
