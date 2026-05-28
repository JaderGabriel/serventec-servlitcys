<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Agregados municipais CadÚnico (extração Cecad / MDs) — sem CPF/NIS individual.
 */
class CadunicoMunicipioSnapshot extends Model
{
    protected $table = 'cadunico_municipio_snapshots';

    protected $fillable = [
        'ibge_municipio',
        'ano_referencia',
        'pessoas_cadastradas',
        'familias_cadastradas',
        'criancas_0_3',
        'criancas_4_5',
        'criancas_6_10',
        'criancas_11_14',
        'criancas_15_17',
        'populacao_escolar_estimada',
        'fonte',
        'schema_version',
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
            'pessoas_cadastradas' => 'integer',
            'familias_cadastradas' => 'integer',
            'criancas_0_3' => 'integer',
            'criancas_4_5' => 'integer',
            'criancas_6_10' => 'integer',
            'criancas_11_14' => 'integer',
            'criancas_15_17' => 'integer',
            'populacao_escolar_estimada' => 'integer',
            'metadados' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function totalCriancasEscolaridade(): int
    {
        if ($this->populacao_escolar_estimada > 0) {
            return (int) $this->populacao_escolar_estimada;
        }

        return (int) $this->criancas_4_5
            + (int) $this->criancas_6_10
            + (int) $this->criancas_11_14
            + (int) $this->criancas_15_17;
    }
}
