<?php

namespace App\Support\Admin;

/**
 * Entradas de documentação interna (menu admin).
 *
 * @return list<array{title: string, description: string, items: list<array{label: string, path: string, hint?: string}>}>
 */
final class DocumentationCatalog
{
    public static function sections(): array
    {
        return [
            [
                'title' => __('Projeto'),
                'description' => __('Visão, estado e planeamento.'),
                'items' => [
                    ['label' => __('Índice da documentação'), 'path' => 'docs/README.md', 'hint' => __('Ponto de entrada')],
                    ['label' => __('Estado do projeto'), 'path' => 'docs/STATUS_PROJETO.md'],
                    ['label' => __('Backlog de implementações'), 'path' => 'docs/BACKLOG_IMPLEMENTACOES.md'],
                    ['label' => __('Documentação executiva'), 'path' => 'docs/DOCUMENTACAO_EXECUTIVA.md'],
                ],
            ],
            [
                'title' => __('Técnico & consultoria'),
                'description' => __('Regras de cálculo, integrações e desenho.'),
                'items' => [
                    ['label' => __('Ponderações técnicas'), 'path' => 'docs/PONDERACOES_TECNICAS.md'],
                    ['label' => __('Design system (UI / consultoria)'), 'path' => 'docs/DESIGN_SYSTEM.md'],
                    ['label' => __('FUNDEB / VAAF'), 'path' => 'docs/FUNDEB_VAAF_E_ONDA1.md'],
                    ['label' => __('Consultas externas'), 'path' => 'docs/CONSULTAS_EXTERNAS.md'],
                    ['label' => __('Métricas & Pulse (analytics)'), 'path' => 'docs/METRICAS_QUERIES_ANALYTICS.md'],
                    ['label' => __('Gráficos MEC/INEP'), 'path' => 'docs/SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md'],
                ],
            ],
            [
                'title' => __('Operação'),
                'description' => __('Deploy, segurança e CLI.'),
                'items' => [
                    ['label' => __('Implantação em produção'), 'path' => 'docs/IMPLANTACAO_PRODUCAO.md'],
                    ['label' => __('Segurança'), 'path' => 'docs/SEGURANCA.md'],
                    ['label' => __('Comandos Artisan'), 'path' => 'docs/COMANDOS_ARTISAN.md'],
                    ['label' => __('Perfis de utilizador'), 'path' => 'docs/PERFIS_UTILIZADOR.md'],
                    ['label' => __('README (instalação)'), 'path' => 'README.md'],
                ],
            ],
        ];
    }
}
