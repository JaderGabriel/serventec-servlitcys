<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\NeeConditionClassifier;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Matriz de exposição do ano atual (escolas ativas), no espírito dos
 * «Resultados finais» municipais (infantil / fundamental / EJA × parcial/integral × urbana/rural × regular/especial).
 * Sem comparação com ano anterior.
 */
final class CampaignActiveCensusMatrixBuilder
{
    public function __construct(
        private readonly CsvReader $csv,
        private readonly RelationCsvAggregator $aggregator,
        private readonly NeeConditionClassifier $nee = new NeeConditionClassifier,
    ) {}

    /**
     * @return array{
     *   available: bool,
     *   year: int,
     *   municipality: string,
     *   uf: string,
     *   ibge: string,
     *   schools_active: int,
     *   note: string,
     *   infantil: array<string, mixed>,
     *   fundamental: array<string, mixed>,
     *   eja: array<string, mixed>,
     *   geral: array<string, mixed>
     * }
     */
    public function build(ClioCampaign $campaign): array
    {
        $disk = (string) config('clio.disk', 'local');
        $cells = $this->emptyCells();
        $schoolsActive = 0;
        $rowsCounted = 0;

        foreach ($campaign->schools as $school) {
            if (CampaignAnalysisPresenter::isInactiveFunctioning($school->functioning_status)) {
                continue;
            }
            $schoolsActive++;
            $meta = is_array($school->meta) ? $school->meta : [];
            $location = $this->normalizeLocation((string) ($meta['location'] ?? ''));

            $turmaArt = $school->artifacts->firstWhere('kind', 'relacao_turma_escola');
            $alunoArt = $school->artifacts->firstWhere('kind', 'relacao_aluno_escola');
            if (! $alunoArt instanceof ClioCampaignArtifact) {
                continue;
            }

            $profiles = [];
            if ($turmaArt instanceof ClioCampaignArtifact) {
                $turmaPath = $this->absolutePath($disk, $turmaArt->storage_path);
                if ($turmaPath !== null) {
                    try {
                        $turmaData = $this->csv->read($turmaPath, 1);
                        $profiles = $this->aggregator->aggregateTurmas($turmaData['rows'], $this->csv)['turma_profiles'] ?? [];
                    } catch (Throwable) {
                        $profiles = [];
                    }
                }
            }

            $alunoPath = $this->absolutePath($disk, $alunoArt->storage_path);
            if ($alunoPath === null) {
                continue;
            }
            try {
                $alunoData = $this->csv->read($alunoPath, 1);
            } catch (Throwable) {
                continue;
            }

            foreach ($alunoData['rows'] as $row) {
                $turmaCode = trim($this->csv->value($row, 'Código da turma'));
                $etapa = trim($this->csv->value($row, 'Etapa de ensino'));
                $profile = is_array($profiles[$turmaCode] ?? null) ? $profiles[$turmaCode] : [];
                $bucket = (string) ($profile['bucket'] ?? $this->aggregator->classifyTipoTurma(
                    trim($this->csv->value($row, 'Tipo de turma')),
                ));

                // Atividade complementar não entra na matriz de resultados (como no modelo de referência).
                if ($bucket === RelationCsvAggregator::BUCKET_AC) {
                    continue;
                }

                $stage = $this->classifyStage($etapa, (string) ($profile['etapa'] ?? ''), (string) ($profile['agregada'] ?? ''));
                $jornada = ! empty($profile['extended']) ? 'integral' : 'parcial';
                $isAee = $bucket === RelationCsvAggregator::BUCKET_AEE;
                $hasNee = $this->nee->classifyRow($row)['flagged'];

                if ($stage === null) {
                    // AEE puro sem etapa curricular: entra só no total de Educação Especial.
                    if ($isAee || $hasNee) {
                        $cells['_especial_extra'] = (int) ($cells['_especial_extra'] ?? 0) + 1;
                    }

                    continue;
                }

                // Regular = vínculo curricular; Especial = AEE ou marcador NEE/TEA/AH na linha.
                if ($isAee || $hasNee) {
                    $this->increment($cells, $stage, $jornada, $location, 'especial');
                }
                if (! $isAee) {
                    $this->increment($cells, $stage, $jornada, $location, 'regular');
                }
                $rowsCounted++;
            }
        }

        $especialExtra = (int) ($cells['_especial_extra'] ?? 0);
        unset($cells['_especial_extra']);

        $infantil = $this->blockInfantil($cells);
        $fundamental = $this->blockFundamental($cells);
        $eja = $this->blockEja($cells);
        $geral = $this->blockGeral($cells, $especialExtra);

        return [
            'available' => $rowsCounted > 0,
            'year' => (int) $campaign->year,
            'municipality' => (string) $campaign->municipality_name,
            'uf' => (string) $campaign->uf,
            'ibge' => (string) ($campaign->ibge_municipio ?? ''),
            'schools_active' => $schoolsActive,
            'rows_counted' => $rowsCounted,
            'note' => __('Exposição do ano atual nas escolas em atividade. Sem comparação com ano anterior. Regular = vínculo curricular; Especial = AEE ou marcador de deficiência/TEA/AH. Parcial/Integral usa Turno/CH da turma quando existir (senão parcial). Rural/Urbana vem da Localização do Acompanhamento.'),
            'infantil' => $infantil,
            'fundamental' => $fundamental,
            'eja' => $eja,
            'geral' => $geral,
        ];
    }

