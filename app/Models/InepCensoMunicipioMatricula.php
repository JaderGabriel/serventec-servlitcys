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
            'escolas_contagem' => 'integer',
            'imported_at' => 'datetime',
        ];
    }
}
