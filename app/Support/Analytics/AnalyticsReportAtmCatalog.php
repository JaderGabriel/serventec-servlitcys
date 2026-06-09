<?php

namespace App\Support\Analytics;

/**
 * Estrutura de secções inspirada no Relatório Municipal Completo ATM (MEC/EducaDados).
 *
 * @phpstan-type SectionDef array{
 *   id: string,
 *   title: string,
 *   group: string,
 *   scope: string,
 *   intro: string,
 *   order: int
 * }
 */
final class AnalyticsReportAtmCatalog
{
    /**
     * @return list<SectionDef>
     */
    public static function sections(): array
    {
        return [
            [
                'id' => 'indicadores_educacionais',
                'title' => __('Indicadores Educacionais'),
                'group' => 'diagnostico',
                'scope' => 'socioeconomic_and_network_volume',
                'intro' => __('Panorama territorial e volumetria da educação básica no município, com comparativos quando houver base.'),
                'order' => 10,
            ],
            [
                'id' => 'rede_municipal',
                'title' => __('Rede Municipal'),
                'group' => 'diagnostico',
                'scope' => 'municipal_network_ieducar',
                'intro' => __('Os números do município no recorte dos filtros: matrículas, distorção idade-série, fluxo escolar e oferta.'),
                'order' => 20,
            ],
            [
                'id' => 'redes_publicas',
                'title' => __('Redes Públicas'),
                'group' => 'diagnostico',
                'scope' => 'censo_network_share',
                'intro' => __('Participação das redes municipal, estadual e privada no território (Censo Escolar quando indexado).'),
                'order' => 30,
            ],
            [
                'id' => 'fundeb',
                'title' => __('Fundeb — Fundo de Manutenção e Desenvolvimento da Educação Básica'),
                'group' => 'financiamento',
                'scope' => 'fundeb_vaaf_vaat_vaar',
                'intro' => __('Financiamento constitucional da educação básica: VAAF, VAAT, complementação VAAR e previsão no recorte.'),
                'order' => 40,
            ],
            [
                'id' => 'finance_realtime',
                'title' => __('Finanças — repasses em tempo real'),
                'group' => 'financiamento',
                'scope' => 'finance_realtime_transfers',
                'intro' => __('Repasses observados (Tesouro/Transparência) confrontados com expectativa FUNDEB no exercício.'),
                'order' => 45,
            ],
            [
                'id' => 'salario_educacao',
                'title' => __('Salário-Educação'),
                'group' => 'financiamento',
                'scope' => 'salario_educacao_transfers',
                'intro' => __('Repasses vinculados ao Salário-Educação e articulação com programas federais.'),
                'order' => 50,
            ],
            [
                'id' => 'programas_universais',
                'title' => __('Programas Universais'),
                'group' => 'programas',
                'scope' => 'complementary_programs',
                'intro' => __('PNAE, PNATE, PDDE e demais programas transversais monitorados na plataforma.'),
                'order' => 60,
            ],
            [
                'id' => 'educacao_infantil',
                'title' => __('Educação Infantil'),
                'group' => 'programas',
                'scope' => 'early_childhood',
                'intro' => __('Creche e pré-escola: matrículas, políticas de expansão e programas de apoio.'),
                'order' => 70,
            ],
            [
                'id' => 'inclusao_equidade',
                'title' => __('Inclusão, equidade e permanência'),
                'group' => 'programas',
                'scope' => 'inclusion_nee_vaar',
                'intro' => __('NEE, recursos em prova, VAAR e indicadores de permanência no mesmo filtro.'),
                'order' => 80,
            ],
            [
                'id' => 'desempenho_aprendizagem',
                'title' => __('Aprendizagem e indicadores externos (SAEB / IDEB)'),
                'group' => 'programas',
                'scope' => 'saeb_ideb_performance',
                'intro' => __('Resultados de aprendizagem e metas quando houver microdados ou importação configurada.'),
                'order' => 90,
            ],
            [
                'id' => 'cadastro_censo',
                'title' => __('Cadastro, discrepâncias e Censo Escolar'),
                'group' => 'gestao',
                'scope' => 'discrepancies_censo',
                'intro' => __('Qualidade do cadastro i-Educar, rotinas de correção e ritmo de exportação ao Censo.'),
                'order' => 100,
            ],
            [
                'id' => 'cadunico_previsao',
                'title' => __('CadÚnico — previsão fora da rede'),
                'group' => 'gestao',
                'scope' => 'cadunico_gap_territory',
                'intro' => __('Cruza agregados Cecad com matrículas i-Educar: lacuna por faixa, territórios prioritários (distância, pressão e lacuna) — tabelas, sem mapa.'),
                'order' => 105,
            ],
            [
                'id' => 'territorio_rede',
                'title' => __('Território e unidades escolares'),
                'group' => 'gestao',
                'scope' => 'school_map_geo',
                'intro' => __('Distribuição geográfica das escolas e peso das matrículas no recorte.'),
                'order' => 110,
            ],
            [
                'id' => 'publicacao_digital',
                'title' => __('Publicação digital e verificação'),
                'group' => 'meta',
                'scope' => 'bibliography_qr',
                'intro' => __('Identificador bibliográfico, QR para download e painel interactivo na plataforma.'),
                'order' => 120,
            ],
        ];
    }

    /**
     * @return list<array{id: string, title: string, page_hint: int}>
     */
    public static function tableOfContents(): array
    {
        $page = 4;
        $out = [];
        foreach (self::sections() as $section) {
            $out[] = [
                'id' => $section['id'],
                'title' => $section['title'],
                'page_hint' => $page,
            ];
            $page += match ($section['id']) {
                'indicadores_educacionais', 'rede_municipal' => 3,
                'fundeb', 'cadastro_censo', 'cadunico_previsao' => 4,
                'publicacao_digital' => 2,
                default => 2,
            };
        }

        return $out;
    }
}
