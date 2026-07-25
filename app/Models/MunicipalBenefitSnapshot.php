<?php

namespace App\Models;

use App\Models\Concerns\ScopesByIbge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agregados mensais PBF / Novo Bolsa Família / BPC por IBGE (Portal da Transparência).
 * Sem NIS/CPF — só quantidade e valor do endpoint *-por-municipio.
 */
final class MunicipalBenefitSnapshot extends Model
{
    use ScopesByIbge;

    public const PROGRAMA_PBF = 'pbf';

    public const PROGRAMA_NBF = 'nbf';

    public const PROGRAMA_BPC = 'bpc';

    /** @var list<string> */
    public const PROGRAMAS = [
        self::PROGRAMA_PBF,
        self::PROGRAMA_NBF,
        self::PROGRAMA_BPC,
    ];

    protected $fillable = [
        'city_id',
        'ibge_municipio',
        'programa',
        'mes_ano',
        'quantidade_beneficiados',
        'valor',
        'data_referencia',
        'tipo_descricao',
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
            'city_id' => 'integer',
            'mes_ano' => 'integer',
            'quantidade_beneficiados' => 'integer',
            'valor' => 'float',
            'payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function mesAnoLabel(): string
    {
        $m = (int) ($this->mes_ano % 100);
        $y = (int) floor($this->mes_ano / 100);

        if ($m < 1 || $m > 12 || $y < 2000) {
            return (string) $this->mes_ano;
        }

        return sprintf('%02d/%04d', $m, $y);
    }
}
