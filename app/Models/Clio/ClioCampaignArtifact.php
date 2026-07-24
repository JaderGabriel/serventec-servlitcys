<?php

namespace App\Models\Clio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClioCampaignArtifact extends Model
{
    public const PARSE_PENDING = 'pending';

    public const PARSE_OK = 'ok';

    public const PARSE_WARNING = 'warning';

    public const PARSE_FAILED = 'failed';

    protected $table = 'clio_campaign_artifacts';

    protected $fillable = [
        'campaign_id',
        'school_id',
        'kind',
        'original_name',
        'storage_path',
        'sha256',
        'size_bytes',
        'parse_status',
        'row_count',
        'parse_meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'row_count' => 'integer',
            'parse_meta' => 'array',
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

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'acomp_coleta_1etapa' => __('Acompanhamento 1ª etapa'),
            'relacao_aluno_escola' => __('Relação alunos'),
            'relacao_turma_escola' => __('Relação turmas'),
            'relacao_profissional_escola' => __('Relação profissionais'),
            'pacote_zip' => __('Pacote ZIP'),
            'migracao_txt' => __('Migração TXT'),
            default => __('Desconhecido'),
        };
    }

    public function parseStatusLabel(): string
    {
        return match ($this->parse_status) {
            self::PARSE_OK => __('Ok'),
            self::PARSE_WARNING => __('Aviso'),
            self::PARSE_FAILED => __('Falhou'),
            self::PARSE_PENDING => __('Pendente'),
            default => (string) $this->parse_status,
        };
    }

    public function parseStatusTone(): string
    {
        return match ($this->parse_status) {
            self::PARSE_OK => 'ok',
            self::PARSE_WARNING => 'warn',
            self::PARSE_FAILED => 'error',
            self::PARSE_PENDING => 'warn',
            default => 'muted',
        };
    }

    public function kindSortKey(): int
    {
        return match ($this->kind) {
            'acomp_coleta_1etapa' => 0,
            'pacote_zip' => 1,
            'migracao_txt' => 2,
            'relacao_aluno_escola' => 10,
            'relacao_turma_escola' => 11,
            'relacao_profissional_escola' => 12,
            default => 50,
        };
    }
}
