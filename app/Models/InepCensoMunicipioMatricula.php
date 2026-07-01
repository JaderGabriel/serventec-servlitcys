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
        'matriculas_regular',
        'matriculas_eja',
        'matriculas_especial',
        'matriculas_complementar',
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
            'matriculas_regular' => 'integer',
            'matriculas_eja' => 'integer',
            'matriculas_especial' => 'integer',
            'matriculas_complementar' => 'integer',
            'escolas_contagem' => 'integer',
            'imported_at' => 'datetime',
        ];
    }
}
