<?php

namespace App\Services\Clio\Parse;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class CampaignParseService
{
    /** @var list<string> */
    private const PARSEABLE = [
        'acomp_coleta_1etapa',
        'relacao_aluno_escola',
        'relacao_turma_escola',
        'relacao_profissional_escola',
    ];

    /** @var list<ArtifactParser> */
    private array $parsers;

    private CsvReader $csv;

    /**
     * @param  list<ArtifactParser>|null  $parsers
     */
    public function __construct(?array $parsers = null, ?CsvReader $csv = null)
    {
        $this->csv = $csv ?? new CsvReader;

        if ($parsers !== null) {
            $this->parsers = $parsers;

            return;
        }

        $this->parsers = [
            new AcompColeta1EtapaParser($this->csv),
            new RelacaoAlunoEscolaParser($this->csv),
            new RelacaoTurmaEscolaParser($this->csv),
            new RelacaoProfissionalEscolaParser($this->csv),
        ];
    }

    /**
     * @return array{
     *   parsed: int,
     *   ok: int,
     *   warning: int,
     *   failed: int,
     *   skipped: int
     * }
     */
    public function parseCampaign(ClioCampaign $campaign, bool $reparse = false): array
    {
        $disk = (string) config('clio.disk', 'local');
        $stats = ['parsed' => 0, 'ok' => 0, 'warning' => 0, 'failed' => 0, 'skipped' => 0];

        $query = ClioCampaignArtifact::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('kind', self::PARSEABLE)
            ->orderBy('id');

        if (! $reparse) {
            $query->where('parse_status', ClioCampaignArtifact::PARSE_PENDING);
        }

        foreach ($query->get() as $artifact) {
            $parser = $this->parserFor($artifact->kind);
            if ($parser === null) {
                $stats['skipped']++;

                continue;
            }

            $absolute = Storage::disk($disk)->path($artifact->storage_path);
            try {
                $result = $parser->parse($absolute, $artifact);
            } catch (Throwable $e) {
                $result = ParseResult::failed('EDU-REL-EX', $e->getMessage());
            }

            $this->persistArtifact($campaign, $artifact, $result);
            $stats['parsed']++;
            $stats[$result->status] = ($stats[$result->status] ?? 0) + 1;
        }

        $this->markNonParseable($campaign);
        $this->refreshCampaignStatus($campaign);

        return $stats;
    }

