<?php

namespace App\Models\Clio;

use App\Models\City;
use App\Models\User;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
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

    public const STATUS_CROSS_CHECKED = 'cross_checked';

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

    public function hasReportReady(): bool
    {
        return in_array($this->status, [
            self::STATUS_ANALYZED,
            self::STATUS_CROSS_CHECKED,
        ], true);
    }

    /**
     * Destino principal a partir da home: painel analítico se já houver análise; senão a central da coleta.
     */
    public function primaryReportUrl(): string
    {
        if ($this->hasReportReady() || $this->status === self::STATUS_PARSED) {
            return route('clio.campaigns.analysis', $this);
        }

        return route('clio.campaigns.show', $this);
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
            self::STATUS_INGESTING => __('Em ingestão'),
            self::STATUS_PARSED => __('Interpretado'),
            self::STATUS_ANALYZED => __('Analisado'),
            self::STATUS_CROSS_CHECKED => __('Cruzado i-Educar'),
            default => $this->status,
        };
    }

    /**
     * Data de referência do Acomp (portal), formatada para UI pt-BR.
     */
    public function referenceDateDisplay(): ?string
    {
        if ($this->reference_date === null) {
            return null;
        }

        return $this->reference_date
            ->timezone(config('app.timezone'))
            ->format('d/m/Y');
    }

    /**
     * Momento da última análise / atualização relevante da coleta.
     */
    public function lastActivityDisplay(): ?string
    {
        $at = $this->updated_at;
        if ($at === null) {
            return null;
        }

        return $at->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    /**
     * Escopo operacional: escolas em atividade × demais situações + cobertura da tríade.
     *
     * @return array{
     *   active: int,
     *   other: int,
     *   total: int,
     *   triade_complete: int,
     *   triade_pct: ?float
     * }
     */
    public function schoolScopeStats(): array
    {
        if ($this->relationLoaded('schools')) {
            $active = 0;
            $other = 0;
            $triadeComplete = 0;

            foreach ($this->schools as $school) {
                if (CampaignAnalysisPresenter::isInactiveFunctioning($school->functioning_status)) {
                    $other++;

                    continue;
                }

                $active++;
                $kinds = $school->relationLoaded('artifacts')
                    ? $school->artifacts->pluck('kind')->unique()->all()
                    : $school->artifacts()->pluck('kind')->unique()->all();

                if (
                    in_array('relacao_aluno_escola', $kinds, true)
                    && in_array('relacao_turma_escola', $kinds, true)
                    && in_array('relacao_profissional_escola', $kinds, true)
                ) {
                    $triadeComplete++;
                }
            }

            return [
                'active' => $active,
                'other' => $other,
                'total' => $active + $other,
                'triade_complete' => $triadeComplete,
                'triade_pct' => $active > 0
                    ? round(100 * $triadeComplete / $active, 1)
                    : ($active + $other === 0 ? null : 0.0),
            ];
        }

        $inf = $this->relationLoaded('inferences')
            ? $this->inferences->firstWhere('code', 'INF-COE')
            : $this->inferences()->where('code', 'INF-COE')->first();

        $payload = is_array($inf?->payload) ? $inf->payload : [];
        if ($payload === []) {
            return [
                'active' => (int) ($this->schools_count ?? 0),
                'other' => 0,
                'total' => (int) ($this->schools_count ?? 0),
                'triade_complete' => 0,
                'triade_pct' => null,
            ];
        }

        $active = (int) ($payload['schools_active'] ?? 0);
        $other = (int) ($payload['schools_other'] ?? 0);
        $triadeComplete = (int) ($payload['schools_triade_complete'] ?? 0);
        $pct = array_key_exists('triade_coverage_pct', $payload)
            ? (float) $payload['triade_coverage_pct']
            : null;

        return [
            'active' => $active,
            'other' => $other,
            'total' => $active + $other,
            'triade_complete' => $triadeComplete,
            'triade_pct' => $pct,
        ];
    }

    /**
     * Cobertura da tríade (%) nas escolas em atividade.
     */
    public function triadeCoveragePct(): ?float
    {
        if ($this->relationLoaded('schools')) {
            return $this->schoolScopeStats()['triade_pct'];
        }

        $inf = $this->relationLoaded('inferences')
            ? $this->inferences->firstWhere('code', 'INF-COE')
            : $this->inferences()->where('code', 'INF-COE')->first();

        $payload = is_array($inf?->payload) ? $inf->payload : [];
        if (! array_key_exists('triade_coverage_pct', $payload)) {
            return null;
        }

        return (float) $payload['triade_coverage_pct'];
    }
}