    /**
     * @return array<string, array<string, array<string, array<string, int>>>>
     */
    private function emptyCells(): array
    {
        $stages = ['creche', 'pre_escola', 'anos_iniciais', 'anos_finais', 'eja_fundamental'];
        $cells = [];
        foreach ($stages as $stage) {
            foreach (['parcial', 'integral'] as $jornada) {
                foreach (['Urbana', 'Rural'] as $loc) {
                    $cells[$stage][$jornada][$loc] = ['regular' => 0, 'especial' => 0];
                }
            }
        }

        return $cells;
    }

    /**
     * @param  array<string, mixed>  $cells
     */
    private function increment(array &$cells, string $stage, string $jornada, string $location, string $modality): void
    {
        if (! isset($cells[$stage][$jornada][$location][$modality])) {
            return;
        }
        $cells[$stage][$jornada][$location][$modality]++;
    }

    private function classifyStage(string $etapa, string $turmaEtapa, string $agregada): ?string
    {
        $blob = mb_strtolower(trim($etapa.' '.$turmaEtapa.' '.$agregada));
        if ($blob === '') {
            return null;
        }
        if (preg_match('/\baee\b|atendimento educacional/u', $blob) === 1) {
            // Sem etapa curricular: não aloca coluna de etapa.
            return null;
        }
        if (preg_match('/\beja\b|jovens e adultos/u', $blob) === 1) {
            if (preg_match('/m[eé]dio/u', $blob) === 1) {
                return null; // fora do recorte do modelo de referência (EJA fundamental)
            }

            return 'eja_fundamental';
        }
        if (preg_match('/creche|ber[cç][aá]rio/u', $blob) === 1) {
            return 'creche';
        }
        if (preg_match('/pr[eé][\-\s]?escola|infantil/u', $blob) === 1) {
            return 'pre_escola';
        }
        if (preg_match('/anos\s*iniciais|[1-5][ºo]\s*ano|1º ao 5|fundamental.*([1-5])/u', $blob) === 1
            && preg_match('/anos\s*finais|[6-9]/u', $blob) !== 1) {
            return 'anos_iniciais';
        }
        if (preg_match('/anos\s*finais|[6-9][ºo]\s*ano|6º ao 9/u', $blob) === 1) {
            return 'anos_finais';
        }
        if (preg_match('/fundamental/u', $blob) === 1) {
            return 'anos_iniciais';
        }

        return null;
    }

    private function normalizeLocation(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        if (preg_match('/rural/u', $s) === 1) {
            return 'Rural';
        }

        return 'Urbana';
    }

