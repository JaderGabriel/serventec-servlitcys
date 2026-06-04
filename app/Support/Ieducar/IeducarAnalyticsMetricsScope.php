<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Services\CityDataConnection;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;

/**
 * Métricas i-Educar partilhadas entre abas do painel (uma leitura por pedido/filtro).
 *
 * Registar com {@see bindForRequest()} no início do carregamento do analytics;
 * consultas via {@see MatriculaChartQueries::totalMatriculasAtivasFiltradas()} e
 * {@see MatriculaChartQueries::distorcaoIdadeSerieContagens()} reutilizam o cache quando o scope está activo.
 */
final class IeducarAnalyticsMetricsScope
{
    private static ?self $bound = null;

    private ?array $cache = null;

    public function __construct(
        private readonly CityDataConnection $cityData,
        private readonly City $city,
        private readonly IeducarFilterState $filters,
    ) {}

    public static function bindForRequest(
        CityDataConnection $cityData,
        City $city,
        IeducarFilterState $filters,
        bool $warm = true,
    ): self {
        $scope = new self($cityData, $city, $filters);
        if ($warm) {
            $scope->warm();
        }
        self::$bound = $scope;

        return $scope;
    }

    public static function resolve(): ?self
    {
        return self::$bound;
    }

    public static function forget(): void
    {
        self::$bound = null;
    }

    public function matches(City $city, IeducarFilterState $filters): bool
    {
        return (int) $city->getKey() === (int) $this->city->getKey()
            && $this->filtersSignature($filters) === $this->filtersSignature($this->filters);
    }

    /**
     * Pré-carrega matrículas activas e distorção (uma ida à base por pedido).
     */
    public function warm(): void
    {
        $this->ensureLoaded();
    }

    public function matriculasAtivas(): ?int
    {
        $this->ensureLoaded();
        $v = $this->cache['matriculas_ativas'] ?? null;

        return $v !== null ? (int) $v : null;
    }

    public function alunosDistintosAtivos(): ?int
    {
        $this->ensureLoaded();
        if (! ($this->cache['alunos_available'] ?? false)) {
            return null;
        }
        $v = $this->cache['alunos_distintos'] ?? null;

        return $v !== null ? (int) $v : null;
    }

    /**
     * @return array{matriculas: int, alunos: ?int, alunos_available: bool}
     */
    public function volumeCounts(): array
    {
        $this->ensureLoaded();

        return [
            'matriculas' => (int) ($this->cache['matriculas_ativas'] ?? 0),
            'alunos' => $this->alunosDistintosAtivos(),
            'alunos_available' => (bool) ($this->cache['alunos_available'] ?? false),
        ];
    }

    /**
     * @return array{
     *   com: int,
     *   sem: int,
     *   total: int,
     *   fonte: string,
     *   metodo?: string,
     *   cobertura_pct?: ?float,
     *   mecanismos?: list<array<string, mixed>>
     * }|null
     */
    public function distorcaoPack(): ?array
    {
        $this->ensureLoaded();
        $pack = $this->cache['distorcao_pack'] ?? null;

        return is_array($pack) ? $pack : null;
    }

