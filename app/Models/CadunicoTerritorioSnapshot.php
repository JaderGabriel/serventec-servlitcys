<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Agregados territoriais CadÚnico / IBGE / SUAS — sem endereço individual.
 */
class CadunicoTerritorioSnapshot extends Model
{
    protected $table = 'cadunico_territorio_snapshots';

    protected $fillable = [
        'ibge_municipio',
        'ano_referencia',
        'territorio_codigo',
        'territorio_nome',
        'territorio_tipo',
        'criancas_4_17',
        'criancas_4_5',
        'criancas_6_10',
        'criancas_11_14',
        'criancas_15_17',
        'familias_beneficio',
        'indice_vulnerabilidade',
        'latitude',
        'longitude',
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
            'criancas_4_17' => 'integer',
            'criancas_4_5' => 'integer',
            'criancas_6_10' => 'integer',
            'criancas_11_14' => 'integer',
            'criancas_15_17' => 'integer',
            'familias_beneficio' => 'integer',
            'indice_vulnerabilidade' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'metadados' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function totalEscolar(): int
    {
        if ($this->criancas_4_17 > 0) {
            return (int) $this->criancas_4_17;
        }

        return (int) $this->criancas_4_5
            + (int) $this->criancas_6_10
            + (int) $this->criancas_11_14
            + (int) $this->criancas_15_17;
    }
}
