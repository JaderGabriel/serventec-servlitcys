<?php

namespace App\Support\Horizonte;

/** Fases do abastecimento bimestral Horizonte (ordem fixa). */
final class HorizonteFortnightlyFeedPhaseCatalog
{
    /**
     * @return list<array{key: string, label: string, skip_option: string, icon: string, tone: string, group: string, description: string, incremental: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'fundeb_receita',
                'label' => 'FUNDEB',
                'skip_option' => 'skip_fundeb',
                'icon' => 'banknotes',
                'tone' => 'amber',
                'group' => 'financeiro',
                'description' => __('Receita FNDE por município — base do mapa de oportunidade e indicadores FUNDEB.'),
                'incremental' => false,
            ],
            [
                'key' => 'censo_matriculas',
                'label' => 'Censo',
                'skip_option' => 'skip_censo',
                'icon' => 'academic-cap',
                'tone' => 'emerald',
                'group' => 'educacional',
                'description' => __('Matrículas agregadas por município a partir dos microdados INEP (Educacenso).'),
                'incremental' => false,
            ],
            [
                'key' => 'educacenso',
                'label' => 'Educacenso',
                'skip_option' => 'skip_educacenso',
                'icon' => 'chart-bar',
                'tone' => 'sky',
                'group' => 'educacional',
                'description' => __('Série histórica multi-ano × UF para gráficos de matrículas no modal Horizonte.'),
                'incremental' => true,
            ],
            [
                'key' => 'cadunico_sync',
                'label' => 'CadÚnico',
                'skip_option' => 'skip_cadunico',
                'icon' => 'users',
                'tone' => 'rose',
                'group' => 'social',
                'description' => __('Agregados municipais Cecad/Misocial — pressão social e escolarização.'),
                'incremental' => false,
            ],
            [
                'key' => 'sidra_demography',
                'label' => 'SIDRA',
                'skip_option' => 'skip_sidra',
                'icon' => 'users',
                'tone' => 'slate',
                'group' => 'social',
                'description' => __('População 4–17 anos por UF (IBGE SIDRA) — contexto demográfico.'),
                'incremental' => true,
            ],
            [
                'key' => 'repasses_tesouro',
                'label' => 'Repasses',
                'skip_option' => 'skip_repasses',
                'icon' => 'banknotes',
                'tone' => 'emerald',
                'group' => 'financeiro',
                'description' => __('Repasses FUNDEB observados (Tesouro CKAN) — série nacional de transferências.'),
                'incremental' => false,
            ],
            [
                'key' => 'siconfi_sync',
                'label' => 'SICONFI',
                'skip_option' => 'skip_siconfi',
                'icon' => 'chart-bar',
                'tone' => 'violet',
                'group' => 'financeiro',
                'description' => __('Indicadores fiscais municipais (RREO) — saúde financeira no mapa.'),
                'incremental' => true,
            ],
            [
                'key' => 'transparency_sync',
                'label' => 'Transparência',
                'skip_option' => 'skip_transparency',
                'icon' => 'building-office-2',
                'tone' => 'indigo',
                'group' => 'financeiro',
                'description' => __('Convênios e empenhos (Portal da Transparência) — contexto de execução.'),
                'incremental' => false,
            ],
            [
                'key' => 'saeb_planilhas',
                'label' => 'SAEB',
                'skip_option' => 'skip_saeb',
                'icon' => 'signal',
                'tone' => 'violet',
                'group' => 'educacional',
                'description' => __('Planilhas oficiais INEP — IDEB e desempenho por município.'),
                'incremental' => true,
            ],
            [
                'key' => 'ibge_catalog',
                'label' => 'IBGE',
                'skip_option' => 'skip_ibge',
                'icon' => 'map',
                'tone' => 'sky',
                'group' => 'territorial',
                'description' => __('Catálogo municipal IBGE aquecido por UF — geocodificação e filtros.'),
                'incremental' => true,
            ],
            [
                'key' => 'ibge_municipal_geo',
                'label' => 'IBGE malha',
                'skip_option' => 'skip_ibge_municipal_geo',
                'icon' => 'map-pin',
                'tone' => 'indigo',
                'group' => 'territorial',
                'description' => __('Polígonos municipais e área territorial — camada geo do mapa.'),
                'incremental' => true,
            ],
            [
                'key' => 'sge_registry',
                'label' => 'SGE',
                'skip_option' => 'skip_sge',
                'icon' => 'circle-stack',
                'tone' => 'slate',
                'group' => 'operacional',
                'description' => __('Registo de sistemas de gestão educacional por município.'),
                'incremental' => false,
            ],
            [
                'key' => 'municipal_alerts',
                'label' => 'Alertas MEC/FNDE',
                'skip_option' => 'skip_alerts',
                'icon' => 'exclamation-triangle',
                'tone' => 'amber',
                'group' => 'operacional',
                'description' => __('VAAT inabilitados, bloqueios e avisos oficiais MEC/FNDE.'),
                'incremental' => false,
            ],
            [
                'key' => 'official_check',
                'label' => 'Verificação',
                'skip_option' => 'skip_verify',
                'icon' => 'shield-check',
                'tone' => 'emerald',
                'group' => 'operacional',
                'description' => __('Confirma disponibilidade das fontes oficiais após o ciclo.'),
                'incremental' => false,
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function groups(): array
    {
        return [
            'financeiro' => [
                'label' => __('Financeiro'),
                'description' => __('FUNDEB, repasses, SICONFI e transparência fiscal.'),
            ],
            'educacional' => [
                'label' => __('Educacional'),
                'description' => __('Censo, Educacenso e SAEB — matrículas e aprendizagem.'),
            ],
            'social' => [
                'label' => __('Social'),
                'description' => __('CadÚnico e demografia IBGE.'),
            ],
            'territorial' => [
                'label' => __('Territorial'),
                'description' => __('Catálogo IBGE e malha municipal.'),
            ],
            'operacional' => [
                'label' => __('Operacional'),
                'description' => __('SGE, alertas oficiais e verificação final.'),
            ],
        ];
    }

    /**
     * @param  array<string, bool>  $options
     * @return list<string>
     */
    public static function queueFromOptions(array $options): array
    {
        $queue = [];
        foreach (self::definitions() as $def) {
            $skipKey = $def['skip_option'];
            if ($options[$skipKey] ?? false) {
                continue;
            }
            $queue[] = $def['key'];
        }

        return $queue;
    }

    public static function label(string $key): string
    {
        foreach (self::definitions() as $def) {
            if ($def['key'] === $key) {
                return __($def['label']);
            }
        }

        return $key;
    }
}
