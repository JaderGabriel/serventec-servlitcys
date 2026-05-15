<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundebMunicipioReference extends Model
{
    protected $fillable = [
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
}
