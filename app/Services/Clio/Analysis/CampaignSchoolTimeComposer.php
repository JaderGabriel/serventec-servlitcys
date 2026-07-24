<?php

namespace App\Services\Clio\Analysis;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use Illuminate\Support\Facades\Storage;

/**
 * Tempo escolar semanal sob a ótica do aluno: CH por segmento e tipo de turma.
 */
final class CampaignSchoolTimeComposer
{
    public function __construct(
        private readonly RelationCsvAggregator $aggregator = new RelationCsvAggregator,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     has_ch: bool,
     *     note: string,
     *     segments: list<array{
     *         key: string,
     *         label: string,
     *         turmas: int,
     *         alunos: int,
     *         ch_media_turma: float|null,
     *         ch_media_aluno: float|null,
     *         horas_aluno_semana: float|null,
     *         curricular: array{turmas: int, alunos: int, ch_media_aluno: float|null},
     *         aee: array{turmas: int, alunos: int, ch_media_aluno: float|null},
     *         ac: array{turmas: int, alunos: int, ch_media_aluno: float|null}
     *     }>,
     *     network: array{ch_media_aluno: float|null, horas_aluno_semana: float|null, alunos_com_ch: int}
     * }
     */
    public function compose(ClioCampaign $campaign): array
    {
        $disk = (string) config('clio.disk', 'local');
        $csv = app(\App\Services\Clio\Parse\CsvReader::class);

        $profiles = [];
        $hasCh = false;
        foreach ($this->artifactsOfKind($campaign, 'relacao_turma_escola') as $artifact) {
            if (! $artifact instanceof ClioCampaignArtifact) {
                continue;
            }
            $agg = $this->resolveTurmaAggregates($artifact, $disk, $csv);
            foreach (is_array($agg['turma_profiles'] ?? null) ? $agg['turma_profiles'] : [] as $code => $profile) {
                if (! is_array($profile)) {
                    continue;
                }
                $profiles[(string) $code] = $profile;
                if (($profile['ch_hours'] ?? null) !== null) {
                    $hasCh = true;
                }
            }
        }

        $alunosPorTurma = [];
        foreach ($this->artifactsOfKind($campaign, 'relacao_aluno_escola') as $artifact) {
            if (! $artifact instanceof ClioCampaignArtifact) {
                continue;
            }
            $agg = $this->resolveAlunoAggregates($artifact, $disk, $csv, (int) $campaign->year);
            foreach (is_array($agg['by_turma'] ?? null) ? $agg['by_turma'] : [] as $code => $n) {
                $alunosPorTurma[(string) $code] = ($alunosPorTurma[(string) $code] ?? 0) + (int) $n;
            }
        }

        /** @var array<string, array{label: string, turmas: int, alunos: int, ch_sum_turma: float, ch_n_turma: int, ch_sum_aluno: float, ch_alunos: int, curricular: array{turmas: int, alunos: int, ch_sum: float, ch_alunos: int}, aee: array{turmas: int, alunos: int, ch_sum: float, ch_alunos: int}, ac: array{turmas: int, alunos: int, ch_sum: float, ch_alunos: int}}> $buckets */
        $buckets = [];

        foreach ($profiles as $code => $profile) {
            $segment = $this->segmentKey($profile);
            if (! isset($buckets[$segment])) {
                $buckets[$segment] = $this->emptyBucket($segment);
            }
            $alunos = (int) ($alunosPorTurma[$code] ?? 0);
            $ch = isset($profile['ch_hours']) && is_numeric($profile['ch_hours']) ? (float) $profile['ch_hours'] : null;
            $bucket = (string) ($profile['bucket'] ?? RelationCsvAggregator::BUCKET_OUTRA);

            $buckets[$segment]['turmas']++;
            $buckets[$segment]['alunos'] += $alunos;
            if ($ch !== null) {
                $buckets[$segment]['ch_sum_turma'] += $ch;
                $buckets[$segment]['ch_n_turma']++;
                if ($alunos > 0) {
                    $buckets[$segment]['ch_sum_aluno'] += $ch * $alunos;
                    $buckets[$segment]['ch_alunos'] += $alunos;
                }
            }

            $tipoKey = match ($bucket) {
                RelationCsvAggregator::BUCKET_AEE => 'aee',
                RelationCsvAggregator::BUCKET_AC => 'ac',
                default => 'curricular',
            };
            $buckets[$segment][$tipoKey]['turmas']++;
            $buckets[$segment][$tipoKey]['alunos'] += $alunos;
            if ($ch !== null && $alunos > 0) {
                $buckets[$segment][$tipoKey]['ch_sum'] += $ch * $alunos;
                $buckets[$segment][$tipoKey]['ch_alunos'] += $alunos;
            }
        }

        $segments = [];
        $netChSum = 0.0;
        $netAlunos = 0;
        foreach ($this->segmentOrder() as $key) {
            if (! isset($buckets[$key]) || $buckets[$key]['turmas'] === 0) {
                continue;
            }
            $b = $buckets[$key];
            $chTurma = $b['ch_n_turma'] > 0 ? round($b['ch_sum_turma'] / $b['ch_n_turma'], 1) : null;
            $chAluno = $b['ch_alunos'] > 0 ? round($b['ch_sum_aluno'] / $b['ch_alunos'], 1) : null;
            if ($b['ch_alunos'] > 0) {
                $netChSum += $b['ch_sum_aluno'];
                $netAlunos += $b['ch_alunos'];
            }
            $segments[] = [
                'key' => $key,
                'label' => $b['label'],
                'turmas' => $b['turmas'],
                'alunos' => $b['alunos'],
                'ch_media_turma' => $chTurma,
                'ch_media_aluno' => $chAluno,
                'horas_aluno_semana' => $chAluno,
                'curricular' => $this->tipoOut($b['curricular']),
                'aee' => $this->tipoOut($b['aee']),
                'ac' => $this->tipoOut($b['ac']),
            ];
        }

        return [
            'available' => $profiles !== [],
            'has_ch' => $hasCh,
            'note' => $hasCh
                ? __('Horas/semana estimadas a partir da Carga horária das turmas, ponderadas pelos alunos vinculados (Relação). Curricular, AEE e atividade complementar são apresentados em separado.')
                : __('As Relações de turmas não trouxeram Carga horária — só contagens de turmas/alunos por segmento.'),
            'segments' => $segments,
            'network' => [
                'ch_media_aluno' => $netAlunos > 0 ? round($netChSum / $netAlunos, 1) : null,
                'horas_aluno_semana' => $netAlunos > 0 ? round($netChSum / $netAlunos, 1) : null,
                'alunos_com_ch' => $netAlunos,
            ],
        ];
    }

