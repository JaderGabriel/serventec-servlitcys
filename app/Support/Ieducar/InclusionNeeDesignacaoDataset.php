<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Support\Dashboard\ChartPayload;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

/**
 * Fonte única para gráficos NEE por designação: mesma consulta i-Educar, vista agrupada (3 blocos) e detalhada (catálogo).
 */
final class InclusionNeeDesignacaoDataset
{
    /**
     * @return ?array{
     *   uses_fisica: bool,
     *   footnote: string,
     *   grupos: array{deficiencias: int, sindromes_tea: int, ne_altas_habilidades: int},
     *   catalog: list<array{label: string, value: float, kind: string, norm: string, grupo: string}>,
     *   matriculas_nee: int
     * }
     */
    public static function build(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $rows = InclusionDashboardQueries::getMatriculasPorDeficiencia($db, $city, $filters, null);
            $entries = InclusionEducacensoCatalog::mergedDeficienciaEntriesForChart($db, $city);

            if ($entries === [] && $rows->isEmpty()) {
                return null;
            }

            $maps = InclusionEducacensoCatalog::deficienciaCountMapsFromRows(
                $rows,
                static fn ($row) => (string) ($row->deficiencia ?? ''),
                static fn ($row) => (int) ($row->total ?? 0),
                static fn ($row) => (string) ($row->def_id ?? ''),
            );

            $catalog = self::buildCatalogRows($entries, $maps, $rows);
            $grupos = self::aggregateGruposFromCatalog($catalog);
            $matriculasNee = InclusionDashboardQueries::countMatriculasComNee($db, $city, $filters);

            $usesFisica = InclusionDashboardQueries::inclusionNeeUsesFisicaPath($db, $city);

            return [
                'uses_fisica' => $usesFisica,
                'footnote' => $usesFisica
                    ? __(
                        'Mesma origem nos dois gráficos: matrículas activas distintas por designação em cadastro.deficiencia (vínculo fisica_deficiencia). Os três grupos somam as barras do catálogo com a mesma classificação por palavras-chave; uma matrícula com vários vínculos pode aparecer em mais do que uma barra.'
                    )
                    : __(
                        'Mesma origem nos dois gráficos: matrículas activas por designação em aluno_deficiencia + cadastro.deficiencia. Os grupos somam as contagens do catálogo com a mesma classificação por palavras-chave.'
                    ),
                'grupos' => $grupos,
                'catalog' => $catalog,
                'matriculas_nee' => $matriculasNee,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    public static function chartGrupo(array $dataset, ?int $denominator = null): ?array
    {
        $g = $dataset['grupos'] ?? null;
        if (! is_array($g)) {
            return null;
        }

        $nDef = (int) ($g['deficiencias'] ?? 0);
        $nSin = (int) ($g['sindromes_tea'] ?? 0);
        $nNe = (int) ($g['ne_altas_habilidades'] ?? 0);
        if ($nDef + $nSin + $nNe <= 0) {
            return null;
        }

        $chart = ChartPayload::bar(
            __('Matrículas por grupo: deficiências, síndromes/TEA e NE (altas habilidades)'),
            __('Matrículas (vínculos por designação)'),
            [
                __('Deficiências (cadastro)'),
                __('Síndromes e TEA'),
                __('NE — altas habilidades / superdotação'),
            ],
            [(float) $nDef, (float) $nSin, (float) $nNe]
        );
        $chart['subtitle'] = (string) ($dataset['footnote'] ?? '');

        if ($denominator !== null && $denominator > 0) {
            $chart = InclusionDashboardQueries::attachMatriculaKpiTotalPublic($chart, $denominator, true);
        }

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    public static function chartCatalogo(array $dataset, ?int $denominator, bool $includeZeros): ?array
    {
        $catalog = $dataset['catalog'] ?? null;
        if (! is_array($catalog) || $catalog === []) {
            return null;
        }

        $rows = $catalog;
        if (! $includeZeros) {
            $rows = array_values(array_filter(
                $catalog,
                static fn (array $r): bool => (float) ($r['value'] ?? 0) > 0
            ));
            if ($rows === []) {
                return null;
            }
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => $b['value'] <=> $a['value']
                ?: strcmp((string) $a['label'], (string) $b['label'])
        );

        $series = InclusionEducacensoCatalog::neeCatalogChartSeries($rows);
        if ($series['labels'] === []) {
            return null;
        }

        $title = $includeZeros
            ? __('NEE — catálogo completo MEC e i-Educar (todas as opções)')
            : __('NEE — designações com matrículas (catálogo MEC / i-Educar)');

        $chart = ChartPayload::barHorizontal(
            $title,
            __('Matrículas distintas'),
            $series['labels'],
            $series['values']
        );
        $chart['datasets'][0]['backgroundColor'] = $series['colors'];
        $chart['datasets'][0]['borderColor'] = $series['colors'];
        $chart['subtitle'] = $includeZeros
            ? __(
                'Todas as opções do catálogo (valor 0 = sem vínculo no filtro). Agrupado e detalhado usam a mesma consulta. Cores: índigo = INEP/Censo · violeta = complementar · âmbar = só i-Educar.'
            )
            : __(
                'Apenas designações com matrícula no recorte. A soma pode exceder o total de matrículas NEE quando há vários vínculos no cadastro.'
            );
        $chart['footnote'] = (string) ($dataset['footnote'] ?? '');
        $chart['options'] = array_merge(
            is_array($chart['options'] ?? null) ? $chart['options'] : [],
            ['panelHeight' => $includeZeros ? 'xxl' : 'xl', 'skipHorizontalBarAutoHeight' => false]
        );
        $chart['catalog_include_zeros'] = $includeZeros;

        if ($denominator !== null && $denominator > 0) {
            $chart = InclusionDashboardQueries::attachMatriculaKpiTotalPublic(
                $chart,
                $denominator,
                true,
                __('Soma das barras (pode exceder o total por vínculos múltiplos)')
            );
        }

        return $chart;
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return ?array{
     *   deficiencias: list<array{nome: string, total: int}>,
     *   sindromes_tea: list<array{nome: string, total: int}>,
     *   ne_altas_habilidades: list<array{nome: string, total: int}>,
     *   totais_por_secao: array{deficiencias: int, sindromes_tea: int, ne_altas_habilidades: int},
     *   footnote: string
     * }
     */
    public static function detalhePorCategoria(array $dataset): ?array
    {
        $catalog = $dataset['catalog'] ?? null;
        if (! is_array($catalog) || $catalog === []) {
            return null;
        }

        $def = [];
        $sin = [];
        $ne = [];

        foreach ($catalog as $row) {
            $total = (int) round((float) ($row['value'] ?? 0));
            if ($total <= 0) {
                continue;
            }
            $item = ['nome' => (string) ($row['label'] ?? ''), 'total' => $total];
            match ((string) ($row['grupo'] ?? 'deficiencia')) {
                'ne' => $ne[] = $item,
                'sindrome' => $sin[] = $item,
                default => $def[] = $item,
            };
        }

        $sort = static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp((string) $a['nome'], (string) $b['nome']);
        usort($def, $sort);
        usort($sin, $sort);
        usort($ne, $sort);

        $sum = static fn (array $rows): int => (int) array_sum(array_column($rows, 'total'));

        if ($sum($def) + $sum($sin) + $sum($ne) <= 0) {
            return null;
        }

        return [
            'deficiencias' => $def,
            'sindromes_tea' => $sin,
            'ne_altas_habilidades' => $ne,
            'totais_por_secao' => [
                'deficiencias' => $sum($def),
                'sindromes_tea' => $sum($sin),
                'ne_altas_habilidades' => $sum($ne),
            ],
            'footnote' => (string) ($dataset['footnote'] ?? ''),
        ];
    }

    /**
     * @param  list<array{id: ?string, label: string, norm: string, kind?: string}>  $entries
     * @param  array{by_id: array<string, int>, by_norm: array<string, int>}  $maps
     * @return list<array{label: string, value: float, kind: string, norm: string, grupo: string}>
     */
    private static function buildCatalogRows(array $entries, array $maps, Collection $rawRows): array
    {
        $catalog = [];
        $consumedId = [];
        $consumedNorm = [];

        foreach ($entries as $entry) {
            $value = (float) InclusionEducacensoCatalog::countForDeficienciaEntry($entry, $maps);
            $norm = (string) ($entry['norm'] ?? InclusionEducacensoCatalog::normalizeLabel((string) ($entry['label'] ?? '')));
            $catalog[] = [
                'label' => InclusionEducacensoCatalog::deficienciaChartLabel($entry),
                'value' => $value,
                'kind' => (string) ($entry['kind'] ?? InclusionEducacensoCatalog::classifyDeficienciaKind($entry)),
                'norm' => $norm,
                'grupo' => InclusionDashboardQueries::classificarDesignacaoNeeGrupo((string) ($entry['label'] ?? '')),
            ];
            $id = $entry['id'] ?? null;
            if ($id !== null && $id !== '' && $value > 0) {
                $consumedId[(string) $id] = true;
            }
            if ($norm !== '' && $value > 0) {
                $consumedNorm[$norm] = true;
            }
        }

        foreach ($rawRows as $row) {
            $nome = trim((string) ($row->deficiencia ?? ''));
            if ($nome === '') {
                $nome = (string) __('Não informado');
            }
            $total = (int) ($row->total ?? 0);
            if ($total <= 0) {
                continue;
            }
            $defId = trim((string) ($row->def_id ?? ''));
            $norm = InclusionEducacensoCatalog::resolveCatalogNorm($nome);
            if ($defId !== '' && isset($consumedId[$defId])) {
                continue;
            }
            if ($norm !== '' && isset($consumedNorm[$norm])) {
                continue;
            }

            $entry = [
                'id' => $defId !== '' ? $defId : null,
                'label' => $nome,
                'norm' => $norm,
            ];
            $entry['kind'] = InclusionEducacensoCatalog::classifyDeficienciaKind($entry);
            $catalog[] = [
                'label' => InclusionEducacensoCatalog::deficienciaChartLabel($entry),
                'value' => (float) $total,
                'kind' => (string) $entry['kind'],
                'norm' => $norm,
                'grupo' => InclusionDashboardQueries::classificarDesignacaoNeeGrupo($nome),
            ];
            if ($defId !== '') {
                $consumedId[$defId] = true;
            }
            if ($norm !== '') {
                $consumedNorm[$norm] = true;
            }
        }

        return $catalog;
    }

    /**
     * @param  list<array{value: float, grupo: string}>  $catalog
     * @return array{deficiencias: int, sindromes_tea: int, ne_altas_habilidades: int}
     */
    private static function aggregateGruposFromCatalog(array $catalog): array
    {
        $out = [
            'deficiencias' => 0,
            'sindromes_tea' => 0,
            'ne_altas_habilidades' => 0,
        ];

        foreach ($catalog as $row) {
            $v = (int) round((float) ($row['value'] ?? 0));
            if ($v <= 0) {
                continue;
            }
            match ((string) ($row['grupo'] ?? 'deficiencia')) {
                'ne' => $out['ne_altas_habilidades'] += $v,
                'sindrome' => $out['sindromes_tea'] += $v,
                default => $out['deficiencias'] += $v,
            };
        }

        return $out;
    }
}
