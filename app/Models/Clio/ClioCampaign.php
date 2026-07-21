<?php

namespace App\Models\Clio;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClioCampaign extends Model
{
    public const STAGE_1 = 'stage1';

    public const PROFILE_ANALYSIS_ONLY = 'analysis_only';

    public const PROFILE_CONSULTANCY = 'consultancy';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_INGESTING = 'ingesting';

    public const STATUS_PARSED = 'parsed';

    public const STATUS_ANALYZED = 'analyzed';

    protected $table = 'clio_campaigns';

    protected $fillable = [
        'uuid',
        'city_id',
        'municipality_name',
        'uf',
        'ibge_municipio',
        'year',
        'stage',
        'profile',
        'status',
        'reference_date',
        'source',
        'meta',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'reference_date' => 'date',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (blank($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schools(): HasMany
    {
        return $this->hasMany(ClioCampaignSchool::class, 'campaign_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(ClioCampaignArtifact::class, 'campaign_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ClioCampaignFinding::class, 'campaign_id');
    }

    public function inferences(): HasMany
    {
        return $this->hasMany(ClioCampaignInference::class, 'campaign_id');
    }

    public function isAnalysisOnly(): bool
    {
        return $this->profile === self::PROFILE_ANALYSIS_ONLY;
    }

    public function profileLabel(): string
    {
        return $this->isAnalysisOnly()
            ? __('Só coleta')
            : __('Consultoria');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => __('Rascunho'),
            self::STATUS_INGESTING => __('A ingerir'),
            self::STATUS_PARSED => __('Interpretado'),
            self::STATUS_ANALYZED => __('Analisado'),
            default => $this->status,
        };
    }
}
