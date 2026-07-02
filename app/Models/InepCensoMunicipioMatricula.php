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
        'matriculas_regular_municipal',
        'matriculas_regular_nao_municipal',
        'matriculas_eja',
        'matriculas_eja_municipal',
        'matriculas_eja_nao_municipal',
        'matriculas_especial',
        'matriculas_especial_municipal',
        'matriculas_especial_nao_municipal',
        'matriculas_complementar',
        'matriculas_complementar_municipal',
        'matriculas_complementar_nao_municipal',
        'matriculas_infantil',
        'matriculas_infantil_municipal',
        'matriculas_infantil_nao_municipal',
        'matriculas_fundamental_1',
        'matriculas_fundamental_1_municipal',
        'matriculas_fundamental_1_nao_municipal',
        'matriculas_fundamental_2',
        'matriculas_fundamental_2_municipal',
        'matriculas_fundamental_2_nao_municipal',
        'matriculas_medio',
        'matriculas_medio_municipal',
        'matriculas_medio_nao_municipal',
        'matriculas_profissional',
        'matriculas_profissional_municipal',
        'matriculas_profissional_nao_municipal',
        'escolas_contagem',
        'fonte',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $integers = [
            'ano',
            'matriculas_total',
            'matriculas_municipal',
            'matriculas_nao_municipal',
            'matriculas_regular',
            'matriculas_regular_municipal',
            'matriculas_regular_nao_municipal',
            'matriculas_eja',
            'matriculas_eja_municipal',
            'matriculas_eja_nao_municipal',
            'matriculas_especial',
            'matriculas_especial_municipal',
            'matriculas_especial_nao_municipal',
            'matriculas_complementar',
            'matriculas_complementar_municipal',
            'matriculas_complementar_nao_municipal',
            'matriculas_infantil',
            'matriculas_infantil_municipal',
            'matriculas_infantil_nao_municipal',
            'matriculas_fundamental_1',
            'matriculas_fundamental_1_municipal',
            'matriculas_fundamental_1_nao_municipal',
            'matriculas_fundamental_2',
            'matriculas_fundamental_2_municipal',
            'matriculas_fundamental_2_nao_municipal',
            'matriculas_medio',
            'matriculas_medio_municipal',
            'matriculas_medio_nao_municipal',
            'matriculas_profissional',
            'matriculas_profissional_municipal',
            'matriculas_profissional_nao_municipal',
            'escolas_contagem',
        ];

        $casts = ['imported_at' => 'datetime'];
        foreach ($integers as $column) {
            $casts[$column] = 'integer';
        }

        return $casts;
    }
}