    public function parserFor(string $kind): ?ArtifactParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($kind)) {
                return $parser;
            }
        }

        return null;
    }

    /**
     * Cobertura da tríade e inventário para CLI/UI.
     * Denominador da %: só escolas em atividade (extinta/paralisada/reforma fora).
     *
     * @return array<string, mixed>
     */
    public function coverage(ClioCampaign $campaign): array
    {
        $campaign->load(['schools.artifacts', 'artifacts']);

        $byKind = $campaign->artifacts->groupBy('kind')->map->count()->all();
        $parseStats = $campaign->artifacts
            ->whereIn('kind', self::PARSEABLE)
            ->groupBy('parse_status')
            ->map->count()
            ->all();

        $schools = [];
        $completeActive = 0;
        $activeCount = 0;
        $otherCount = 0;
        $missingActive = 0;

        foreach ($campaign->schools as $school) {
            $kinds = $school->artifacts->pluck('kind')->unique()->all();
            $hasAluno = in_array('relacao_aluno_escola', $kinds, true);
            $hasTurma = in_array('relacao_turma_escola', $kinds, true);
            $hasProf = in_array('relacao_profissional_escola', $kinds, true);
            $triade = $hasAluno && $hasTurma && $hasProf;
            $inactive = CampaignAnalysisPresenter::isInactiveFunctioning($school->functioning_status);

            if ($inactive) {
                $otherCount++;
            } else {
                $activeCount++;
                if ($triade) {
                    $completeActive++;
                } else {
                    $missingActive++;
                }
            }

            $schools[] = [
                'inep' => $school->inep_code,
                'name' => $school->name,
                'aluno' => $hasAluno,
                'turma' => $hasTurma,
                'profissional' => $hasProf,
                'triade' => $triade,
                'inactive' => $inactive,
                'functioning' => $school->functioning_status,
            ];
        }

        $pct = $activeCount === 0
            ? 0.0
            : round(100 * $completeActive / $activeCount, 1);

        return [
            'status' => $campaign->status,
            'status_label' => $campaign->statusLabel(),
            'reference_date' => optional($campaign->reference_date)?->toDateString(),
            'artifacts_by_kind' => $byKind,
            'parse_stats' => $parseStats,
            'schools_total' => $campaign->schools->count(),
            'schools_active' => $activeCount,
            'schools_other' => $otherCount,
            'schools_triade_complete' => $completeActive,
            'schools_missing_triade' => $missingActive,
            'triade_coverage_pct' => $pct,
            'has_acomp' => ($byKind['acomp_coleta_1etapa'] ?? 0) > 0,
            'schools' => $schools,
            'denominator_note' => max(1, $activeCount),
        ];
    }

    private function persistArtifact(ClioCampaign $campaign, ClioCampaignArtifact $artifact, ParseResult $result): void
    {
        $existingMeta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
        $meta = $this->csv->deepUtf8(array_merge($existingMeta, $result->meta, [
            'parsed_at' => now()->toIso8601String(),
            'warnings' => $result->warnings,
            'code' => $result->code,
        ]));

        foreach ($result->schools as $schoolData) {
            ClioCampaignSchool::query()->updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'inep_code' => $schoolData['inep_code'],
                ],
                [
                    'name' => $this->csv->toUtf8((string) $schoolData['name']),
                    'dependency' => $this->csv->toUtf8((string) $schoolData['dependency']),
                    'collection_form' => $this->csv->toUtf8((string) $schoolData['collection_form']),
                    'functioning_status' => $this->csv->toUtf8((string) $schoolData['functioning_status']),
                    'meta' => $this->csv->deepUtf8($schoolData['meta']),
                ],
            );
        }

        if ($result->referenceDate !== null && $campaign->reference_date === null) {
            $campaign->update(['reference_date' => $result->referenceDate]);
            $campaign->refresh();
        }

        $artifact->fill([
            'parse_status' => $result->status,
            'row_count' => $result->rowCount,
            'parse_meta' => $meta,
        ]);
        $artifact->save();
    }

    private function markNonParseable(ClioCampaign $campaign): void
    {
        $artifacts = ClioCampaignArtifact::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('kind', self::PARSEABLE)
            ->where('parse_status', ClioCampaignArtifact::PARSE_PENDING)
            ->get();

        foreach ($artifacts as $artifact) {
            if ($artifact->kind !== 'pacote_zip') {
                continue;
            }
            $meta = is_array($artifact->parse_meta) ? $artifact->parse_meta : [];
            if (empty($meta['expanded_at'])) {
                continue;
            }
            $artifact->update([
                'parse_status' => ClioCampaignArtifact::PARSE_OK,
                'row_count' => (int) ($meta['extracted_files'] ?? 0),
                'parse_meta' => $this->csv->deepUtf8(array_merge($meta, [
                    'parsed_at' => now()->toIso8601String(),
                    'note' => 'zip_expanded',
                ])),
            ]);
        }
    }

    private function refreshCampaignStatus(ClioCampaign $campaign): void
    {
        $parseable = ClioCampaignArtifact::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('kind', self::PARSEABLE);

        if (! (clone $parseable)->exists()) {
            return;
        }

        if ((clone $parseable)->where('parse_status', ClioCampaignArtifact::PARSE_PENDING)->exists()) {
            return;
        }

        $hasOk = (clone $parseable)
            ->whereIn('parse_status', [ClioCampaignArtifact::PARSE_OK, ClioCampaignArtifact::PARSE_WARNING])
            ->exists();

        if ($hasOk && in_array($campaign->status, [
            ClioCampaign::STATUS_DRAFT,
            ClioCampaign::STATUS_INGESTING,
            ClioCampaign::STATUS_PARSED,
        ], true)) {
            $campaign->update(['status' => ClioCampaign::STATUS_PARSED]);
        }
    }
}
