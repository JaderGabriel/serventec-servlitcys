<?php

namespace App\Support\Rx;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Services\Fundeb\FundebFndeVaatCsvService;
use App\Support\Dashboard\ChartPayload;
use App\Support\Fundeb\FundebFndePortariaCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Painel RX — portaria FUNDEB consolidada (distinto do cadastro em andamento).
 */
final class RxFundebPortariaChart
{
    /**
     * @param  Collection<int, City>  $cities
     * @param  array<int, array<string, mixed>>  $rxRowsByCityId
     * @return array<string, mixed>
     */
    public static function buildForCities(Collection $cities, int $exercicio, array $rxRowsByCityId = []): array
    {
        $exercicio = self::resolveFundebExercicio($exercicio);
        $portaria = FundebFndePortariaCatalog::activePublication($exercicio);
        $portariaMeta = FundebFndePortariaCatalog::metaForExercicio($exercicio);
        $floors = FundebFndePortariaCatalog::nationalFloors($exercicio);
        $national = FundebFndePortariaCatalog::nationalTotals($exercicio);
        $vaatMin = $floors['vaat_min'] ?? null;

        $receitaSvc = app(FundebFndeReceitaCsvService::class);
        $vaatSvc = app(FundebFndeVaatCsvService::class);

        $municipios = [];
        foreach ($cities as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            if ($ibge === null) {
                continue;
            }

            $rxRow = $rxRowsByCityId[(int) $city->id] ?? null;
            $row = self::municipalityRow($city, $ibge, $exercicio, $rxRow, $receitaSvc, $vaatSvc, $vaatMin);
            if ($row !== null) {
                $municipios[] = $row;
            }
        }

        usort($municipios, static fn (array $a, array $b): int => strcasecmp((string) $a['city_name'], (string) $b['city_name']));

        if ($municipios === []) {
            return self::emptyPayload($exercicio, $portariaMeta, $portaria);
        }

        $chartRows = array_values(array_filter(
            $municipios,
            static fn (array $r): bool => (float) ($r['compl_total'] ?? 0) > 0,
        ));
        usort($chartRows, static fn (array $a, array $b): int => ($b['compl_total'] <=> $a['compl_total']) ?: strcasecmp((string) $a['city_name'], (string) $b['city_name']));

        $portariaLabel = self::portariaLabel($portariaMeta, $portaria);
        $totalMun = count($municipios);
        $comVaar = count(array_filter($municipios, static fn (array $r): bool => $r['compl_vaar'] !== null && (float) $r['compl_vaar'] > 0));
        $semVaat = count(array_filter($municipios, static fn (array $r): bool => $r['compl_vaat'] === null || (float) ($r['compl_vaat'] ?? 0) <= 0));
        $vaatPisoDb = count(array_filter($municipios, static fn (array $r): bool => (bool) ($r['vaat_piso_erro'] ?? false)));
        $ieducarZero = count(array_filter($municipios, static fn (array $r): bool => (int) ($r['ieducar_matriculas'] ?? 0) === 0));

        $sumReceita = array_sum(array_map(static fn (array $r): float => (float) ($r['receita_total'] ?? 0), $municipios));
        $sumVaaf = array_sum(array_map(static fn (array $r): float => (float) ($r['compl_vaaf'] ?? 0), $municipios));
        $sumVaat = array_sum(array_map(static fn (array $r): float => (float) ($r['compl_vaat'] ?? 0), $municipios));
        $sumVaar = array_sum(array_map(static fn (array $r): float => (float) ($r['compl_vaar'] ?? 0), $municipios));

        $withVaar = array_values(array_filter($municipios, static fn (array $r): bool => $r['compl_vaar'] !== null && (float) $r['compl_vaar'] > 0));
        $withoutVaar = array_values(array_filter($municipios, static fn (array $r): bool => $r['compl_vaar'] === null || (float) ($r['compl_vaar'] ?? 0) <= 0));

        $ibgeWarnings = array_values(array_filter($municipios, static fn (array $r): bool => (bool) ($r['ibge_nome_divergente'] ?? false)));

        return [
            'available' => true,
            'exercicio' => $exercicio,
            'portaria' => $portariaMeta,
            'portaria_label' => $portariaLabel,
            'municipios_total' => $totalMun,
            'municipios_com_dados' => count($chartRows),
            'national' => [
                'vaaf_min' => $floors['vaaf_min'] ?? null,
                'vaat_min' => $vaatMin,
                'receita_vinculada' => $national['receita_vinculada'] ?? null,
                'complementacao_uniao' => $national['complementacao_uniao'] ?? null,
            ],
            'municipal_stats' => [
                'receita_total' => $sumReceita,
                'com_vaar' => $comVaar,
                'sem_vaat' => $semVaat,
                'vaat_piso_db' => $vaatPisoDb,
                'ieducar_zero' => $ieducarZero,
            ],
            'ibge_table' => $municipios,
            'ibge_warnings' => $ibgeWarnings,
            'totals_cards' => [
                'compl_vaaf' => $sumVaaf,
                'compl_vaat' => $sumVaat,
                'compl_vaar' => $sumVaar,
                'compl_total' => $sumVaaf + $sumVaat + $sumVaar,
                'with_vaar' => array_map(static fn (array $r): string => (string) $r['label'], $withVaar),
                'without_vaar' => array_map(static fn (array $r): string => (string) $r['label'], $withoutVaar),
            ],
            'vaat_compare' => $municipios,
            'gaps_table' => $municipios,
            'danger_callout' => [
                'show' => $ieducarZero > 0 && $totalMun > 0,
                'ieducar_zero' => $ieducarZero,
                'total' => $totalMun,
            ],
            'chart' => self::buildChart($chartRows, $exercicio, $portariaLabel),
            'rows' => $chartRows,
            'totals' => [
                'compl_vaaf' => $sumVaaf,
                'compl_vaat' => $sumVaat,
                'compl_vaar' => $sumVaar,
                'compl_total' => $sumVaaf + $sumVaat + $sumVaar,
            ],
        ];
    }

