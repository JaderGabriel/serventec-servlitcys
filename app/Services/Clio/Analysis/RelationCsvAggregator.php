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

        foreach ($rows as $row) {
            $etapa = trim($csv->value($row, 'Etapa de ensino'));
            $agregada = trim($csv->value($row, 'Etapa Agregada'));
            $tipo = trim($csv->value($row, 'Tipo de turma'));
            $mediacao = trim($csv->value($row, 'Tipo de mediação'));

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
     *   without_turma: int
     * }
     */
    public function aggregateAlunos(array $rows, CsvReader $csv): array
    {
        $byEtapa = [];
        $withoutEtapa = 0;
        $withoutTurma = 0;

        foreach ($rows as $row) {
            $etapa = trim($csv->value($row, 'Etapa de ensino'));
            $turma = trim($csv->value($row, 'Código da turma'));

            if ($turma === '') {
                $withoutTurma++;
            }
            if ($etapa === '') {
                $withoutEtapa++;
                $etapa = __('Não informado');
            }

            $byEtapa[$etapa] = ($byEtapa[$etapa] ?? 0) + 1;
        }

        return [
            'total' => count($rows),
            'by_etapa_ensino' => $this->sortDesc($byEtapa),
            'without_etapa' => $withoutEtapa,
            'without_turma' => $withoutTurma,
        ];
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
