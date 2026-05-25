<?php

namespace App\Support\Admin;

use Illuminate\Support\Str;

/**
 * Entradas de documentação interna (menu admin) e validação de caminhos legíveis.
 *
 * Qualquer ficheiro .md em docs/ (ou README.md na raiz) pode ser aberto no leitor;
 * o menu lista entradas curadas e acrescenta automaticamente os restantes ficheiros.
 */
final class DocumentationCatalog
{
    public static function defaultPath(): string
    {
        return 'docs/README.md';
    }

    public static function isAllowedPath(string $path): bool
    {
        return self::resolveReadablePath($path) !== null;
    }

    /**
     * Normaliza e valida caminho (sem directory traversal). Devolve path relativo ao project root.
     */
    public static function resolveReadablePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (! str_ends_with(strtolower($path), '.md')) {
            return null;
        }

        if (! str_contains($path, '/')) {
            $path = strcasecmp($path, 'README.md') === 0 ? 'README.md' : 'docs/'.$path;
        }

        $root = realpath(base_path());
        if ($root === false) {
            return null;
        }

        $absolute = realpath(base_path($path));
        if ($absolute === false || ! is_file($absolute)) {
            return null;
        }

        if (! str_starts_with($absolute, $root.DIRECTORY_SEPARATOR) && $absolute !== $root) {
            return null;
        }

        $docsDir = realpath(base_path('docs'));
        $readme = realpath(base_path('README.md'));

        if ($docsDir !== false && str_starts_with($absolute, $docsDir.DIRECTORY_SEPARATOR)) {
            return self::pathRelativeToRoot($root, $absolute);
        }

        if ($readme !== false && $absolute === $readme) {
            return 'README.md';
        }

