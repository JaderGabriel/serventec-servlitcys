<?php

namespace App\Support\Admin;

use App\Models\User;
use App\Support\Product\ProductReleaseTag;
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
            'docs/ESTUDO_AGENTES_IA_SERVLITCYS.md',
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
            foreach (self::sectionItemsFlat($section) as $item) {
                $paths[] = $item['path'];
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Caminho do release em produção (config documentation.product.release_tag).
     */
    public static function productionReleasePath(): ?string
    {
        $tag = trim((string) config('documentation.product.release_tag', ''));
        $path = ProductReleaseTag::releaseDocPath($tag);
        if ($path === null) {
            return null;
        }

        return self::resolveReadablePath($path);
    }

    /**
     * @return list<array{label: string, path: string, hint?: string, sort_key: string}>
     */
    public static function discoverReleaseEntries(): array
    {
        $entries = [];
        foreach (self::discoverDocPaths() as $path) {
            if (! self::isReleasePath($path)) {
                continue;
            }
            $entries[] = self::releaseEntryFromPath($path);
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($b['sort_key'], $a['sort_key']));

        return $entries;
    }

    /**
     * @return array{featured: list<array{label: string, path: string, hint?: string}>, submenu: list<array{label: string, path: string, hint?: string}>}
     */
    public static function releaseOutrosLayout(int $featuredCount = 4): array
    {
        $featuredCount = max(1, $featuredCount);
        $all = self::discoverReleaseEntries();
        $production = self::productionReleasePath();
        $featured = [];
        $seen = [];

        if ($production !== null) {
            foreach ($all as $entry) {
                if ($entry['path'] === $production) {
                    $featured[] = self::withoutSortKey($entry);
                    $seen[$production] = true;
                    break;
                }
            }
        }

        foreach ($all as $entry) {
            if (count($featured) >= $featuredCount) {
                break;
            }
            if (isset($seen[$entry['path']])) {
                continue;
            }
            $featured[] = self::withoutSortKey($entry);
            $seen[$entry['path']] = true;
        }

        $submenu = [];
        foreach ($all as $entry) {
            if (isset($seen[$entry['path']])) {
                continue;
            }
            $submenu[] = self::withoutSortKey($entry);
        }

        return ['featured' => $featured, 'submenu' => $submenu];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return list<array{label: string, path: string, hint?: string}>
     */
    private static function sectionItemsFlat(array $section): array
    {
        $items = is_array($section['items'] ?? null) ? $section['items'] : [];
        foreach ($section['submenus'] ?? [] as $submenu) {
            if (! is_array($submenu)) {
                continue;
            }
            foreach ($submenu['items'] ?? [] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }
        foreach ($section['trailing_items'] ?? [] as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function isReleasePath(string $path): bool
    {
        return (bool) preg_match('/\/RELEASE_\d{8}[a-z]?_[^\/]+\.md$/i', str_replace('\\', '/', $path));
    }

    /**
     * @return array{label: string, path: string, hint?: string, sort_key: string}
     */
    private static function releaseEntryFromPath(string $path): array
    {
        $basename = basename($path, '.md');
        $parsed = ProductReleaseTag::parseDocBasename($basename);
        if ($parsed === null) {
            return [
                'label' => self::labelFromPath($path),
                'path' => $path,
                'sort_key' => '00000000',
            ];
        }

        $dateKey = $parsed['date'];
        $codename = Str::title($parsed['codename']);
        $production = self::productionReleasePath();
        $version = ($path === $production)
            ? trim((string) config('documentation.product.version', ''))
            : '';

        $label = $version !== ''
            ? __('Release :version — :name', ['version' => $version, 'name' => $codename])
            : __('Release — :name', ['name' => $codename]);

        $hint = substr($dateKey, 6, 2).'/'.substr($dateKey, 4, 2).'/'.substr($dateKey, 0, 4);
        if ($path === $production && filter_var(config('documentation.product.in_production', false), FILTER_VALIDATE_BOOL)) {
            $hint = trim((string) config('documentation.product.production_label', __('Em produção'))).' · '.$hint;
        }

        return [
            'label' => $label,
            'path' => $path,
            'hint' => $hint,
            'sort_key' => $parsed['sort_key'],
        ];
    }

    /**
     * @param  array{label: string, path: string, hint?: string, sort_key?: string}  $entry
     * @return array{label: string, path: string, hint?: string}
     */
    private static function withoutSortKey(array $entry): array
    {
        unset($entry['sort_key']);

        return $entry;
    }

    /**
     * @return array{label: string, path: string, hint?: string, section_title: string}|null
     */
    public static function findItemByPath(string $path): ?array
    {
        $normalized = self::resolveReadablePath($path) ?? str_replace('\\', '/', trim($path));

        foreach (self::sections() as $section) {
            foreach (self::sectionItemsFlat($section) as $item) {
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
                    ['label' => __('Hub de documentação'), 'path' => 'docs/HUB_DOCUMENTACAO.md', 'hint' => __('Mapa visual · 4.x · releases')],
                    ['label' => __('Índice da documentação'), 'path' => 'docs/README.md', 'hint' => __('Ponto de entrada')],
                    ['label' => __('Padrão editorial'), 'path' => 'docs/PADRAO_DOCUMENTACAO.md', 'hint' => __('Qualidade e manutenção')],
                    ['label' => __('Arquitectura e fluxos'), 'path' => 'docs/ARQUITETURA_E_FLUXOS.md', 'hint' => __('Diagramas Mermaid')],
                    ['label' => __('Documentação executiva'), 'path' => 'docs/DOCUMENTACAO_EXECUTIVA.md'],
                    ['label' => __('Perfis de utilizador'), 'path' => 'docs/PERFIS_UTILIZADOR.md'],
                    ['label' => __('Design system (UI)'), 'path' => 'docs/DESIGN_SYSTEM.md'],
                    ['label' => __('Estado do projeto'), 'path' => 'docs/STATUS_PROJETO.md'],
                    ['label' => __('Histórico de versões'), 'path' => 'docs/HISTORICO_VERSOES.md', 'hint' => __('Tags, commits e trajetória')],
                    ['label' => __('Backlog de implementações'), 'path' => 'docs/BACKLOG_IMPLEMENTACOES.md'],
                    ['label' => __('Entregas escalonadas (arquivo mai/2026)'), 'path' => 'docs/ENTREGAS_ESCALONADAS_MAIO_2026.md'],
                ],
            ],
            [
                'title' => __('Painel de análise'),
                'description' => __('Abas, métricas, inclusão, relatórios e cadastro.'),
                'audience' => self::AUDIENCE_ALL,
                'items' => [
                    ['label' => __('Navegação consultoria (5 áreas)'), 'path' => 'docs/ANALYTICS_NAVEGACAO_UI.md', 'hint' => __('4.1.1 — cenário C')],
                    ['label' => __('Decisão abas consultoria'), 'path' => 'docs/CONSULTORIA_ABAS_DECISAO.md'],
                    ['label' => __('Início dashboard'), 'path' => 'docs/INICIO_DASHBOARD.md'],
                    ['label' => __('Métricas & Pulse (analytics)'), 'path' => 'docs/METRICAS_QUERIES_ANALYTICS.md'],
                    ['label' => __('CadÚnico — faixas etárias e FUNDEB'), 'path' => 'docs/CADUNICO_FAIXAS_ETARIAS_FUNDEB.md', 'hint' => __('4–17, 0–3, VAAF/VAAT/IEI')],
                    ['label' => __('CadÚnico / Cecad (importação)'), 'path' => 'docs/CADUNICO_CECAD.md'],
                    ['label' => __('CadÚnico previsão territorial'), 'path' => 'docs/CADUNICO_PREVISAO_TERRITORIAL.md'],
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
                    ['label' => __('Consultas externas (produção)'), 'path' => 'docs/CONSULTAS_EXTERNAS.md', 'hint' => __('FNDE, Tesouro, repasses')],
                    ['label' => __('Extrato BB / Open Finance'), 'path' => 'docs/BB_EXTRATO_OPEN_FINANCE.md'],
                    ['label' => __('Exportação planilha FUNDEB'), 'path' => 'docs/EXPORTACAO_DADOS_FUNDEB_PLANILHA.md'],
                    ['label' => __('Comparativo VAAF vs FNDE/MEC'), 'path' => 'docs/COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md'],
                ],
            ],
            [
                'title' => __('Integrações e dados públicos'),
                'description' => __('Ingestão administrativa, APIs e estudos de integração.'),
                'audience' => self::AUDIENCE_ADMIN,
                'items' => [
                    ['label' => __('Estudo: agentes e IA'), 'path' => 'docs/ESTUDO_AGENTES_IA_SERVLITCYS.md', 'hint' => __('Copilot, RAG, automação')],
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

            $items = self::filterItemsForAudience($section['items'] ?? [], $isAdmin);
            $submenus = [];
            foreach ($section['submenus'] ?? [] as $submenu) {
                if (! is_array($submenu)) {
                    continue;
                }
                $subItems = self::filterItemsForAudience($submenu['items'] ?? [], $isAdmin);
                if ($subItems === []) {
                    continue;
                }
                $submenus[] = array_merge($submenu, ['items' => $subItems]);
            }
            $trailing = self::filterItemsForAudience($section['trailing_items'] ?? [], $isAdmin);

            if ($items === [] && $submenus === [] && $trailing === []) {
                continue;
            }

            $section['items'] = $items;
            if ($submenus !== []) {
                $section['submenus'] = $submenus;
            } else {
                unset($section['submenus']);
            }
            if ($trailing !== []) {
                $section['trailing_items'] = $trailing;
            } else {
                unset($section['trailing_items']);
            }
            $filtered[] = $section;
        }

        return $filtered;
    }

    /**
     * @param  list<array{label: string, path: string, hint?: string, audience?: string}>  $items
     * @return list<array{label: string, path: string, hint?: string, audience?: string}>
     */
    private static function filterItemsForAudience(array $items, bool $isAdmin): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['audience'] ?? self::AUDIENCE_ALL) === self::AUDIENCE_ADMIN && ! $isAdmin) {
                continue;
            }
            if (! $isAdmin && in_array($item['path'] ?? '', self::adminOnlyPaths(), true)) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
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
            if (isset($listed[$path]) || self::isReleasePath($path)) {
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

        $releases = self::releaseOutrosLayout(4);
        $hasReleases = $releases['featured'] !== [] || $releases['submenu'] !== [];

        if ($extras === [] && ! $hasReleases) {
            return $sections;
        }

        usort($extras, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $outros = [
            'title' => __('Outros documentos'),
            'description' => __('Releases e ficheiros adicionais em docs/.'),
            'audience' => self::AUDIENCE_ALL,
            'items' => $releases['featured'],
        ];

        if ($releases['submenu'] !== []) {
            $outros['submenus'] = [[
                'title' => __('Demais releases'),
                'items' => $releases['submenu'],
            ]];
        }

        if ($extras !== []) {
            $outros['trailing_items'] = $extras;
        }

        $sections[] = $outros;

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
