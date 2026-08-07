<?php

namespace App\Services\Clio\Export;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignOperationalRules;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Consolida filtros operacionais (Acomp + Relações) para o Excel CLI-XLS.
 */
final class CampaignFiltrosOperacionaisComposer
{
    public function __construct(
        private readonly CsvReader $csv = new CsvReader,
        private readonly RelationCsvAggregator $aggregator = new RelationCsvAggregator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compose(ClioCampaign $campaign): array
    {
        $campaign->loadMissing(['schools.artifacts', 'artifacts']);
        $disk = (string) config('clio.disk', 'local');

        $aptas = [];
        $fora = [];
        $acompSums = $this->emptyAcompSums();

        foreach ($campaign->schools as $school) {
            if (! $school instanceof ClioCampaignSchool) {
                continue;
            }
            $meta = is_array($school->meta) ? $school->meta : [];
            $row = [
                'inep' => (string) $school->inep_code,
                'name' => (string) $school->name,
                'dependency' => (string) ($school->dependency ?? ''),
                'functioning' => (string) ($school->functioning_status ?? ''),
                'location' => (string) ($meta['location'] ?? ''),
                'private_category' => (string) ($meta['private_category'] ?? ''),
                'partnership_authority' => (string) ($meta['partnership_authority'] ?? ''),
                'total_curricular' => $this->intOrNull($meta['total_curricular'] ?? null),
                'total_aee' => $this->intOrNull($meta['total_aee'] ?? null),
                'total_ac' => $this->intOrNull($meta['total_ac'] ?? null),
                'total_curricular_ac' => $this->intOrNull($meta['total_curricular_ac'] ?? null),
                'mat_creche' => $this->intOrNull($meta['mat_creche'] ?? null),
                'mat_pre_escola' => $this->intOrNull($meta['mat_pre_escola'] ?? null),
                'mat_fund_iniciais' => $this->intOrNull($meta['mat_fund_iniciais'] ?? null),
                'mat_fund_finais' => $this->intOrNull($meta['mat_fund_finais'] ?? null),
                'mat_eja' => $this->intOrNull($meta['mat_eja'] ?? null),
            ];

            if (CampaignOperationalRules::isOperationallyEligible(
                $school->functioning_status,
                $school->dependency,
                $meta,
            )) {
                $aptas[] = $row;
                $this->accumulateAcomp($acompSums, $row);
            } else {
                $fora[] = $row;
            }
        }

        $turmas = [];
        $turmaSums = [
            'turmas' => 0,
            'alunos' => 0,
            'profissionais' => 0,
            'parcial' => 0,
            'integral' => 0,
            'aee' => 0,
            'ac' => 0,
            'ac_proxy_ok' => 0,
        ];
        $alerts = [];
        $demo = ['by_cor' => [], 'undeclared' => []];
        $nee = [
            'with_k' => 0,
            'with_l' => 0,
            'l_without_k' => [],
            'with_nee_without_aee' => 0,
            'nee_without_aee' => [],
            'nee_without_aee_by_inep' => [],
            'nee_without_aee_truncated' => false,
            'total_rows' => 0,
        ];
        $pnate = [
            'residence_column' => false,
            'elegivel' => 0,
            'excluido_urbano_urbano' => 0,
            'sem_transporte' => 0,
            'by_veiculo' => [],
            'excluded_sample' => [],
        ];
        $etapas = [];
        $tempoIntegral = [
            'pleno' => 0,
            'contraturno_proxy' => 0,
            'aee' => 0,
            'eja_excluido' => 0,
            'note' => __('Fonte: Relação turma (CH ≥ 35 h ou turno integral). EJA/AEE fora do tempo integral.'),
        ];

        $aptaIneps = array_fill_keys(array_column($aptas, 'inep'), true);
        $schoolByInep = $campaign->schools->keyBy('inep_code');

        foreach ($campaign->schools as $school) {
            $inep = (string) $school->inep_code;
            if (! isset($aptaIneps[$inep])) {
                continue;
            }
            $meta = is_array($school->meta) ? $school->meta : [];
            $schoolLocation = (string) ($meta['location'] ?? '');

            $turmaArt = $school->artifacts->firstWhere('kind', 'relacao_turma_escola');
            $alunoArt = $school->artifacts->firstWhere('kind', 'relacao_aluno_escola');
            $profiles = [];

            if ($turmaArt instanceof ClioCampaignArtifact) {
                $path = $this->absolutePath($disk, $turmaArt->storage_path);
                if ($path !== null) {
                    try {
                        $data = $this->csv->read($path, 1);
                        $agg = $this->aggregator->aggregateTurmas($data['rows'], $this->csv);
                        $profiles = is_array($agg['turma_profiles'] ?? null) ? $agg['turma_profiles'] : [];
                        foreach ($data['rows'] as $row) {
                            $classified = $this->classifyTurmaRow($row, $school, $profiles);
                            $turmas[] = $classified;
                            $turmaSums['turmas']++;
                            $turmaSums['alunos'] += $classified['alunos'];
                            $turmaSums['profissionais'] += $classified['profissionais'];
                            if ($classified['bucket'] === RelationCsvAggregator::BUCKET_AEE) {
                                $turmaSums['aee']++;
                                $tempoIntegral['aee'] += $classified['alunos'];
                            } elseif ($classified['bucket'] === RelationCsvAggregator::BUCKET_AC) {
                                $turmaSums['ac']++;
                                if ($classified['ac_proxy_ok']) {
                                    $turmaSums['ac_proxy_ok']++;
                                }
                                if ($classified['alert_ac_below']) {
                                    $alerts[] = [
                                        'code' => 'AC-CH-LT15',
                                        'school_inep' => $inep,
                                        'school_name' => (string) $school->name,
                                        'detail' => $classified['codigo'].' · '.$classified['ch_label'],
                                        'message' => __('Atividade complementar com CH &lt; 15 h/semana'),
                                    ];
                                }
                            } elseif ($classified['jornada'] === 'integral') {
                                $turmaSums['integral']++;
                                if (! $classified['is_eja']) {
                                    $tempoIntegral['pleno'] += $classified['alunos'];
                                } else {
                                    $tempoIntegral['eja_excluido'] += $classified['alunos'];
                                }
                            } else {
                                $turmaSums['parcial']++;
                            }

                            if ($classified['alert_eja_low']) {
                                $alerts[] = [
                                    'code' => 'EJA-CH-LT20',
                                    'school_inep' => $inep,
                                    'school_name' => (string) $school->name,
                                    'detail' => $classified['codigo'].' · '.$classified['ch_label'],
                                    'message' => __('EJA com CH &lt; 20 h/semana'),
                                ];
                            }
                        }
                    } catch (Throwable) {
                        // ignore unreadable artifact
                    }
                }
            }

            if ($alunoArt instanceof ClioCampaignArtifact) {
                $path = $this->absolutePath($disk, $alunoArt->storage_path);
                if ($path !== null) {
                    try {
                        $data = $this->csv->read($path, 1);
                        $this->accumulateAlunos(
                            $data['rows'],
                            $school,
                            $schoolLocation,
                            $profiles,
                            $demo,
                            $nee,
                            $pnate,
                            $etapas,
                            $tempoIntegral,
                            $alerts,
                        );
                    } catch (Throwable) {
                        // ignore
                    }
                }
            }
        }

        unset($schoolByInep);

        if (! empty($nee['nee_without_aee_truncated'])) {
            $alerts[] = [
                'code' => 'NEE-SEM-AEE-TRUNC',
                'school_inep' => '',
                'school_name' => '',
                'detail' => '',
                'message' => __('Lista NEE/TRS sem AEE truncada (2000) — o contador mantém o total'),
            ];
        }

        return [
            'meta' => [
                'municipality' => (string) $campaign->municipality_name,
                'uf' => (string) ($campaign->uf ?? ''),
                'ibge' => (string) ($campaign->ibge_municipio ?? ''),
                'year' => (int) $campaign->year,
                'reference_date' => optional($campaign->reference_date)?->toDateString(),
                'uuid' => (string) $campaign->uuid,
                'emitted_at' => now()->toDateTimeString(),
                'schools_aptas' => count($aptas),
                'schools_fora' => count($fora),
                'rules' => [
                    'aptas' => __('Municipal OU privada filantrópica com parceria Municipal; em atividade; localização Urbana/Rural'),
                    'parcial_integral' => __('Parcial &lt; 35 h · Integral ≥ 35 h (ou turno integral)'),
                    'eja_alerta' => __('Alertar EJA com CH &lt; 20 h'),
                    'ac' => __('AC elegível a proxy se CH ≥ 15 h; alertar se CH &lt; 15 h'),
                    'pnate' => __('Exclui transporte Sim com escola Urbana e residência Urbana'),
                    'integral_canonico' => __('Tempo integral canónico por CH/turno; proxy Acomp AA+AB é só referência (sem CH)'),
                ],
            ],
            'escolas_aptas' => $aptas,
            'escolas_fora' => $fora,
            'somatarios_acomp' => $acompSums,
            'turmas' => $turmas,
            'somatarios_turmas' => $turmaSums,
            'demografia' => $demo,
            'nee' => $nee,
            'pnate' => $pnate,
            'etapas' => $this->sortDesc($etapas),
            'tempo_integral' => $tempoIntegral,
            'alerts' => $alerts,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, array<string, mixed>>  $profiles
     * @return array<string, mixed>
     */
    private function classifyTurmaRow(array $row, ClioCampaignSchool $school, array $profiles): array
    {
        $codigo = trim($this->csv->value($row, 'Código da turma'));
        $tipo = trim($this->csv->value($row, 'Tipo de turma'));
        $agregada = trim($this->csv->value($row, 'Etapa Agregada'));
        $etapa = trim($this->csv->value($row, 'Etapa de ensino'));
        $turno = trim($this->csv->value($row, 'Turno'));
        $chRaw = trim($this->firstHeader($row, [
            'Carga horária semanal (hh:mm)',
            'Carga horária semanal',
            'Carga horaria semanal',
        ]));
        $profile = is_array($profiles[$codigo] ?? null) ? $profiles[$codigo] : [];
        $bucket = (string) ($profile['bucket'] ?? $this->aggregator->classifyTipoTurma($tipo));
        $chHours = isset($profile['ch_hours']) && is_numeric($profile['ch_hours'])
            ? (float) $profile['ch_hours']
            : $this->aggregator->parseCargaHoraria($chRaw);
        $extended = CampaignOperationalRules::isIntegralHours($chHours, $turno !== '' ? $turno : (string) ($profile['turno_raw'] ?? ''));
        $isEja = $this->isEjaEtapa($etapa, $agregada);
        $alunos = $this->intFromCell($this->firstHeader($row, [
            'Quantidade de Alunos (as)',
            'Quantidade de Alunos',
            'Quantidade de alunos',
        ]));
        $profs = $this->intFromCell($this->firstHeader($row, [
            'Quantidade de Profissionais escolares',
            'Quantidade de Profissionais',
        ]));

        $jornada = '—';
        if ($bucket === RelationCsvAggregator::BUCKET_CURRICULAR) {
            $jornada = $extended ? 'integral' : 'parcial';
        }

        return [
            'school_inep' => (string) $school->inep_code,
            'school_name' => (string) $school->name,
            'codigo' => $codigo,
            'tipo' => $tipo,
            'bucket' => $bucket,
            'agregada' => $agregada,
            'etapa' => $etapa,
            'turno' => $turno,
            'ch_hours' => $chHours,
            'ch_label' => $chHours !== null ? number_format($chHours, 1, ',', '').' h' : ($chRaw !== '' ? $chRaw : '—'),
            'jornada' => $jornada,
            'is_eja' => $isEja,
            'alunos' => $alunos,
            'profissionais' => $profs,
            'ac_proxy_ok' => $bucket === RelationCsvAggregator::BUCKET_AC
                && CampaignOperationalRules::isAcEligibleForIntegralProxy($chHours),
            'alert_ac_below' => $bucket === RelationCsvAggregator::BUCKET_AC
                && CampaignOperationalRules::isAcBelowFloor($chHours),
            'alert_eja_low' => $isEja && CampaignOperationalRules::isEjaLowHours($chHours),
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, array<string, mixed>>  $profiles
     * @param  array<string, mixed>  $demo
     * @param  array<string, mixed>  $nee
     * @param  array<string, mixed>  $pnate
     * @param  array<string, int>  $etapas
     * @param  array<string, mixed>  $tempoIntegral
     * @param  list<array<string, string>>  $alerts
     */
    private function accumulateAlunos(
        array $rows,
        ClioCampaignSchool $school,
        string $schoolLocation,
        array $profiles,
        array &$demo,
        array &$nee,
        array &$pnate,
        array &$etapas,
        array &$tempoIntegral,
        array &$alerts,
    ): void {
        if ($rows === []) {
            return;
        }
        $headers = array_keys($rows[0]);
        $corHeader = $this->findHeader($headers, ['Cor/Raça', 'Cor/Raca', 'Raça', 'Raca', 'Cor']);
        $defHeader = $this->findHeader($headers, [
            'Tipo(s) de deficiência(s), transtorno(s) do espectro autista e altas habilidades ou superdotação',
            'Deficiência',
            'Deficiencia',
        ]);
        $trsHeader = $this->findHeader($headers, [
            'Tipo(s) de transtorno(s) que impacta(m) o desenvolvimento da aprendizagem',
            'Transtorno do espectro autista',
        ]);
        $usoHeader = $this->findHeaderMatching($headers, '/transporte\s*escolar/iu');
        $veiculoHeader = $this->findHeaderMatching($headers, '/ve[ií]culo/iu');
        $resHeader = $this->findHeader($headers, [
            'Localização/Zona de residência',
            'Localização / Zona de residência',
            'Zona de residência',
            'Localização de residência',
        ]);
        $pnate['residence_column'] = $pnate['residence_column'] || $resHeader !== null;
        $nomeHeader = $this->findHeader($headers, ['Nome', 'Nome do aluno']);
        $idHeader = $this->findHeader($headers, ['Identificação única', 'ID']);

        /** @var array<string, array{has_nee: bool, has_aee: bool, id: string, nome: string, deficiencia: string, transtorno: string}> $people */
        $people = [];

        foreach ($rows as $row) {
            $nee['total_rows']++;
            $etapa = trim($this->csv->value($row, 'Etapa de ensino'));
            $turma = trim($this->csv->value($row, 'Código da turma'));
            $aeeTipo = trim($this->firstHeader($row, [
                'Tipo de atendimento educacional especializado (AEE)',
                'Tipo de atendimento educacional especializado',
            ]));
            $etapaKey = $this->classifyAlunoEtapa($etapa, $aeeTipo);
            $etapas[$etapaKey] = ($etapas[$etapaKey] ?? 0) + 1;

            if ($corHeader !== null) {
                $cor = trim($this->csv->value($row, $corHeader));
                if ($cor === '' || $this->isUndeclared($cor)) {
                    $corLabel = __('Não declarado');
                    $demo['undeclared'][] = [
                        'inep' => (string) $school->inep_code,
                        'school' => (string) $school->name,
                        'id' => $idHeader ? trim($this->csv->value($row, $idHeader)) : '',
                        'nome' => $nomeHeader ? trim($this->csv->value($row, $nomeHeader)) : '',
                    ];
                } else {
                    $corLabel = $cor;
                }
                $demo['by_cor'][$corLabel] = ($demo['by_cor'][$corLabel] ?? 0) + 1;
            }

            $k = $defHeader !== null ? trim($this->csv->value($row, $defHeader)) : '';
            $l = $trsHeader !== null ? trim($this->csv->value($row, $trsHeader)) : '';
            $kFilled = $this->isNeeMarkerFilled($k);
            $lFilled = $this->isNeeMarkerFilled($l);
            if ($kFilled) {
                $nee['with_k']++;
            }
            if ($lFilled) {
                $nee['with_l']++;
            }
            if ($lFilled && ! $kFilled) {
                $nee['l_without_k'][] = [
                    'inep' => (string) $school->inep_code,
                    'school' => (string) $school->name,
                    'id' => $idHeader ? trim($this->csv->value($row, $idHeader)) : '',
                    'nome' => $nomeHeader ? trim($this->csv->value($row, $nomeHeader)) : '',
                    'transtorno' => $l,
                ];
            }

            $personId = $this->personKey($row, $idHeader, $nomeHeader);
            if (! isset($people[$personId])) {
                $people[$personId] = [
                    'has_nee' => false,
                    'has_aee' => false,
                    'id' => $idHeader ? trim($this->csv->value($row, $idHeader)) : '',
                    'nome' => $nomeHeader ? trim($this->csv->value($row, $nomeHeader)) : '',
                    'deficiencia' => '',
                    'transtorno' => '',
                ];
            }
            if ($kFilled) {
                $people[$personId]['has_nee'] = true;
                $people[$personId]['deficiencia'] = $k;
            }
            if ($lFilled) {
                $people[$personId]['has_nee'] = true;
                $people[$personId]['transtorno'] = $l;
            }
            if ($this->rowIsAee($turma, $etapa, $aeeTipo, $profiles)) {
                $people[$personId]['has_aee'] = true;
            }

            if ($usoHeader !== null) {
                $uso = trim($this->csv->value($row, $usoHeader));
                $uses = $this->isYes($uso);
                $residence = $resHeader !== null ? trim($this->csv->value($row, $resHeader)) : null;
                $class = CampaignOperationalRules::classifyPnate(
                    $uses,
                    $schoolLocation,
                    $residence,
                    $resHeader !== null,
                );
                $pnate[$class] = (int) ($pnate[$class] ?? 0) + 1;
                if ($uses && $veiculoHeader !== null) {
                    $veiculo = trim($this->csv->value($row, $veiculoHeader)) ?: __('Não informado');
                    if ($class === 'elegivel') {
                        $pnate['by_veiculo'][$veiculo] = ($pnate['by_veiculo'][$veiculo] ?? 0) + 1;
                    }
                }
                if ($class === 'excluido_urbano_urbano' && count($pnate['excluded_sample']) < 200) {
                    $pnate['excluded_sample'][] = [
                        'inep' => (string) $school->inep_code,
                        'school' => (string) $school->name,
                        'id' => $idHeader ? trim($this->csv->value($row, $idHeader)) : '',
                        'residencia' => (string) $residence,
                    ];
                }
            }

            $profile = is_array($profiles[$turma] ?? null) ? $profiles[$turma] : [];
            $bucket = (string) ($profile['bucket'] ?? '');
            if ($bucket === RelationCsvAggregator::BUCKET_AC
                && CampaignOperationalRules::isAcEligibleForIntegralProxy(
                    isset($profile['ch_hours']) && is_numeric($profile['ch_hours']) ? (float) $profile['ch_hours'] : null
                )
            ) {
                // proxy contraturno: aluno em AC ≥15 (contagem auxiliar; pleno já veio das turmas)
                $tempoIntegral['contraturno_proxy']++;
            }
        }

        foreach ($people as $person) {
            if (! $person['has_nee'] || $person['has_aee']) {
                continue;
            }
            $nee['with_nee_without_aee']++;
            $inepKey = (string) $school->inep_code;
            $nee['nee_without_aee_by_inep'][$inepKey] = (int) ($nee['nee_without_aee_by_inep'][$inepKey] ?? 0) + 1;
            if (count($nee['nee_without_aee']) < 2000) {
                $nee['nee_without_aee'][] = [
                    'inep' => (string) $school->inep_code,
                    'school' => (string) $school->name,
                    'id' => $person['id'],
                    'nome' => $person['nome'],
                    'deficiencia' => $person['deficiencia'],
                    'transtorno' => $person['transtorno'],
                ];
            } else {
                $nee['nee_without_aee_truncated'] = true;
            }
        }

        if (count($demo['undeclared']) > 500) {
            $demo['undeclared'] = array_slice($demo['undeclared'], 0, 500);
            $alerts[] = [
                'code' => 'DEMO-TRUNC',
                'school_inep' => (string) $school->inep_code,
                'school_name' => (string) $school->name,
                'detail' => '',
                'message' => __('Lista de Cor/Raça não declarada truncada (500)'),
            ];
        }
    }

    private function classifyAlunoEtapa(string $etapa, string $aeeTipo): string
    {
        $e = mb_strtolower($etapa);
        $aeeEmpty = $this->isEmptyDash($aeeTipo);

        if (str_contains($e, 'não se aplica') || str_contains($e, 'nao se aplica')) {
            return $aeeEmpty ? 'Atividade complementar' : 'AEE';
        }
        if (str_contains($e, 'creche')) {
            return 'Creche';
        }
        if (str_contains($e, 'pré-escola') || str_contains($e, 'pre-escola') || str_contains($e, 'pré escola')) {
            return 'Pré-escola';
        }
        if (str_contains($e, 'unificada') || (str_contains($e, 'infantil') && str_contains($e, '0 a 5'))) {
            return 'Multietapa infantil';
        }
        if (preg_match('/ensino fundamental de 9 anos\s*-\s*([1-5])/iu', $etapa) === 1) {
            return 'Fundamental anos iniciais';
        }
        if (preg_match('/ensino fundamental de 9 anos\s*-\s*([6-9])/iu', $etapa) === 1) {
            return 'Fundamental anos finais';
        }
        if (str_contains($e, 'multi') && str_contains($e, 'fundamental')) {
            return 'Multietapa fundamental';
        }
        if (str_contains($e, 'eja')) {
            return 'EJA';
        }
        if (str_contains($e, 'multietapa') || (str_contains($e, 'infantil') && str_contains($e, 'fundamental'))) {
            return 'Outros (multietapa)';
        }

        return $etapa !== '' ? $etapa : __('Não informado');
    }

    /**
     * @return array<string, int|null>
     */
    private function emptyAcompSums(): array
    {
        return [
            'total_curricular' => 0,
            'total_aee' => 0,
            'total_ac' => 0,
            'total_curricular_ac' => 0,
            'infantil' => 0,
            'fundamental' => 0,
            'eja' => 0,
            'proxy_integral_fund' => 0,
            'proxy_integral_note' => __('Proxy AA+AB (sem CH) — canónico é Relação turma / jornada ≥ 35 h'),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $sums
     * @param  array<string, mixed>  $row
     */
    private function accumulateAcomp(array &$sums, array $row): void
    {
        foreach (['total_curricular', 'total_aee', 'total_ac', 'total_curricular_ac'] as $key) {
            $sums[$key] = (int) $sums[$key] + (int) ($row[$key] ?? 0);
        }
        $sums['infantil'] = (int) $sums['infantil']
            + (int) ($row['mat_creche'] ?? 0)
            + (int) ($row['mat_pre_escola'] ?? 0);
        $sums['fundamental'] = (int) $sums['fundamental']
            + (int) ($row['mat_fund_iniciais'] ?? 0)
            + (int) ($row['mat_fund_finais'] ?? 0);
        $sums['eja'] = (int) $sums['eja'] + (int) ($row['mat_eja'] ?? 0);
        $sums['proxy_integral_fund'] = (int) $sums['proxy_integral_fund']
            + (int) ($row['total_ac'] ?? 0)
            + (int) ($row['total_curricular_ac'] ?? 0);
    }

    private function isEjaEtapa(string $etapa, string $agregada): bool
    {
        $blob = mb_strtolower($etapa.' '.$agregada);

        return str_contains($blob, 'eja') || str_contains($blob, 'jovens e adultos');
    }

    private function isEmptyDash(string $value): bool
    {
        $v = trim($value);

        return $v === '' || $v === '--' || $v === '—' || mb_strtolower($v) === 'não' || mb_strtolower($v) === 'nao';
    }

    /** Marcador K/L preenchido (ignora vazio, Não e «Não se aplica»). */
    private function isNeeMarkerFilled(string $value): bool
    {
        if ($this->isEmptyDash($value)) {
            return false;
        }
        $v = mb_strtolower(trim($value));

        return ! str_contains($v, 'não se aplica') && ! str_contains($v, 'nao se aplica');
    }

    /**
     * @param  array<string, string>  $row
     */
    private function personKey(array $row, ?string $idHeader, ?string $nomeHeader): string
    {
        if ($idHeader !== null) {
            $id = trim($this->csv->value($row, $idHeader));
            if ($id !== '') {
                return 'id:'.$id;
            }
        }
        $mat = trim($this->csv->value($row, 'Código da Matrícula'));
        if ($mat !== '') {
            return 'mat:'.$mat;
        }
        $nome = $nomeHeader !== null ? trim($this->csv->value($row, $nomeHeader)) : '';
        $nasc = trim($this->csv->value($row, 'Data de nascimento'));

        return 'fallback:'.$nome.'|'.$nasc;
    }

    /**
     * @param  array<string, array<string, mixed>>  $profiles
     */
    private function rowIsAee(string $turma, string $etapa, string $aeeTipo, array $profiles): bool
    {
        $profile = is_array($profiles[$turma] ?? null) ? $profiles[$turma] : [];
        if (($profile['bucket'] ?? '') === RelationCsvAggregator::BUCKET_AEE) {
            return true;
        }
        $e = mb_strtolower($etapa);
        if ($e !== '' && (
            str_contains($e, 'atendimento educacional')
            || preg_match('/\baee\b/u', $e) === 1
        )) {
            return true;
        }
        // Coluna tipo AEE preenchida + etapa «não se aplica» = linha de AEE (não AC).
        if (! $this->isEmptyDash($aeeTipo) && (
            str_contains($e, 'não se aplica') || str_contains($e, 'nao se aplica')
        )) {
            return true;
        }

        return false;
    }

    private function isUndeclared(string $value): bool
    {
        $v = CampaignOperationalRules::normalizeToken($value);

        return $v === '' || str_contains($v, 'nao declarado') || str_contains($v, 'nao informado');
    }

    private function isYes(string $value): bool
    {
        $v = CampaignOperationalRules::normalizeToken($value);

        return $v === 'sim' || $v === 's' || $v === '1' || $v === 'true';
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $candidates
     */
    private function findHeader(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            foreach ($headers as $header) {
                if (mb_strtolower(trim($header)) === mb_strtolower(trim($candidate))) {
                    return $header;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     */
    private function findHeaderMatching(array $headers, string $pattern): ?string
    {
        foreach ($headers as $header) {
            if (preg_match($pattern, $header) === 1) {
                return $header;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     */
    private function firstHeader(array $row, array $headers): string
    {
        foreach ($headers as $header) {
            $v = $this->csv->value($row, $header);
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    private function intFromCell(string $raw): int
    {
        $normalized = preg_replace('/[^\d\-]/', '', $raw) ?? '';

        return $normalized !== '' && is_numeric($normalized) ? (int) $normalized : 0;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sortDesc(array $counts): array
    {
        arsort($counts);

        return $counts;
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

        return is_file($path) ? $path : null;
    }
}
