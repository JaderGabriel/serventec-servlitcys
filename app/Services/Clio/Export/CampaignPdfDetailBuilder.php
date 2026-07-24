<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Services\Clio\Analysis\AgeGradeRules;
use App\Services\Clio\Analysis\EtapaLabelOrder;
use App\Services\Clio\Analysis\NeeConditionClassifier;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tabelas detalhadas para o PDF Clio (inclui nome e CPF das Relações para correção operacional).
 */
final class CampaignPdfDetailBuilder
{
    private const SAMPLE_LIMIT = 60;

    public function __construct(
        private readonly CsvReader $csv,
        private readonly RelationCsvAggregator $aggregator,
        private readonly CampaignActiveCensusMatrixBuilder $censusMatrixBuilder,
        private readonly AgeGradeRules $ageRules = new AgeGradeRules,
    ) {}

    /**
     * @return array{
     *   distortion_by_etapa: list<array<string, mixed>>,
     *   distortion_students: list<array<string, mixed>>,
     *   missing_demographics: list<array<string, mixed>>,
     *   nee_students: list<array<string, mixed>>,
     *   nee_without_aee: int,
     *   nee_total: int,
     *   missing_demographics_total: int
     * }
     */
    public function build(ClioCampaign $campaign): array
    {
        $year = (int) $campaign->year;
        $disk = (string) config('clio.disk', 'local');

        $etapaStats = [];
        $distortionStudents = [];
        $missingDemo = [];
        $neeStudents = [];
        $neeWithoutAee = 0;
        $neeTotal = 0;
        $neeWithUnder = 0;
        $aeeWithoutNee = 0;
        $missingTotal = 0;
        $neeClassifier = new NeeConditionClassifier;
        $etapaOrder = new EtapaLabelOrder;

        $alunoArtifacts = $campaign->artifacts
            ->where('kind', 'relacao_aluno_escola')
            ->values();

        foreach ($alunoArtifacts as $artifact) {
            if (! $artifact instanceof ClioCampaignArtifact) {
                continue;
            }
            $school = $artifact->school;
            $schoolName = (string) ($school?->name ?? __('Escola'));
            $inep = (string) ($school?->inep_code ?? '');

            $path = $this->absolutePath($disk, $artifact->storage_path);
            if ($path === null) {
                continue;
            }

            try {
                $data = $this->csv->read($path, headerOffset: 1);
            } catch (Throwable) {
                continue;
            }

            $aeeTurmas = $this->aeeTurmaCodesForSchool($campaign, $artifact->school_id, $disk);
                $people = $this->groupPeople($data['rows'], $aeeTurmas, $year, $neeClassifier);

            foreach ($people as $person) {
                foreach ($person['etapas_distorcao'] as $etapa => $info) {
                    if (! isset($etapaStats[$etapa])) {
                        $etapaStats[$etapa] = [
                            'etapa' => $etapa,
                            'eligible' => 0,
                            'distorcao' => 0,
                            'schools' => [],
                            'alunos' => 0,
                        ];
                    }
                    $etapaStats[$etapa]['eligible']++;
                    if ($info['distorcao']) {
                        $etapaStats[$etapa]['distorcao']++;
                        $etapaStats[$etapa]['alunos']++;
                        $etapaStats[$etapa]['schools'][$inep !== '' ? $inep : $schoolName] = true;
                        if (count($distortionStudents) < self::SAMPLE_LIMIT) {
                            $distortionStudents[] = [
                                'id' => $person['id_raw'],
                                'name' => $person['name'] !== '' ? $person['name'] : '—',
                                'cpf' => $person['cpf'] !== '' ? $person['cpf'] : '—',
                                'school' => $schoolName,
                                'inep' => $inep,
                                'turma' => $info['turma'] !== '' ? $info['turma'] : '—',
                                'matricula' => $info['matricula'] !== '' ? $info['matricula'] : '—',
                                'etapa' => $etapa,
                                'age' => $info['age'],
                                'expected' => $info['expected'],
                                'delay' => $info['delay'],
                            ];
                        }
                    }
                }

                // Demografia: uma linha por pessoa se falta cor e/ou sexo (quando colunas existem)
                if ($person['has_cor_col'] || $person['has_sexo_col']) {
                    $missing = [];
                    if ($person['has_cor_col'] && $person['without_cor']) {
                        $missing[] = __('Cor/Raça');
                    }
                    if ($person['has_sexo_col'] && $person['without_sexo']) {
                        $missing[] = __('Sexo');
                    }
                    if ($missing !== []) {
                        $missingTotal++;
                        if (count($missingDemo) < self::SAMPLE_LIMIT) {
                            $missingDemo[] = [
                                'id' => $person['id_raw'],
                                'name' => $person['name'] !== '' ? $person['name'] : '—',
                                'cpf' => $person['cpf'] !== '' ? $person['cpf'] : '—',
                                'school' => $schoolName,
                                'inep' => $inep,
                                'faltando' => implode(', ', $missing),
                                'matriculas' => $person['matriculas'] !== [] ? implode(', ', $person['matriculas']) : '—',
                                'turmas' => $person['turmas'] !== [] ? implode(', ', $person['turmas']) : '—',
                            ];
                        }
                    }
                }

                if ($person['nee_tags'] !== [] || $person['underreporting'] !== []) {
                    if ($person['nee_tags'] !== []) {
                        $neeTotal++;
                    }
                    $hasAee = $person['has_aee'];
                    $hasNeeTags = $person['nee_tags'] !== [];
                    if ($hasNeeTags && ! $hasAee) {
                        $neeWithoutAee++;
                    }
                    if ($hasAee && ! $hasNeeTags) {
                        $aeeWithoutNee++;
                    }
                    if ($person['underreporting'] !== []) {
                        $neeWithUnder++;
                    }
                    if (count($neeStudents) < self::SAMPLE_LIMIT) {
                        $defLabels = array_map(
                            static fn (array $c): string => $c['code'].' · '.$c['label'],
                            $person['deficiencies'],
                        );
                        $trsLabels = array_map(
                            static fn (array $c): string => $c['code'].' · '.$c['label'],
                            $person['disorders'],
                        );
                        $ahLabels = array_map(
                            static fn (array $c): string => $c['code'].' · '.$c['label'],
                            $person['ah'],
                        );
                        $underLabels = array_map(
                            static fn (array $f): string => $f['code'].' · '.$f['label'],
                            $person['underreporting'],
                        );
                        $aeeFlag = match (true) {
                            $hasAee && ! $hasNeeTags => __('AEE sem deficiência/TEA/AH'),
                            $hasAee => __('Com AEE'),
                            default => __('NEE sem matrícula AEE'),
                        };
                        $neeStudents[] = [
                            'id' => $person['id_raw'],
                            'name' => $person['name'] !== '' ? $person['name'] : '—',
                            'cpf' => $person['cpf'] !== '' ? $person['cpf'] : '—',
                            'school' => $schoolName,
                            'inep' => $inep,
                            'needs' => implode(', ', $person['nee_tags']) ?: '—',
                            'deficiencies' => $defLabels !== [] ? implode('; ', $defLabels) : '—',
                            'disorders' => $trsLabels !== [] ? implode('; ', $trsLabels) : '—',
                            'ah' => $ahLabels !== [] ? implode('; ', $ahLabels) : '—',
                            'deficiency_codes' => array_column($person['deficiencies'], 'code'),
                            'disorder_codes' => array_column($person['disorders'], 'code'),
                            'underreporting' => $underLabels !== [] ? implode('; ', $underLabels) : '—',
                            'underreporting_flags' => $person['underreporting'],
                            'has_underreporting' => $person['underreporting'] !== [],
                            'has_matricula' => $person['matriculas'] !== [],
                            'matriculas' => $person['matriculas'] !== [] ? implode(', ', $person['matriculas']) : '—',
                            'turmas' => $person['turmas'] !== [] ? implode(', ', $person['turmas']) : '—',
                            'has_aee' => $hasAee,
                            'has_nee' => $hasNeeTags,
                            'aee_without_nee' => $hasAee && ! $hasNeeTags,
                            'aee_flag' => $aeeFlag,
                        ];
                    }
                }
            }
        }

        // Preferir estatísticas oficiais da inferência quando existirem; enriquecer com nº escolas/alunos do scan
        $inf = $campaign->inferences->firstWhere('code', 'INF-DIS');
        $infByEtapa = is_array($inf?->payload['by_etapa'] ?? null) ? $inf->payload['by_etapa'] : [];
        $distortionByEtapa = [];
        $keys = $infByEtapa !== [] ? array_keys($infByEtapa) : array_keys($etapaStats);
        foreach ($keys as $etapa) {
            $scan = $etapaStats[$etapa] ?? null;
            $infRow = is_array($infByEtapa[$etapa] ?? null) ? $infByEtapa[$etapa] : [];
            $eligible = (int) ($infRow['eligible'] ?? $scan['eligible'] ?? 0);
            $dist = (int) ($infRow['distorcao'] ?? $scan['distorcao'] ?? 0);
            $schools = $scan['schools'] ?? [];
            $distortionByEtapa[] = [
                'etapa' => $etapa,
                'eligible' => $eligible,
                'distorcao' => $dist,
                'pct' => $eligible > 0 ? round(100 * $dist / $eligible, 1) : null,
                'escolas' => count($schools),
                'alunos' => (int) ($scan['alunos'] ?? $dist),
            ];
        }
        $distortionByEtapa = $etapaOrder->sortRowsByEtapaKey($distortionByEtapa, 'etapa');
        $distortionByEtapa = array_slice($distortionByEtapa, 0, 40);
        $distortionStudents = $etapaOrder->sortRowsByEtapaKey($distortionStudents, 'etapa');

        // Atenção: AEE sem NEE e NEE sem AEE primeiro
        usort($neeStudents, static function (array $a, array $b): int {
            $score = static fn (array $r): int => ((int) (! empty($r['aee_without_nee'])) * 12)
                + ((int) empty($r['has_aee']) * 10)
                + ((int) (! empty($r['has_underreporting'])) * 5);

            return $score($b) <=> $score($a);
        });

        $censusMatrix = $this->censusMatrixBuilder->build($campaign);
        $demographics = $this->demographicsFromInference($campaign);

        return [
            'distortion_by_etapa' => $distortionByEtapa,
            'distortion_students' => $distortionStudents,
            'missing_demographics' => $missingDemo,
            'demographics' => $demographics,
            'nee_students' => $neeStudents,
            'nee_without_aee' => $neeWithoutAee,
            'nee_aee_without_condition' => $aeeWithoutNee,
            'nee_total' => $neeTotal,
            'nee_underreporting' => $neeWithUnder,
            'missing_demographics_total' => $missingTotal,
            'census_matrix' => $censusMatrix,
        ];
    }

