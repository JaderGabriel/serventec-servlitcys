<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InepCensoMunicipioMatricula extends Model
{
    protected $table = 'inep_censo_municipio_matriculas';

    protected $fillable = [
        'ibge_municipio',
        'ano',
        'matriculas_total',
        'matriculas_municipal',
        'matriculas_nao_municipal',
        'escolas_contagem',
        'fonte',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'matriculas_total' => 'integer',
            'matriculas_municipal' => 'integer',
            'matriculas_nao_municipal' => 'integer',
            'escolas_contagem' => 'integer',
            'imported_at' => 'datetime',
        ];
    }
}
