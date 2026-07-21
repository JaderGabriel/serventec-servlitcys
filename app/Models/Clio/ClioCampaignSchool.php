<?php

namespace App\Models\Clio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClioCampaignSchool extends Model
{
    protected $table = 'clio_campaign_schools';

    protected $fillable = [
        'campaign_id',
        'inep_code',
        'name',
        'dependency',
        'collection_form',
        'functioning_status',
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

    public function artifacts(): HasMany
    {
        return $this->hasMany(ClioCampaignArtifact::class, 'school_id');
    }
}
