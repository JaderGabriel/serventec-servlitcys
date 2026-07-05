<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MunicipalFiscalSnapshot extends Model
{
    protected $fillable = [
        'ibge_municipio',
        'ano',
        'periodo',
        'receita_corrente_liquida',
        'despesa_educacao_liquidada',
        'pct_educacao_receita_corrente',
        'pct_minimo_constitucional',
        'divida_consolidada',
        'disponibilidade_caixa',
        'restos_pagar_processados',
        'restos_pagar_educacao',
        'receita_propria',
        'pct_receita_propria',
        'fiscal_capacity_score',
        'fonte',
        'metadados',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'periodo' => 'integer',
            'receita_corrente_liquida' => 'float',
            'despesa_educacao_liquidada' => 'float',
            'pct_educacao_receita_corrente' => 'float',
            'pct_minimo_constitucional' => 'float',
            'divida_consolidada' => 'float',
            'disponibilidade_caixa' => 'float',
            'restos_pagar_processados' => 'float',
            'restos_pagar_educacao' => 'float',
            'receita_propria' => 'float',
            'pct_receita_propria' => 'float',
            'fiscal_capacity_score' => 'integer',
            'metadados' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