    public static function resolveFundebExercicio(int $vigenteYear): int
    {
        $configured = (int) config('rx.fundeb_portaria_exercicio', 0);

        return $configured > 0 ? $configured : $vigenteYear;
    }

    public static function formatBrl(?float $value, bool $compact = false): string
    {
        if ($value === null || ! is_finite($value)) {
            return '—';
        }

        if ($compact && abs($value) >= 1_000_000_000) {
            return 'R$ '.number_format($value / 1_000_000_000, 2, ',', '.').' bi';
        }

        if ($compact && abs($value) >= 1_000_000) {
            return 'R$ '.number_format($value / 1_000_000, 2, ',', '.').' mi';
        }

        return 'R$ '.number_format($value, 2, ',', '.');
    }

    public static function formatPerPupil(?float $value): string
    {
        if ($value === null || ! is_finite($value)) {
            return '—';
        }

        return 'R$ '.number_format($value, 2, ',', '.');
    }

    public static function formatInt(?int $value): string
    {
        return $value === null ? '—' : number_format($value, 0, ',', '.');
    }

    public static function formatYesNo(?float $value): string
    {
        return $value !== null && $value > 0 ? __('Sim') : __('Não');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return ?array<string, mixed>
     */
    private static function buildChart(array $rows, int $exercicio, string $portariaLabel): ?array
    {
        if ($rows === []) {
            return null;
        }

        $labels = array_map(
            static fn (array $r): string => self::chartAxisLabel((string) ($r['city_name'] ?? $r['label'] ?? '')),
            $rows,
        );
        $toMillions = static fn (?float $v): float => round(max(0.0, (float) ($v ?? 0)) / 1_000_000, 2);

        $count = count($labels);
        $panelHeight = match (true) {
            $count > 16 => 'xl',
            $count > 10 => 'lg',
            default => 'md',
        };

        $chart = ChartPayload::barStacked(
            __('Complementações previstas por município'),
            __('Milhões de R$'),
            $labels,
            [
                ['label' => __('Compl. VAAF'), 'data' => array_map(static fn (array $r): float => $toMillions($r['compl_vaaf']), $rows)],
                ['label' => __('Compl. VAAT'), 'data' => array_map(static fn (array $r): float => $toMillions($r['compl_vaat']), $rows)],
                ['label' => __('Compl. VAAR'), 'data' => array_map(static fn (array $r): float => $toMillions($r['compl_vaar']), $rows)],
            ],
        );

        $chart['subtitle'] = __('Exercício FUNDEB :ano · passe o rato sobre cada município para ver o nome e os valores VAAF, VAAT, VAAR e total em R$.', [
            'ano' => (string) $exercicio,
        ]);
        $chart['footnote'] = __('Fonte: CSV receita FNDE — :portaria.', [
            'portaria' => $portariaLabel,
        ]);
        $chart['options'] = array_merge($chart['options'] ?? [], [
            'valueFormat' => 'brl_millions',
            'showAllCategoryTicks' => true,
            'panelHeight' => $panelHeight,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
                'axis' => 'x',
            ],
            'hover' => [
                'mode' => 'index',
                'intersect' => false,
                'axis' => 'x',
            ],
            'plugins' => [
                'datalabels' => [
                    'display' => false,
                ],
            ],
            'layout' => [
                'padding' => [
                    'left' => 4,
                    'right' => 8,
                    'top' => 12,
                    'bottom' => 28,
                ],
            ],
        ]);

        return $chart;
    }

