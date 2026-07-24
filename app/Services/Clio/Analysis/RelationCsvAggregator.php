<?php

namespace App\Services\Clio\Analysis;

use App\Services\Clio\Parse\CsvReader;

/**
 * Agrega linhas de Relação turma/aluno sem persistir PII — só histogramas e buckets Educacenso.
 */
final class RelationCsvAggregator
{
    public const BUCKET_CURRICULAR = 'curricular';

    public const BUCKET_AEE = 'aee';

    public const BUCKET_AC = 'atividade_complementar';

    public const BUCKET_OUTRA = 'outra';

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{
     *   total: int,
     *   by_etapa_ensino: array<string, int>,
     *   by_etapa_agregada: array<string, int>,
     *   by_tipo_turma: array<string, int>,
     *   by_mediacao: array<string, int>,
     *   by_tipo_bucket: array{curricular: int, aee: int, atividade_complementar: int, outra: int},
     *   without_etapa: int,
     *   without_tipo: int
     * }
     */
    public function aggregateTurmas(array $rows, CsvReader $csv): array
    {
        $byEtapa = [];
        $byAgregada = [];
        $byTipo = [];
        $byMediacao = [];
        $buckets = [
            self::BUCKET_CURRICULAR => 0,
            self::BUCKET_AEE => 0,
            self::BUCKET_AC => 0,
            self::BUCKET_OUTRA => 0,
        ];
        $withoutEtapa = 0;
        $withoutTipo = 0;
        $turmaCodes = [];
        $byTurno = [];
        $byTurnoOutros = [];
        $byChBand = [];
        $byChExact = [];
        $profiles = [];
        $headerKeys = array_keys($rows[0] ?? []);
        $turnoHeader = $this->findTurnoHeader($headerKeys);
        $chHeader = $this->findCargaHorariaHeader($headerKeys);
        $hasTurno = $turnoHeader !== null;
        $hasCh = $chHeader !== null;

        foreach ($rows as $row) {
            $etapa = trim($csv->value($row, 'Etapa de ensino'));
            $agregada = trim($csv->value($row, 'Etapa Agregada'));
            $tipo = trim($csv->value($row, 'Tipo de turma'));
            $mediacao = trim($csv->value($row, 'Tipo de mediação'));
            $codigo = trim($csv->value($row, 'Código da turma'));
            if ($codigo !== '') {
                $turmaCodes[$codigo] = true;
            }

            if ($etapa === '') {
                $withoutEtapa++;
                $etapa = __('Não informado');
            }
            if ($agregada === '') {
                $agregada = __('Não informado');
            }
            if ($tipo === '') {
                $withoutTipo++;
                $tipo = __('Não informado');
            }
            if ($mediacao === '') {
                $mediacao = __('Não informado');
            }

            $bucket = $this->classifyTipoTurma($tipo);
            $turno = $hasTurno ? trim($csv->value($row, $turnoHeader)) : '';
            if ($hasTurno) {
                $meta = $this->turnoDisplayMeta($turno);
                $turnoLabel = (string) ($meta['label'] ?? __('Não informado'));
                $byTurno[$turnoLabel] = ($byTurno[$turnoLabel] ?? 0) + 1;
                if (! empty($meta['is_other']) && $turno !== '') {
                    $detail = (string) ($meta['detail'] ?? $meta['raw_compact'] ?? $turno);
                    $byTurnoOutros[$detail] = ($byTurnoOutros[$detail] ?? 0) + 1;
                }
            }
            $chHours = $hasCh ? $this->parseCargaHoraria(trim($csv->value($row, $chHeader))) : null;
            if ($hasCh) {
                $bandMeta = $this->cargaHorariaBandMeta($chHours);
                $byChBand[$bandMeta['label']] = ($byChBand[$bandMeta['label']] ?? 0) + 1;
                if ($chHours !== null) {
                    $exact = $this->cargaHorariaLabel($chHours);
                    $byChExact[$exact] = ($byChExact[$exact] ?? 0) + 1;
                }
            }
            $extended = $this->isExtendedHours($turno, $chHours);

            if ($codigo !== '') {
                $turnoMeta = $turno !== '' ? $this->turnoDisplayMeta($turno) : null;
                $profiles[$codigo] = [
                    'bucket' => $bucket,
                    'turno' => $turnoMeta !== null ? (string) ($turnoMeta['label'] ?? '') : '',
                    'turno_raw' => $turno,
                    'turno_bucket' => $turnoMeta !== null ? (string) ($turnoMeta['bucket'] ?? '') : '',
                    'ch_hours' => $chHours,
                    'extended' => $extended,
                    'etapa' => $etapa,
                    'agregada' => $agregada,
                    'infantil' => $this->isInfantilEtapa($etapa, $agregada),
                    'fundamental' => $this->isFundamentalEtapa($etapa, $agregada),
                ];
            }

            $byEtapa[$etapa] = ($byEtapa[$etapa] ?? 0) + 1;
            $byAgregada[$agregada] = ($byAgregada[$agregada] ?? 0) + 1;
            $byTipo[$tipo] = ($byTipo[$tipo] ?? 0) + 1;
            $byMediacao[$mediacao] = ($byMediacao[$mediacao] ?? 0) + 1;
            $buckets[$bucket]++;
        }

        return [
            'total' => count($rows),
            'by_etapa_ensino' => $this->sortDesc($byEtapa),
            'by_etapa_agregada' => $this->sortDesc($byAgregada),
            'by_tipo_turma' => $this->sortDesc($byTipo),
            'by_mediacao' => $this->sortDesc($byMediacao),
            'by_tipo_bucket' => $buckets,
            'by_turno' => $this->sortDesc($byTurno),
            'by_turno_outros' => $this->sortDesc($byTurnoOutros),
            'by_ch_band' => $this->sortCargaBandLabels($byChBand),
            'by_ch_exact' => $this->sortCargaBands($byChExact),
            'turma_codes' => array_keys($turmaCodes),
            'turma_profiles' => $profiles,
            'without_etapa' => $withoutEtapa,
            'without_tipo' => $withoutTipo,
            'columns' => [
                'turno' => $hasTurno,
                'carga_horaria' => $hasCh,
            ],
        ];
    }

