<?php

namespace App\Support\Ieducar;

/**
 * Secção única de indicadores NEE: KPIs, medidores (%), legenda do catálogo e gráfico de detalhe.
 */
final class InclusionNeeIndicatorsPanel
{
    /**
     * @param  ?array<string, mixed>  $neeDataset
     * @param  list<array{chart: array<string, mixed>, caption: string}>  $gauges
     * @param  list<array<string, mixed>>  $neeCharts
     * @return ?array{
     *   title: string,
     *   subtitle: string,
     *   catalog_warning: ?string,
     *   legend: list<array{color: string, label: string, hint: string}>,
     *   kpis: list<array{label: string, value: string, hint: ?string, tone: string}>,
     *   gauges: list<array{chart: array<string, mixed>, caption: string}>,
     *   catalog_chart: ?array<string, mixed>,
     *   calc_note: ?array{formula: ?string, note: ?string}
     * }
     */
    public static function build(
        ?array $neeDataset,
        array $gauges,
        ?int $totalMatriculas,
        ?int $matriculasNee,
        ?string $catalogWarning,
        array $neeCharts,
    ): ?array {
        $hasGauges = $gauges !== [];
        $catalogChart = self::findCatalogChart($neeCharts);
        $grupos = is_array($neeDataset['grupos'] ?? null) ? $neeDataset['grupos'] : null;
        $nee = $matriculasNee ?? (int) ($neeDataset['matriculas_nee'] ?? 0);
        $comCadastro = (int) ($neeDataset['matriculas_com_cadastro_nee'] ?? 0);

        if (! $hasGauges && $catalogChart === null && $grupos === null && $nee <= 0) {
            return null;
        }

        $nDef = (int) ($grupos['deficiencias'] ?? 0);
        $nSin = (int) ($grupos['sindromes_tea'] ?? 0);
        $nNe = (int) ($grupos['ne_altas_habilidades'] ?? 0);
        $denRede = $totalMatriculas ?? 0;

        $kpis = [
            self::kpi(
                __('Matrículas NEE (total)'),
                self::fmtInt($nee),
                __('Cadastro de deficiência e/ou turma AEE no recorte dos filtros.'),
                'violet',
            ),
            self::kpi(
                __('Com cadastro de deficiência'),
                self::fmtInt($comCadastro),
                $nee > 0
                    ? __(':pct% do universo NEE.', ['pct' => self::pct($comCadastro, $nee)])
                    : null,
                'teal',
            ),
            self::kpiGrupo(__('Deficiências (grupo)'), $nDef, $nee, $denRede),
            self::kpiGrupo(__('Síndromes e TEA'), $nSin, $nee, $denRede),
            self::kpiGrupo(__('NE — altas habilidades'), $nNe, $nee, $denRede),
        ];

        $pathNote = ($neeDataset['uses_fisica'] ?? false)
            ? __('cadastro.fisica_deficiencia + deficiência')
            : __('aluno_deficiencia + deficiência');

        return [
            'title' => __('Indicadores NEE — cadastro, grupos e catálogo Censo'),
            'subtitle' => __(
                'Os medidores e os cartões usam a mesma classificação por designação em :path. O catálogo abaixo detalha cada opção MEC/i-Educar.',
                ['path' => $pathNote]
            ),
            'catalog_warning' => $catalogWarning !== null && $catalogWarning !== ''
                ? $catalogWarning
                : (is_string($neeDataset['catalog_warning'] ?? null) ? $neeDataset['catalog_warning'] : null),
            'legend' => self::legendRows(),
            'kpis' => $kpis,
            'gauges' => $gauges,
            'catalog_chart' => $catalogChart,
            'calc_note' => [
                'formula' => __(
                    'Grupos: matrículas NEE classificadas por designação (mesma lógica da exportação). Medidores: Rede % = grupo ÷ matrículas ativas no filtro; Universo NEE % = grupo ÷ total NEE. Catálogo: cada matrícula NEE conta em cada designação do cadastro (várias barras por aluno).'
                ),
                'note' => __(
                    'Inclui cadastro sem match no catálogo MEC (âmbar). «Só turma AEE» não entra nos três grupos — aparece no catálogo como remanescente.'
                ),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $neeCharts
     * @return ?array<string, mixed>
     */
    private static function findCatalogChart(array $neeCharts): ?array
    {
        foreach ($neeCharts as $chart) {
            $id = (string) ($chart['chart_id'] ?? '');
            if (in_array($id, ['nee_catalogo', 'nee_catalogo_mec'], true)) {
                return $chart;
            }
        }

        return null;
    }

    /**
     * @return list<array{color: string, label: string, hint: string}>
     */
    private static function legendRows(): array
    {
        return [
            [
                'color' => 'indigo',
                'label' => __('INEP / Censo'),
                'hint' => __('Designação alinhada ao catálogo oficial.'),
            ],
            [
                'color' => 'violet',
                'label' => __('Complementar'),
                'hint' => __('Opção MEC/i-Educar com vínculo no cadastro local.'),
            ],
            [
                'color' => 'amber',
                'label' => __('Remanescente'),
                'hint' => __('Só turma AEE ou cadastro sem match no catálogo MEC/INEP.'),
            ],
            [
                'color' => 'slate',
                'label' => __('Três grupos'),
                'hint' => __('Deficiências, síndromes/TEA e NE — mesmos totais dos medidores e cartões.'),
            ],
        ];
    }

    /**
     * @return array{label: string, value: string, hint: ?string, tone: string}
     */
    private static function kpiGrupo(string $label, int $count, int $nee, int $denRede): array
    {
        $hints = [];
        if ($nee > 0) {
            $hints[] = __(':n de :nee NEE (:pct% do universo NEE).', [
                'n' => self::fmtInt($count),
                'nee' => self::fmtInt($nee),
                'pct' => self::pct($count, $nee),
            ]);
        }
        if ($denRede > 0) {
            $hints[] = __(':pct% das matrículas ativas no filtro (rede).', ['pct' => self::pct($count, $denRede)]);
        }

        return self::kpi($label, self::fmtInt($count), $hints !== [] ? implode(' ', $hints) : null, 'violet');
    }

    /**
     * @return array{label: string, value: string, hint: ?string, tone: string}
     */
    private static function kpi(string $label, string $value, ?string $hint, string $tone): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'tone' => $tone,
        ];
    }

    private static function fmtInt(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    private static function pct(int $num, int $den): string
    {
        if ($den <= 0) {
            return '0';
        }

        return (string) round(100.0 * $num / $den, 1);
    }
}