    /**
     * @param  ?array<string, mixed>  $rxRow
     * @return ?array<string, mixed>
     */
    private static function municipalityRow(
        City $city,
        string $ibge,
        int $exercicio,
        ?array $rxRow,
        FundebFndeReceitaCsvService $receitaSvc,
        FundebFndeVaatCsvService $vaatSvc,
        ?float $vaatMin,
    ): ?array {
        $ref = FundebMunicipioReference::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $exercicio)
            ->first();

        $receitaCsv = $receitaSvc->rowForIbge($ibge, $exercicio);
        $vaatCsv = $vaatSvc->rowForIbge($ibge, $exercicio);
        $meta = is_array($ref?->meta) ? $ref->meta : [];

        if ($ref === null && $receitaCsv === null && $vaatCsv === null) {
            return null;
        }

        $nomeOficial = trim((string) ($meta['nome_oficial_fnde'] ?? ($receitaCsv['entidade'] ?? '')));
        $receitaTotal = $ref?->receita_total !== null
            ? (float) $ref->receita_total
            : (isset($receitaCsv['total_receita']) ? (float) $receitaCsv['total_receita'] : null);

        $complVaaf = $ref?->complementacao_vaaf !== null
            ? (float) $ref->complementacao_vaaf
            : (isset($receitaCsv['complementacao_vaaf']) ? (float) $receitaCsv['complementacao_vaaf'] : null);
        $complVaat = $ref?->complementacao_vaat !== null
            ? (float) $ref->complementacao_vaat
            : (isset($receitaCsv['complementacao_vaat']) ? (float) $receitaCsv['complementacao_vaat'] : null);
        $complVaar = $ref?->complementacao_vaar !== null
            ? (float) $ref->complementacao_vaar
            : (isset($receitaCsv['complementacao_vaar']) ? (float) $receitaCsv['complementacao_vaar'] : null);

        $vaatAntes = isset($meta['vaat_antes']) && is_numeric($meta['vaat_antes'])
            ? (float) $meta['vaat_antes']
            : ($vaatCsv['vaat_antes'] ?? null);
        $vaatComCompl = isset($meta['vaat_com_compl']) && is_numeric($meta['vaat_com_compl'])
            ? (float) $meta['vaat_com_compl']
            : ($vaatCsv['vaat_com_compl'] ?? null);
        $vaatDb = $ref?->vaat !== null ? (float) $ref->vaat : null;
        $vaafDb = $ref?->vaaf !== null ? (float) $ref->vaaf : null;
        $ieiPct = trim((string) ($meta['iei_pct'] ?? ($vaatCsv['iei_pct'] ?? '')));

        $ieducarMat = (int) ($rxRow['matriculas_vigente'] ?? 0);
        if ($ieducarMat <= 0 && $ref?->matriculas_base !== null && str_contains((string) $ref->matriculas_fonte, 'ieducar')) {
            $ieducarMat = (int) $ref->matriculas_base;
        }

        $censoMat = null;
        if ($ref?->matriculas_base !== null && str_contains((string) $ref->matriculas_fonte, 'censo')) {
            $censoMat = (int) $ref->matriculas_base;
        } elseif ($ieducarMat <= 0 && $ref?->matriculas_base !== null) {
            $censoMat = (int) $ref->matriculas_base;
        }

        $vaatPisoErro = $vaatMin !== null
            && $vaatDb !== null
            && abs($vaatDb - $vaatMin) < 0.02
            && $vaatAntes !== null
            && $vaatAntes < $vaatMin - 0.02;

        $gaps = self::detectGaps($complVaat, $complVaar, $vaatPisoErro, $ieducarMat, $censoMat, $vaatMin, $vaatAntes, $vaatDb);

