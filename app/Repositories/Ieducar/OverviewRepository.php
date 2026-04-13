<?php

namespace App\Repositories\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarSchema;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;

class OverviewRepository
{
    public function __construct(
        private CityDataConnection $cityData
    ) {}

    /**
     * @return array{
     *   kpis: ?array{escolas: ?int, turmas: ?int, matriculas: ?int},
     *   charts: list<array{type: string, title: string, labels: list<string>, datasets: list<array<string, mixed>>}>,
     *   filter_note: ?string,
     *   error: ?string
     * }
     */
    public function summary(?City $city, IeducarFilterState $filters): array
    {
        if ($city === null) {
            return ['kpis' => null, 'charts' => [], 'filter_note' => null, 'error' => null];
        }

        try {
            return $this->cityData->run($city, function (Connection $db) use ($city, $filters) {
                $kpis = [
                    'escolas' => $this->countEscolas($db, $city, $filters),
                    'turmas' => $this->countTurmas($db, $city, $filters),
                    'matriculas' => $this->countMatriculas($db, $city, $filters),
                ];

                $charts = [];
                if ($kpis['escolas'] !== null || $kpis['turmas'] !== null || $kpis['matriculas'] !== null) {
                    $charts[] = ChartPayload::bar(
                        __('Totais (visão geral)'),
                        __('Quantidade'),
                        [__('Escolas'), __('Turmas'), __('Matrículas')],
                        [
                            (float) ($kpis['escolas'] ?? 0),
                            (float) ($kpis['turmas'] ?? 0),
                            (float) ($kpis['matriculas'] ?? 0),
                        ]
                    );
                }

                $note = $this->filterNote($filters);

                return [
                    'kpis' => $kpis,
                    'charts' => $charts,
                    'filter_note' => $note,
                    'error' => null,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'kpis' => null,
                'charts' => [],
                'filter_note' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function filterNote(IeducarFilterState $filters): ?string
    {
        $has = $filters->ano_letivo !== null
            || $filters->escola_id
            || $filters->curso_id
            || $filters->serie_id
            || $filters->segmento_id
            || $filters->etapa_id
            || $filters->turno_id;

        if (! $has) {
            return null;
        }

        return __('Os totais acima aplicam os filtros selecionados (por tabela turma / escola quando disponível na base). O segmento pode exigir colunas adicionais no iEducar.');
    }

    private function countEscolas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $table = IeducarSchema::resolveTable('escola', $city);
            $id = (string) config('ieducar.columns.escola.id');
            $q = $db->table($table);
            if ($filters->escola_id) {
                $q->where($id, $filters->escola_id);
            }

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    private function countTurmas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $table = IeducarSchema::resolveTable('turma', $city);
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $serie = (string) config('ieducar.columns.turma.serie');
            $turno = (string) config('ieducar.columns.turma.turno');

            $q = $db->table($table);
            if ($filters->ano_letivo !== null && $year !== '') {
                $q->where($year, $filters->ano_letivo);
            }
            if ($filters->escola_id && $escola !== '') {
                $q->where($escola, $filters->escola_id);
            }
            if ($filters->curso_id && $curso !== '') {
                $q->where($curso, $filters->curso_id);
            }
            $serieVal = $filters->serie_id ?: $filters->etapa_id;
            if ($serieVal && $serie !== '') {
                $q->where($serie, $serieVal);
            }
            if ($filters->turno_id && $turno !== '') {
                $q->where($turno, $filters->turno_id);
            }

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }

    private function countMatriculas(Connection $db, City $city, IeducarFilterState $filters): ?int
    {
        try {
            $mat = IeducarSchema::resolveTable('matricula', $city);
            $mTurma = (string) config('ieducar.columns.matricula.turma');
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');

            $needsTurma = $filters->ano_letivo !== null
                || $filters->escola_id
                || $filters->curso_id
                || $filters->serie_id
                || $filters->etapa_id
                || $filters->turno_id;

            if (! $needsTurma) {
                $q = $db->table($mat);
                if ($mAtivo !== '') {
                    $q->whereIn($mAtivo, [1, '1', true, 't', 'true']);
                }

                return (int) $q->count();
            }

            $turma = IeducarSchema::resolveTable('turma', $city);
            $tId = (string) config('ieducar.columns.turma.id');
            $year = (string) config('ieducar.columns.turma.year');
            $escola = (string) config('ieducar.columns.turma.escola');
            $curso = (string) config('ieducar.columns.turma.curso');
            $serie = (string) config('ieducar.columns.turma.serie');
            $turno = (string) config('ieducar.columns.turma.turno');

            $q = $db->table($mat.' as m')->join($turma.' as t', 'm.'.$mTurma, '=', 't.'.$tId);
            if ($mAtivo !== '') {
                $q->whereIn('m.'.$mAtivo, [1, '1', true, 't', 'true']);
            }
            if ($filters->ano_letivo !== null && $year !== '') {
                $q->where('t.'.$year, $filters->ano_letivo);
            }
            if ($filters->escola_id && $escola !== '') {
                $q->where('t.'.$escola, $filters->escola_id);
            }
            if ($filters->curso_id && $curso !== '') {
                $q->where('t.'.$curso, $filters->curso_id);
            }
            $serieVal = $filters->serie_id ?: $filters->etapa_id;
            if ($serieVal && $serie !== '') {
                $q->where('t.'.$serie, $serieVal);
            }
            if ($filters->turno_id && $turno !== '') {
                $q->where('t.'.$turno, $filters->turno_id);
            }

            return (int) $q->count();
        } catch (QueryException|\InvalidArgumentException) {
            return null;
        }
    }
}
