<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot financeiro de uma obra de educação (empenhos).
 *
 * @property int $id
 * @property string $id_projeto_investimento
 * @property string|null $fonte_orcamentaria
 * @property float|null $valor_empenho
 * @property float|null $valor_liquidado
 * @property float|null $valor_pago
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon|null $imported_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EducationWorkFinanceSnapshot extends Model
{
    protected $table = 'education_work_finance_snapshots';

    protected $fillable = [
        'id_projeto_investimento',
        'fonte_orcamentaria',
        'valor_empenho',
        'valor_liquidado',
        'valor_pago',
        'meta',
        'imported_at',
    ];

    protected $casts = [
        'valor_empenho' => 'decimal:2',
        'valor_liquidado' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'meta' => 'array',
        'imported_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(MunicipalEducationWork::class, 'id_projeto_investimento', 'id_projeto_investimento');
    }
}
