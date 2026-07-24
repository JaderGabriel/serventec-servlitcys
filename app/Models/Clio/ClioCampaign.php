<?php

namespace App\Models\Clio;

use App\Models\City;
use App\Models\User;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
     * Resumo discreto de processamento de ficheiros (home / cartão do município).
     *
     * @return array{
     *   total: int,
     *   ok: int,
     *   failed: int,
     *   pending: int,
     *   tone: string,
     *   label: string,
     *   acomp: array{
     *     present: bool,
     *     status: string,
     *     tone: string,
     *     label: string,
     *     name: ?string
     *   }
     * }
     */
    public function fileProcessingSummary(): array
    {
        $ok = (int) ($this->artifacts_ok_count ?? 0);
        $failed = (int) ($this->artifacts_failed_count ?? 0);
        $pending = (int) ($this->artifacts_pending_count ?? 0);
        $total = (int) ($this->artifacts_count ?? ($ok + $failed + $pending));

        if (! isset($this->artifacts_ok_count) && $this->relationLoaded('artifacts')) {
            $ok = $this->artifacts->whereIn('parse_status', [
                ClioCampaignArtifact::PARSE_OK,
                ClioCampaignArtifact::PARSE_WARNING,
            ])->count();
            $failed = $this->artifacts->where('parse_status', ClioCampaignArtifact::PARSE_FAILED)->count();
            $pending = $this->artifacts->where('parse_status', ClioCampaignArtifact::PARSE_PENDING)->count();
            $total = $this->artifacts->count();
        }

        $tone = match (true) {
            $total === 0 => 'muted',
            $failed > 0 => 'error',
            $pending > 0 => 'warn',
            default => 'ok',
        };

        $label = match (true) {
            $total === 0 => __('Sem ficheiros'),
            $failed > 0 => __(':ok ok · :err erro(s)', ['ok' => $ok, 'err' => $failed]),
            $pending > 0 => __(':ok processado(s) · :p pendente(s)', ['ok' => $ok, 'p' => $pending]),
            default => __(':n ficheiro(s) ok', ['n' => $ok]),
        };

        $acomp = null;
        if ($this->relationLoaded('acompArtifact')) {
            $acomp = $this->acompArtifact;
        } elseif ($this->relationLoaded('artifacts')) {
            $acomp = $this->artifacts->firstWhere('kind', 'acomp_coleta_1etapa');
        }

        if ($acomp instanceof ClioCampaignArtifact) {
            $acompStatus = (string) $acomp->parse_status;
            $acompTone = match ($acompStatus) {
                ClioCampaignArtifact::PARSE_OK, ClioCampaignArtifact::PARSE_WARNING => 'ok',
                ClioCampaignArtifact::PARSE_FAILED => 'error',
                default => 'warn',
            };
            $acompLabel = match ($acompStatus) {
                ClioCampaignArtifact::PARSE_OK => __('Acomp ok'),
                ClioCampaignArtifact::PARSE_WARNING => __('Acomp com aviso'),
                ClioCampaignArtifact::PARSE_FAILED => __('Acomp com erro'),
                ClioCampaignArtifact::PARSE_PENDING => __('Acomp pendente'),
                default => __('Acomp :s', ['s' => $acompStatus]),
            };
            $acompBlock = [
                'present' => true,
                'status' => $acompStatus,
                'tone' => $acompTone,
                'label' => $acompLabel,
                'name' => $acomp->original_name,
            ];
        } else {
            $acompBlock = [
                'present' => false,
                'status' => 'missing',
                'tone' => $total > 0 ? 'warn' : 'muted',
                'label' => __('Acomp ausente'),
                'name' => null,
            ];
        }

        return [
            'total' => $total,
            'ok' => $ok,
            'failed' => $failed,
            'pending' => $pending,
            'tone' => $tone,
            'label' => $label,
            'acomp' => $acompBlock,
        ];
    }

    public function acompArtifact(): HasOne
    {
        return $this->hasOne(ClioCampaignArtifact::class, 'campaign_id')
            ->ofMany(['id' => 'max'], function ($query) {
                $query->where('kind', 'acomp_coleta_1etapa');
            });
    }

    /**
     * Inventário da Central: arquivo geral primeiro, depois escolas (INEP) com a tríade agrupada.
     *
     * @return array{
     *   municipal: list<ClioCampaignArtifact>,
     *   schools: list<array{
     *     school_id: ?int,
     *     inep: string,
     *     name: string,
     *     tone: string,
     *     artifacts: list<ClioCampaignArtifact>
     *   }>,
     *   total: int
     * }
     */
    public function filesInventory(): array
    {
        $artifacts = $this->relationLoaded('artifacts')
            ? $this->artifacts
            : $this->artifacts()->with('school')->get();

        $municipalKinds = ['acomp_coleta_1etapa', 'pacote_zip', 'migracao_txt'];
        $triadeKinds = [
            'relacao_aluno_escola',
            'relacao_turma_escola',
            'relacao_profissional_escola',
        ];

        $municipal = $artifacts
            ->filter(function (ClioCampaignArtifact $a) use ($municipalKinds, $triadeKinds): bool {
                if (in_array($a->kind, $municipalKinds, true)) {
                    return true;
                }

                return $a->school_id === null && ! in_array($a->kind, $triadeKinds, true);
            })
            ->sortBy(fn (ClioCampaignArtifact $a): array => [$a->kindSortKey(), $a->id])
            ->values()
            ->all();

        $bySchool = [];
        foreach ($artifacts as $artifact) {
            if (in_array($artifact->kind, $municipalKinds, true) && $artifact->school_id === null) {
                continue;
            }
            if (! in_array($artifact->kind, $triadeKinds, true) && $artifact->school_id === null) {
                continue;
            }

            $school = $artifact->school;
            $key = $artifact->school_id !== null
                ? 'id:'.$artifact->school_id
                : 'inep:'.(string) ($school?->inep_code ?? 'orphan-'.$artifact->id);

            if (! isset($bySchool[$key])) {
                $bySchool[$key] = [
                    'school_id' => $artifact->school_id,
                    'inep' => (string) ($school?->inep_code ?? '—'),
                    'name' => (string) ($school?->name ?? __('Sem escola vinculada')),
                    'artifacts' => [],
                ];
            }
            $bySchool[$key]['artifacts'][] = $artifact;
        }

        $schools = [];
        foreach ($bySchool as $group) {
            $sorted = collect($group['artifacts'])
                ->sortBy(fn (ClioCampaignArtifact $a): array => [$a->kindSortKey(), $a->id])
                ->values()
                ->all();
            $tones = array_map(fn (ClioCampaignArtifact $a): string => $a->parseStatusTone(), $sorted);
            $tone = match (true) {
                in_array('error', $tones, true) => 'error',
                in_array('warn', $tones, true) => 'warn',
                $tones !== [] && count(array_filter($tones, fn (string $t): bool => $t === 'ok')) === count($tones) => 'ok',
                default => 'muted',
            };
            $schools[] = [
                'school_id' => $group['school_id'],
                'inep' => $group['inep'],
                'name' => $group['name'],
                'tone' => $tone,
                'artifacts' => $sorted,
            ];
        }

        usort($schools, static function (array $a, array $b): int {
            $byName = strcasecmp($a['name'], $b['name']);
            if ($byName !== 0) {
                return $byName;
            }

            return strcmp($a['inep'], $b['inep']);
        });

        return [
            'municipal' => $municipal,
            'schools' => $schools,
            'total' => $artifacts->count(),
        ];
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
