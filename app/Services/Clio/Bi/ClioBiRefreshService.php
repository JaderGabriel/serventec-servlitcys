<?php

namespace App\Services\Clio\Bi;

use App\Models\Bi\BiClioCampaign;
use App\Models\Bi\BiClioEnrollmentStage;
use App\Models\Bi\BiClioInclusion;
use App\Models\Bi\BiClioInsight;
use App\Models\Bi\BiClioQuality;
use App\Models\Bi\BiClioSchool;
use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignInference;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\CampaignNeeCensusBuilder;
use App\Services\Clio\Analysis\CampaignSchoolTimeComposer;
use App\Services\Clio\Parse\CampaignParseService;
use Illuminate\Support\Facades\DB;

/**
 * ETL idempotente das tabelas bi_clio_* (zero PII).
 */
final class ClioBiRefreshService
{
    public function __construct(
        private readonly CampaignParseService $parser,
        private readonly CampaignNeeCensusBuilder $neeBuilder,
        private readonly ClioBiInsightComposer $insights,
        private readonly CampaignSchoolTimeComposer $schoolTime,
    ) {}

    public function refreshCampaign(ClioCampaign $campaign): void
    {
        $campaign->load(['city', 'schools.artifacts', 'artifacts', 'inferences', 'findings']);

        $coverage = $this->parser->coverage($campaign);
        $inferences = $campaign->inferences->keyBy('code');
        $stats = $campaign->schoolScopeStats();
        $nee = $this->neeBuilder->build($campaign);
        $schoolTime = $this->schoolTime->compose($campaign);

        $mat = $this->payload($inferences->get('INF-MAT'));
        $dis = $this->payload($inferences->get('INF-DIS'));
        $den = $this->payload($inferences->get('INF-DEN'));
        $doc = $this->payload($inferences->get('INF-DOC'));
        $tra = $this->payload($inferences->get('INF-TRA'));
        $gap = $this->payload($inferences->get('INF-GAP'));
        $coe = $this->payload($inferences->get('INF-COE'));
        $delta = $this->payload($inferences->get('INF-DELTA'));

        $errors = $campaign->findings->where('severity', ClioCampaignFinding::SEVERITY_ERROR)->count();
        $warnings = $campaign->findings->where('severity', ClioCampaignFinding::SEVERITY_WARNING)->count();

        $triadePct = $stats['triade_pct']
            ?? $coe['triade_coverage_pct']
            ?? ($coverage['triade_coverage_pct'] ?? null);

        $schoolsIncomplete = max(0, (int) ($stats['active'] ?? 0) - (int) ($stats['triade_complete'] ?? 0));

        $snapshot = [
            'triade_pct' => is_numeric($triadePct) ? (float) $triadePct : null,
            'schools_active' => (int) ($stats['active'] ?? 0),
            'schools_incomplete_triad' => $schoolsIncomplete,
            'findings_errors' => $errors,
            'distortion_pct' => isset($dis['pct_distorcao']) && is_numeric($dis['pct_distorcao']) ? (float) $dis['pct_distorcao'] : null,
            'density_avg' => isset($den['media_alunos_por_turma']) && is_numeric($den['media_alunos_por_turma'])
                ? (float) $den['media_alunos_por_turma']
                : null,
            'turmas_ge_40' => (int) ($den['turmas_ge_40'] ?? 0),
            'turmas_sem_docente' => (int) ($doc['turmas_sem_docente'] ?? 0),
            'nee_people' => (int) ($nee['flagged'] ?? 0),
            'nee_people_scanned' => (int) ($nee['people_scanned'] ?? 0),
            'nee_without_aee' => (int) ($nee['without_aee'] ?? 0),
            'aee_without_nee' => (int) ($nee['aee_without_nee'] ?? 0),
            'school_time_available' => (bool) ($schoolTime['available'] ?? false),
            'school_time_has_ch' => (bool) ($schoolTime['has_ch'] ?? false),
            'school_time_hours' => ($schoolTime['network']['horas_aluno_semana'] ?? null),
            'delta_rede' => $delta['divergent_curricular_sum'] ?? $mat['delta_curricular'] ?? null,
            'tra_rural_pct_active' => $this->ruralPct($tra),
            'gap_clio_only' => (int) ($gap['only_clio'] ?? $gap['clio_only'] ?? 0),
            'gap_ieducar_only' => (int) ($gap['only_ieducar'] ?? $gap['ieducar_only'] ?? 0),
        ];

        DB::transaction(function () use ($campaign, $mat, $dis, $den, $doc, $nee, $stats, $triadePct, $errors, $warnings, $inferences, $snapshot): void {
            $this->purgeCampaign($campaign->id);

            BiClioCampaign::query()->create([
                'campaign_id' => $campaign->id,
                'city_id' => $campaign->city_id,
                'ibge' => $campaign->city?->ibge_municipio,
                'year' => (int) $campaign->year,
                'municipality_name' => $campaign->municipality_name ?? $campaign->city?->name,
                'uf' => $campaign->uf ?? $campaign->city?->uf,
                'profile' => $campaign->profile,
                'status' => $campaign->status,
                'reference_date' => $campaign->reference_date,
                'triade_pct' => is_numeric($triadePct) ? round((float) $triadePct, 1) : null,
                'schools_active' => (int) ($stats['active'] ?? 0),
                'schools_total' => (int) ($stats['total'] ?? $campaign->schools->count()),
                'mat_curricular' => (int) ($mat['acomp_curricular_sum'] ?? 0),
                'mat_aee' => (int) ($mat['acomp_aee_sum'] ?? 0),
                'mat_ac' => (int) ($mat['acomp_ac_sum'] ?? 0),
                'findings_errors' => $errors,
                'findings_warnings' => $warnings,
                'distortion_pct' => $snapshot['distortion_pct'],
                'density_avg' => $snapshot['density_avg'],
                'nee_people' => (int) ($nee['flagged'] ?? 0),
                'refreshed_at' => now(),
            ]);

            $coverageSchools = collect($this->parser->coverage($campaign)['schools'] ?? [])->keyBy('inep');
            $findingsBySchool = $campaign->findings
                ->where('severity', ClioCampaignFinding::SEVERITY_ERROR)
                ->groupBy('school_id');

            foreach ($campaign->schools as $school) {
                if (! $school instanceof ClioCampaignSchool) {
                    continue;
                }
                $inep = (string) $school->inep_code;
                $cov = $coverageSchools->get($inep);
                $parts = 0;
                if (is_array($cov)) {
                    $parts = (int) (! empty($cov['aluno'])) + (int) (! empty($cov['turma'])) + (int) (! empty($cov['profissional']));
                }
                $meta = is_array($school->meta) ? $school->meta : [];
                $active = ! CampaignAnalysisPresenter::isInactiveFunctioning($school->functioning_status);
                $rowsAluno = (int) $school->artifacts->where('kind', 'relacao_aluno_escola')->sum('row_count');
                $rowsTurma = (int) $school->artifacts->where('kind', 'relacao_turma_escola')->sum('row_count');
                $rowsProf = (int) $school->artifacts->where('kind', 'relacao_profissional_escola')->sum('row_count');
                $acompCurr = is_numeric($meta['total_curricular'] ?? null) ? (int) $meta['total_curricular'] : null;
                $deltaCurr = $acompCurr !== null ? $rowsAluno - $acompCurr : null;

                BiClioSchool::query()->create([
                    'campaign_id' => $campaign->id,
                    'school_id' => $school->id,
                    'inep' => $inep,
                    'name' => (string) $school->name,
                    'functioning_status' => $school->functioning_status,
                    'location' => $meta['location'] ?? null,
                    'dependency' => $school->dependency,
                    'is_active' => $active,
                    'triade_parts' => $parts,
                    'rows_aluno' => $rowsAluno,
                    'rows_turma' => $rowsTurma,
                    'rows_profissional' => $rowsProf,
                    'delta_curricular' => $deltaCurr,
                    'findings_errors' => (int) ($findingsBySchool->get($school->id)?->count() ?? 0),
                ]);

                BiClioQuality::query()->create([
                    'campaign_id' => $campaign->id,
                    'inep' => $inep,
                    'missing_triad' => $active && $parts < 3,
                    'delta_acomp' => $deltaCurr,
                    'distortion_pct' => null,
                    'distortion_n' => 0,
                    'eligible' => 0,
                    'density_avg' => $active ? $snapshot['density_avg'] : null,
                    'turmas_ge_40' => 0,
                    'turmas_sem_docente' => 0,
                ]);

                $schoolNee = $this->neeBuilder->build($campaign, (int) $school->id);
                BiClioInclusion::query()->create([
                    'campaign_id' => $campaign->id,
                    'inep' => $inep,
                    'qt_nee_people' => (int) ($schoolNee['flagged'] ?? 0),
                    'qt_deficiency' => (int) ($schoolNee['deficiency_flagged'] ?? 0),
                    'qt_disorder' => (int) ($schoolNee['disorder_flagged'] ?? 0),
                    'qt_ah' => (int) ($schoolNee['ah_flagged'] ?? 0),
                    'qt_without_aee' => (int) ($schoolNee['without_aee'] ?? 0),
                    'qt_aee_without_nee' => (int) ($schoolNee['aee_without_nee'] ?? 0),
                    'qt_underreporting' => (int) ($schoolNee['underreporting_flagged'] ?? 0),
                ]);
            }

            $byEtapaAluno = is_array($mat['by_etapa_ensino'] ?? null) ? $mat['by_etapa_ensino'] : [];
            $tur = $this->payload($inferences->get('INF-TUR'));
            $byEtapaTurma = is_array($tur['by_etapa_ensino'] ?? null) ? $tur['by_etapa_ensino'] : [];
            $etapas = array_unique(array_merge(array_keys($byEtapaAluno), array_keys($byEtapaTurma)));
            foreach ($etapas as $etapa) {
                BiClioEnrollmentStage::query()->create([
                    'campaign_id' => $campaign->id,
                    'inep' => null,
                    'etapa' => (string) $etapa,
                    'qt_alunos' => (int) ($byEtapaAluno[$etapa] ?? 0),
                    'qt_turmas' => (int) ($byEtapaTurma[$etapa] ?? 0),
                ]);
            }

            foreach ($this->insights->compose($snapshot) as $row) {
                BiClioInsight::query()->create([
                    'campaign_id' => $campaign->id,
                    'code' => $row['code'],
                    'severity' => $row['severity'],
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'metric_value' => $row['metric_value'],
                    'sort' => $row['sort'],
                ]);
            }
        });
    }

