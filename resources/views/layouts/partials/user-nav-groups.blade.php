{{--
    Grupos alinhados ao acesso rápido da dashboard (Início).
    variant: dropdown (menu do utilizador) | mobile (menu expandido no telemóvel)
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

    $groups = array_values(array_filter([
        [
            'show' => $user->is_active,
            'title' => __('Consultoria'),
            'icon' => 'chart-bar',
            'tone' => 'teal',
            'routes' => ['dashboard.analytics*', 'admin.analytics-diagnostics'],
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
                    'title' => __('Testar ligação i-Educar por município.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.ieducar-compatibility.index'),
                    'label' => __('Compatibilidade i-Educar'),
                    'icon' => 'squares-2x2',
                    'active' => $req->routeIs('admin.ieducar-compatibility.*'),
                    'title' => __('Compatibilidade de schema e importação FUNDEB.'),
                ],
            ],
        ],
        [
            'show' => $user->canImportOrConfigure(),
            'title' => __('Monitorização'),
            'icon' => 'computer-desktop',
            'tone' => 'sky',
            'routes' => ['admin.sync-queue.*', 'pulse'],
            'items' => [
                [
                    'show' => true,
                    'href' => route('admin.sync-queue.index'),
                    'label' => __('Filas de processamento'),
                    'icon' => 'queue-list',
                    'active' => $req->routeIs('admin.sync-queue.*'),
                    'title' => __('Sincronização admin e exportação PDF em fila.'),
                ],
                [
                    'show' => true,
                    'href' => route('pulse'),
                    'label' => __('Pulse'),
                    'icon' => 'signal',
                    'active' => $req->routeIs('pulse'),
                    'title' => __('Monitorização em tempo real (Pulse).'),
                ],
            ],
        ],
        [
            'show' => $user->canImportOrConfigure(),
            'title' => __('Sincronização'),
            'icon' => 'map',
            'tone' => 'sky',
            'routes' => ['admin.geo-sync.*', 'admin.pedagogical-sync.*'],
            'items' => [
                [
                    'show' => true,
                    'href' => route('admin.geo-sync.index'),
                    'label' => __('Geográfica'),
                    'icon' => 'map',
                    'active' => $req->routeIs('admin.geo-sync.*'),
                    'title' => __('Coordenadas i-Educar, INEP e microdados.'),
                ],
                [
                    'show' => true,
                    'href' => route('admin.pedagogical-sync.index'),
                    'label' => __('Pedagógica (SAEB)'),
                    'icon' => 'academic-cap',
                    'active' => $req->routeIs('admin.pedagogical-sync.*'),
                    'title' => __('Importação pedagógica SAEB.'),
                ],
            ],
        ],
        [
            'show' => $user->canImportOrConfigure(),
            'title' => __('Administração'),
            'icon' => 'command-line',
            'tone' => 'sky',
            'routes' => ['admin.artisan-commands.*', 'admin.documentation.*', 'settings.mail.*'],
            'items' => [
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
                    'href' => route('admin.documentation.index'),
                    'label' => __('Documentação'),
                    'icon' => 'document-text',
                    'active' => $req->routeIs('admin.documentation.*'),
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
            'title' => __('Equipa'),
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
