<?php

namespace App\Services\Rx;

use App\Models\User;
use App\Support\Auth\UserCityAccess;
use App\Support\Pulse\PulseOperationRecorder;
use App\Support\Rx\RxCensoDeadline;
use App\Support\Rx\RxCityMetricsCollector;
use App\Support\Rx\RxColumnHelp;
use App\Support\Rx\RxEducacensoToolkit;
use App\Support\Rx\RxFundebPortariaChart;

/**
 * Painel RX: todas as cidades visíveis ao utilizador, ano vigente vs anterior.
 */
final class RxOverviewService
{
    public function __construct(
        private RxCityMetricsCollector $collector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        return PulseOperationRecorder::measure('rx:overview', fn (): array => $this->buildOverview($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverview(User $user): array
    {
        $vigenteYear = (int) config('rx.vigente_year', (int) date('Y'));
        $anteriorYear = $vigenteYear - 1;
        $deadline = RxCensoDeadline::forYear($vigenteYear);

        $cities = UserCityAccess::citiesQuery($user)->get();
        $rows = [];
        $errors = 0;
        $connectionErrors = 0;
        $queryErrors = 0;
        $partialCount = 0;
        $okCount = 0;

        /** @var \App\Models\City $city */
        foreach ($cities as $city) {
            if (! $user->hasCityAccess($city)) {
                continue;
            }
            $row = $this->collector->collect($city, $vigenteYear);
            $rows[] = $row;
            $codigo = (string) ($row['situacao_codigo'] ?? '');

            if ($row['ok'] ?? false) {
                $okCount++;
                if ($codigo === 'parcial') {
                    $partialCount++;
                }
            } elseif (filled($row['error'] ?? null)) {
                $errors++;
                if ($codigo === 'conexao' || ($row['conexao_ok'] ?? null) === false) {
                    $connectionErrors++;
                } else {
                    $queryErrors++;
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $pendA = (int) ($a['registros_restantes'] ?? 0);
            $pendB = (int) ($b['registros_restantes'] ?? 0);
            if ($pendA !== $pendB) {
                return $pendB <=> $pendA;
            }

            return strcasecmp((string) ($a['city_name'] ?? ''), (string) ($b['city_name'] ?? ''));
        });

        $totals = $this->aggregateTotals($rows);
        $semaphoreSummary = $this->aggregateSemaphore($rows);
        $rxRowsByCity = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['city_id'] ?? 0);
            if ($cid > 0) {
                $rxRowsByCity[$cid] = $row;
            }
        }
        $fundebPortaria = RxFundebPortariaChart::buildForCities($cities, $vigenteYear, $rxRowsByCity);

        return [
            'vigente_ano' => $vigenteYear,
            'anterior_ano' => $anteriorYear,
            'deadline' => $deadline,
            'educacenso_toolkit' => RxEducacensoToolkit::forYear($vigenteYear),
            'cities_total' => count($rows),
            'cities_ok' => $okCount,
            'cities_error' => $errors,
            'cities_connection_error' => $connectionErrors,
            'cities_query_error' => $queryErrors,
            'cities_partial' => $partialCount,
            'rows' => $rows,
            'totals' => $totals,
            'semaphore_summary' => $semaphoreSummary,
            'column_help' => RxColumnHelp::columns($vigenteYear, $anteriorYear),
            'meta_pct_per_salto' => (float) config('rx.meta_pct_per_salto', 5),
            'scope_label' => $this->scopeLabel($user),
            'fundeb_portaria' => $fundebPortaria,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{green: int, yellow: int, red: int, neutral: int, error: int}
     */
    private function aggregateSemaphore(array $rows): array
    {
        $out = ['green' => 0, 'yellow' => 0, 'red' => 0, 'neutral' => 0, 'error' => 0];
        foreach ($rows as $row) {
            $st = (string) ($row['semaforo'] ?? 'neutral');
            if (! array_key_exists($st, $out)) {
                $st = 'neutral';
            }
            $out[$st]++;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int|float|null>
     */
    private function aggregateTotals(array $rows): array
    {
        $okRows = array_filter($rows, static fn (array $r): bool => (bool) ($r['ok'] ?? false));

        $sum = static function (string $key) use ($okRows): int {
            return array_sum(array_map(static fn (array $r): int => (int) ($r[$key] ?? 0), $okRows));
        };

        $matV = $sum('matriculas_vigente');
        $matA = $sum('matriculas_anterior');
        $pendentesCenso = array_sum(array_map(
            static fn (array $r): int => (int) (($r['censo']['pendentes'] ?? 0)),
            $okRows
        ));
        $escolasCenso = array_sum(array_map(
            static fn (array $r): int => (int) (($r['censo']['total_escolas'] ?? 0)),
            $okRows
        ));
        $concluidasCenso = array_sum(array_map(
            static function (array $r): int {
                $c = $r['censo'] ?? [];

                return (int) ($c['exportadas'] ?? 0) + (int) ($c['fechadas'] ?? 0);
            },
            $okRows
        ));

        return [
            'alunos_vigente' => $sum('alunos_vigente'),
            'alunos_anterior' => $sum('alunos_anterior'),
            'matriculas_vigente' => $matV,
            'matriculas_anterior' => $matA,
            'matriculas_delta' => $matV - $matA,
            'turmas_vigente' => $sum('turmas_vigente'),
            'enturmacoes_vigente' => $sum('enturmacoes_vigente'),
            'registros_restantes' => $sum('registros_restantes'),
            'horas_estimadas' => round(array_sum(array_map(
                static fn (array $r): float => (float) ($r['horas_estimadas'] ?? 0),
                $okRows
            )), 1),
            'escolas_censo' => $escolasCenso,
            'escolas_censo_concluidas' => $concluidasCenso,
            'escolas_censo_pendentes' => $pendentesCenso,
            'pct_censo_rede' => $escolasCenso > 0
                ? round(100.0 * $concluidasCenso / $escolasCenso, 1)
                : null,
        ];
    }

    private function scopeLabel(User $user): string
    {
        if ($user->isAdmin()) {
            return __('Todos os municípios activos com base i-Educar');
        }
        if ($user->isUsuário()) {
            return __('Todos os municípios activos com base i-Educar');
        }

        return __('Municípios vinculados à sua conta');
    }
}
