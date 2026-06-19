<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** População municipal agregada (IBGE SIDRA) — sem dado individual. */
class MunicipalDemographySnapshot extends Model
{
    protected $table = 'municipal_demography_snapshots';

    protected $fillable = [
        'ibge_municipio',
        'ano_referencia',
        'populacao_4_17',
        'populacao_total',
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
            'populacao_4_17' => 'integer',
            'populacao_total' => 'integer',
            'metadados' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