        $cityName = (string) $city->name;
        $ibgeDivergente = $nomeOficial !== '' && ! self::namesRoughlyMatch($cityName, $nomeOficial);

        $complTotal = ($complVaaf ?? 0) + ($complVaat ?? 0) + ($complVaar ?? 0);

        return [
            'city_id' => (int) $city->id,
            'city_name' => $cityName,
            'uf' => (string) $city->uf,
            'ibge' => $ibge,
            'label' => self::shortLabel($cityName),
            'nome_oficial' => $nomeOficial !== '' ? $nomeOficial : null,
            'receita_total' => $receitaTotal,
            'compl_vaaf' => $complVaaf,
            'compl_vaat' => $complVaat,
            'compl_vaar' => $complVaar,
            'compl_total' => $complTotal,
            'vaat_antes' => $vaatAntes,
            'vaat_com_compl' => $vaatComCompl,
            'vaat_db' => $vaatDb,
            'vaaf_db' => $vaafDb,
            'iei_pct' => $ieiPct !== '' ? $ieiPct : null,
            'censo_matriculas' => $censoMat,
            'ieducar_matriculas' => $ieducarMat,
            'vaat_piso_erro' => $vaatPisoErro,
            'ibge_nome_divergente' => $ibgeDivergente,
            'gaps' => $gaps,
            'gaps_label' => implode(' · ', $gaps),
            'vaat_diagnostico' => $vaatPisoErro
                ? __('Piso gravado (deveria ser municipal)')
                : __('OK'),
        ];
    }

    /**
     * @return list<string>
     */
    private static function detectGaps(
        ?float $complVaat,
        ?float $complVaar,
        bool $vaatPisoErro,
        int $ieducarMat,
        ?int $censoMat,
        ?float $vaatMin,
        ?float $vaatAntes,
        ?float $vaatDb,
    ): array {
        $gaps = [];

        if ($vaatPisoErro) {
            $gaps[] = __('VAAT DB = piso nacional (municipal não gravado)');
        }
        if ($complVaat === null || $complVaat <= 0) {
            $gaps[] = __('Sem complementação VAAT');
        }
        if ($complVaar === null || $complVaar <= 0) {
            $gaps[] = __('Sem VAAR');
        }
        if ($ieducarMat <= 0 && ($censoMat ?? 0) > 0) {
            $gaps[] = __('VAAF via Censo (i-Educar=0)');
        }

        return $gaps;
    }

    private static function namesRoughlyMatch(string $systemName, string $officialName): bool
    {
        $norm = static function (string $s): string {
            $s = mb_strtoupper($s, 'UTF-8');
            $s = preg_replace('/^\d+\s*-\s*/', '', $s) ?? $s;
            $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;

            return preg_replace('/[^A-Z0-9]/', '', $s) ?? '';
        };

        $a = $norm($systemName);
        $b = $norm($officialName);
        if ($a === '' || $b === '') {
            return true;
        }

        return str_contains($a, $b) || str_contains($b, $a);
    }

    /**
     * @param  ?array<string, mixed>  $portaria
     * @return array<string, mixed>
     */
    private static function emptyPayload(int $exercicio, array $portariaMeta, ?array $portaria): array
    {
        return [
            'available' => false,
            'exercicio' => $exercicio,
            'portaria' => $portariaMeta,
            'portaria_label' => self::portariaLabel($portariaMeta, $portaria),
            'chart' => null,
            'municipios_com_dados' => 0,
        ];
    }

    /**
     * @param  ?array<string, mixed>  $portaria
     */
    private static function portariaLabel(array $portariaMeta, ?array $portaria): string
    {
        $label = trim((string) ($portariaMeta['portaria_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $numero = trim((string) ($portariaMeta['portaria_numero'] ?? ($portaria['numero'] ?? '')));
        $data = trim((string) ($portariaMeta['portaria_data'] ?? ($portaria['data'] ?? '')));
        if ($numero !== '' && $data !== '') {
            return $numero.' ('.$data.')';
        }

        return $numero !== '' ? $numero : __('portaria FNDE');
    }

    private static function shortLabel(string $name): string
    {
        return Str::limit(self::chartAxisLabel($name), 14, '…');
    }

    /** Nome completo no eixo do gráfico e no tooltip (sem prefixo numérico do cadastro). */
    private static function chartAxisLabel(string $name): string
    {
        $name = preg_replace('/^\d+\s*-\s*/', '', trim($name)) ?? trim($name);

        return $name !== '' ? $name : '—';
    }
}
