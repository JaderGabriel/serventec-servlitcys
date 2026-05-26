<?php

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Entradas de documentação interna (menu) e validação de caminhos legíveis.
 *
 * Qualquer ficheiro .md em docs/ (ou README.md na raiz) pode ser aberto no leitor;
 * o menu lista entradas curadas por percurso lógico do sistema; utilizadores não-admin
 * não veem secções de operação/integração administrativa.
 */
final class DocumentationCatalog
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_ADMIN = 'admin';

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

    public static function canUserReadPath(User $user, string $path): bool
    {
        $resolved = self::resolveReadablePath($path);
        if ($resolved === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return ! in_array($resolved, self::adminOnlyPaths(), true);
    }

    /**
     * @return list<string>
     */
    public static function adminOnlyPaths(): array
    {
        return [
            'README.md',
            'docs/VARIAVEIS_AMBIENTE.md',
            'docs/IMPLANTACAO_PRODUCAO.md',
            'docs/PERFORMANCE.md',
            'docs/COMANDOS_ARTISAN.md',
            'docs/PLANO_TESTES_UNITARIOS.md',
            'docs/IMPORTACAO_DADOS_PUBLICOS.md',
            'docs/IMPORTACAO_SAEB_PLANILHAS_INEP.md',
            'docs/ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md',
            'docs/CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md',
            'docs/ROADMAP_BASES_CALCULOS_FINANCEIROS.md',
        ];
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

    public static function readerUrl(string $path, ?string $routePrefix = null): string
    {
        $resolved = self::resolveReadablePath($path) ?? self::defaultPath();
        $routePrefix ??= self::readerRoutePrefix();

        return route($routePrefix.'.show', ['doc' => $resolved]);
    }

    public static function readerRoutePrefix(?User $user = null): string
    {
        $user ??= auth()->user();

        return ($user !== null && $user->isAdmin())
            ? 'admin.documentation'
            : 'documentation';
    }

    /**
     * @return list<array{title: string, description: string, audience?: string, items: list<array{label: string, path: string, hint?: string, audience?: string}>}>
     */
    public static function sectionsForUser(?User $user = null): array
    {
        $user ??= auth()->user();
        $isAdmin = $user?->isAdmin() ?? false;

        return self::filterSectionsForAudience(self::sections(), $isAdmin);
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
                'title' => __('Começar'),
                'description' => __('Visão do produto, perfis e identidade visual.'),
                'audience' => self::AUDIENCE_ALL,
                'items' => [
                    ['label' => __('Índice da documentação'), 'path' => 'docs/README.md', 'hint' => __('Ponto de entrada')],
                    ['label' => __('Documentação executiva'), 'path' => 'docs/DOCUMENTACAO_EXECUTIVA.md'],
                    ['label' => __('Perfis de utilizador'), 'path' => 'docs/PERFIS_UTILIZADOR.md'],
                    ['label' => __('Design system (UI)'), 'path' => 'docs/DESIGN_SYSTEM.md'],
                    ['label' => __('Estado do projeto'), 'path' => 'docs/STATUS_PROJETO.md'],
                    ['label' => __('Histórico de versões'), 'path' => 'docs/HISTORICO_VERSOES.md', 'hint' => __('Tags, commits e trajetória')],
                    ['label' => __('Entregas escalonadas (mai/2026)'), 'path' => 'docs/ENTREGAS_ESCALONADAS_MAIO_2026.md'],
                    ['label' => __('Backlog de implementações'), 'path' => 'docs/BACKLOG_IMPLEMENTACOES.md'],
                ],
            ],
            [
                'title' => __('Painel de análise'),
                'description' => __('Abas, métricas, inclusão, relatórios e cadastro.'),
                'audience' => self::AUDIENCE_ALL,
                'items' => [
                    ['label' => __('Métricas & Pulse (analytics)'), 'path' => 'docs/METRICAS_QUERIES_ANALYTICS.md'],
                    ['label' => __('Gráficos MEC/INEP'), 'path' => 'docs/SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md'],
                    ['label' => __('SAEB — referências pedagógicas'), 'path' => 'docs/saeb_pedagogico_referencias.md'],
                    ['label' => __('Plugins e cadastro i-Educar'), 'path' => 'docs/PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md'],
                    ['label' => __('Roadmap inclusão e cadastro NEE'), 'path' => 'docs/DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md'],
                    ['label' => __('Relatório PDF ATM'), 'path' => 'docs/RELATORIO_PDF_ATM.md'],
                    ['label' => __('Ponderações técnicas'), 'path' => 'docs/PONDERACOES_TECNICAS.md'],
                ],
            ],
            [
                'title' => __('Financiamento e Censo'),
                'description' => __('FUNDEB, VAAF, exportações e consultas públicas.'),
                'audience' => self::AUDIENCE_ALL,
                'items' => [
                    ['label' => __('FUNDEB / VAAF'), 'path' => 'docs/FUNDEB_VAAF_E_ONDA1.md'],
                    ['label' => __('Exportação planilha FUNDEB'), 'path' => 'docs/EXPORTACAO_DADOS_FUNDEB_PLANILHA.md'],
                    ['label' => __('Comparativo VAAF vs FNDE/MEC'), 'path' => 'docs/COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md'],
                    ['label' => __('Consultas externas (produção)'), 'path' => 'docs/CONSULTAS_EXTERNAS.md', 'hint' => __('FNDE, Tesouro, INEP')],
                ],
            ],
            [
                'title' => __('Releases'),
                'description' => __('Notas por tag de deploy (mais recentes primeiro).'),
                'audience' => self::AUDIENCE_ALL,
                'items' => [
                    ['label' => __('Release 3.4.0 — Nemesis'), 'path' => 'docs/RELEASE_20260531_NEMESIS.md'],
                    ['label' => __('Analytics — navegação e UI (3.4.0)'), 'path' => 'docs/ANALYTICS_NAVEGACAO_UI.md'],
                    ['label' => __('Release 3.3.2 — Metis'), 'path' => 'docs/RELEASE_20260530_METIS.md'],
                    ['label' => __('Release 3.3.1 — Helios'), 'path' => 'docs/RELEASE_20260529_HELIOS.md'],
                    ['label' => __('Release 3.3.0 — Eos'), 'path' => 'docs/RELEASE_20260528_EOS.md'],
                    ['label' => __('Release 3.2.0 — Notus'), 'path' => 'docs/RELEASE_20260527_NOTUS.md'],
                    ['label' => __('Release 3.1.0 — Boreas'), 'path' => 'docs/RELEASE_20260526_BOREAS.md'],
                    ['label' => __('Release 3.0.0 — Apollo'), 'path' => 'docs/RELEASE_20260525_APOLLO.md'],
                    ['label' => __('Release 2.4.0 — Ceres'), 'path' => 'docs/RELEASE_20260524_CERES.md'],
                    ['label' => __('Release 2.3.6 — Janus'), 'path' => 'docs/RELEASE_20260522_JANUS.md'],
                    ['label' => __('Release 2.3.7 — Minerva'), 'path' => 'docs/RELEASE_20260521_MINERVA.md'],
                    ['label' => __('Release 2.3.8 — Mercury'), 'path' => 'docs/RELEASE_20260521_MERCURY.md'],
                ],
            ],
            [
                'title' => __('Integrações e dados públicos'),
                'description' => __('Ingestão administrativa, APIs e estudos de integração.'),
                'audience' => self::AUDIENCE_ADMIN,
                'items' => [
                    ['label' => __('Estudo: setor público e demanda'), 'path' => 'docs/ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md'],
                    ['label' => __('Catálogo API i-Educar (proposta)'), 'path' => 'docs/CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md'],
                    ['label' => __('Importação dados públicos'), 'path' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md'],
                    ['label' => __('Importação SAEB (planilhas INEP)'), 'path' => 'docs/IMPORTACAO_SAEB_PLANILHAS_INEP.md'],
                    ['label' => __('Roadmap bases financeiras'), 'path' => 'docs/ROADMAP_BASES_CALCULOS_FINANCEIROS.md'],
                ],
            ],
            [
                'title' => __('Operação e deploy'),
                'description' => __('Ambiente, segurança, filas e CLI (administradores).'),
                'audience' => self::AUDIENCE_ADMIN,
                'items' => [
                    ['label' => __('Variáveis de ambiente (.env)'), 'path' => 'docs/VARIAVEIS_AMBIENTE.md'],
                    ['label' => __('Implantação em produção'), 'path' => 'docs/IMPLANTACAO_PRODUCAO.md'],
                    ['label' => __('Performance e Redis'), 'path' => 'docs/PERFORMANCE.md'],
                    ['label' => __('Segurança'), 'path' => 'docs/SEGURANCA.md'],
                    ['label' => __('Comandos Artisan'), 'path' => 'docs/COMANDOS_ARTISAN.md'],
                    ['label' => __('Plano de testes unitários'), 'path' => 'docs/PLANO_TESTES_UNITARIOS.md'],
                    ['label' => __('README (instalação)'), 'path' => 'README.md'],
                ],
            ],
            [
                'title' => __('Notas executivas (arquivo)'),
                'description' => __('Documentos de desenho e revisão pontuais.'),
                'audience' => self::AUDIENCE_ALL,
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
     * @param  list<array{title: string, description: string, audience?: string, items: list<array{label: string, path: string, hint?: string, audience?: string}>}>  $sections
     * @return list<array{title: string, description: string, audience?: string, items: list<array{label: string, path: string, hint?: string, audience?: string}>}>
     */
    private static function filterSectionsForAudience(array $sections, bool $isAdmin): array
    {
        $filtered = [];

        foreach ($sections as $section) {
            if (($section['audience'] ?? self::AUDIENCE_ALL) === self::AUDIENCE_ADMIN && ! $isAdmin) {
                continue;
            }

            $items = [];
            foreach ($section['items'] as $item) {
                if (($item['audience'] ?? self::AUDIENCE_ALL) === self::AUDIENCE_ADMIN && ! $isAdmin) {
                    continue;
                }
                if (! $isAdmin && in_array($item['path'], self::adminOnlyPaths(), true)) {
                    continue;
                }
                $items[] = $item;
            }

            if ($items === []) {
                continue;
            }

            $section['items'] = $items;
            $filtered[] = $section;
        }

        return $filtered;
    }

    /**
     * @param  list<array{title: string, description: string, audience?: string, items: list<array{label: string, path: string, hint?: string}>}>  $sections
     * @return list<array{title: string, description: string, audience?: string, items: list<array{label: string, path: string, hint?: string}>}>
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
                'audience' => in_array($path, self::adminOnlyPaths(), true)
                    ? self::AUDIENCE_ADMIN
                    : self::AUDIENCE_ALL,
            ];
        }

        if ($extras === []) {
            return $sections;
        }

        usort($extras, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $sections[] = [
            'title' => __('Outros documentos'),
            'description' => __('Ficheiros adicionais em docs/.'),
            'audience' => self::AUDIENCE_ALL,
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
