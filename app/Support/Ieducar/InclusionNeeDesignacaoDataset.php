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
     *   matriculas_nee: int,
     *   matriculas_com_cadastro_nee: int,
     *   catalog_warning: ?string
     * }
     */
    public static function build(Connection $db, City $city, IeducarFilterState $filters): ?array
    {
        try {
            $matriculasNee = InclusionDashboardQueries::countMatriculasComNee($db, $city, $filters);
            $matriculasComCadastroNee = InclusionDashboardQueries::countMatriculasNeeComCadastroDeficiencia($db, $city, $filters);
            $entries = InclusionEducacensoCatalog::mergedDeficienciaEntriesForChart($db, $city);

            if ($entries === [] && $matriculasNee <= 0) {
                return null;
            }

            $rows = InclusionDashboardQueries::getMatriculasPorDeficiencia($db, $city, $filters, null);

            $maps = InclusionEducacensoCatalog::deficienciaCountMapsFromRows(
                $rows,
                static fn ($row) => (string) ($row->deficiencia ?? ''),
                static fn ($row) => (int) ($row->total ?? 0),
                static fn ($row) => (string) ($row->def_id ?? ''),
            );

            $catalog = self::buildCatalogRows($entries, $maps, $rows);
            $assignedCatalog = self::sumCatalogAssigned($catalog);
            $catalog = self::appendSemDesignacaoCatalogoRows($catalog, $matriculasNee, $matriculasComCadastroNee, $assignedCatalog);
            $grupos = self::aggregateGruposForIndicators($catalog, $rows);

            $usesFisica = InclusionDashboardQueries::inclusionNeeUsesFisicaPath($db, $city);
            $pathNote = $usesFisica
                ? __('cadastro.fisica_deficiencia + deficiência')
                : __('aluno_deficiencia + deficiência');

            $catalogWarning = null;
            $gruposSum = self::sumGrupos($grupos);
            if ($matriculasNee > 0 && $matriculasComCadastroNee > 0 && $assignedCatalog <= 0 && $gruposSum <= 0) {
                $catalogWarning = __(
                    'Há :n matrícula(s) NEE com cadastro de deficiência, mas nenhuma designação foi distribuída nas barras do catálogo. Verifique o vínculo cadastro.deficiência (fisica_deficiencia / aluno_deficiencia) e os aliases em ieducar.inclusion.deficiencia_label_aliases.',
                    ['n' => number_format($matriculasComCadastroNee)]
                );
            } elseif ($matriculasNee > 0 && $matriculasComCadastroNee > 0 && $assignedCatalog <= 0 && $gruposSum > 0) {
                $catalogWarning = __(
                    'Há :n matrícula(s) com cadastro no i-Educar cujos rótulos não casaram com o catálogo MEC/INEP (barras índigo/violeta). Os totais por grupo e medidores usam as designações do cadastro; barras âmbar mostram o remanescente sem match.',
                    ['n' => number_format($matriculasComCadastroNee)]
                );
            }

            $footnote = __(
                'Matrículas NEE (:total): COUNT(DISTINCT matrícula) com :path ou turma/curso AEE.',
                ['total' => number_format($matriculasNee), 'path' => $pathNote]
            );

            return [
                'uses_fisica' => $usesFisica,
                'footnote' => $footnote,
                'grupos' => $grupos,
                'catalog' => $catalog,
                'matriculas_nee' => $matriculasNee,
                'matriculas_com_cadastro_nee' => $matriculasComCadastroNee,
                'catalog_warning' => $catalogWarning,
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
        $matriculasNee = (int) ($dataset['matriculas_nee'] ?? 0);

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
        $chart['subtitle'] = __('Cálculo: soma de matrículas NEE por grupo de designação (deficiência, síndrome/TEA, NE).');

        if ($denominator !== null && $denominator > 0) {
            $chart = InclusionDashboardQueries::attachMatriculaKpiTotalPublic(
                $chart,
                $denominator,
                true,
                $matriculasNee > 0
                    ? __('Total matrículas NEE no filtro: :n', ['n' => number_format($matriculasNee)])
                    : null
            );
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
            ? __('Cálculo: 1 matrícula NEE → 1 barra (código ou rótulo normalizado). Valor 0 = sem vínculo no filtro.')
            : __('Cálculo: só designações com matrícula no recorte.');
        $chart['footnote'] = __('Âmbar: só turma AEE (Total NEE − com cadastro) ou cadastro sem match no catálogo MEC/i-Educar.');
        $chart['options'] = array_merge(
            is_array($chart['options'] ?? null) ? $chart['options'] : [],
            ['panelHeight' => $includeZeros ? 'xxl' : 'xl', 'skipHorizontalBarAutoHeight' => false]
        );
        $chart['catalog_include_zeros'] = $includeZeros;

        $catalogWarning = trim((string) ($dataset['catalog_warning'] ?? ''));
        if ($catalogWarning !== '') {
            $chart['catalog_warning'] = $catalogWarning;
            $chart['footnote'] = trim(((string) ($chart['footnote'] ?? '')).' '.$catalogWarning);
        }

        $matriculasNee = (int) ($dataset['matriculas_nee'] ?? 0);
        if ($denominator !== null && $denominator > 0) {
            $chart = InclusionDashboardQueries::attachMatriculaKpiTotalPublic(
                $chart,
                $denominator,
                true,
                $matriculasNee > 0
                    ? __('Matrículas NEE no filtro: :n (soma das barras = total NEE; âmbar = só AEE ou cadastro sem match)', ['n' => number_format($matriculasNee)])
                    : __('Soma das barras (pode exceder o total por vínculos múltiplos)')
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
            if (self::isSemDesignacaoNorm((string) ($row['norm'] ?? ''))) {
                continue;
            }
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
        [$catalog, $remaining] = InclusionEducacensoCatalog::assignDeficienciaCountsExclusive($entries, $maps);

        foreach ($rawRows as $row) {
            $nome = trim((string) ($row->deficiencia ?? ''));
            if ($nome === '') {
                $nome = (string) __('Não informado');
            }
            $defId = trim((string) ($row->def_id ?? ''));
            $norm = InclusionEducacensoCatalog::resolveCatalogNorm($nome);
            $total = 0;

            if ($defId !== '' && isset($remaining['by_id'][$defId])) {
                $total = (int) $remaining['by_id'][$defId];
                unset($remaining['by_id'][$defId]);
                if ($norm !== '' && isset($remaining['by_norm'][$norm])) {
                    unset($remaining['by_norm'][$norm]);
                }
            } elseif ($norm !== '' && isset($remaining['by_norm'][$norm])) {
                $total = (int) $remaining['by_norm'][$norm];
                unset($remaining['by_norm'][$norm]);
            } else {
                continue;
            }

            if ($total <= 0) {
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
        }

        foreach ($remaining['by_id'] as $id => $count) {
            if ($count <= 0) {
                continue;
            }
            $entry = ['id' => (string) $id, 'label' => (string) __('Designação (cód. :id)', ['id' => $id]), 'norm' => ''];
            $entry['kind'] = 'ieducar';
            $catalog[] = [
                'label' => InclusionEducacensoCatalog::deficienciaChartLabel($entry),
                'value' => (float) $count,
                'kind' => 'ieducar',
                'norm' => '',
                'grupo' => 'deficiencia',
            ];
        }

        foreach ($remaining['by_norm'] as $norm => $count) {
            if ($count <= 0 || $norm === '') {
                continue;
            }
            $entry = ['id' => null, 'label' => $norm, 'norm' => $norm];
            $entry['kind'] = InclusionEducacensoCatalog::classifyDeficienciaKind($entry);
            $catalog[] = [
                'label' => InclusionEducacensoCatalog::deficienciaChartLabel($entry),
                'value' => (float) $count,
                'kind' => (string) $entry['kind'],
                'norm' => $norm,
                'grupo' => InclusionDashboardQueries::classificarDesignacaoNeeGrupo($norm),
            ];
        }

        return $catalog;
    }

    /**
     * @param  list<array{label: string, value: float, kind: string, norm: string, grupo?: string}>  $catalog
     * @return list<array{label: string, value: float, kind: string, norm: string, grupo: string}>
     */
    private static function appendSemDesignacaoCatalogoRows(
        array $catalog,
        int $matriculasNee,
        int $matriculasComCadastroNee,
        int $assignedCatalog,
    ): array {
        if ($matriculasNee <= 0) {
            return $catalog;
        }

        $comCadastro = min($matriculasComCadastroNee, $matriculasNee);
        $somenteAee = max(0, $matriculasNee - $comCadastro);
        $cadastroSemMatch = max(0, $comCadastro - $assignedCatalog);
        $residual = max(0, $matriculasNee - $assignedCatalog - $somenteAee - $cadastroSemMatch);

        if ($somenteAee > 0) {
            $catalog[] = [
                'label' => __('Só turma AEE (sem cadastro de deficiência)').' — '.__('cadastro i-Educar'),
                'value' => (float) $somenteAee,
                'kind' => 'ieducar',
                'norm' => '__sem_designacao_aee__',
                'grupo' => 'deficiencia',
            ];
        }

        if ($cadastroSemMatch > 0) {
            $catalog[] = [
                'label' => __('Com cadastro de deficiência, sem correspondência no catálogo unificado').' — '.__('cadastro i-Educar'),
                'value' => (float) $cadastroSemMatch,
                'kind' => 'ieducar',
                'norm' => '__sem_designacao_cadastro__',
                'grupo' => 'deficiencia',
            ];
        }

        if ($residual > 0) {
            $catalog[] = [
                'label' => __('Outras matrículas NEE sem barra no catálogo').' — '.__('cadastro i-Educar'),
                'value' => (float) $residual,
                'kind' => 'ieducar',
                'norm' => '__sem_designacao__',
                'grupo' => 'deficiencia',
            ];
        }

        return $catalog;
    }

    /**
     * @param  list<array{value: float, norm: string}>  $catalog
     */
    private static function sumCatalogAssigned(array $catalog): int
    {
        $sum = 0;
        foreach ($catalog as $row) {
            if (self::isSemDesignacaoNorm((string) ($row['norm'] ?? ''))) {
                continue;
            }
            $sum += (int) round((float) ($row['value'] ?? 0));
        }

        return $sum;
    }

    private static function isSemDesignacaoNorm(string $norm): bool
    {
        return in_array($norm, [
            '__sem_designacao__',
            '__sem_designacao_aee__',
            '__sem_designacao_cadastro__',
        ], true);
    }

    /**
     * Totais para medidores e gráfico por grupo: prioriza rótulos do cadastro; inclui cadastro sem match MEC.
     *
     * @param  list<array{label: string, value: float, kind: string, norm: string, grupo?: string}>  $catalog
     */
    private static function aggregateGruposForIndicators(array $catalog, Collection $rows): array
    {
        $fromRows = self::aggregateGruposFromMatriculaRows($rows);
        if (self::sumGrupos($fromRows) > 0) {
            return $fromRows;
        }

        $fromCatalog = self::aggregateGruposFromCatalog($catalog);
        foreach ($catalog as $row) {
            if ((string) ($row['norm'] ?? '') === '__sem_designacao_cadastro__') {
                $fromCatalog['deficiencias'] += (int) round((float) ($row['value'] ?? 0));
            }
        }

        return $fromCatalog;
    }

    /**
     * @param  Collection<int, object{deficiencia?: string, total?: int}>  $rows
     * @return array{deficiencias: int, sindromes_tea: int, ne_altas_habilidades: int}
     */
    private static function aggregateGruposFromMatriculaRows(Collection $rows): array
    {
        $out = [
            'deficiencias' => 0,
            'sindromes_tea' => 0,
            'ne_altas_habilidades' => 0,
        ];

        foreach ($rows as $row) {
            $nome = trim((string) ($row->deficiencia ?? ''));
            $total = (int) ($row->total ?? 0);
            if ($total <= 0) {
                continue;
            }
            match (InclusionDashboardQueries::classificarDesignacaoNeeGrupo($nome)) {
                'ne' => $out['ne_altas_habilidades'] += $total,
                'sindrome' => $out['sindromes_tea'] += $total,
                default => $out['deficiencias'] += $total,
            };
        }

        return $out;
    }

    /**
     * @param  array{deficiencias: int, sindromes_tea: int, ne_altas_habilidades: int}  $grupos
     */
    private static function sumGrupos(array $grupos): int
    {
        return (int) ($grupos['deficiencias'] ?? 0)
            + (int) ($grupos['sindromes_tea'] ?? 0)
            + (int) ($grupos['ne_altas_habilidades'] ?? 0);
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
            if (self::isSemDesignacaoNorm((string) ($row['norm'] ?? ''))) {
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
