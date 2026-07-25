<?php

namespace App\Models;

use App\Models\Concerns\ScopesByIbge;
use App\Models\Concerns\ScopesByYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Emenda parlamentar (educação) ligada ao município via localidadeDoGasto do Portal.
 *
 * @property int $id
 * @property int|null $city_id
 * @property string $ibge_municipio
 * @property int $ano
 * @property string $codigo_emenda
 * @property string|null $numero_emenda
 * @property string|null $tipo_emenda
 * @property string|null $autor
 * @property string|null $localidade_do_gasto
 * @property string|null $funcao
 * @property string|null $subfuncao
 * @property float|null $valor_empenhado
 * @property float|null $valor_liquidado
 * @property float|null $valor_pago
 * @property float|null $valor_resto_inscrito
 * @property float|null $valor_resto_cancelado
 * @property float|null $valor_resto_pago
 * @property array|null $documentos
 * @property array|null $payload
 * @property string $fonte
 * @property \Illuminate\Support\Carbon|null $imported_at
 */
final class MunicipalEmendaSnapshot extends Model
{
    use ScopesByIbge;
    use ScopesByYear;

    protected $table = 'municipal_emenda_snapshots';

    protected $fillable = [
        'city_id',
        'ibge_municipio',
        'ano',
        'codigo_emenda',
        'numero_emenda',
        'tipo_emenda',
        'autor',
        'localidade_do_gasto',
        'funcao',
        'subfuncao',
        'valor_empenhado',
        'valor_liquidado',
        'valor_pago',
        'valor_resto_inscrito',
        'valor_resto_cancelado',
        'valor_resto_pago',
        'documentos',
        'payload',
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
            'valor_empenhado' => 'float',
            'valor_liquidado' => 'float',
            'valor_pago' => 'float',
            'valor_resto_inscrito' => 'float',
            'valor_resto_cancelado' => 'float',
            'valor_resto_pago' => 'float',
            'documentos' => 'array',
            'payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