    /**
     * @param  array{turmas: int, alunos: int, ch_sum: float, ch_alunos: int}  $t
     * @return array{turmas: int, alunos: int, ch_media_aluno: float|null}
     */
    private function tipoOut(array $t): array
    {
        return [
            'turmas' => $t['turmas'],
            'alunos' => $t['alunos'],
            'ch_media_aluno' => $t['ch_alunos'] > 0 ? round($t['ch_sum'] / $t['ch_alunos'], 1) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function segmentKey(array $profile): string
    {
        $bucket = (string) ($profile['bucket'] ?? '');
        if ($bucket === RelationCsvAggregator::BUCKET_AEE) {
            return 'aee';
        }
        if ($bucket === RelationCsvAggregator::BUCKET_AC) {
            return 'atividade_complementar';
        }

        $etapa = (string) ($profile['etapa'] ?? '');
        $agregada = (string) ($profile['agregada'] ?? '');
        $blob = mb_strtolower($etapa.' '.$agregada);

        if (! empty($profile['infantil']) || str_contains($blob, 'infantil') || str_contains($blob, 'creche') || str_contains($blob, 'pré')) {
            return 'infantil';
        }
        if (str_contains($blob, 'eja') || str_contains($blob, 'jovens e adultos')) {
            return 'eja';
        }
        if (str_contains($blob, 'profissional') || str_contains($blob, 'técnico') || str_contains($blob, 'tecnico')) {
            return 'profissional';
        }
        if (str_contains($blob, 'médio') || str_contains($blob, 'medio')) {
            return 'medio';
        }
        if (
            str_contains($blob, 'anos finais')
            || preg_match('/\b(6|7|8|9)[ºo°]\s*ano\b/u', $blob) === 1
        ) {
            return 'fundamental_2';
        }
        if (
            str_contains($blob, 'anos iniciais')
            || str_contains($blob, 'fundamental')
            || preg_match('/\b(1|2|3|4|5)[ºo°]\s*ano\b/u', $blob) === 1
        ) {
            return 'fundamental_1';
        }

        return 'outro';
    }

    /**
     * @return list<string>
     */
    private function segmentOrder(): array
    {
        return [
            'infantil',
            'fundamental_1',
            'fundamental_2',
            'medio',
            'eja',
            'profissional',
            'aee',
            'atividade_complementar',
            'outro',
        ];
    }

    /**
     * @return array{label: string, turmas: int, alunos: int, ch_sum_turma: float, ch_n_turma: int, ch_sum_aluno: float, ch_alunos: int, curricular: array{turmas: int, alunos: int, ch_sum: float, ch_alunos: int}, aee: array{turmas: int, alunos: int, ch_sum: float, ch_alunos: int}, ac: array{turmas: int, alunos: int, ch_sum: float, ch_alunos: int}}
     */
    private function emptyBucket(string $key): array
    {
        $emptyTipo = ['turmas' => 0, 'alunos' => 0, 'ch_sum' => 0.0, 'ch_alunos' => 0];

        return [
            'label' => $this->segmentLabel($key),
            'turmas' => 0,
            'alunos' => 0,
            'ch_sum_turma' => 0.0,
            'ch_n_turma' => 0,
            'ch_sum_aluno' => 0.0,
            'ch_alunos' => 0,
            'curricular' => $emptyTipo,
            'aee' => $emptyTipo,
            'ac' => $emptyTipo,
        ];
    }

    private function segmentLabel(string $key): string
    {
        return match ($key) {
            'infantil' => __('Educação Infantil'),
            'fundamental_1' => __('Fundamental — anos iniciais'),
            'fundamental_2' => __('Fundamental — anos finais'),
            'medio' => __('Ensino Médio'),
            'eja' => __('EJA'),
            'profissional' => __('Educação profissional'),
            'aee' => __('AEE'),
            'atividade_complementar' => __('Atividade complementar'),
            default => __('Outros'),
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, ClioCampaignArtifact>
     */
    private function artifactsOfKind(ClioCampaign $campaign, string $kind): \Illuminate\Support\Collection
    {
        $fromCampaign = collect();
        if ($campaign->relationLoaded('artifacts')) {
            $fromCampaign = $campaign->artifacts->where('kind', $kind)->values();
        }

        $fromSchools = collect();
        if ($campaign->relationLoaded('schools')) {
            $fromSchools = $campaign->schools
                ->flatMap(static function ($school) use ($kind) {
                    if (! $school->relationLoaded('artifacts')) {
                        return [];
                    }

                    return $school->artifacts->where('kind', $kind)->all();
                })
                ->values();
        }

        if ($fromCampaign->isEmpty() && $fromSchools->isEmpty()) {
            if ($campaign->relationLoaded('artifacts') || $campaign->relationLoaded('schools')) {
                return collect();
            }

            return $campaign->artifacts()->where('kind', $kind)->get();
        }

        return $fromCampaign
            ->concat($fromSchools)
            ->unique('id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTurmaAggregates(ClioCampaignArtifact $artifact, string $disk, \App\Services\Clio\Parse\CsvReader $csv): array
    {
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
        $agg = $meta['aggregates'] ?? null;
        if (is_array($agg) && isset($agg['turma_profiles'])) {
            return $agg;
        }

        try {
            $data = $csv->read(Storage::disk($disk)->path((string) $artifact->storage_path), 1);
        } catch (\Throwable) {
            return [];
        }

        return $this->aggregator->aggregateTurmas($data['rows'] ?? [], $csv);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAlunoAggregates(
        ClioCampaignArtifact $artifact,
        string $disk,
        \App\Services\Clio\Parse\CsvReader $csv,
        int $year,
    ): array {
        $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
        $agg = $meta['aggregates'] ?? null;
        if (is_array($agg) && isset($agg['by_turma'])) {
            return $agg;
        }

        try {
            $data = $csv->read(Storage::disk($disk)->path((string) $artifact->storage_path), 1);
        } catch (\Throwable) {
            return [];
        }

        return $this->aggregator->aggregateAlunos($data['rows'] ?? [], $csv, $year);
    }
}
