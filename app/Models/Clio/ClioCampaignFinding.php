<?php

namespace App\Models\Clio;

use App\Services\Clio\Support\ClioUserCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClioCampaignFinding extends Model
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_ERROR = 'error';

    protected $table = 'clio_campaign_findings';

    protected $fillable = [
        'campaign_id',
        'school_id',
        'artifact_id',
        'code',
        'severity',
        'message',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ClioCampaign::class, 'campaign_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(ClioCampaignSchool::class, 'school_id');
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(ClioCampaignArtifact::class, 'artifact_id');
    }

    public function severityLabel(): string
    {
        return ClioUserCopy::severityLabel((string) $this->severity);
    }

    public function severityHint(): string
    {
        return match ($this->severity) {
            self::SEVERITY_ERROR => __('Precisa de correção na coleta ou no sistema'),
            self::SEVERITY_WARNING => __('Revisar antes de concluir'),
            self::SEVERITY_INFO => __('Registro informativo'),
            default => '',
        };
    }

    public function actionHint(): string
    {
        return ClioUserCopy::findingAction($this->code, (string) $this->severity);
    }
}
