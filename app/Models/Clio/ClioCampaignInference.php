<?php

namespace App\Models\Clio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClioCampaignInference extends Model
{
    protected $table = 'clio_campaign_inferences';

    protected $fillable = [
        'campaign_id',
        'code',
        'summary',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ClioCampaign::class, 'campaign_id');
    }

    public function label(): string
    {
        return match ($this->code) {
            'INF-COL' => __('Coleta'),
            'INF-ESC' => __('Rede escolar'),
            'INF-MAT' => __('Matrícula'),
            'INF-TUR' => __('Turmas'),
            'INF-DOC' => __('Profissionais'),
            'INF-NEE' => __('Inclusão / NEE'),
            'INF-COE' => __('Coerência'),
            'INF-DUP' => __('Duplicidades'),
            'INF-DELTA' => __('Delta Acomp × Relações'),
            'INF-GAP' => __('Gap × i-Educar'),
            default => $this->code,
        };
    }
}
