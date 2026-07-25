<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanção CEIS/CNEP/CEPIM de fornecedor curado (HOR-08g — due diligence).
 *
 * @property int $id
 * @property string $fonte
 * @property string $cnpj
 * @property string $external_id
 * @property string|null $nome
 * @property string|null $categoria
 * @property string|null $data_inicio
 * @property string|null $data_fim
 * @property string|null $orgao
 * @property string|null $vendor_label
 * @property array|null $payload
 * @property string $fonte_api
 * @property \Illuminate\Support\Carbon|null $imported_at
 */
final class PortalVendorSanctionSnapshot extends Model
{
    public const FONTE_CEIS = 'ceis';

    public const FONTE_CNEP = 'cnep';

    public const FONTE_CEPIM = 'cepim';

    protected $table = 'portal_vendor_sanction_snapshots';

    protected $fillable = [
        'fonte',
        'cnpj',
        'external_id',
        'nome',
        'categoria',
        'data_inicio',
        'data_fim',
        'orgao',
        'vendor_label',
        'payload',
        'fonte_api',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCnpj(Builder $query, string $cnpj): Builder
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?: '';

        return $query->where('cnpj', $digits);
    }
}