    /**
     * Barras demográficas alinhadas a INF-DEM / UI (sem PII).
     *
     * @return array{available: bool, by_cor_raca: list<array<string, mixed>>, by_sexo: list<array<string, mixed>>, by_faixa_etaria: list<array<string, mixed>>, scanned: int}
     */
    private function demographicsFromInference(ClioCampaign $campaign): array
    {
        $inf = $campaign->inferences->firstWhere('code', 'INF-DEM')
            ?? $campaign->inferences()->where('code', 'INF-DEM')->first();
        $payload = is_array($inf?->payload) ? $inf->payload : [];
        $agg = new RelationCsvAggregator;

        return [
            'available' => $payload !== [],
            'scanned' => (int) ($payload['scanned'] ?? 0),
            'by_cor_raca' => $agg->toBars(is_array($payload['by_cor_raca'] ?? null) ? $payload['by_cor_raca'] : [], 10),
            'by_sexo' => $agg->toBars(is_array($payload['by_sexo'] ?? null) ? $payload['by_sexo'] : [], 6),
            'by_faixa_etaria' => $agg->toBars(is_array($payload['by_faixa_etaria'] ?? null) ? $payload['by_faixa_etaria'] : [], 8),
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, true>  $aeeTurmas
     * @return list<array<string, mixed>>
     */
    private function groupPeople(array $rows, array $aeeTurmas, int $year, NeeConditionClassifier $neeClassifier): array
    {
        if ($rows === []) {
            return [];
        }

        $sample = $rows[0];
        $hasCor = $this->hasHeader($sample, ['Cor/Raça', 'Cor/Raca', 'Raça', 'Raca', 'Cor']);
        $hasSexo = $this->hasHeader($sample, ['Sexo', 'Sexo biológico', 'Sexo biologico', 'Gênero', 'Genero']);
        $hasGranularDefColumns = (bool) ($neeClassifier->classifyRow($sample)['has_granular_def_columns'] ?? false);

        /** @var array<string, array<string, mixed>> $byId */
        $byId = [];

        $aeeIds = [];
        foreach ($rows as $row) {
            $id = $this->personKey($row);
            $turma = trim($this->csv->value($row, 'Código da turma'));
            $etapa = trim($this->csv->value($row, 'Etapa de ensino'));
            if ($this->isAeeRow($turma, $etapa, $aeeTurmas)) {
                $aeeIds[$id] = true;
            }
        }

        foreach ($rows as $row) {
            $id = $this->personKey($row);
            if (! isset($byId[$id])) {
                $byId[$id] = [
                    'id_raw' => $id,
                    'name' => '',
                    'cpf' => '',
                    'matriculas' => [],
                    'turmas' => [],
                    'nee_tags' => [],
                    'deficiencies' => [],
                    'disorders' => [],
                    'ah' => [],
                    'underreporting' => [],
                    'has_aee' => isset($aeeIds[$id]),
                    'has_cor_col' => $hasCor,
                    'has_sexo_col' => $hasSexo,
                    'without_cor' => false,
                    'without_sexo' => false,
                    'etapas_distorcao' => [],
                ];
            }

            $mat = trim($this->csv->value($row, 'Código da Matrícula'));
            if ($mat === '') {
                $mat = trim($this->csv->value($row, 'Código da matrícula'));
            }
            $turma = trim($this->csv->value($row, 'Código da turma'));
            $etapa = trim($this->csv->value($row, 'Etapa de ensino'));
            $nasc = trim($this->csv->value($row, 'Data de nascimento'));
            if ($nasc === '') {
                $nasc = trim($this->csv->value($row, 'Data Nascimento'));
            }
            $nome = trim($this->csv->value($row, 'Nome'));
            if ($nome === '') {
                $nome = trim($this->csv->value($row, 'Nome completo'));
            }
            $cpf = trim($this->csv->value($row, 'CPF'));
            if ($nome !== '' && $byId[$id]['name'] === '') {
                $byId[$id]['name'] = $nome;
            }
            if ($cpf !== '' && $byId[$id]['cpf'] === '') {
                $byId[$id]['cpf'] = $cpf;
            }

            if ($mat !== '') {
                $byId[$id]['matriculas'][$mat] = $mat;
            }
            if ($turma !== '') {
                $byId[$id]['turmas'][$turma] = $turma;
            }

            if ($hasCor) {
                $cor = $this->firstNonEmpty($row, ['Cor/Raça', 'Cor/Raca', 'Raça', 'Raca', 'Cor']);
                if ($cor === '') {
                    $byId[$id]['without_cor'] = true;
                }
            }
            if ($hasSexo) {
                $sexo = $this->firstNonEmpty($row, ['Sexo', 'Sexo biológico', 'Sexo biologico', 'Gênero', 'Genero']);
                if ($sexo === '') {
                    $byId[$id]['without_sexo'] = true;
                }
            }

            $classified = $neeClassifier->classifyRow($row);
            foreach ($classified['tags'] as $tag) {
                $byId[$id]['nee_tags'][$tag] = $tag;
            }
            foreach ($classified['deficiencies'] as $cond) {
                $byId[$id]['deficiencies'][$cond['code']] = $cond;
            }
            foreach ($classified['disorders'] as $cond) {
                $byId[$id]['disorders'][$cond['code']] = $cond;
            }
            foreach ($classified['ah'] as $cond) {
                $byId[$id]['ah'][$cond['code']] = $cond;
            }

            if ($etapa !== '') {
                $cls = $this->ageRules->classify($etapa, $nasc, $year);
                if (in_array($cls['status'], [
                    AgeGradeRules::STATUS_ON_TRACK,
                    AgeGradeRules::STATUS_EARLY,
                    AgeGradeRules::STATUS_DELAY_1,
                    AgeGradeRules::STATUS_DISTORTION,
                ], true)) {
                    $isDist = $cls['status'] === AgeGradeRules::STATUS_DISTORTION;
                    $prev = $byId[$id]['etapas_distorcao'][$etapa] ?? null;
                    if ($prev === null || ($isDist && ! $prev['distorcao'])) {
                        $byId[$id]['etapas_distorcao'][$etapa] = [
                            'distorcao' => $isDist,
                            'turma' => $turma,
                            'matricula' => $mat,
                            'age' => $cls['age'],
                            'expected' => $cls['expected'],
                            'delay' => $cls['delay'],
                        ];
                    }
                }
            }
        }

        $out = [];
        foreach ($byId as $person) {
            $person['matriculas'] = array_values($person['matriculas']);
            $person['turmas'] = array_values($person['turmas']);
            $person['nee_tags'] = array_values($person['nee_tags']);
            $person['deficiencies'] = array_values($person['deficiencies']);
            $person['disorders'] = array_values($person['disorders']);
            $person['ah'] = array_values($person['ah']);
            $merged = [
                'conditions' => array_merge($person['deficiencies'], $person['disorders'], $person['ah']),
                'deficiencies' => $person['deficiencies'],
                'disorders' => $person['disorders'],
                'ah' => $person['ah'],
                'codes' => array_merge(
                    array_column($person['deficiencies'], 'code'),
                    array_column($person['disorders'], 'code'),
                    array_column($person['ah'], 'code'),
                ),
                'flagged' => $person['nee_tags'] !== [],
                'has_specific_deficiency' => array_filter(
                    $person['deficiencies'],
                    static fn (array $c): bool => $c['code'] !== 'DEF',
                ) !== [],
                'has_generic_deficiency' => array_filter(
                    $person['deficiencies'],
                    static fn (array $c): bool => $c['code'] === 'DEF',
                ) !== [],
                'has_granular_def_columns' => $hasGranularDefColumns,
            ];
            $person['underreporting'] = $neeClassifier->assessUnderreporting($merged, (bool) $person['has_aee']);
            $out[] = $person;
        }

        return $out;
    }

    /**
     * @return array<string, true>
     */
    private function aeeTurmaCodesForSchool(ClioCampaign $campaign, ?int $schoolId, string $disk): array
    {
        if ($schoolId === null) {
            return [];
        }
        $turmaArt = $campaign->artifacts
            ->where('kind', 'relacao_turma_escola')
            ->firstWhere('school_id', $schoolId);
        if (! $turmaArt instanceof ClioCampaignArtifact) {
            return [];
        }
        $path = $this->absolutePath($disk, $turmaArt->storage_path);
        if ($path === null) {
            return [];
        }
        try {
            $data = $this->csv->read($path, headerOffset: 1);
        } catch (Throwable) {
            return [];
        }
        $codes = [];
        foreach ($data['rows'] as $row) {
            $code = trim($this->csv->value($row, 'Código da turma'));
            $tipo = trim($this->csv->value($row, 'Tipo de turma'));
            $etapa = trim($this->csv->value($row, 'Etapa de ensino'));
            if ($code === '') {
                continue;
            }
            if (
                $this->aggregator->classifyTipoTurma($tipo) === RelationCsvAggregator::BUCKET_AEE
                || $this->isAeeEtapa($etapa)
            ) {
                $codes[$code] = true;
            }
        }

        return $codes;
    }

    /**
     * @param  array<string, true>  $aeeTurmas
     */
    private function isAeeRow(string $turma, string $etapa, array $aeeTurmas): bool
    {
        if ($turma !== '' && isset($aeeTurmas[$turma])) {
            return true;
        }

        return $this->isAeeEtapa($etapa);
    }

    private function isAeeEtapa(string $etapa): bool
    {
        $e = mb_strtolower(trim($etapa));

        return $e !== '' && (
            str_contains($e, 'atendimento educacional')
            || preg_match('/\baee\b/u', $e) === 1
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function personKey(array $row): string
    {
        $id = trim($this->csv->value($row, 'Identificação única'));
        if ($id !== '') {
            return $id;
        }
        $mat = trim($this->csv->value($row, 'Código da Matrícula'));
        if ($mat === '') {
            $mat = trim($this->csv->value($row, 'Código da matrícula'));
        }

        return $mat !== '' ? 'mat:'.$mat : 'row:'.md5(implode('|', $row));
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     */
    private function hasHeader(array $row, array $headers): bool
    {
        foreach ($headers as $header) {
            if (array_key_exists($header, $row)) {
                return true;
            }
        }
        $keys = array_map(
            static fn ($k) => mb_strtolower(ltrim((string) $k, "\xEF\xBB\xBF")),
            array_keys($row),
        );
        foreach ($headers as $header) {
            if (in_array(mb_strtolower($header), $keys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     */
    private function firstNonEmpty(array $row, array $headers): string
    {
        foreach ($headers as $header) {
            $v = trim($this->csv->value($row, $header));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function detectNeeTagsPublic(array $row): array
    {
        return (new NeeConditionClassifier)->classifyRow($row)['tags'];
    }

    private function absolutePath(string $disk, ?string $storagePath): ?string
    {
        if ($storagePath === null || $storagePath === '') {
            return null;
        }
        try {
            $path = Storage::disk($disk)->path($storagePath);
        } catch (Throwable) {
            return null;
        }

        return is_file($path) && is_readable($path) ? $path : null;
    }
}
