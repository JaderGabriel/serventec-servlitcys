<?php

namespace App\Support\Admin;

/**
 * Entradas de documentação interna (menu admin).
 *
 * @return list<array{title: string, description: string, items: list<array{label: string, path: string, hint?: string}>}>
 */
final class DocumentationCatalog
{
    public static function defaultPath(): string
    {
        return 'docs/README.md';
    }

    public static function isAllowedPath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        return in_array($path, self::allowedPaths(), true);
    }

    /**
     * @return list<string>
     */
    public static function allowedPaths(): array
    {
        $paths = [];
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                $paths[] = $item['path'];
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return array{label: string, path: string, hint?: string, section_title: string}|null
     */
    public static function findItemByPath(string $path): ?array
    {
        $path = str_replace('\\', '/', trim($path));

        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                if ($item['path'] === $path) {
                    return array_merge($item, ['section_title' => $section['title']]);
                }
            }
        }

        return null;
    }

    public static function githubRepositoryUrl(): string
    {
        return (string) config('documentation.github.repository', '');
    }

    public static function githubBranch(): string
    {
        return (string) config('documentation.github.branch', 'main');
    }

    public static function githubBlobUrl(string $path): string
    {
        $repo = self::githubRepositoryUrl();
        $branch = self::githubBranch();
        $path = str_replace('\\', '/', $path);

        return $repo.'/blob/'.$branch.'/'.$path;
    }

    public static function githubTreeUrl(): string
    {
        $repo = self::githubRepositoryUrl();
        $branch = self::githubBranch();

        return $repo.'/tree/'.$branch.'/docs';
    }

    public static function sections(): array
    {
        return [
            [
                'title' => __('Projeto'),
                'description' => __('Visão, estado e planeamento.'),
                'items' => [
                    ['label' => __('Índice da documentação'), 'path' => 'docs/README.md', 'hint' => __('Ponto de entrada')],
                    ['label' => __('Histórico de versões'), 'path' => 'docs/HISTORICO_VERSOES.md', 'hint' => __('Tags, commits e trajetória')],
                    ['label' => __('Estado do projeto'), 'path' => 'docs/STATUS_PROJETO.md'],
                    ['label' => __('Backlog de implementações'), 'path' => 'docs/BACKLOG_IMPLEMENTACOES.md'],
                    ['label' => __('Documentação executiva'), 'path' => 'docs/DOCUMENTACAO_EXECUTIVA.md'],
                ],
            ],
            [
                'title' => __('Integrações'),
                'description' => __('Fontes públicas, ingestão e previsão de demanda educacional.'),
                'items' => [
                    ['label' => __('Estudo: setor público e demanda'), 'path' => 'docs/ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md', 'hint' => __('Saúde, SUAS, Tesouro, janelas e APIs')],
                    ['label' => __('Consultas externas (produção)'), 'path' => 'docs/CONSULTAS_EXTERNAS.md', 'hint' => __('FNDE, Tesouro, INEP — .env e abas')],
                    ['label' => __('Importação dados públicos'), 'path' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md', 'hint' => __('Hub /admin/dados-publicos')],
                    ['label' => __('Roadmap bases financeiras'), 'path' => 'docs/ROADMAP_BASES_CALCULOS_FINANCEIROS.md'],
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
                    ['label' => __('Importação dados públicos'), 'path' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md', 'hint' => __('Hub admin + PDF ATM')],
                    ['label' => __('Exportação planilha FUNDEB'), 'path' => 'docs/EXPORTACAO_DADOS_FUNDEB_PLANILHA.md'],
                    ['label' => __('Métricas & Pulse (analytics)'), 'path' => 'docs/METRICAS_QUERIES_ANALYTICS.md'],
                    ['label' => __('Gráficos MEC/INEP'), 'path' => 'docs/SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md'],
                    ['label' => __('Plugins e cadastro i-Educar'), 'path' => 'docs/PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md', 'hint' => __('Dados a refinar, módulos e integrações')],
                ],
            ],
            [
                'title' => __('Operação'),
                'description' => __('Deploy, segurança e CLI.'),
                'items' => [
                    ['label' => __('Implantação em produção'), 'path' => 'docs/IMPLANTACAO_PRODUCAO.md'],
                    ['label' => __('Segurança'), 'path' => 'docs/SEGURANCA.md'],
                    ['label' => __('Comandos Artisan'), 'path' => 'docs/COMANDOS_ARTISAN.md'],
                    ['label' => __('Perfis de usuário'), 'path' => 'docs/PERFIS_UTILIZADOR.md'],
                    ['label' => __('README (instalação)'), 'path' => 'README.md'],
                ],
            ],
        ];
    }
}