    /**
     * @return array{refreshed: int}
     */
    public function refreshAll(?int $year = null): array
    {
        $q = ClioCampaign::query()->whereIn('status', [
            ClioCampaign::STATUS_ANALYZED,
            ClioCampaign::STATUS_CROSS_CHECKED,
        ]);
        if ($year !== null) {
            $q->where('year', $year);
        }

        $n = 0;
        $q->orderBy('id')->each(function (ClioCampaign $campaign) use (&$n): void {
            $this->refreshCampaign($campaign);
            $n++;
        });

        return ['refreshed' => $n];
    }

    private function purgeCampaign(int $campaignId): void
    {
        BiClioInsight::query()->where('campaign_id', $campaignId)->delete();
        BiClioInclusion::query()->where('campaign_id', $campaignId)->delete();
        BiClioQuality::query()->where('campaign_id', $campaignId)->delete();
        BiClioEnrollmentStage::query()->where('campaign_id', $campaignId)->delete();
        BiClioSchool::query()->where('campaign_id', $campaignId)->delete();
        BiClioCampaign::query()->where('campaign_id', $campaignId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?ClioCampaignInference $inf): array
    {
        return is_array($inf?->payload) ? $inf->payload : [];
    }

    /**
     * @param  array<string, mixed>  $tra
     */
    private function ruralPct(array $tra): ?float
    {
        $byLoc = is_array($tra['active']['by_location_users'] ?? null)
            ? $tra['active']['by_location_users']
            : (is_array($tra['by_location_users'] ?? null) ? $tra['by_location_users'] : []);
        if ($byLoc === []) {
            return null;
        }
        $total = array_sum(array_map('intval', $byLoc));
        if ($total <= 0) {
            return null;
        }
        $rural = 0;
        foreach ($byLoc as $label => $n) {
            if (preg_match('/rural/iu', (string) $label) === 1) {
                $rural += (int) $n;
            }
        }

        return round(100 * $rural / $total, 1);
    }
}
