{{--
    Grupos alinhados ao acesso rápido da dashboard (Início).
    variant: dropdown (menu do usuário) | mobile (menu expandido no telemóvel)
--}}
@props(['variant' => 'dropdown'])

@php
    $variant = $variant ?? 'dropdown';
    $isMobile = $variant === 'mobile';
    $submenuComponent = $isMobile ? 'responsive-nav-submenu' : 'dropdown-submenu';
    $linkComponent = $isMobile ? 'responsive-nav-link' : 'dropdown-submenu-link';
    $separatorClass = $isMobile
        ? 'my-1 border-t border-gray-200/90 dark:border-gray-600/90 mx-3'
        : 'my-1 border-t border-slate-200/90 dark:border-gray-600/90';

    $user = Auth::user();
    $req = request();
    $syncQueueRoutePrefix = \App\Support\SyncQueue\SyncQueueUserScope::routePrefix($user);
    $documentationRoutePrefix = $user->isAdmin() ? 'admin.documentation' : 'documentation';

    $groups = array_values(array_filter([
        [
            'show' => $user->is_active,
            'title' => __('Consultoria'),
            'icon' => 'chart-bar',
            'tone' => 'teal',
            'routes' => ['dashboard.analytics*', 'dashboard.rx*', 'dashboard.horizonte', 'admin.analytics-diagnostics'],
            'items' => array_values(array_filter([
                [
                    'show' => true,
                    'href' => route('dashboard.analytics'),
                    'label' => __('Painel analítico'),
                    'icon' => 'chart-bar',
                    'active' => $req->routeIs('dashboard.analytics*'),
                    'title' => __('Painel analítico — FUNDEB, matrículas, rede e Censo.'),
                ],
                [
                    'show' => true,
                    'href' => route('dashboard.rx'),
                    'label' => __('RX — cadastro e Censo'),
                    'icon' => 'clipboard-document-list',
                    'active' => $req->routeIs('dashboard.rx*'),
                    'title' => __('Todos os municípios: volume digitado, status Censo e trabalho restante.'),
                ],
                [
                    'show' => $user->canViewAdminDashboard(),
                    'href' => route('dashboard.horizonte'),
                    'label' => __('Horizonte'),
                    'icon' => 'globe-alt',
                    'active' => $req->routeIs('dashboard.horizonte'),
                    'title' => __('Mapa de oportunidade — déficits públicos e propensão de Consultoria.'),
                ],
                [
                    'show' => $user->isAdmin(),
                    'href' => route('admin.analytics-diagnostics'),
                    'label' => __('Diagnóstico analítico'),
                    'icon' => 'signal',
                    'active' => $req->routeIs('admin.analytics-diagnostics'),
                    'title' => __('Diagnóstico do painel analítico.'),
                ],
            ])),
        ],
        [
            'show' => ! $user->isAdmin() && ($user->canViewDocumentation() || $user->canViewSyncQueue()),
            'title' => __('Recursos'),
            'icon' => 'document-text',
            'tone' => 'teal',
            'routes' => ['documentation.*', 'sync-queue.*'],
            'items' => array_values(array_filter([
                [
                    'show' => $user->canViewDocumentation(),
                    'href' => route($documentationRoutePrefix.'.index'),
                    'label' => __('Documentação'),
                    'icon' => 'document-text',
                    'active' => $req->routeIs(['admin.documentation.*', 'documentation.*']),
                    'title' => __('Manual do sistema, métricas e releases.'),
                ],
                [
                    'show' => $user->canViewSyncQueue(),
                    'href' => route($syncQueueRoutePrefix.'.index'),
                    'label' => __('Filas de exportação'),
                    'icon' => 'queue-list',
                    'active' => $req->routeIs(['admin.sync-queue.*', 'sync-queue.*']),
                    'title' => __('Exportações NEE e relatórios PDF enfileirados por si.'),
                ],
            ])),
        ],
        [
            'show' => $user->isAdmin(),
            'title' => __('Municípios'),
            'icon' => 'map-pin',
            'tone' => 'violet',
            'routes' => ['cities.*', 'admin.connections.*', 'admin.ieducar-compatibility.*'],
            'items' => [
                [
                    'show' => true,
                    'href' => route('cities.index'),
                    'label' => __('Cidades'),
                    'icon' => 'map-pin',
                    'active' => $req->routeIs('cities.*'),
                    'title' => __('Cadastro de cidades, IBGE e activação no mapa.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.connections.index'),
                    'label' => __('Conexões i-Educar'),
                    'icon' => 'circle-stack',
                    'active' => $req->routeIs('admin.connections.*'),
                    'title' => __('Testar conexão i-Educar por município.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.ieducar-compatibility.index'),
                    'label' => __('admin_ieducar_compatibility.page.nav_label'),
                    'icon' => 'banknotes',
                    'active' => $req->routeIs('admin.ieducar-compatibility.*'),
                    'title' => __('admin_ieducar_compatibility.page.nav_tooltip'),
                ],
            ],
        ],
        [
            'show' => $user->canImportOrConfigure(),
            'title' => __('Operação'),
            'icon' => 'computer-desktop',
            'tone' => 'slate',
            'routes' => ['admin.module-monitor.*', 'pulse'],
            'items' => [
                [
                    'show' => true,
                    'href' => route('admin.module-monitor.index'),
                    'label' => __('Monitor de módulos'),
                    'icon' => 'signal',
                    'active' => $req->routeIs('admin.module-monitor.*'),
                    'title' => __('Saúde por módulo, falhas e lentidões.'),
                ],
                [
                    'show' => true,
                    'href' => route('pulse'),
                    'label' => __('Monitorização (Pulse)'),
                    'icon' => 'signal',
                    'active' => $req->routeIs('pulse'),
                    'title' => __('Monitorização em tempo real — pedidos, SQL e infraestrutura (Laravel Pulse).'),
                ],
            ],
        ],
        [
            'show' => $user->canImportOrConfigure(),
            'title' => __('Dados públicos'),
            'icon' => 'globe-alt',
            'tone' => 'emerald',
            'routes' => ['admin.public-data.*', 'admin.geo-sync.*', 'admin.pedagogical-sync.*', 'admin.cadunico-sync.*', 'admin.sync-queue.*', 'sync-queue.*'],
            'items' => [
                [
                    'show' => true,
                    'href' => route('admin.public-data.index'),
                    'label' => __('Dados públicos'),
                    'icon' => 'squares-2x2',
                    'active' => $req->routeIs('admin.public-data.*') && $req->query('hub', 'hub') === 'hub',
                    'title' => __('Visão geral, lacunas PDF e fontes por área.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.public-data.index', ['hub' => 'repasses']).'#source-repasses_tesouro',
                    'label' => __('Repasses / Tempo Real'),
                    'icon' => 'banknotes',
                    'active' => $req->routeIs('admin.public-data.*') && $req->query('hub') === 'repasses',
                    'title' => __('CKAN, SISWEB e BB — alimenta Finanças → Tempo Real.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.geo-sync.index'),
                    'label' => __('Geográfica (INEP)'),
                    'icon' => 'map-pin',
                    'active' => $req->routeIs('admin.geo-sync.*'),
                    'title' => __('Mapa e divergência INEP — alimenta Unidades escolares na consultoria.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.pedagogical-sync.index'),
                    'label' => __('SAEB / INEP'),
                    'icon' => 'academic-cap',
                    'active' => $req->routeIs('admin.pedagogical-sync.*'),
                    'title' => __('SAEB/IDEB — alimenta a aba Desempenho na consultoria.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.cadunico-sync.index'),
                    'label' => __('CadÚnico / Cecad'),
                    'icon' => 'users',
                    'active' => $req->routeIs('admin.cadunico-sync.*'),
                    'title' => __('Agregados municipais — aba previsão fora da rede na consultoria.'),
                ],
                [
                    'show' => true,
                    'href' => route($syncQueueRoutePrefix.'.index'),
                    'label' => __('Filas de processamento'),
                    'icon' => 'queue-list',
                    'active' => $req->routeIs(['admin.sync-queue.*', 'sync-queue.*']),
                    'title' => __('Sincronização admin e exportação PDF em fila.'),
                ],
            ],
        ],
        [
            'show' => $user->canImportOrConfigure(),
            'title' => __('Administração'),
            'icon' => 'command-line',
            'tone' => 'sky',
            'routes' => ['admin.artisan-commands.*', 'admin.documentation.*', 'admin.legal-consents.*', 'admin.legal-documents.*', 'settings.mail.*'],
            'items' => [
                [
                    'show' => true,
                    'href' => route('admin.legal-documents.index'),
                    'label' => __('Documentos legais'),
                    'icon' => 'document-text',
                    'active' => $req->routeIs('admin.legal-documents.*'),
                    'title' => __('Editar política de privacidade e cookies; publicar versões.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.legal-consents.index'),
                    'label' => __('Consentimentos LGPD'),
                    'icon' => 'shield-check',
                    'active' => $req->routeIs('admin.legal-consents.*'),
                    'title' => __('Aceites, revogações e auditoria por utilizador.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.artisan-commands.index'),
                    'label' => __('Comandos Artisan'),
                    'icon' => 'command-line',
                    'active' => $req->routeIs('admin.artisan-commands.*'),
                    'title' => __('Referência de comandos Artisan.'),
                ],
                [
                    'show' => true,
                    'href' => route($documentationRoutePrefix.'.index'),
                    'label' => __('Documentação'),
                    'icon' => 'document-text',
                    'active' => $req->routeIs(['admin.documentation.*', 'documentation.*']),
                    'title' => __('Documentação técnica do sistema.'),
                ],
                [
                    'show' => true,
                    'href' => route('settings.mail.edit'),
                    'label' => __('E-mail (SMTP)'),
                    'icon' => 'envelope',
                    'active' => $req->routeIs('settings.mail.*'),
                    'title' => __('Configuração SMTP.'),
                ],
            ],
        ],
        [
            'show' => $user->canManageUsers(),
            'title' => __('Utilizadores'),
            'icon' => 'users',
            'tone' => 'slate',
            'routes' => ['users.*'],
            'items' => array_values(array_filter([
                [
                    'show' => true,
                    'href' => route('users.index'),
                    'label' => __('Utilizadores'),
                    'icon' => 'users',
                    'active' => $req->routeIs('users.index') || $req->routeIs('users.edit'),
                    'title' => __('Lista e gestão de contas.'),
                ],
                [
                    'show' => true,
                    'href' => route('users.create'),
                    'label' => __('Novo utilizador'),
                    'icon' => 'user-plus',
                    'active' => $req->routeIs('users.create'),
                    'title' => __('Criar novo utilizador.'),
                ],
                [
                    'show' => $user->isAdmin(),
                    'href' => route('users.sessions.index'),
                    'label' => __('Sessões ativas'),
                    'icon' => 'computer-desktop',
                    'active' => $req->routeIs('users.sessions.*'),
                    'title' => __('Sessões ativas na aplicação.'),
                ],
            ])),
        ],
    ], fn (array $group): bool => ($group['show'] ?? false) && count($group['items'] ?? []) > 0));

    $groupIsActive = function (array $group) use ($req): bool {
        foreach ($group['routes'] ?? [] as $pattern) {
            if ($req->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };
@endphp

@foreach ($groups as $index => $group)
    @if ($index > 0)
        <div class="{{ $separatorClass }}" role="separator"></div>
    @endif

    <x-dynamic-component
        :component="$submenuComponent"
        :icon="$group['icon']"
        :tone="$group['tone']"
        :open="$groupIsActive($group)"
    >
        {{ $group['title'] }}

        <x-slot name="links">
            @foreach ($group['items'] as $item)
                @if ($item['show'] ?? true)
                    @if ($isMobile)
                        <x-responsive-nav-link
                            :href="$item['href']"
                            :active="$item['active'] ?? false"
                            :icon="$item['icon']"
                            :title="$item['title'] ?? null"
                            class="!ps-6 !py-1.5 !text-xs"
                        >
                            {{ $item['label'] }}
                        </x-responsive-nav-link>
                    @else
                        <x-dropdown-submenu-link
                            :href="$item['href']"
                            :icon="$item['icon']"
                            :active="$item['active'] ?? false"
                            :title="$item['title'] ?? null"
                        >
                            {{ $item['label'] }}
                        </x-dropdown-submenu-link>
                    @endif
                @endif
            @endforeach
        </x-slot>
    </x-dynamic-component>
@endforeach
