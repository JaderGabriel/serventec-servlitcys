<?php

namespace App\Support\Analytics;

/**
 * Informes narrativos da aba Previsão CadÚnico.
 */
final class CadunicoPrevisaoInformeBuilder
{
    /**
     * @param  array<string, mixed>  $report
     * @return array{available: bool, aviso: string, blocos: list<array<string, mixed>>}
     */
    public static function build(array $report): array
    {
        $gap = is_array($report['gap'] ?? null) ? $report['gap'] : [];
        if (! ($gap['available'] ?? false)) {
            return ['available' => false, 'aviso' => '', 'blocos' => []];
        }

        $territorial = is_array($report['territorial'] ?? null) ? $report['territorial'] : [];

        $blocos = array_values(array_filter([
            self::blocoCobertura($gap),
            self::blocoFinanceiro($gap),
            self::blocoCenarios($gap),
            self::blocoTerritorial($territorial),
            self::blocoBuscaAtiva($gap),
        ]));

        return [
            'available' => $blocos !== [],
            'aviso' => __(
                'Leitura para planeamento e busca ativa. O CadÚnico não identifica automaticamente quem deve matricular-se na rede municipal.'
            ),
            'blocos' => $blocos,
        ];
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return ?array<string, mixed>
     */
    private static function blocoCobertura(array $gap): ?array
    {
        $cad = (int) ($gap['cadunico_total_escolar'] ?? 0);
        $base = (int) ($gap['ieducar_base_calculo'] ?? $gap['ieducar_matriculas'] ?? 0);
        if ($cad <= 0) {
            return null;
        }

        $status = (string) ($gap['status'] ?? 'neutral');

        return [
            'id' => 'cobertura',
            'titulo' => __('Cobertura da rede face ao CadÚnico'),
            'subtitulo' => __('Exercício :ano', ['ano' => (string) ($gap['cadunico_ano'] ?? '')]),
            'status' => $status === 'success' ? 'success' : ($status === 'warning' ? 'warning' : 'neutral'),
            'status_label' => (string) ($gap['status_label'] ?? ''),
            'paragrafos' => [
                (string) ($gap['nota'] ?? ''),
            ],
            'indicadores' => [
                ['label' => __('População escolar CadÚnico'), 'value' => number_format($cad, 0, ',', '.'), 'hint' => null],
                ['label' => __('Base rede (cálculo)'), 'value' => number_format($base, 0, ',', '.'), 'hint' => null],
                ['label' => __('Cobertura'), 'value' => (string) ($gap['cobertura_label'] ?? '—'), 'hint' => null],
                ['label' => __('Lacuna estimada'), 'value' => (string) ($gap['gap_total_fmt'] ?? '—'), 'hint' => null],
            ],
            'acoes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return ?array<string, mixed>
     */
    private static function blocoFinanceiro(array $gap): ?array
    {
        $impacto = is_array($gap['impacto_financeiro'] ?? null) ? $gap['impacto_financeiro'] : [];
        if (($impacto['gap_anual'] ?? 0) <= 0) {
            return null;
        }

        return [
            'id' => 'financeiro',
            'titulo' => __('Impacto FUNDEB indicativo da lacuna'),
            'subtitulo' => __('Matrículas adicionais × VAAF de referência'),
            'status' => 'warning',
            'status_label' => __('Ordem de grandeza'),
            'paragrafos' => array_filter([(string) ($impacto['formula'] ?? '')]),
            'indicadores' => [
                ['label' => __('VAAF'), 'value' => (string) ($impacto['vaaf_label'] ?? '—'), 'hint' => null],
                ['label' => __('Potencial anual'), 'value' => (string) ($impacto['gap_anual_label'] ?? '—'), 'hint' => null],
            ],
            'acoes' => [
                __('Cruzar com aba Comparativo e FUNDEB antes de meta orçamentária.'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return ?array<string, mixed>
     */
    private static function blocoCenarios(array $gap): ?array
    {
        $cenarios = is_array($gap['cenarios_financeiros'] ?? null) ? $gap['cenarios_financeiros'] : [];
        if (! ($cenarios['available'] ?? false)) {
            return null;
        }

        $acoes = [];
        foreach (is_array($cenarios['itens'] ?? null) ? $cenarios['itens'] : [] as $item) {
            $acoes[] = __(':titulo — :valor', [
                'titulo' => (string) ($item['titulo'] ?? ''),
                'valor' => (string) ($item['valor_label'] ?? '—'),
            ]);
        }

        return [
            'id' => 'cenarios',
            'titulo' => __('Cenários financeiros sobre a lacuna'),
            'subtitulo' => __('Proporções NEE/AEE/VAAR observadas na rede'),
            'status' => 'neutral',
            'status_label' => __('Indicativo'),
            'paragrafos' => array_filter([(string) ($cenarios['aviso_geral'] ?? '')]),
            'indicadores' => [
                ['label' => __('Soma cenários'), 'value' => (string) ($cenarios['total_cenarios_label'] ?? '—'), 'hint' => null],
            ],
            'acoes' => $acoes,
        ];
    }

    /**
     * @param  array<string, mixed>  $territorial
     * @return ?array<string, mixed>
     */
    private static function blocoTerritorial(array $territorial): ?array
    {
        $ranking = is_array($territorial['ranking'] ?? null) ? $territorial['ranking'] : [];
        if ($ranking === []) {
            return null;
        }

        $acoes = [];
        foreach (array_slice($ranking, 0, 5) as $row) {
            $acoes[] = __(':nome — lacuna est. :g (pressão :p)', [
                'nome' => (string) ($row['nome'] ?? ''),
                'g' => (string) ($row['gap_fmt'] ?? '0'),
                'p' => number_format((float) ($row['pressao'] ?? 0), 0, ',', '.'),
            ]);
        }

        return [
            'id' => 'territorial',
            'titulo' => __('Prioridade territorial'),
            'subtitulo' => __('Bairros/setores com maior pressão'),
            'status' => 'warning',
            'status_label' => __('Mapa'),
            'paragrafos' => array_filter([(string) ($territorial['nota'] ?? '')]),
            'indicadores' => [
                ['label' => __('Territórios'), 'value' => (string) (int) ($territorial['territorios_count'] ?? 0), 'hint' => null],
            ],
            'acoes' => $acoes,
        ];
    }

    /**
     * @param  array<string, mixed>  $gap
     * @return ?array<string, mixed>
     */
    private static function blocoBuscaAtiva(array $gap): ?array
    {
        $porEtapa = is_array($gap['por_etapa'] ?? null) ? $gap['por_etapa'] : [];
        $top = array_slice($porEtapa, 0, 3);
        if ($top === []) {
            return null;
        }

        $acoes = [];
        foreach ($top as $row) {
            if ((int) ($row['gap'] ?? 0) > 0) {
                $acoes[] = __(':etapa — cerca de :n fora da rede (indicativo).', [
                    'etapa' => (string) ($row['etapa'] ?? ''),
                    'n' => (string) ($row['gap_fmt'] ?? '0'),
                ]);
            }
        }

        return [
            'id' => 'busca_ativa',
            'titulo' => __('Prioridades por nível de ensino'),
            'subtitulo' => __('Maiores lacunas entre CadÚnico e matrículas'),
            'status' => 'warning',
            'status_label' => __('Busca ativa'),
            'paragrafos' => [],
            'indicadores' => [],
            'acoes' => $acoes,
        ];
    }
}
