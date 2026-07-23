<?php

namespace App\Services\Clio\Analysis;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Services\Clio\Parse\CsvReader;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Censo de inclusão por pessoa (não por linha de matrícula), alinhado ao PDF:
 * total com marcador, NEE sem AEE, AEE sem deficiência/TEA/AH, subnotificação,
 * e breakdown DEF / TRS / AH.
 */
final class CampaignNeeCensusBuilder
{
    public function __construct(
        private readonly CsvReader $csv,
        private readonly RelationCsvAggregator $aggregator,
        private readonly NeeConditionClassifier $nee = new NeeConditionClassifier,
    ) {}

    /**
     * @return array{
     *   available: bool,
     *   people_scanned: int,
     *   flagged: int,
     *   deficiency_flagged: int,
     *   disorder_flagged: int,
     *   ah_flagged: int,
     *   without_aee: int,
     *   aee_without_nee: int,
     *   underreporting_flagged: int,
     *   by_nee: array<string, int>,
     *   by_deficiency: array<string, int>,
     *   by_disorder: array<string, int>,
     *   by_ah: array<string, int>,
     *   by_underreporting: array<string, int>,
     *   has_nee_columns: bool,
     *   unit: string
     * }
     */
    public function build(ClioCampaign $campaign): array
    {
        $disk = (string) config('clio.disk', 'local');
        $peopleScanned = 0;
        $flagged = 0;
        $defFlagged = 0;
        $disorderFlagged = 0;
        $ahFlagged = 0;
        $withoutAee = 0;
        $aeeWithoutNee = 0;
        $underFlagged = 0;
        $byNee = [];
        $byDef = [];
        $byDisorder = [];
        $byAh = [];
        $byUnder = [];
        $hasNeeCol = false;
        $filesRead = 0;

        foreach ($campaign->artifacts->where('kind', 'relacao_aluno_escola') as $artifact) {
            if (! $artifact instanceof ClioCampaignArtifact) {
                continue;
            }
            $path = $this->absolutePath($disk, $artifact->storage_path);
            if ($path === null) {
                continue;
            }
            try {
                $data = $this->csv->read($path, headerOffset: 1);
            } catch (Throwable) {
                continue;
            }
            $filesRead++;
            $rows = $data['rows'];
            if ($rows === []) {
                continue;
            }

            $headerKeys = array_keys($rows[0]);
            if ($this->aggregator->headersMatchNee($headerKeys)) {
                $hasNeeCol = true;
            }

            $aeeTurmas = $this->aeeTurmaCodes($campaign, $artifact->school_id, $disk);
            $people = $this->groupPeople($rows, $aeeTurmas);
            $peopleScanned += count($people);

            foreach ($people as $person) {
                $hasNee = $person['nee_tags'] !== [];
                $hasAee = $person['has_aee'];
                if ($hasNee) {
                    $flagged++;
                    foreach ($person['nee_tags'] as $tag) {
                        $byNee[$tag] = ($byNee[$tag] ?? 0) + 1;
                    }
                    if ($person['deficiencies'] !== []) {
                        $defFlagged++;
                        foreach ($person['deficiencies'] as $cond) {
                            $label = $cond['code'].' · '.$cond['label'];
                            $byDef[$label] = ($byDef[$label] ?? 0) + 1;
                        }
                    }
                    if ($person['disorders'] !== []) {
                        $disorderFlagged++;
                        foreach ($person['disorders'] as $cond) {
                            $label = $cond['code'].' · '.$cond['label'];
                            $byDisorder[$label] = ($byDisorder[$label] ?? 0) + 1;
                        }
                    }
                    if ($person['ah'] !== []) {
                        $ahFlagged++;
                        foreach ($person['ah'] as $cond) {
                            $label = $cond['code'].' · '.$cond['label'];
                            $byAh[$label] = ($byAh[$label] ?? 0) + 1;
                        }
                    }
                    if (! $hasAee) {
                        $withoutAee++;
                    }
                }
                if ($hasAee && ! $hasNee) {
                    $aeeWithoutNee++;
                }
                if ($person['underreporting'] !== []) {
                    $underFlagged++;
                    foreach ($person['underreporting'] as $flag) {
                        $ulabel = $flag['code'].' · '.$flag['label'];
                        $byUnder[$ulabel] = ($byUnder[$ulabel] ?? 0) + 1;
                    }
                }
            }
        }

        return [
            'available' => $filesRead > 0 && $hasNeeCol,
            'people_scanned' => $peopleScanned,
            'flagged' => $flagged,
            'deficiency_flagged' => $defFlagged,
            'disorder_flagged' => $disorderFlagged,
            'ah_flagged' => $ahFlagged,
            'without_aee' => $withoutAee,
            'aee_without_nee' => $aeeWithoutNee,
            'underreporting_flagged' => $underFlagged,
            'by_nee' => $this->aggregator->sortDesc($byNee),
            'by_deficiency' => $this->aggregator->sortDesc($byDef),
            'by_disorder' => $this->aggregator->sortDesc($byDisorder),
            'by_ah' => $this->aggregator->sortDesc($byAh),
            'by_underreporting' => $this->aggregator->sortDesc($byUnder),
            'has_nee_columns' => $hasNeeCol,
            'unit' => 'people',
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, true>  $aeeTurmas
     * @return list<array<string, mixed>>
     */
    public function groupPeople(array $rows, array $aeeTurmas): array
    {
        if ($rows === []) {
            return [];
        }

        $sample = $rows[0];
        $hasGranularDefColumns = (bool) ($this->nee->classifyRow($sample)['has_granular_def_columns'] ?? false);

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
                    'nee_tags' => [],
                    'deficiencies' => [],
                    'disorders' => [],
                    'ah' => [],
                    'underreporting' => [],
                    'has_aee' => isset($aeeIds[$id]),
                ];
            }

            $classified = $this->nee->classifyRow($row);
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
        }

        $out = [];
        foreach ($byId as $person) {
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
            $person['underreporting'] = $this->nee->assessUnderreporting($merged, (bool) $person['has_aee']);
            $out[] = $person;
        }

        return $out;
    }

    /**
     * @return array<string, true>
     */
    public function aeeTurmaCodes(ClioCampaign $campaign, ?int $schoolId, string $disk): array
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
            $codigo = trim($this->csv->value($row, 'Código da turma'));
            $tipo = trim($this->csv->value($row, 'Tipo de turma'));
            $etapa = trim($this->csv->value($row, 'Etapa de ensino'));
            if ($codigo === '') {
                continue;
            }
            if (
                $this->aggregator->classifyTipoTurma($tipo) === RelationCsvAggregator::BUCKET_AEE
                || preg_match('/\baee\b|atendimento educacional/iu', $etapa) === 1
            ) {
                $codes[$codigo] = true;
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

        return $etapa !== '' && (
            str_contains(mb_strtolower($etapa), 'atendimento educacional')
            || preg_match('/\baee\b/u', mb_strtolower($etapa)) === 1
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
