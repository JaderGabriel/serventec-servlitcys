<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MunicipalTransparencySnapshot extends Model
{
    protected $fillable = [
        'ibge_municipio',
        'ano',
        'convenios_ativos',
        'empenhos_educacao',
        'empenhos_tecnologia',
        'contratos_software',
        'highlights',
        'fonte',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'convenios_ativos' => 'integer',
            'empenhos_educacao' => 'float',
            'empenhos_tecnologia' => 'float',
            'contratos_software' => 'integer',
            'highlights' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
