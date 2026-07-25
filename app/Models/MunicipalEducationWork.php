<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Obras de educação importadas do Obrasgov (SIMEC/FNDE).
 *
 * @property int $id
 * @property string $id_projeto_investimento
 * @property string|null $ibge_municipio
 * @property string $ibge_confidence
 * @property string $uf_principal
 * @property string $situacao
 * @property string|null $especie_intervencao
 * @property string|null $natureza_intervencao
 * @property string|null $desc_nome
 * @property string|null $desc_meta_global
 * @property string|null $sistema_resp
 * @property string|null $organizacao_resp
 * @property string|null $cnpj_organizacao_resp
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $percentual_execucao_fisica
 * @property float|null $valor_empenhado
 * @property float|null $valor_pago
 * @property float|null $valor_previsto
 * @property \Illuminate\Support\Carbon|null $data_inicio
 * @property \Illuminate\Support\Carbon|null $data_paralisacao
 * @property \Illuminate\Support\Carbon|null $data_ultima_afericao
 * @property array|null $historico_paralisacao
 * @property array|null $meta_execucao
 * @property array|null $meta
 * @property string $fonte
 * @property \Illuminate\Support\Carbon|null $imported_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class MunicipalEducationWork extends Model
{
    protected $table = 'municipal_education_works';

    protected $fillable = [
        'id_projeto_investimento',
        'ibge_municipio',
        'ibge_confidence',
        'uf_principal',
        'situacao',
        'especie_intervencao',
        'natureza_intervencao',
        'desc_nome',
        'desc_meta_global',
        'sistema_resp',
        'organizacao_resp',
        'cnpj_organizacao_resp',
        'latitude',
        'longitude',
        'percentual_execucao_fisica',
        'valor_empenhado',
        'valor_pago',
        'valor_previsto',
        'data_inicio',
        'data_paralisacao',
        'data_ultima_afericao',
        'historico_paralisacao',
        'meta_execucao',
        'meta',
        'fonte',
        'imported_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'percentual_execucao_fisica' => 'decimal:2',
        'valor_empenhado' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'valor_previsto' => 'decimal:2',
        'data_inicio' => 'date',
        'data_paralisacao' => 'date',
        'data_ultima_afericao' => 'date',
        'historico_paralisacao' => 'array',
        'meta_execucao' => 'array',
        'meta' => 'array',
        'imported_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'ibge_municipio', 'ibge_municipio');
    }
}
