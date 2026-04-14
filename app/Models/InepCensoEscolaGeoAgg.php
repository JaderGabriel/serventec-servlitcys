<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InepCensoEscolaGeoAgg extends Model
{
    protected $table = 'inep_censo_escola_geo_agg';

    protected $primaryKey = 'inep_code';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'inep_code',
        'nu_ano_censo',
        'no_municipio',
        'sg_uf',
        'no_uf',
        'no_regiao',
        'tp_localizacao',
    ];

    protected function casts(): array
    {
        return [
            'inep_code' => 'integer',
            'nu_ano_censo' => 'integer',
            'tp_localizacao' => 'integer',
        ];
    }
}
