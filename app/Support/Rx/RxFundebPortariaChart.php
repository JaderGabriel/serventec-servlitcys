<?php

namespace App\Support\Rx;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Services\Fundeb\FundebFndeReceitaCsvService;
use App\Support\Dashboard\ChartPayload;
use App\Support\Fundeb\FundebFndePortariaCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Gráfico RX — complementações VAAF/VAAT/VAAR da portaria FNDE (dados consolidados).
 *
 * Distinto do cadastro «em andamento» do painel RX: usa CSV receita / referências importadas.
 */
final class RxFundebPortariaChart
{
    /**
     * @param  Collection<int, City>  $cities
     * @return array<string, mixed>
     */
    public static function buildForCities(Collection $cities, int $exercicio): array
    {
        $exercicio = self::resolveFundebExercicio($exercicio);
        $portaria = FundebFndePortariaCatalog::activePublication($exercicio);
        $portariaMeta = FundebFndePortariaCatalog::metaForExercicio($exercicio);
        $receitaSvc = app(FundebFndeReceitaCsvService::class);

        $rows = [];
        foreach ($cities as $city) {
            $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
            if ($ibge === null) {
                continue;
            }

            $compl = self::complementacoesForIbge($ibge, $exercicio, $receitaSvc);
            if ($compl === null) {
                continue;
            }

            $total = ($compl['vaaf'] ?? 0.0) + ($compl['vaat'] ?? 0.0) + ($compl['vaar'] ?? 0.0);
            if ($total <= 0) {
                continue;
            }

            $rows[] = [
                'city_id' => (int) $city->id,
                'city_name' => (string) $city->name,
                'uf' => (string) $city->uf,
                'ibge' => $ibge,
                'label' => self::shortLabel((string) $city->name),
                'compl_vaaf' => $compl['vaaf'],
                'compl_vaat' => $compl['vaat'],
                'compl_vaar' => $compl['vaar'],
                'total' => $total,
                'fonte' => $compl['fonte'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['total'] <=> $a['total']) ?: strcasecmp($a['city_name'], $b['city_name']));

        if ($rows === []) {
            return [
                'available' => false,
                'exercicio' => $exercicio,
                'portaria' => $portariaMeta,
                'portaria_label' => self::portariaLabel($portariaMeta, $portaria),
                'chart' => null,
                'municipios_com_dados' => 0,
            ];
        }

        $labels = array_map(static fn (array $r): string => (string) $r['label'], $rows);
        $toMillions = static fn (?float $v): float => round(max(0.0, (float) ($v ?? 0)) / 1_000_000, 2);

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

        $portariaLabel = self::portariaLabel($portariaMeta, $portaria);
        $chart['subtitle'] = __('Exercício FUNDEB :ano · valores consolidados da portaria (não refletem o cadastro em andamento do RX).', [
            'ano' => (string) $exercicio,
        ]);
        $chart['footnote'] = __('Fonte: CSV receita FNDE — :portaria. Eixo Y: milhões de R$. Cadastro RX (:rx) mede volume digitado no i-Educar; impacto financeiro oficial segue a portaria.', [
            'portaria' => $portariaLabel,
            'rx' => (string) $exercicio,
        ]);

        $sumVaaf = array_sum(array_map(static fn (array $r): float => (float) $r['compl_vaaf'], $rows));
        $sumVaat = array_sum(array_map(static fn (array $r): float => (float) $r['compl_vaat'], $rows));
        $sumVaar = array_sum(array_map(static fn (array $r): float => (float) $r['compl_vaar'], $rows));

        return [
            'available' => true,
            'exercicio' => $exercicio,
            'portaria' => $portariaMeta,
            'portaria_label' => $portariaLabel,
            'chart' => $chart,
            'municipios_com_dados' => count($rows),
            'totals' => [
                'compl_vaaf' => $sumVaaf,
                'compl_vaat' => $sumVaat,
                'compl_vaar' => $sumVaar,
                'compl_total' => $sumVaaf + $sumVaat + $sumVaar,
            ],
            'rows' => $rows,
        ];
    }

    public static function resolveFundebExercicio(int $vigenteYear): int
    {
        $configured = (int) config('rx.fundeb_portaria_exercicio', 0);

        return $configured > 0 ? $configured : $vigenteYear;
    }

    /**
     * @return ?array{vaaf: ?float, vaat: ?float, vaar: ?float, fonte: string}
     */
    private static function complementacoesForIbge(string $ibge, int $exercicio, FundebFndeReceitaCsvService $receitaSvc): ?array
    {
        $ref = FundebMunicipioReference::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $exercicio)
            ->first();

        if ($ref !== null) {
            return [
                'vaaf' => $ref->complementacao_vaaf !== null ? (float) $ref->complementacao_vaaf : null,
                'vaat' => $ref->complementacao_vaat !== null ? (float) $ref->complementacao_vaat : null,
                'vaar' => $ref->complementacao_vaar !== null ? (float) $ref->complementacao_vaar : null,
                'fonte' => 'fundeb_municipio_references',
            ];
        }

        $csv = $receitaSvc->rowForIbge($ibge, $exercicio);
        if ($csv === null) {
            return null;
        }

        return [
            'vaaf' => isset($csv['complementacao_vaaf']) ? (float) $csv['complementacao_vaaf'] : null,
            'vaat' => isset($csv['complementacao_vaat']) ? (float) $csv['complementacao_vaat'] : null,
            'vaar' => isset($csv['complementacao_vaar']) ? (float) $csv['complementacao_vaar'] : null,
            'fonte' => 'fnde_csv_receita',
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
        $name = preg_replace('/^\d+\s*-\s*/', '', trim($name)) ?? trim($name);
        $parts = preg_split('/\s+/', $name) ?: [$name];
        $first = (string) ($parts[0] ?? $name);

        return Str::limit($first, 14, '…');
    }
}
