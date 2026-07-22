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

            $byEtapa[$etapa] = ($byEtapa[$etapa] ?? 0) + 1;
            $byAgregada[$agregada] = ($byAgregada[$agregada] ?? 0) + 1;
            $byTipo[$tipo] = ($byTipo[$tipo] ?? 0) + 1;
            $byMediacao[$mediacao] = ($byMediacao[$mediacao] ?? 0) + 1;
            $buckets[$this->classifyTipoTurma($tipo)]++;
        }

        return [
            'total' => count($rows),
            'by_etapa_ensino' => $this->sortDesc($byEtapa),
            'by_etapa_agregada' => $this->sortDesc($byAgregada),
            'by_tipo_turma' => $this->sortDesc($byTipo),
            'by_mediacao' => $this->sortDesc($byMediacao),
            'by_tipo_bucket' => $buckets,
            'turma_codes' => array_keys($turmaCodes),
            'without_etapa' => $withoutEtapa,
            'without_tipo' => $withoutTipo,
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
        $refYear = $referenceYear ?? (int) date('Y');
        $ageRules = new AgeGradeRules;
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
                $neeTags = $this->detectNeeTags($row);
                if ($neeTags !== []) {
                    $neeFlagged++;
                    foreach ($neeTags as $tag) {
                        $byNee[$tag] = ($byNee[$tag] ?? 0) + 1;
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
        $ageGrade['by_etapa'] = $this->sortDescEtapaAge($ageGrade['by_etapa']);

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
     * @param  array<string, array<string, int>>  $byEtapa
     * @return array<string, array<string, int|float|null>>
     */
    private function sortDescEtapaAge(array $byEtapa): array
    {
        uasort($byEtapa, static fn (array $a, array $b): int => ($b['eligible'] ?? 0) <=> ($a['eligible'] ?? 0));
        $out = [];
        $i = 0;
        foreach ($byEtapa as $label => $row) {
            if ($i >= 25) {
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
    private function headersMatchNee(array $headerKeys): bool
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
        $tags = [];
        foreach ($row as $key => $value) {
            $v = trim((string) $value);
            if ($v === '' || in_array(mb_strtolower($v), ['não', 'nao', 'n', '0', 'false', 'não possui', 'nao possui'], true)) {
                continue;
            }
            $k = (string) $key;
            if (preg_match('/transtorno\s+do\s+espectro|autis|\btea\b/i', $k) === 1) {
                $tags['TEA'] = 'TEA';
            } elseif (preg_match('/altas\s*habil/i', $k) === 1) {
                $tags['AH'] = 'AH';
            } elseif (preg_match('/defici/i', $k) === 1) {
                $tags['Deficiência'] = __('Deficiência');
            } elseif (preg_match('/\bnee\b|\baee\b/i', $k) === 1 && ! preg_match('/turma|tipo/i', $k)) {
                $tags['NEE'] = 'NEE';
            }
        }

        return array_values($tags);
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
        if (
            str_contains($t, 'atividade complementar')
            || preg_match('/\bac\b/u', $t) === 1
            || $t === 'ac'
        ) {
            return self::BUCKET_AC;
        }
        if (str_contains($t, 'curricular')) {
            return self::BUCKET_CURRICULAR;
        }

        return self::BUCKET_OUTRA;
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
    private function sortDesc(array $counts): array
    {
        arsort($counts, SORT_NUMERIC);

        return $counts;
    }
}