    /**
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>
     */
    private function blockInfantil(array $cells): array
    {
        return [
            'title' => __('Educação infantil'),
            'columns' => [
                ['key' => 'creche_parcial', 'label' => __('Creche Parcial'), 'stage' => 'creche', 'jornada' => 'parcial'],
                ['key' => 'creche_integral', 'label' => __('Creche Integral'), 'stage' => 'creche', 'jornada' => 'integral'],
                ['key' => 'pre_parcial', 'label' => __('Pré-escola Parcial'), 'stage' => 'pre_escola', 'jornada' => 'parcial'],
                ['key' => 'pre_integral', 'label' => __('Pré-escola Integral'), 'stage' => 'pre_escola', 'jornada' => 'integral'],
            ],
            'rows' => [
                'regular' => __('Regular'),
                'especial' => __('Especial'),
            ],
            'values' => $this->columnValues($cells, [
                ['creche', 'parcial'],
                ['creche', 'integral'],
                ['pre_escola', 'parcial'],
                ['pre_escola', 'integral'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>
     */
    private function blockFundamental(array $cells): array
    {
        return [
            'title' => __('Educação fundamental'),
            'columns' => [
                ['key' => 'ai_parcial', 'label' => __('Anos Iniciais Parcial'), 'stage' => 'anos_iniciais', 'jornada' => 'parcial'],
                ['key' => 'ai_integral', 'label' => __('Anos Iniciais Integral'), 'stage' => 'anos_iniciais', 'jornada' => 'integral'],
                ['key' => 'af_parcial', 'label' => __('Anos Finais Parcial'), 'stage' => 'anos_finais', 'jornada' => 'parcial'],
                ['key' => 'af_integral', 'label' => __('Anos Finais Integral'), 'stage' => 'anos_finais', 'jornada' => 'integral'],
            ],
            'rows' => [
                'regular' => __('Regular'),
                'especial' => __('Especial'),
            ],
            'values' => $this->columnValues($cells, [
                ['anos_iniciais', 'parcial'],
                ['anos_iniciais', 'integral'],
                ['anos_finais', 'parcial'],
                ['anos_finais', 'integral'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>
     */
    private function blockEja(array $cells): array
    {
        return [
            'title' => __('EJA presencial fundamental'),
            'columns' => [
                ['key' => 'eja', 'label' => __('EJA Presencial Fundamental'), 'stage' => 'eja_fundamental', 'jornada' => 'parcial'],
            ],
            'rows' => [
                'regular' => __('Regular'),
                'especial' => __('Especial'),
            ],
            // EJA: soma parcial+integral na mesma coluna (recorte do modelo).
            'values' => [
                'eja' => [
                    'Urbana' => [
                        'regular' => (int) ($cells['eja_fundamental']['parcial']['Urbana']['regular'] ?? 0)
                            + (int) ($cells['eja_fundamental']['integral']['Urbana']['regular'] ?? 0),
                        'especial' => (int) ($cells['eja_fundamental']['parcial']['Urbana']['especial'] ?? 0)
                            + (int) ($cells['eja_fundamental']['integral']['Urbana']['especial'] ?? 0),
                    ],
                    'Rural' => [
                        'regular' => (int) ($cells['eja_fundamental']['parcial']['Rural']['regular'] ?? 0)
                            + (int) ($cells['eja_fundamental']['integral']['Rural']['regular'] ?? 0),
                        'especial' => (int) ($cells['eja_fundamental']['parcial']['Rural']['especial'] ?? 0)
                            + (int) ($cells['eja_fundamental']['integral']['Rural']['especial'] ?? 0),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $cells
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return array<string, array<string, array<string, int>>>
     */
    private function columnValues(array $cells, array $pairs): array
    {
        $keys = ['creche_parcial', 'creche_integral', 'pre_parcial', 'pre_integral', 'ai_parcial', 'ai_integral', 'af_parcial', 'af_integral'];
        $out = [];
        foreach ($pairs as $i => [$stage, $jornada]) {
            $key = $keys[$i] ?? ($stage.'_'.$jornada);
            // Remap keys for fundamental block indices 0-3 after infantil's 4 keys — use explicit:
            if ($stage === 'anos_iniciais' && $jornada === 'parcial') {
                $key = 'ai_parcial';
            } elseif ($stage === 'anos_iniciais' && $jornada === 'integral') {
                $key = 'ai_integral';
            } elseif ($stage === 'anos_finais' && $jornada === 'parcial') {
                $key = 'af_parcial';
            } elseif ($stage === 'anos_finais' && $jornada === 'integral') {
                $key = 'af_integral';
            } elseif ($stage === 'creche' && $jornada === 'parcial') {
                $key = 'creche_parcial';
            } elseif ($stage === 'creche' && $jornada === 'integral') {
                $key = 'creche_integral';
            } elseif ($stage === 'pre_escola' && $jornada === 'parcial') {
                $key = 'pre_parcial';
            } elseif ($stage === 'pre_escola' && $jornada === 'integral') {
                $key = 'pre_integral';
            }
            $out[$key] = [
                'Urbana' => $cells[$stage][$jornada]['Urbana'] ?? ['regular' => 0, 'especial' => 0],
                'Rural' => $cells[$stage][$jornada]['Rural'] ?? ['regular' => 0, 'especial' => 0],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cells
     * @return array{title: string, columns: list<array{key: string, label: string}>, values: array<string, int>}
     */
    private function blockGeral(array $cells, int $especialExtra = 0): array
    {
        $sumStage = function (string $stage, string $jornada) use ($cells): int {
            return (int) ($cells[$stage][$jornada]['Urbana']['regular'] ?? 0)
                + (int) ($cells[$stage][$jornada]['Rural']['regular'] ?? 0);
        };
        $sumEspecial = $especialExtra;
        foreach ($cells as $stageCells) {
            if (! is_array($stageCells)) {
                continue;
            }
            foreach ($stageCells as $jornadaCells) {
                if (! is_array($jornadaCells)) {
                    continue;
                }
                foreach ($jornadaCells as $locCells) {
                    if (! is_array($locCells)) {
                        continue;
                    }
                    $sumEspecial += (int) ($locCells['especial'] ?? 0);
                }
            }
        }

        $infantilParcial = $sumStage('creche', 'parcial') + $sumStage('pre_escola', 'parcial');
        $infantilIntegral = $sumStage('creche', 'integral') + $sumStage('pre_escola', 'integral');
        $fundParcial = $sumStage('anos_iniciais', 'parcial') + $sumStage('anos_finais', 'parcial');
        $fundIntegral = $sumStage('anos_iniciais', 'integral') + $sumStage('anos_finais', 'integral');
        $eja = $sumStage('eja_fundamental', 'parcial') + $sumStage('eja_fundamental', 'integral');
        $geral = $infantilParcial + $infantilIntegral + $fundParcial + $fundIntegral + $eja;

        return [
            'title' => __('Análise geral'),
            'columns' => [
                ['key' => 'infantil_parcial', 'label' => __('Educação Infantil Parcial')],
                ['key' => 'infantil_integral', 'label' => __('Educação Infantil Integral')],
                ['key' => 'fund_parcial', 'label' => __('Educação Fundamental Parcial')],
                ['key' => 'fund_integral', 'label' => __('Educação Fundamental Integral')],
                ['key' => 'eja', 'label' => __('EJA Presencial Fundamental')],
                ['key' => 'especial', 'label' => __('Educação Especial')],
                ['key' => 'geral', 'label' => __('GERAL')],
            ],
            'values' => [
                'infantil_parcial' => $infantilParcial,
                'infantil_integral' => $infantilIntegral,
                'fund_parcial' => $fundParcial,
                'fund_integral' => $fundIntegral,
                'eja' => $eja,
                'especial' => $sumEspecial,
                'geral' => $geral,
            ],
        ];
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