    /**
     * KPI normalizado para cartões / impacto (denominador = matrículas com idade+série válidas).
     *
     * @return array{
     *   com: int,
     *   sem: int,
     *   total: int,
     *   pct: float,
     *   fonte: string,
     *   metodo: string,
     *   cobertura_pct: ?float
     * }|null
     */
    public function distorcaoKpi(): ?array
    {
        $this->ensureLoaded();
        $kpi = $this->cache['distorcao_kpi'] ?? null;

        return is_array($kpi) ? $kpi : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function distorcaoMecanismos(): array
    {
        $this->ensureLoaded();
        $rows = $this->cache['mecanismos'] ?? [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * Campos extra para {@see \App\Support\Dashboard\AnalyticsMunicipalityContext}.
     *
     * @return array<string, mixed>
     */
    public function toMunicipalityContextExtras(): array
    {
        $volume = $this->volumeCounts();
        $mat = $volume['matriculas'];
        $out = [
            'total_matriculas' => $mat > 0 ? $mat : null,
            'total_alunos_distintos' => ($volume['alunos_available'] && ($volume['alunos'] ?? 0) > 0)
                ? (int) $volume['alunos']
                : null,
        ];

        $kpi = $this->distorcaoKpi();
        if ($kpi !== null) {
            $out['distorcao_com'] = (int) $kpi['com'];
            $out['distorcao_pct'] = (float) $kpi['pct'];
            $out['distorcao_elegivel_total'] = (int) $kpi['total'];
            $out['distorcao_metodo'] = (string) ($kpi['metodo'] ?? '');
            $out['distorcao_cobertura_pct'] = $kpi['cobertura_pct'] ?? null;
        }

        return $out;
    }

    /**
     * @param  array{
     *   com: int,
     *   sem: int,
     *   total: int,
     *   fonte: string,
     *   metodo?: string,
     *   cobertura_pct?: ?float,
     *   mecanismos?: list<array<string, mixed>>
     * }|null  $pack
     * @return array{
     *   com: int,
     *   sem: int,
     *   total: int,
     *   pct: float,
     *   fonte: string,
     *   metodo: string,
     *   cobertura_pct: ?float
     * }|null
     */
    public static function normalizeDistorcaoKpi(?array $pack): ?array
    {
        if ($pack === null || (int) ($pack['total'] ?? 0) <= 0) {
            return null;
        }

        $tot = (int) $pack['total'];
        $com = (int) $pack['com'];

        return [
            'com' => $com,
            'sem' => (int) $pack['sem'],
            'total' => $tot,
            'pct' => round(100.0 * $com / $tot, 1),
            'fonte' => (string) ($pack['fonte'] ?? 'automatico'),
            'metodo' => (string) ($pack['metodo'] ?? ''),
            'cobertura_pct' => isset($pack['cobertura_pct']) ? (float) $pack['cobertura_pct'] : null,
        ];
    }

    private function ensureLoaded(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = $this->cityData->run($this->city, function (Connection $db): array {
            $volume = MatriculaVolumeCounts::count($db, $this->city, $this->filters);
            $matriculas = $volume['matriculas'];
            $pack = DistorcaoIdadeSerieEngine::contagens($db, $this->city, $this->filters);
            $mecanismos = is_array($pack['mecanismos'] ?? null) && $pack['mecanismos'] !== []
                ? $pack['mecanismos']
                : DistorcaoIdadeSerieEngine::apurarTodosMecanismos($db, $this->city, $this->filters);

            $analiticos = is_array($pack['analiticos'] ?? null)
                ? $pack['analiticos']
                : DistorcaoIdadeSerieEngine::analiticos($db, $this->city, $this->filters);

            return [
                'matriculas_ativas' => $matriculas,
                'alunos_distintos' => $volume['alunos'],
                'alunos_available' => $volume['alunos_available'],
                'distorcao_pack' => $pack,
                'distorcao_kpi' => self::normalizeDistorcaoKpi($pack),
                'mecanismos' => $mecanismos,
                'distorcao_analiticos' => $analiticos,
            ];
        });
    }

    /**
     * @return array{
     *   histograma_faixas: ?array<string, mixed>,
     *   histograma_serie: ?array<string, mixed>,
     *   histograma_escola: ?array<string, mixed>,
     *   situacao_cruzada: list<array<string, mixed>>
     * }
     */
    public function distorcaoAnaliticos(): array
    {
        $this->ensureLoaded();
        $a = $this->cache['distorcao_analiticos'] ?? [];

        return is_array($a) ? $a : [
            'histograma_faixas' => null,
            'histograma_serie' => null,
            'histograma_escola' => null,
            'situacao_cruzada' => [],
        ];
    }

    private function filtersSignature(IeducarFilterState $filters): string
    {
        $params = $filters->toQueryParamsWithCity((int) $this->city->getKey());
        ksort($params);

        return md5(json_encode($params));
    }
}