    /**
     * Perfis de jornada por pessoa (Identificação única), sem PII no retorno.
     *
     * @param  list<array<string, string>>  $alunoRows
     * @param  array<string, array<string, mixed>>  $turmaProfiles
     * @return array{
     *   people: int,
     *   fund_aee_contraturno: int,
     *   curricular_ac: int,
     *   infantil_turma_estendida: int,
     *   multi_enrollment: int,
     *   by_turno_curricular: array<string, int>,
     *   columns_turno: bool,
     *   columns_ch: bool
     * }
     */
    public function aggregateEnrollmentDayPatterns(array $alunoRows, CsvReader $csv, array $turmaProfiles): array
    {
        $byPerson = [];
        foreach ($alunoRows as $row) {
            $id = trim($csv->value($row, 'Identificação única'));
            if ($id === '') {
                $id = trim($csv->value($row, 'Código da Matrícula'));
            }
            if ($id === '') {
                $id = trim($csv->value($row, 'Código da matrícula'));
            }
            if ($id === '') {
                continue;
            }
            $turma = trim($csv->value($row, 'Código da turma'));
            $etapa = trim($csv->value($row, 'Etapa de ensino'));
            if (! isset($byPerson[$id])) {
                $byPerson[$id] = [
                    'turmas' => [],
                    'etapas' => [],
                ];
            }
            if ($turma !== '') {
                $byPerson[$id]['turmas'][$turma] = $turma;
            }
            if ($etapa !== '') {
                $byPerson[$id]['etapas'][] = $etapa;
            }
        }

        $fundAee = 0;
        $currAc = 0;
        $infantilExt = 0;
        $multi = 0;
        $byTurnoCurricular = [];
        $hasTurno = false;
        $hasCh = false;

        foreach ($turmaProfiles as $profile) {
            if (($profile['turno'] ?? '') !== '') {
                $hasTurno = true;
            }
            if (($profile['ch_hours'] ?? null) !== null) {
                $hasCh = true;
            }
        }

        foreach ($byPerson as $person) {
            $turmaCodes = array_values($person['turmas']);
            if (count($turmaCodes) > 1) {
                $multi++;
            }

            $buckets = [];
            $turnos = [];
            $hasFundCurricular = false;
            $hasInfantilCurricular = false;
            $infantilExtended = false;
            $curricularTurno = '';

            foreach ($turmaCodes as $code) {
                $profile = $turmaProfiles[$code] ?? null;
                if ($profile === null) {
                    continue;
                }
                $bucket = (string) ($profile['bucket'] ?? self::BUCKET_OUTRA);
                $buckets[$bucket] = true;
                $turno = (string) ($profile['turno'] ?? '');
                if ($turno !== '') {
                    $turnos[$turno] = true;
                }
                if ($bucket === self::BUCKET_CURRICULAR) {
                    if (! empty($profile['fundamental']) || $this->etapasIncludeFundamental($person['etapas'])) {
                        $hasFundCurricular = true;
                    }
                    if (! empty($profile['infantil']) || $this->etapasIncludeInfantil($person['etapas'])) {
                        $hasInfantilCurricular = true;
                        if (! empty($profile['extended'])) {
                            $infantilExtended = true;
                        }
                    }
                    $curricularTurno = $turno !== '' ? $turno : $curricularTurno;
                }
            }

            if ($curricularTurno !== '') {
                $byTurnoCurricular[$curricularTurno] = ($byTurnoCurricular[$curricularTurno] ?? 0) + 1;
            }

            $hasCurricular = isset($buckets[self::BUCKET_CURRICULAR]);
            $hasAee = isset($buckets[self::BUCKET_AEE]);
            $hasAc = isset($buckets[self::BUCKET_AC]);

            // Fundamental regular + AEE: jornada tipicamente em contraturno (mesmo sem coluna Turno).
            if ($hasFundCurricular && $hasAee) {
                $fundAee++;
            }
            // Regular + atividade complementar (não confundir com AEE).
            if ($hasCurricular && $hasAc) {
                $currAc++;
            }
            // Infantil em uma única matrícula curricular com turma de funcionamento estendido.
            if (
                $hasInfantilCurricular
                && $infantilExtended
                && ! $hasAee
                && ! $hasAc
                && count($turmaCodes) === 1
            ) {
                $infantilExt++;
            }
        }

        return [
            'people' => count($byPerson),
            'fund_aee_contraturno' => $fundAee,
            'curricular_ac' => $currAc,
            'infantil_turma_estendida' => $infantilExt,
            'multi_enrollment' => $multi,
            'by_turno_curricular' => $this->sortDesc($byTurnoCurricular),
            'columns_turno' => $hasTurno,
            'columns_ch' => $hasCh,
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{
     *   total: int,
     *   by_etapa_ensino: array<string, int>,
     *   without_etapa: int,
     *   without_turma: int,
     *   by_cor_raca: array<string, int>,
     *   by_sexo: array<string, int>,
     *   by_faixa_etaria: array<string, int>,
     *   by_nee: array<string, int>,
     *   nee_flagged: int,
     *   by_transporte: array<string, int>,
     *   transporte_flagged: int,
     *   without_transporte: int,
     *   transporte_sem_poder: int,
     *   by_poder_publico_transporte: array<string, int>,
     *   by_veiculo_transporte: array<string, int>,
     *   without_cor: int,
     *   without_sexo: int,
     *   without_nascimento: int,
     *   columns: array{
     *     cor_raca: bool,
     *     sexo: bool,
     *     nascimento: bool,
     *     nee: bool,
     *     transporte: bool,
     *     poder_publico: bool,
     *     poder_publico_transporte: bool,
     *     veiculo_transporte: bool
     *   }
     * }
     */
    public function aggregateAlunos(array $rows, CsvReader $csv, ?int $referenceYear = null): array
    {
        $byEtapa = [];
        $byCor = [];
        $bySexo = [];
        $byIdade = [];
        $byNee = [];
        $byDef = [];
        $byDisorder = [];
        $byAh = [];
        $byUnder = [];
        $byTurma = [];
        $byTransporte = [];
        $byPoderPublicoTra = [];
        $byVeiculoTra = [];
        $withoutEtapa = 0;
        $withoutTurma = 0;
        $withoutCor = 0;
        $withoutSexo = 0;
        $withoutNasc = 0;
        $withoutTransporte = 0;
        $transporteFlagged = 0;
        $transporteSemPoder = 0;
        $neeFlagged = 0;
        $defFlagged = 0;
        $disorderFlagged = 0;
        $ahFlagged = 0;
        $underFlagged = 0;
        $refYear = $referenceYear ?? (int) date('Y');
        $ageRules = new AgeGradeRules;
        $neeClassifier = new NeeConditionClassifier;
        $ageGrade = [
            'eligible' => 0,
            'distorcao' => 0,
            'atraso_1' => 0,
            'adequado' => 0,
            'adiantado' => 0,
            'indefinido' => 0,
            'excluido' => 0,
            'by_etapa' => [],
        ];

        $sampleHeaders = $rows[0] ?? [];
        $headerKeys = array_keys($sampleHeaders);
        // Se a 1.ª linha for dados, headers ainda estão nas chaves do associative array.
        $hasCor = $this->rowHasAnyHeader($sampleHeaders, ['Cor/Raça', 'Cor/Raca', 'Raça', 'Raca', 'Cor']);
        $hasSexo = $this->rowHasAnyHeader($sampleHeaders, ['Sexo', 'Sexo biológico', 'Sexo biologico', 'Gênero', 'Genero']);
        $hasNasc = $this->rowHasAnyHeader($sampleHeaders, ['Data de nascimento', 'Data Nascimento', 'Nascimento']);
        $hasNee = $this->headersMatchNee($headerKeys);
        $usoTransporteHeader = $this->findTransporteUsoHeader($headerKeys);
        $poderTraHeader = $this->findHeaderMatching($headerKeys, '/poder\s*p[uú]blico/iu');
        $veiculoTraHeader = $this->findHeaderMatching($headerKeys, '/ve[ií]culo/iu');
        $hasTransporte = $usoTransporteHeader !== null
            || $this->headersMatchPattern($headerKeys, '/transporte/iu');
        $hasPoderPublicoTra = $poderTraHeader !== null;
        $hasVeiculoTra = $veiculoTraHeader !== null;
        $hasPoderPublico = $this->headersMatchPattern($headerKeys, '/poder\s*p[uú]blico|bolsa\s*fam[ií]lia|cad[\s\-]?[uú]nico|nis\b/iu');

        foreach ($rows as $row) {
            $etapa = trim($csv->value($row, 'Etapa de ensino'));
            $turma = trim($csv->value($row, 'Código da turma'));

            if ($turma === '') {
                $withoutTurma++;
            } else {
                $byTurma[$turma] = ($byTurma[$turma] ?? 0) + 1;
            }
            if ($etapa === '') {
                $withoutEtapa++;
                $etapa = __('Não informado');
            }
            $byEtapa[$etapa] = ($byEtapa[$etapa] ?? 0) + 1;

            if ($hasCor) {
                $cor = $this->firstNonEmpty($csv, $row, ['Cor/Raça', 'Cor/Raca', 'Raça', 'Raca', 'Cor']);
                if ($cor === '') {
                    $withoutCor++;
                    $cor = __('Não informado');
                }
                $byCor[$cor] = ($byCor[$cor] ?? 0) + 1;
            }

            if ($hasSexo) {
                $sexo = $this->firstNonEmpty($csv, $row, ['Sexo', 'Sexo biológico', 'Sexo biologico', 'Gênero', 'Genero']);
                if ($sexo === '') {
                    $withoutSexo++;
                    $sexo = __('Não informado');
                }
                $sexo = $this->normalizeSexo($sexo);
                $bySexo[$sexo] = ($bySexo[$sexo] ?? 0) + 1;
            }

            $nasc = '';
            if ($hasNasc) {
                $nasc = $this->firstNonEmpty($csv, $row, ['Data de nascimento', 'Data Nascimento', 'Nascimento']);
                $band = $this->ageBandFromDate($nasc, $refYear);
                if ($band === null) {
                    $withoutNasc++;
                    $band = __('Não informado');
                }
                $byIdade[$band] = ($byIdade[$band] ?? 0) + 1;
            }

            if ($hasNasc || $etapa !== __('Não informado')) {
                $cls = $ageRules->classify($etapa, $nasc, $refYear);
                $status = $cls['status'];
                $ageGrade[$status] = ($ageGrade[$status] ?? 0) + 1;
                if (in_array($status, [
                    AgeGradeRules::STATUS_ON_TRACK,
                    AgeGradeRules::STATUS_EARLY,
                    AgeGradeRules::STATUS_DELAY_1,
                    AgeGradeRules::STATUS_DISTORTION,
                ], true)) {
                    $ageGrade['eligible']++;
                    if (! isset($ageGrade['by_etapa'][$etapa])) {
                        $ageGrade['by_etapa'][$etapa] = [
                            'eligible' => 0,
                            'distorcao' => 0,
                            'atraso_1' => 0,
                            'adequado' => 0,
                            'adiantado' => 0,
                        ];
                    }
                    $ageGrade['by_etapa'][$etapa]['eligible']++;
                    if (isset($ageGrade['by_etapa'][$etapa][$status])) {
                        $ageGrade['by_etapa'][$etapa][$status]++;
                    }
                }
            }

            if ($hasNee) {
                $classified = $neeClassifier->classifyRow($row);
                $neeTags = $classified['tags'];
                if ($classified['flagged']) {
                    $neeFlagged++;
                    foreach ($neeTags as $tag) {
                        $byNee[$tag] = ($byNee[$tag] ?? 0) + 1;
                    }
                    if ($classified['deficiencies'] !== []) {
                        $defFlagged++;
                        foreach ($classified['deficiencies'] as $cond) {
                            $label = $cond['code'].' · '.$cond['label'];
                            $byDef[$label] = ($byDef[$label] ?? 0) + 1;
                        }
                    }
                    if ($classified['disorders'] !== []) {
                        $disorderFlagged++;
                        foreach ($classified['disorders'] as $cond) {
                            $label = $cond['code'].' · '.$cond['label'];
                            $byDisorder[$label] = ($byDisorder[$label] ?? 0) + 1;
                        }
                    }
                    if ($classified['ah'] !== []) {
                        $ahFlagged++;
                        foreach ($classified['ah'] as $cond) {
                            $label = $cond['code'].' · '.$cond['label'];
                            $byAh[$label] = ($byAh[$label] ?? 0) + 1;
                        }
                    }
                }
                $underFlags = $neeClassifier->assessUnderreporting(
                    $classified,
                    $this->rowLooksLikeAee($csv, $row, $etapa),
                );
                if ($underFlags !== []) {
                    $underFlagged++;
                    foreach ($underFlags as $flag) {
                        $ulabel = $flag['code'].' · '.$flag['label'];
                        $byUnder[$ulabel] = ($byUnder[$ulabel] ?? 0) + 1;
                    }
                }
            }

            $usoTra = null;
            if ($usoTransporteHeader !== null) {
                $usoTra = $this->normalizeYesNo(trim($csv->value($row, $usoTransporteHeader)));
                if ($usoTra === __('Não informado')) {
                    $withoutTransporte++;
                }
                if ($usoTra === __('Sim')) {
                    $transporteFlagged++;
                }
                $byTransporte[$usoTra] = ($byTransporte[$usoTra] ?? 0) + 1;
            }

            $poderTra = null;
            if ($poderTraHeader !== null) {
                $poderTra = trim($csv->value($row, $poderTraHeader));
                if ($poderTra === '') {
                    $poderTra = __('Não informado');
                }
                $byPoderPublicoTra[$poderTra] = ($byPoderPublicoTra[$poderTra] ?? 0) + 1;
            }

            if ($veiculoTraHeader !== null) {
                $veiculo = trim($csv->value($row, $veiculoTraHeader));
                if ($veiculo === '') {
                    $veiculo = __('Não informado');
                }
                $byVeiculoTra[$veiculo] = ($byVeiculoTra[$veiculo] ?? 0) + 1;
            }

            if (
                $usoTra === __('Sim')
                && $poderTraHeader !== null
                && ($poderTra === null || $poderTra === __('Não informado'))
            ) {
                $transporteSemPoder++;
            }
        }

        $eligible = max(0, (int) $ageGrade['eligible']);
        $ageGrade['pct_distorcao'] = $eligible > 0
            ? round(100 * ((int) $ageGrade['distorcao']) / $eligible, 1)
            : null;
        $ageGrade['by_etapa'] = $this->sortEtapaAgePedagogical($ageGrade['by_etapa']);

        return [
            'total' => count($rows),
            'by_etapa_ensino' => $this->sortDesc($byEtapa),
            'without_etapa' => $withoutEtapa,
            'without_turma' => $withoutTurma,
            'by_turma' => $this->sortDesc($byTurma),
            'by_cor_raca' => $this->sortDesc($byCor),
            'by_sexo' => $this->sortDesc($bySexo),
            'by_faixa_etaria' => $this->sortAgeBands($byIdade),
            'by_nee' => $this->sortDesc($byNee),
            'nee_flagged' => $neeFlagged,
            'by_deficiency' => $this->sortDesc($byDef),
            'by_disorder' => $this->sortDesc($byDisorder),
            'by_ah' => $this->sortDesc($byAh),
            'deficiency_flagged' => $defFlagged,
            'disorder_flagged' => $disorderFlagged,
            'ah_flagged' => $ahFlagged,
            'by_underreporting' => $this->sortDesc($byUnder),
            'underreporting_flagged' => $underFlagged,
            'by_transporte' => $this->sortYesNoFirst($byTransporte),
            'transporte_flagged' => $transporteFlagged,
            'without_transporte' => $withoutTransporte,
            'transporte_sem_poder' => $transporteSemPoder,
            'by_poder_publico_transporte' => $this->sortDesc($byPoderPublicoTra),
            'by_veiculo_transporte' => $this->sortDesc($byVeiculoTra),
            'without_cor' => $withoutCor,
            'without_sexo' => $withoutSexo,
            'without_nascimento' => $withoutNasc,
            'age_grade' => $ageGrade,
            'columns' => [
                'cor_raca' => $hasCor,
                'sexo' => $hasSexo,
                'nascimento' => $hasNasc,
                'nee' => $hasNee,
                'transporte' => $hasTransporte,
                'poder_publico' => $hasPoderPublico,
                'poder_publico_transporte' => $hasPoderPublicoTra,
                'veiculo_transporte' => $hasVeiculoTra,
            ],
        ];
    }

    /**
     * Ordena etapas na sequência escolar (1º, 2º, …) e calcula % de distorção.
     *
     * @param  array<string, array<string, int>>  $byEtapa
     * @return array<string, array<string, int|float|null>>
     */
    private function sortEtapaAgePedagogical(array $byEtapa): array
    {
        $order = new EtapaLabelOrder;
        $byEtapa = $order->sortAssocByLabel($byEtapa);
        $out = [];
        $i = 0;
        foreach ($byEtapa as $label => $row) {
            if ($i >= 40) {
                break;
            }
            $elig = max(1, (int) ($row['eligible'] ?? 0));
            $out[$label] = [
                ...$row,
                'pct_distorcao' => round(100 * ((int) ($row['distorcao'] ?? 0)) / $elig, 1),
            ];
            $i++;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, int>>  $byEtapa
     * @return array<string, array<string, int|float|null>>
     * @deprecated Use sortEtapaAgePedagogical
     */
    private function sortDescEtapaAge(array $byEtapa): array
    {
        return $this->sortEtapaAgePedagogical($byEtapa);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{
     *   total: int,
     *   by_turma: array<string, int>,
     *   without_turma: int,
     *   docente_rows: int
     * }
     */
    public function aggregateProfissionais(array $rows, CsvReader $csv): array
    {
        $byTurma = [];
        $withoutTurma = 0;
        $docente = 0;

        foreach ($rows as $row) {
            $turma = trim($csv->value($row, 'Código da turma'));
            $funcao = mb_strtolower(trim($csv->value($row, 'Função')));
            if ($funcao === '') {
                $funcao = mb_strtolower(trim($csv->value($row, 'Cargo')));
            }
            if ($turma === '') {
                $withoutTurma++;
            } else {
                $byTurma[$turma] = ($byTurma[$turma] ?? 0) + 1;
            }
            if (
                str_contains($funcao, 'docente')
                || str_contains($funcao, 'professor')
                || str_contains($funcao, 'educador')
            ) {
                $docente++;
            }
        }

        return [
            'total' => count($rows),
            'by_turma' => $this->sortDesc($byTurma),
            'without_turma' => $withoutTurma,
            'docente_rows' => $docente > 0 ? $docente : count($rows),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headers
     */
    private function rowHasAnyHeader(array $row, array $headers): bool
    {
        foreach ($headers as $header) {
            if (array_key_exists($header, $row)) {
                return true;
            }
        }
        // Match case-insensitive / BOM-stripped keys
        $keys = array_map(static fn ($k) => mb_strtolower(ltrim((string) $k, "\xEF\xBB\xBF")), array_keys($row));
        foreach ($headers as $header) {
            if (in_array(mb_strtolower($header), $keys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string|int>  $headerKeys
     */
    public function headersMatchNee(array $headerKeys): bool
    {
        foreach ($headerKeys as $key) {
            if (preg_match('/defici|autis|tea|altas\s*habil|nee|transtorno\s+do\s+espectro/i', (string) $key) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string|int>  $headerKeys
     */
    private function headersMatchPattern(array $headerKeys, string $pattern): bool
    {
        return $this->findHeaderMatching($headerKeys, $pattern) !== null;
    }

    /**
     * @param  list<string|int>  $headerKeys
     */
    private function findHeaderMatching(array $headerKeys, string $pattern): ?string
    {
        foreach ($headerKeys as $key) {
            if (preg_match($pattern, (string) $key) === 1) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * Coluna de uso/utilização de transporte (exclui poder público e tipo de veículo).
     *
     * @param  list<string|int>  $headerKeys
     */
    private function findTransporteUsoHeader(array $headerKeys): ?string
    {
        foreach ($headerKeys as $key) {
            $k = (string) $key;
            if (preg_match('/transporte/iu', $k) !== 1) {
                continue;
            }
            if (preg_match('/poder|ve[ií]culo/iu', $k) === 1) {
                continue;
            }

            return $k;
        }

        return null;
    }

    private function normalizeYesNo(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '') {
            return __('Não informado');
        }
        if (in_array($s, ['sim', 's', '1', 'true', 'yes', 'y'], true)) {
            return __('Sim');
        }
        if (in_array($s, ['não', 'nao', 'n', '0', 'false', 'no'], true)) {
            return __('Não');
        }

        return $raw;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sortYesNoFirst(array $counts): array
    {
        $order = [__('Sim'), __('Não'), __('Não informado')];
        $sorted = [];
        foreach ($order as $label) {
            if (isset($counts[$label])) {
                $sorted[$label] = $counts[$label];
                unset($counts[$label]);
            }
        }
        arsort($counts, SORT_NUMERIC);

        return $sorted + $counts;
    }

    /**
     * @param  list<string>  $headers
     */
    private function firstNonEmpty(CsvReader $csv, array $row, array $headers): string
    {
        foreach ($headers as $header) {
            $v = trim($csv->value($row, $header));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    private function normalizeSexo(string $sexo): string
    {
        $s = mb_strtolower(trim($sexo));
        if (in_array($s, ['m', 'masc', 'masculino', 'homem'], true)) {
            return __('Masculino');
        }
        if (in_array($s, ['f', 'fem', 'feminino', 'mulher'], true)) {
            return __('Feminino');
        }
        if (in_array($s, ['ni', 'n/i', 'não informado', 'nao informado', 'não declarado', 'nao declarado'], true)) {
            return __('Não informado');
        }

        return $sexo;
    }

    private function ageBandFromDate(string $raw, int $refYear): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $dt = null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
            $dt = \DateTimeImmutable::createFromFormat('!d/m/Y', sprintf('%s/%s/%s', $m[1], $m[2], $m[3]));
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1) {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        }
        if (! $dt instanceof \DateTimeImmutable) {
            return null;
        }
        $ref = \DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%d-03-31', $refYear));
        if (! $ref instanceof \DateTimeImmutable) {
            return null;
        }
        $age = (int) $dt->diff($ref)->y;
        if ($age < 0 || $age > 120) {
            return null;
        }
        if ($age <= 3) {
            return '0–3';
        }
        if ($age <= 5) {
            return '4–5';
        }
        if ($age <= 10) {
            return '6–10';
        }
        if ($age <= 14) {
            return '11–14';
        }
        if ($age <= 17) {
            return '15–17';
        }

        return '18+';
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function detectNeeTags(array $row): array
    {
        return (new NeeConditionClassifier)->classifyRow($row)['tags'];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sortAgeBands(array $counts): array
    {
        $order = ['0–3', '4–5', '6–10', '11–14', '15–17', '18+', __('Não informado')];
        $sorted = [];
        foreach ($order as $label) {
            if (isset($counts[$label])) {
                $sorted[$label] = $counts[$label];
                unset($counts[$label]);
            }
        }
        arsort($counts, SORT_NUMERIC);

        return $sorted + $counts;
    }

    public function classifyTipoTurma(string $tipo): string
    {
        $t = mb_strtolower(trim($tipo));
        if ($t === '' || $t === mb_strtolower(__('Não informado'))) {
            return self::BUCKET_OUTRA;
        }
        if (
            str_contains($t, 'atendimento educacional')
            || preg_match('/\baee\b/u', $t) === 1
            || $t === 'aee'
        ) {
            return self::BUCKET_AEE;
        }
        // Educacenso: «Curricular … com Atividade Complementar» continua sendo vínculo curricular
        // (a AC é oferta associada). Tem de vir antes do teste puro de AC.
        if (str_contains($t, 'curricular')) {
            return self::BUCKET_CURRICULAR;
        }
        if (
            str_contains($t, 'atividade complementar')
            || preg_match('/\bac\b/u', $t) === 1
            || $t === 'ac'
        ) {
            return self::BUCKET_AC;
        }

        return self::BUCKET_OUTRA;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowLooksLikeAee(CsvReader $csv, array $row, string $etapa): bool
    {
        if (preg_match('/\baee\b|atendimento educacional/iu', $etapa) === 1) {
            return true;
        }
        $tipo = trim($csv->value($row, 'Tipo de turma'));
        if ($tipo === '') {
            $tipo = trim($csv->value($row, 'Tipo de Turma'));
        }

        return $this->classifyTipoTurma($tipo) === self::BUCKET_AEE;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    public function mergeCounts(array $into, array $from): array
    {
        foreach ($from as $key => $value) {
            $into[$key] = ($into[$key] ?? 0) + (int) $value;
        }

        return $this->sortDesc($into);
    }

    /**
     * @param  array{curricular: int, aee: int, atividade_complementar: int, outra: int}  $into
     * @param  array{curricular?: int, aee?: int, atividade_complementar?: int, outra?: int}  $from
     * @return array{curricular: int, aee: int, atividade_complementar: int, outra: int}
     */
    public function mergeBuckets(array $into, array $from): array
    {
        foreach ([self::BUCKET_CURRICULAR, self::BUCKET_AEE, self::BUCKET_AC, self::BUCKET_OUTRA] as $key) {
            $into[$key] = ($into[$key] ?? 0) + (int) ($from[$key] ?? 0);
        }

        return $into;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{label: string, count: int, pct: float}>
     */
    public function toBars(array $counts, int $limit = 12): array
    {
        $total = max(1, array_sum($counts));
        $bars = [];
        $i = 0;
        $other = 0;

        foreach ($counts as $label => $count) {
            if ($i < $limit) {
                $bars[] = [
                    'label' => (string) $label,
                    'count' => (int) $count,
                    'pct' => round(100 * $count / $total, 1),
                ];
                $i++;
            } else {
                $other += (int) $count;
            }
        }

        if ($other > 0) {
            $bars[] = [
                'label' => __('Outros'),
                'count' => $other,
                'pct' => round(100 * $other / $total, 1),
            ];
        }

        return $bars;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    public function sortDesc(array $counts): array
    {
        arsort($counts, SORT_NUMERIC);

        return $counts;
    }

    /**
     * @param  list<string|int>  $headerKeys
     */
    private function findTurnoHeader(array $headerKeys): ?string
    {
        foreach ($headerKeys as $key) {
            $k = (string) $key;
            if (preg_match('/^turno$/iu', $k) === 1) {
                return $k;
            }
        }
        foreach ($headerKeys as $key) {
            $k = (string) $key;
            if (preg_match('/turno|hor[aá]rio\s*de\s*funcionamento/iu', $k) === 1) {
                return $k;
            }
        }

        return null;
    }

    /**
     * @param  list<string|int>  $headerKeys
     */
    private function findCargaHorariaHeader(array $headerKeys): ?string
    {
        foreach ($headerKeys as $key) {
            $k = (string) $key;
            if (preg_match('/carga\s*hor[aá]ria|ch\s*semanal|dura[cç][aã]o\s*(semanal|semanal\s*da\s*turma)?/iu', $k) === 1) {
                return $k;
            }
        }

        return null;
    }

    private function normalizeTurno(string $raw): string
    {
        $meta = $this->turnoDisplayMeta($raw);

        return (string) ($meta['label'] ?? $raw);
    }

    /**
     * Metadados de exibição do turno (rótulo canónico, tom, dias, detalhe de «Outros»).
     *
     * @return array{
     *   bucket: string,
     *   label: string,
     *   short: string,
     *   tone: string,
     *   icon: string,
     *   days: list<string>,
     *   schedule: ?string,
     *   is_other: bool,
     *   detail: ?string,
     *   raw_compact: string
     * }
     */
    public function turnoDisplayMeta(string $raw): array
    {
        $original = trim($raw);
        $s = mb_strtolower($original);
        $days = $this->extractWeekdayAbbrevs($s);
        $schedule = $this->extractScheduleHint($s);
        $rawCompact = $original !== '' ? $this->compactTurnoLabel($original) : '';

        $bucket = 'nao_informado';
        $label = __('Não informado');
        $short = __('N/I');
        $tone = 'slate';
        $icon = 'question';
        $isOther = false;
        $detail = null;

        if ($s !== '') {
            $canonical = $this->resolveTurnoBucket($s, $schedule);
            $bucket = $canonical;

            switch ($canonical) {
                case 'integral':
                    $label = __('Integral');
                    $short = __('Int.');
                    $tone = 'violet';
                    $icon = 'sun-double';
                    break;
                case 'manha':
                    $label = __('Manhã');
                    $short = __('Manhã');
                    $tone = 'amber';
                    $icon = 'sun';
                    break;
                case 'tarde':
                    $label = __('Tarde');
                    $short = __('Tarde');
                    $tone = 'orange';
                    $icon = 'sunset';
                    break;
                case 'noite':
                    $label = __('Noite');
                    $short = __('Noite');
                    $tone = 'indigo';
                    $icon = 'moon';
                    break;
                case 'intermediario':
                    $label = __('Intermediário');
                    $short = __('Inter.');
                    $tone = 'sky';
                    $icon = 'clock';
                    break;
                case 'outros':
                    $label = __('Outros');
                    $short = __('Outros');
                    $tone = 'slate';
                    $icon = 'calendar';
                    $isOther = true;
                    $detail = $rawCompact !== '' ? $rawCompact : $original;
                    break;
                default:
                    break;
            }
        }

        return [
            'bucket' => $bucket,
            'label' => $label,
            'short' => $short,
            'tone' => $tone,
            'icon' => $icon,
            'days' => $days,
            'schedule' => $schedule,
            'is_other' => $isOther,
            'detail' => $detail,
            'raw_compact' => $rawCompact,
        ];
    }

    /**
     * Resolve o balde canónico do turno (texto + horário quando necessário).
     */
    private function resolveTurnoBucket(string $lower, ?string $schedule): string
    {
        if (preg_match('/^outros$/iu', $lower) === 1) {
            return 'outros';
        }
        if (preg_match('/integral|tempo\s*integral|manh[aã].*tarde|tarde.*manh[aã]|estendid|dia\s*todo|per[ií]odo\s*integral/u', $lower) === 1) {
            return 'integral';
        }
        if (preg_match('/intermedi/u', $lower) === 1) {
            return 'intermediario';
        }
        if (preg_match('/manh[aã]|matut/u', $lower) === 1) {
            return 'manha';
        }
        if (preg_match('/tarde|vespert/u', $lower) === 1) {
            return 'tarde';
        }
        if (preg_match('/noite|noturn/u', $lower) === 1) {
            return 'noite';
        }

        $fromHours = $this->inferPeriodFromSchedule($lower, $schedule);
        if ($fromHours !== null) {
            return $fromHours;
        }

        return 'outros';
    }

    /**
     * Infere Manhã/Tarde/Noite/Integral a partir de intervalos horários no texto.
     */
    private function inferPeriodFromSchedule(string $lower, ?string $schedule): ?string
    {
        $blob = $schedule !== null ? mb_strtolower($schedule) : $lower;
        if (preg_match_all('/\b(\d{1,2})(?:[:h\.](\d{2}))?\b/u', $blob, $matches, PREG_SET_ORDER) < 1) {
            return null;
        }

        $hours = [];
        foreach ($matches as $m) {
            $h = (int) $m[1];
            if ($h > 23) {
                continue;
            }
            $hours[] = $h;
        }
        if ($hours === []) {
            return null;
        }

        $start = $hours[0];
        $end = $hours[count($hours) - 1];
        $span = $end >= $start ? ($end - $start) : (24 - $start + $end);

        // Dois períodos no mesmo dia ou jornada longa → integral.
        if ($span >= 7 || ($start <= 11 && $end >= 14)) {
            return 'integral';
        }
        if ($start >= 18 || ($start <= 5 && $end <= 8)) {
            return 'noite';
        }
        if ($start >= 12) {
            return 'tarde';
        }
        if ($start >= 5 && $start <= 11) {
            return 'manha';
        }

        return null;
    }

    /**
     * Reagrupa contagens de turno em rótulos canónicos e extrai detalhe de «Outros».
     * Útil para análises antigas (textos livres) e para unificar fontes.
     *
     * @param  array<string, int>  $byTurno
     * @param  array<string, int>  $byTurnoOutros
     * @return array{by_turno: array<string, int>, by_turno_outros: array<string, int>}
     */
    public function rebucketTurnoCounts(array $byTurno, array $byTurnoOutros = []): array
    {
        $canonical = [];
        $outros = $byTurnoOutros;

        foreach ($byTurno as $label => $count) {
            $n = (int) $count;
            if ($n <= 0) {
                continue;
            }
            $meta = $this->turnoDisplayMeta((string) $label);
            $canonLabel = (string) ($meta['label'] ?? __('Não informado'));
            $canonical[$canonLabel] = ($canonical[$canonLabel] ?? 0) + $n;
            if (! empty($meta['is_other'])) {
                $detail = (string) ($meta['detail'] ?? $label);
                if ($detail === '' || strcasecmp($detail, (string) __('Outros')) === 0) {
                    continue;
                }
                $outros[$detail] = ($outros[$detail] ?? 0) + $n;
            }
        }

        return [
            'by_turno' => $this->sortDesc($canonical),
            'by_turno_outros' => $this->sortDesc($outros),
        ];
    }

    /**
     * @param  list<array{label: string, count: int|float, pct: float|int}>  $bars
     * @param  array<string, int>  $outrosDetail
     * @return list<array<string, mixed>>
     */
    public function enrichTurnoBars(array $bars, array $outrosDetail = []): array
    {
        $out = [];
        $canonicalOrder = [
            __('Manhã') => 1,
            __('Intermediário') => 2,
            __('Tarde') => 3,
            __('Noite') => 4,
            __('Integral') => 5,
            __('Outros') => 6,
            __('Não informado') => 7,
        ];

        foreach ($bars as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $meta = $this->turnoDisplayMeta((string) ($bar['label'] ?? ''));
            $row = array_merge($bar, [
                'label' => $meta['label'],
                'short' => $meta['short'],
                'tone' => $meta['tone'],
                'icon' => $meta['icon'],
                'days' => $meta['days'],
                'schedule' => $meta['schedule'],
                'bucket' => $meta['bucket'],
                'is_other' => (bool) $meta['is_other'],
                'details' => [],
            ]);

            if ($row['is_other'] || $meta['bucket'] === 'outros') {
                $details = [];
                foreach ($this->toBars($outrosDetail, 30) as $detailBar) {
                    $details[] = [
                        'label' => (string) ($detailBar['label'] ?? ''),
                        'count' => (int) ($detailBar['count'] ?? 0),
                        'pct' => (float) ($detailBar['pct'] ?? 0),
                    ];
                }
                $row['details'] = $details;
                $row['label'] = __('Outros');
                $row['short'] = __('Outros');
                $row['is_other'] = true;
                $row['bucket'] = 'outros';
                $row['tone'] = 'slate';
                $row['icon'] = 'calendar';
            }

            $out[] = $row;
        }

        usort($out, static function (array $a, array $b) use ($canonicalOrder): int {
            $oa = $canonicalOrder[(string) ($a['label'] ?? '')] ?? 50;
            $ob = $canonicalOrder[(string) ($b['label'] ?? '')] ?? 50;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return ((int) ($b['count'] ?? 0)) <=> ((int) ($a['count'] ?? 0));
        });

        return $out;
    }

    /**
     * @param  list<array{label: string, count: int|float, pct: float|int}>  $bars
     * @return list<array<string, mixed>>
     */
    public function enrichCargaBars(array $bars): array
    {
        $out = [];
        foreach ($bars as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $label = (string) ($bar['label'] ?? '');
            $hours = $this->hoursFromCargaLabel($label);
            $meta = $this->cargaBandMetaFromLabelOrHours($label, $hours);
            $out[] = array_merge($bar, [
                'label' => $meta['label'],
                'short' => $meta['short'],
                'tone' => $meta['tone'],
                'icon' => $meta['icon'],
                'hint' => $meta['hint'],
                'hours' => $meta['hours_anchor'],
                'band' => $meta['key'],
                'range' => $meta['range'],
            ]);
        }

        usort($out, static function (array $a, array $b): int {
            if (($a['band'] ?? '') === 'ni' && ($b['band'] ?? '') !== 'ni') {
                return 1;
            }
            if (($b['band'] ?? '') === 'ni' && ($a['band'] ?? '') !== 'ni') {
                return -1;
            }
            $ha = $a['hours'];
            $hb = $b['hours'];
            if ($ha === null && $hb === null) {
                return 0;
            }
            if ($ha === null) {
                return 1;
            }
            if ($hb === null) {
                return -1;
            }

            return $ha <=> $hb;
        });

        return $out;
    }

    /**
     * Valores exactos de CH (detalhe sob as faixas).
     *
     * @param  list<array{label: string, count: int|float, pct: float|int}>  $bars
     * @return list<array<string, mixed>>
     */
    public function enrichCargaExactBars(array $bars): array
    {
        $out = [];
        foreach ($bars as $bar) {
            if (! is_array($bar)) {
                continue;
            }
            $label = (string) ($bar['label'] ?? '');
            $hours = $this->hoursFromCargaLabel($label);
            if ($hours === null || $this->isCargaBandLabel($label)) {
                continue;
            }
            $band = $this->cargaHorariaBandMeta($hours);
            $out[] = array_merge($bar, [
                'hours' => $hours,
                'short' => __(':n h', ['n' => $this->formatHours($hours)]),
                'band_label' => $band['short'],
                'tone' => $band['tone'],
            ]);
        }

        usort($out, static function (array $a, array $b): int {
            return ((float) ($a['hours'] ?? 0)) <=> ((float) ($b['hours'] ?? 0));
        });

        return $out;
    }

    /**
     * Reagrupa contagens de CH em faixas pedagógicas + valores exactos.
     *
     * @param  array<string, int>  $byCh
     * @param  array<string, int>  $byChExact
     * @return array{by_ch_band: array<string, int>, by_ch_exact: array<string, int>}
     */
    public function rebucketCargaCounts(array $byCh, array $byChExact = []): array
    {
        $bands = [];
        $exact = [];
        $sourceLooksExact = $byChExact === [] && $this->cargaMapLooksExact($byCh);

        foreach ($byCh as $label => $count) {
            $n = (int) $count;
            if ($n <= 0) {
                continue;
            }
            $label = (string) $label;
            $hours = $this->hoursFromCargaLabel($label);
            $meta = $this->cargaBandMetaFromLabelOrHours($label, $hours);
            $bands[$meta['label']] = ($bands[$meta['label']] ?? 0) + $n;

            if ($sourceLooksExact && $hours !== null && ! $this->isCargaBandLabel($label)) {
                $exactLabel = $this->cargaHorariaLabel($hours);
                $exact[$exactLabel] = ($exact[$exactLabel] ?? 0) + $n;
            }
        }

        foreach ($byChExact as $label => $count) {
            $n = (int) $count;
            if ($n <= 0) {
                continue;
            }
            $label = (string) $label;
            $hours = $this->hoursFromCargaLabel($label);
            if ($hours === null) {
                continue;
            }
            $exactLabel = $this->cargaHorariaLabel($hours);
            $exact[$exactLabel] = ($exact[$exactLabel] ?? 0) + $n;
            if ($byCh === []) {
                $meta = $this->cargaHorariaBandMeta($hours);
                $bands[$meta['label']] = ($bands[$meta['label']] ?? 0) + $n;
            }
        }

        return [
            'by_ch_band' => $this->sortCargaBandLabels($bands),
            'by_ch_exact' => $this->sortCargaBands($exact),
        ];
    }

    /**
     * Faixa pedagógica da carga horária semanal.
     *
     * @return array{
     *   key: string,
     *   label: string,
     *   short: string,
     *   range: string,
     *   tone: string,
     *   icon: string,
     *   hint: string,
     *   hours_anchor: float|null
     * }
     */
    public function cargaHorariaBandMeta(?float $hours): array
    {
        if ($hours === null) {
            return [
                'key' => 'ni',
                'label' => __('Não informado'),
                'short' => __('N/I'),
                'range' => '—',
                'tone' => 'slate',
                'icon' => 'question',
                'hint' => __('Sem Carga horária semanal legível no export'),
                'hours_anchor' => null,
            ];
        }
        if ($hours >= 35.0) {
            return [
                'key' => 'integral',
                'label' => __('≥ 35 h — tempo integral'),
                'short' => __('≥ 35 h'),
                'range' => '≥ 35',
                'tone' => 'violet',
                'icon' => 'sun-double',
                'hint' => __('Faixa típica de tempo integral (≥ 35 h/semana)'),
                'hours_anchor' => 35.0,
            ];
        }
        if ($hours >= 25.0) {
            return [
                'key' => 'ampliada',
                'label' => __('25–34 h — jornada ampliada'),
                'short' => __('25–34 h'),
                'range' => '25–34',
                'tone' => 'sky',
                'icon' => 'clock',
                'hint' => __('Jornada ampliada, abaixo do limiar usual de integral'),
                'hours_anchor' => 25.0,
            ];
        }
        if ($hours >= 20.0) {
            return [
                'key' => 'parcial',
                'label' => __('20–24 h — parcial típica'),
                'short' => __('20–24 h'),
                'range' => '20–24',
                'tone' => 'emerald',
                'icon' => 'clock',
                'hint' => __('Jornada parcial mais comum na rede (cerca de 4 h/dia)'),
                'hours_anchor' => 20.0,
            ];
        }
        if ($hours >= 15.0) {
            return [
                'key' => 'curta',
                'label' => __('15–19 h — parcial curta'),
                'short' => __('15–19 h'),
                'range' => '15–19',
                'tone' => 'amber',
                'icon' => 'clock',
                'hint' => __('Parcial abaixo da jornada típica de ~20 h'),
                'hours_anchor' => 15.0,
            ];
        }

        return [
            'key' => 'reduzida',
            'label' => __('Até 14 h — carga reduzida'),
            'short' => __('≤ 14 h'),
            'range' => '≤ 14',
            'tone' => 'orange',
            'icon' => 'clock',
            'hint' => __('Carga reduzida — frequente em AEE, complementar ou turmas especiais'),
            'hours_anchor' => 7.0,
        ];
    }

    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   short: string,
     *   range: string,
     *   tone: string,
     *   icon: string,
     *   hint: string,
     *   hours_anchor: float|null
     * }
     */
    private function cargaBandMetaFromLabelOrHours(string $label, ?float $hours): array
    {
        $s = mb_strtolower(trim($label));
        if ($s === '' || str_contains($s, 'não informado') || str_contains($s, 'nao informado') || $s === 'n/i') {
            return $this->cargaHorariaBandMeta(null);
        }
        if (str_contains($s, '≥ 35') || str_contains($s, '>= 35') || str_contains($s, '35h+') || str_contains($s, 'tempo integral')) {
            return $this->cargaHorariaBandMeta(35.0);
        }
        if ((str_contains($s, '25') && str_contains($s, '34')) || str_contains($s, 'ampliada')) {
            return $this->cargaHorariaBandMeta(25.0);
        }
        if ((str_contains($s, '20') && str_contains($s, '24')) || str_contains($s, 'parcial típica') || str_contains($s, 'parcial tipica')) {
            return $this->cargaHorariaBandMeta(20.0);
        }
        if ((str_contains($s, '15') && str_contains($s, '19')) || str_contains($s, 'parcial curta')) {
            return $this->cargaHorariaBandMeta(15.0);
        }
        if (
            str_contains($s, 'até 14')
            || str_contains($s, 'ate 14')
            || str_contains($s, '≤ 14')
            || str_contains($s, '<= 14')
            || str_contains($s, 'carga reduzida')
        ) {
            return $this->cargaHorariaBandMeta(7.0);
        }
        if (str_contains($s, 'até 20') || str_contains($s, 'ate 20')) {
            return $this->cargaHorariaBandMeta(20.0);
        }

        return $this->cargaHorariaBandMeta($hours);
    }

    private function isCargaBandLabel(string $label): bool
    {
        $s = mb_strtolower(trim($label));
        if ($s === '') {
            return false;
        }

        return str_contains($s, 'tempo integral')
            || str_contains($s, 'jornada ampliada')
            || str_contains($s, 'parcial típica')
            || str_contains($s, 'parcial tipica')
            || str_contains($s, 'parcial curta')
            || str_contains($s, 'carga reduzida')
            || str_contains($s, '≥ 35')
            || str_contains($s, '25–34')
            || str_contains($s, '20–24')
            || str_contains($s, '15–19')
            || str_contains($s, 'até 14')
            || str_contains($s, 'ate 14');
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function cargaMapLooksExact(array $counts): bool
    {
        if ($counts === []) {
            return false;
        }
        $exactish = 0;
        foreach (array_keys($counts) as $label) {
            $label = (string) $label;
            if ($this->isCargaBandLabel($label)) {
                return false;
            }
            $hours = $this->hoursFromCargaLabel($label);
            $lower = mb_strtolower($label);
            if ($hours !== null || str_contains($lower, 'não informado') || str_contains($lower, 'nao informado')) {
                $exactish++;
            }
        }

        return $exactish > 0;
    }

    private function compactTurnoLabel(string $raw): string
    {
        $compact = preg_replace('/\s+/u', ' ', trim($raw)) ?? trim($raw);
        $map = [
            '/segunda[\-\s]?feira/iu' => 'Seg',
            '/ter[cç]a[\-\s]?feira/iu' => 'Ter',
            '/quarta[\-\s]?feira/iu' => 'Qua',
            '/quinta[\-\s]?feira/iu' => 'Qui',
            '/sexta[\-\s]?feira/iu' => 'Sex',
            '/s[aá]bado/iu' => 'Sáb',
            '/domingo/iu' => 'Dom',
        ];
        foreach ($map as $pattern => $repl) {
            $compact = (string) preg_replace($pattern, $repl, $compact);
        }

        return $this->truncateLabel($compact, 56);
    }

    private function truncateLabel(string $label, int $max): string
    {
        if (mb_strlen($label) <= $max) {
            return $label;
        }

        return rtrim(mb_substr($label, 0, max(1, $max - 1))).'…';
    }

    /**
     * @return list<string>
     */
    private function extractWeekdayAbbrevs(string $lower): array
    {
        // Intervalos primeiro («segunda a sexta» / «seg–sex»).
        if (preg_match('/seg(?:unda)?.*sex(?:ta)?|seg\s*[\-–àa]\s*sex/u', $lower) === 1) {
            return ['seg', 'ter', 'qua', 'qui', 'sex'];
        }

        $days = [];
        $map = [
            'seg' => '/\bseg(unda)?\b/u',
            'ter' => '/\bter([cç]a)?\b/u',
            'qua' => '/\bqua(rta)?\b/u',
            'qui' => '/\bqui(nta)?\b/u',
            'sex' => '/\bsex(ta)?\b/u',
            'sáb' => '/\bs[aá]b(ado)?\b/u',
            'dom' => '/\bdom(ingo)?\b/u',
        ];
        foreach ($map as $abbr => $pattern) {
            if (preg_match($pattern, $lower) === 1) {
                $days[] = $abbr;
            }
        }

        return $days;
    }

    private function extractScheduleHint(string $lower): ?string
    {
        if (preg_match('/(\d{1,2}[:h]\d{0,2}\s*(?:às|as|-|–|a)\s*\d{1,2}[:h]\d{0,2})/u', $lower, $m) === 1) {
            return trim((string) preg_replace(['/h/u', '/\s*às\s*/u', '/\s*as\s*/u'], [':', '–', '–'], $m[1]));
        }

        return null;
    }

    private function parseCargaHoraria(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $raw, $m) !== 1) {
            return null;
        }
        $n = (float) str_replace(',', '.', $m[1]);
        if ($n < 0 || $n > 168) {
            return null;
        }

        return $n;
    }

    /**
     * Rótulo exacto da CH semanal encontrada no export (não agrega em faixas grosseiras).
     */
    private function cargaHorariaLabel(?float $hours): string
    {
        if ($hours === null) {
            return __('Não informado');
        }

        return __(':n h/semana', ['n' => $this->formatHours($hours)]);
    }

    private function formatHours(float $hours): string
    {
        $n = (int) round($hours);
        if (abs($hours - $n) < 0.05) {
            return (string) $n;
        }

        return rtrim(rtrim(number_format($hours, 1, ',', ''), '0'), ',');
    }

    private function hoursFromCargaLabel(string $label): ?float
    {
        $s = mb_strtolower(trim($label));
        if ($s === '' || str_contains($s, 'não informado') || str_contains($s, 'nao informado')) {
            return null;
        }
        if (str_contains($s, '35h+') || str_contains($s, 'tempo integral')) {
            return 35.0;
        }
        if (str_contains($s, '21') && str_contains($s, '34')) {
            return 21.0;
        }
        if (str_contains($s, 'até 20') || str_contains($s, 'ate 20')) {
            return 20.0;
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $s, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /**
     * Turma com funcionamento estendido (turno integral ou CH semanal ≥ 35h).
     */
    private function isExtendedHours(string $turnoRaw, ?float $chHours): bool
    {
        $t = mb_strtolower(trim($turnoRaw));
        if ($t !== '' && preg_match('/integral|tempo\s*integral|estendid|manh[aã].*tarde|tarde.*manh[aã]/u', $t) === 1) {
            return true;
        }

        return $chHours !== null && $chHours >= 35.0;
    }

    private function isInfantilEtapa(string $etapa, string $agregada): bool
    {
        $blob = mb_strtolower($etapa.' '.$agregada);

        return preg_match('/infantil|creche|pr[eé][\-\s]?escola|ber[cç][aá]rio/u', $blob) === 1;
    }

    private function isFundamentalEtapa(string $etapa, string $agregada): bool
    {
        $blob = mb_strtolower($etapa.' '.$agregada);

        return preg_match('/fundamental|anos\s*iniciais|anos\s*finais/u', $blob) === 1
            && preg_match('/infantil|eja|m[eé]dio/u', $blob) !== 1;
    }

    /**
     * @param  list<string>  $etapas
     */
    private function etapasIncludeFundamental(array $etapas): bool
    {
        foreach ($etapas as $etapa) {
            if ($this->isFundamentalEtapa($etapa, '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $etapas
     */
    private function etapasIncludeInfantil(array $etapas): bool
    {
        foreach ($etapas as $etapa) {
            if ($this->isInfantilEtapa($etapa, '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sortCargaBandLabels(array $counts): array
    {
        $order = [];
        foreach ($counts as $label => $n) {
            $meta = $this->cargaBandMetaFromLabelOrHours((string) $label, $this->hoursFromCargaLabel((string) $label));
            $order[] = [
                'label' => (string) $label,
                'n' => (int) $n,
                'sort' => $meta['hours_anchor'] ?? 999.0,
                'ni' => $meta['key'] === 'ni',
            ];
        }
        usort($order, static function (array $a, array $b): int {
            if ($a['ni'] !== $b['ni']) {
                return $a['ni'] ? 1 : -1;
            }

            return $a['sort'] <=> $b['sort'];
        });
        $out = [];
        foreach ($order as $row) {
            $out[$row['label']] = $row['n'];
        }

        return $out;
    }

    private function sortCargaBands(array $counts): array
    {
        $items = [];
        foreach ($counts as $label => $n) {
            $items[] = [
                'label' => (string) $label,
                'n' => (int) $n,
                'hours' => $this->hoursFromCargaLabel((string) $label),
            ];
        }
        usort($items, static function (array $a, array $b): int {
            $ha = $a['hours'];
            $hb = $b['hours'];
            if ($ha === null && $hb === null) {
                return strcmp($a['label'], $b['label']);
            }
            if ($ha === null) {
                return 1;
            }
            if ($hb === null) {
                return -1;
            }
            if ($ha !== $hb) {
                return $ha <=> $hb;
            }

            return strcmp($a['label'], $b['label']);
        });

        $sorted = [];
        foreach ($items as $item) {
            $sorted[$item['label']] = $item['n'];
        }

        return $sorted;
    }
}
