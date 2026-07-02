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
        'matriculas_infantil',
        'matriculas_fundamental_1',
        'matriculas_fundamental_2',
        'matriculas_medio',
        'matriculas_profissional',
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
            'matriculas_infantil' => 'integer',
            'matriculas_fundamental_1' => 'integer',
            'matriculas_fundamental_2' => 'integer',
            'matriculas_medio' => 'integer',
            'matriculas_profissional' => 'integer',
            'escolas_contagem' => 'integer',
            'imported_at' => 'datetime',
        ];
    }
}