        return null;
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
        $normalized = self::resolveReadablePath($path) ?? str_replace('\\', '/', trim($path));

        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                if ($item['path'] === $normalized) {
                    return array_merge($item, ['section_title' => $section['title']]);
                }
            }
        }

        if (! self::isAllowedPath($normalized)) {
            return null;
        }

        return [
            'label' => self::labelFromPath($normalized),
            'path' => $normalized,
            'section_title' => __('Documentação'),
        ];
    }

    public static function readerUrl(string $path): string
    {
        $resolved = self::resolveReadablePath($path) ?? self::defaultPath();

        return route('admin.documentation.show', ['doc' => $resolved]);
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
        $resolved = self::resolveReadablePath($path) ?? str_replace('\\', '/', trim($path));
        $repo = self::githubRepositoryUrl();
        $branch = self::githubBranch();

        return $repo.'/blob/'.$branch.'/'.$resolved;
    }

    public static function githubTreeUrl(): string
    {
        $repo = self::githubRepositoryUrl();
        $branch = self::githubBranch();

        return $repo.'/tree/'.$branch.'/docs';
    }

    public static function sections(): array
    {
        $sections = [
            [
                'title' => __('Projeto'),
                'description' => __('Visão, estado e planeamento.'),
                'items' => [
                    ['label' => __('Índice da documentação'), 'path' => 'docs/README.md', 'hint' => __('Ponto de entrada')],
                    ['label' => __('Histórico de versões'), 'path' => 'docs/HISTORICO_VERSOES.md', 'hint' => __('Tags, commits e trajetória')],
                    ['label' => __('Entregas escalonadas (mai/2026)'), 'path' => 'docs/ENTREGAS_ESCALONADAS_MAIO_2026.md', 'hint' => __('Commits e PRs por bloco')],
                    ['label' => __('Estado do projeto'), 'path' => 'docs/STATUS_PROJETO.md'],
                    ['label' => __('Backlog de implementações'), 'path' => 'docs/BACKLOG_IMPLEMENTACOES.md'],
                    ['label' => __('Documentação executiva'), 'path' => 'docs/DOCUMENTACAO_EXECUTIVA.md'],
                ],
            ],
            [
                'title' => __('Releases'),
                'description' => __('Notas por tag de deploy.'),
                'items' => [
                    ['label' => __('Release 3.0.0 — Apollo'), 'path' => 'docs/RELEASE_20260525_APOLLO.md'],
                    ['label' => __('Release 2.4.0 — Ceres'), 'path' => 'docs/RELEASE_20260524_CERES.md'],
                    ['label' => __('Release 2.3.6 — Janus'), 'path' => 'docs/RELEASE_20260522_JANUS.md'],
                    ['label' => __('Release 2.3.7 — Minerva'), 'path' => 'docs/RELEASE_20260521_MINERVA.md'],
                    ['label' => __('Release 2.3.8 — Mercury'), 'path' => 'docs/RELEASE_20260521_MERCURY.md'],
                ],
            ],
            [
                'title' => __('Integrações'),
                'description' => __('Fontes públicas, ingestão e previsão de demanda educacional.'),
                'items' => [
                    ['label' => __('Estudo: setor público e demanda'), 'path' => 'docs/ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md', 'hint' => __('Saúde, SUAS, Tesouro, janelas e APIs')],
                    ['label' => __('Catálogo API i-Educar (proposta)'), 'path' => 'docs/CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md'],
                    ['label' => __('Consultas externas (produção)'), 'path' => 'docs/CONSULTAS_EXTERNAS.md', 'hint' => __('FNDE, Tesouro, INEP — .env e abas')],
                    ['label' => __('Importação dados públicos'), 'path' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md', 'hint' => __('Hub /admin/dados-publicos')],
                    ['label' => __('Importação SAEB (planilhas INEP)'), 'path' => 'docs/IMPORTACAO_SAEB_PLANILHAS_INEP.md'],
                    ['label' => __('Roadmap bases financeiras'), 'path' => 'docs/ROADMAP_BASES_CALCULOS_FINANCEIROS.md'],
                    ['label' => __('Comparativo VAAF vs FNDE/MEC'), 'path' => 'docs/COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md'],
                ],
            ],
            [
                'title' => __('Técnico & consultoria'),
                'description' => __('Regras de cálculo, integrações e desenho.'),
                'items' => [
                    ['label' => __('Ponderações técnicas'), 'path' => 'docs/PONDERACOES_TECNICAS.md'],
                    ['label' => __('Design system (UI / consultoria)'), 'path' => 'docs/DESIGN_SYSTEM.md'],
                    ['label' => __('FUNDEB / VAAF'), 'path' => 'docs/FUNDEB_VAAF_E_ONDA1.md'],
                    ['label' => __('Métricas & Pulse (analytics)'), 'path' => 'docs/METRICAS_QUERIES_ANALYTICS.md'],
                    ['label' => __('Gráficos MEC/INEP'), 'path' => 'docs/SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md'],
                    ['label' => __('SAEB — referências pedagógicas'), 'path' => 'docs/saeb_pedagogico_referencias.md'],
                    ['label' => __('Plugins e cadastro i-Educar'), 'path' => 'docs/PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md', 'hint' => __('Dados a refinar, módulos e integrações')],
                    ['label' => __('Roadmap inclusão e cadastro NEE'), 'path' => 'docs/DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md'],
                    ['label' => __('Exportação planilha FUNDEB'), 'path' => 'docs/EXPORTACAO_DADOS_FUNDEB_PLANILHA.md'],
                    ['label' => __('Relatório PDF ATM'), 'path' => 'docs/RELATORIO_PDF_ATM.md'],
                ],
            ],
            [
                'title' => __('Operação'),
                'description' => __('Deploy, segurança e CLI.'),
                'items' => [
                    ['label' => __('Variáveis de ambiente (.env)'), 'path' => 'docs/VARIAVEIS_AMBIENTE.md'],
                    ['label' => __('Implantação em produção'), 'path' => 'docs/IMPLANTACAO_PRODUCAO.md'],
                    ['label' => __('Performance e Redis'), 'path' => 'docs/PERFORMANCE.md'],
                    ['label' => __('Segurança'), 'path' => 'docs/SEGURANCA.md'],
                    ['label' => __('Comandos Artisan'), 'path' => 'docs/COMANDOS_ARTISAN.md'],
                    ['label' => __('Perfis de usuário'), 'path' => 'docs/PERFIS_UTILIZADOR.md'],
                    ['label' => __('Plano de testes unitários'), 'path' => 'docs/PLANO_TESTES_UNITARIOS.md'],
                    ['label' => __('README (instalação)'), 'path' => 'README.md'],
                ],
            ],
            [
                'title' => __('Notas executivas (arquivo)'),
                'description' => __('Documentos de desenho e revisão pontuais.'),
                'items' => [
                    ['label' => __('Revisão técnica do projeto'), 'path' => 'docs/DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md'],
                    ['label' => __('Rede & oferta — BI'), 'path' => 'docs/DOCUMENTO_EXECUTIVO_REDE_OFERTA_BI.md'],
                    ['label' => __('Testes mapa unidades escolares'), 'path' => 'docs/DOCUMENTO_EXECUTIVO_TESTE_MAPA_UNIDADES_ESCOLARES.md'],
                ],
            ],
        ];

        return self::appendDiscoveredSections($sections);
    }

    /**
     * @param  list<array{title: string, description: string, items: list<array{label: string, path: string, hint?: string}>}>  $sections
     * @return list<array{title: string, description: string, items: list<array{label: string, path: string, hint?: string}>}>
     */
    private static function appendDiscoveredSections(array $sections): array
    {
        $listed = [];
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $listed[$item['path']] = true;
            }
        }

        $extras = [];
        foreach (self::discoverDocPaths() as $path) {
            if (isset($listed[$path])) {
                continue;
            }
            $extras[] = [
                'label' => self::labelFromPath($path),
                'path' => $path,
            ];
        }

        if ($extras === []) {
            return $sections;
        }

        usort($extras, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $sections[] = [
            'title' => __('Outros documentos'),
            'description' => __('Ficheiros adicionais em docs/ (links do índice README).'),
            'items' => $extras,
        ];

        return $sections;
    }

    /**
     * @return list<string>
     */
    private static function discoverDocPaths(): array
    {
        $docsDir = base_path('docs');
        if (! is_dir($docsDir)) {
            return [];
        }

        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($docsDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $relative = 'docs/'.str_replace('\\', '/', substr($file->getPathname(), strlen($docsDir) + 1));
            if (self::resolveReadablePath($relative) !== null) {
                $paths[] = $relative;
            }
        }

        sort($paths);

        return $paths;
    }

    private static function labelFromPath(string $path): string
    {
        $base = basename($path, '.md');
        $base = preg_replace('/^RELEASE_\d{8}_/i', 'Release ', $base) ?? $base;
        $base = str_replace('_', ' ', $base);

        return Str::title(trim($base));
    }

    private static function pathRelativeToRoot(string $root, string $absolute): string
    {
        $relative = substr($absolute, strlen($root) + 1);

        return str_replace('\\', '/', $relative);
    }
}
