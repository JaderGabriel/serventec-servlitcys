<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Funding\FundebExtratoFontePriority;
use App\Support\Ieducar\DiscrepanciesFundingImpact;
use App\Support\Ieducar\MatriculaChartQueries;
use Illuminate\Database\Connection;

/**
 * FIN-04: compara repasse observado (snapshots) com matrículas elegíveis no i-Educar.
 */
final class ProgramRepasseVsMatriculasService
{
    public function __construct(
        private MunicipalTransferSnapshotRepository $snapshots,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $programs  Saída de OtherFundingRepository::buildPrograms
     * @return list<array<string, mixed>>
     */
    public function enrichPrograms(
        Connection $db,
        City $city,
        IeducarFilterState $filters,
        array $programs,
        int $year,
    ): array {
        $totalMat = MatriculaChartQueries::totalMatriculasAtivasFiltradas($db, $city, $filters) ?? 0;
        $snapshots = $this->snapshots->forCityYear($city, $year);
        $repasseByProgram = [];
        foreach (FundebExtratoFontePriority::pickPrimaryPerProgram($snapshots) as $s) {
            $repasseByProgram[(string) $s->programa_id] = (float) $s->valor;
        }

        $out = [];
        foreach ($programs as $prog) {
            if (! is_array($prog)) {
                continue;
            }
            $id = (string) ($prog['id'] ?? '');
            $elegiveis = $this->countElegiveis($db, $city, $filters, $prog);
            $repasse = $this->resolveRepasse($id, $repasseByProgram);
            $repassePorAluno = ($repasse > 0 && $elegiveis > 0)
                ? round($repasse / $elegiveis, 2)
                : null;

            $prog['repasse_observado'] = $repasse > 0 ? [
                'valor' => $repasse,
                'valor_fmt' => DiscrepanciesFundingImpact::formatBrl($repasse),
                'elegiveis' => $elegiveis,
                'total_matriculas' => $totalMat,
                'repasse_por_aluno_fmt' => $repassePorAluno !== null
                    ? DiscrepanciesFundingImpact::formatBrl($repassePorAluno).__('/aluno indicativo')
                    : null,
                'nota' => $repasse > 0
                    ? __('Repasse deduplicado (uma fonte prioritária por programa). Não some com outras linhas nem com VAAF. Elegíveis = matrículas com campo preenchido no i-Educar.')
                    : __('Sem repasse importado para este programa — execute sincronização de transferências no admin.'),
            ] : null;
            $out[] = $prog;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $program
     */
    private function countElegiveis(Connection $db, City $city, IeducarFilterState $filters, array $program): int
    {
        $columns = is_array($program['detected_columns'] ?? null) ? $program['detected_columns'] : [];
        if ($columns === []) {
            return 0;
        }

        try {
            $mat = \App\Support\Ieducar\IeducarSchema::resolveTable('matricula', $city);
            $mAtivo = (string) config('ieducar.columns.matricula.ativo');
            $q = $db->table($mat.' as m');
            \App\Support\Ieducar\MatriculaAtivoFilter::apply($q, $db, 'm.'.$mAtivo, $city);
            \App\Support\Ieducar\MatriculaTurmaJoin::joinMatriculaToTurma($q, $db, $city, 'm');
            \App\Support\Ieducar\MatriculaTurmaJoin::applyPivotAtivoIfNeeded($q, $db, $city);
            \App\Support\Ieducar\MatriculaTurmaJoin::applyTurmaFiltersWhere($q, $db, $city, $filters, 't_filter');

            $q->where(static function ($sub) use ($columns): void {
                foreach ($columns as $col) {
                    $sub->orWhere(function ($inner) use ($col): void {
                        $inner->whereNotNull('m.'.$col)
                            ->where('m.'.$col, '!=', '')
                            ->where('m.'.$col, '!=', '0');
                    });
                }
            });

            return (int) $q->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<string, float>  $repasseByProgram
     */
    private function resolveRepasse(string $programId, array $repasseByProgram): float
    {
        if (isset($repasseByProgram[$programId])) {
            return (float) $repasseByProgram[$programId];
        }

        $aliases = match ($programId) {
            'pdde-qualidade' => ['pdde'],
            default => [],
        };
        foreach ($aliases as $alias) {
            if (isset($repasseByProgram[$alias])) {
                return (float) $repasseByProgram[$alias];
            }
        }

        return 0.0;
    }
}
