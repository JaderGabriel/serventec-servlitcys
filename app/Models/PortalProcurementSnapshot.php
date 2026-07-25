<?php

namespace App\Models;

use App\Models\Concerns\ScopesByYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrato ou licitação do Portal (órgão SIAFI — MEC/FNDE), cache Horizonte HOR-08d/e.
 *
 * @property int $id
 * @property string $tipo
 * @property int $ano
 * @property string $codigo_orgao
 * @property string|null $orgao_sigla
 * @property string|null $orgao_nome
 * @property string $external_id
 * @property string|null $numero
 * @property string|null $objeto
 * @property string|null $situacao
 * @property string|null $modalidade
 * @property float|null $valor
 * @property float|null $valor_final
 * @property string|null $data_assinatura
 * @property string|null $data_inicio_vigencia
 * @property string|null $data_fim_vigencia
 * @property string|null $data_publicacao
 * @property string|null $fornecedor_cnpj
 * @property string|null $fornecedor_nome
 * @property string|null $ibge_municipio
 * @property string|null $municipio_nome
 * @property string|null $uf
 * @property string|null $ug_codigo
 * @property string|null $ug_nome
 * @property bool $vendor_matched
 * @property string|null $vendor_label
 * @property bool $itens_software
 * @property array|null $itens
 * @property array|null $payload
 * @property string $fonte
 * @property \Illuminate\Support\Carbon|null $imported_at
 */
final class PortalProcurementSnapshot extends Model
{
    use ScopesByYear;

    public const TIPO_CONTRATO = 'contrato';

    public const TIPO_LICITACAO = 'licitacao';

    protected $table = 'portal_procurement_snapshots';

    protected $fillable = [
        'tipo',
        'ano',
        'codigo_orgao',
        'orgao_sigla',
        'orgao_nome',
        'external_id',
        'numero',
        'objeto',
        'situacao',
        'modalidade',
        'valor',
        'valor_final',
        'data_assinatura',
        'data_inicio_vigencia',
        'data_fim_vigencia',
        'data_publicacao',
        'fornecedor_cnpj',
        'fornecedor_nome',
        'ibge_municipio',
        'municipio_nome',
        'uf',
        'ug_codigo',
        'ug_nome',
        'vendor_matched',
        'vendor_label',
        'itens_software',
        'itens',
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
            'valor' => 'float',
            'valor_final' => 'float',
            'vendor_matched' => 'boolean',
            'itens_software' => 'boolean',
            'itens' => 'array',
            'payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeContratos(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_CONTRATO);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLicitacoes(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_LICITACAO);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVendorMatched(Builder $query): Builder
    {
        return $query->where('vendor_matched', true);
    }
}
