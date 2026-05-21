{{--
    Grupos alinhados ao acesso rápido da dashboard (Início).
    variant: dropdown (menu do utilizador) | mobile (menu expandido no telemóvel)
--}}
@props(['variant' => 'dropdown'])

@php
    $sectionComponent = $variant === 'mobile' ? 'responsive-nav-section' : 'dropdown-section';
    $linkComponent = $variant === 'mobile' ? 'responsive-nav-link' : 'dropdown-link';
    $separatorClass = $variant === 'mobile'
        ? 'my-1 border-t border-gray-200/90 dark:border-gray-600/90 mx-3'
        : 'my-1 border-t border-slate-200/90 dark:border-gray-600/90';
    $user = Auth::user();
    $showConsultoria = $user->is_active;
    $showMunicipios = $user->isAdmin();
    $showOperacao = $user->canImportOrConfigure();
    $showEquipa = $user->canManageUsers();
    $firstGroup = true;
@endphp

@if ($showConsultoria)
    @if (! $firstGroup)
        <div class="{{ $separatorClass }}" role="separator"></div>
    @endif
    @php $firstGroup = false; @endphp
    <x-dynamic-component :component="$sectionComponent" icon="chart-bar" tone="teal">
        {{ __('Consultoria e relatórios') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('dashboard.analytics')"
        :active="$variant === 'mobile' ? request()->routeIs('dashboard.analytics*') : null"
        icon="chart-bar"
        :title="__('Painel analítico — FUNDEB, matrículas, rede e Censo.')"
    >
        {{ __('Painel analítico') }}
    </x-dynamic-component>
    @if ($user->isAdmin())
        <x-dynamic-component
            :component="$linkComponent"
            :href="route('admin.analytics-diagnostics')"
            :active="$variant === 'mobile' ? request()->routeIs('admin.analytics-diagnostics') : null"
            icon="signal"
            :title="__('Diagnóstico do painel analítico.')"
        >
            {{ __('Diagnóstico analítico') }}
        </x-dynamic-component>
    @endif
@endif

@if ($showMunicipios)
    @if (! $firstGroup)
        <div class="{{ $separatorClass }}" role="separator"></div>
    @endif
    @php $firstGroup = false; @endphp
    <x-dynamic-component :component="$sectionComponent" icon="map-pin" tone="violet">
        {{ __('Municípios e ligações') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('cities.index')"
        :active="$variant === 'mobile' ? request()->routeIs('cities.*') : null"
        icon="map-pin"
        :title="__('Cadastro de cidades, IBGE e activação no mapa.')"
    >
        {{ __('Cidades') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('admin.connections.index')"
        :active="$variant === 'mobile' ? request()->routeIs('admin.connections.*') : null"
        icon="circle-stack"
        :title="__('Testar ligação i-Educar por município.')"
    >
        {{ __('Conexões i-Educar') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('admin.ieducar-compatibility.index')"
        :active="$variant === 'mobile' ? request()->routeIs('admin.ieducar-compatibility.*') : null"
        icon="squares-2x2"
        :title="__('Compatibilidade de schema e importação FUNDEB.')"
    >
        {{ __('Compatibilidade i-Educar') }}
    </x-dynamic-component>
@endif

@if ($showOperacao)
    @if (! $firstGroup)
        <div class="{{ $separatorClass }}" role="separator"></div>
    @endif
    @php $firstGroup = false; @endphp
    <x-dynamic-component :component="$sectionComponent" icon="queue-list" tone="sky">
        {{ __('Operação da plataforma') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('admin.sync-queue.index')"
        :active="$variant === 'mobile' ? request()->routeIs('admin.sync-queue.*') : null"
        icon="queue-list"
        :title="__('Sincronização admin e exportação PDF em fila.')"
    >
        {{ __('Filas de processamento') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('pulse')"
        :active="$variant === 'mobile' ? request()->routeIs('pulse') : null"
        icon="computer-desktop"
        :title="__('Monitorização em tempo real (Pulse).')"
    >
        {{ __('Monitorização (Pulse)') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('admin.geo-sync.index')"
        :active="$variant === 'mobile' ? request()->routeIs('admin.geo-sync.*') : null"
        icon="map"
        :title="__('Coordenadas i-Educar, INEP e microdados.')"
    >
        {{ __('Sincronização geográfica') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('admin.pedagogical-sync.index')"
        :active="$variant === 'mobile' ? request()->routeIs('admin.pedagogical-sync.*') : null"
        icon="academic-cap"
        :title="__('Importação pedagógica SAEB.')"
    >
        {{ __('Sincronização pedagógica') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('admin.artisan-commands.index')"
        :active="$variant === 'mobile' ? request()->routeIs('admin.artisan-commands.*') : null"
        icon="command-line"
        :title="__('Referência de comandos Artisan.')"
    >
        {{ __('Comandos Artisan') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('admin.documentation.index')"
        :active="$variant === 'mobile' ? request()->routeIs('admin.documentation.*') : null"
        icon="document-text"
        :title="__('Documentação técnica do sistema.')"
    >
        {{ __('Documentação') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('settings.mail.edit')"
        :active="$variant === 'mobile' ? request()->routeIs('settings.mail.*') : null"
        icon="envelope"
        :title="__('Configuração SMTP.')"
    >
        {{ __('E-mail (SMTP)') }}
    </x-dynamic-component>
@endif

@if ($showEquipa)
    @if (! $firstGroup)
        <div class="{{ $separatorClass }}" role="separator"></div>
    @endif
    @php $firstGroup = false; @endphp
    <x-dynamic-component :component="$sectionComponent" icon="users" tone="slate">
        {{ __('Equipa') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('users.index')"
        :active="$variant === 'mobile' ? (request()->routeIs('users.index') || request()->routeIs('users.edit')) : null"
        icon="users"
        :title="__('Lista e gestão de contas.')"
    >
        {{ __('Utilizadores') }}
    </x-dynamic-component>
    <x-dynamic-component
        :component="$linkComponent"
        :href="route('users.create')"
        :active="$variant === 'mobile' ? request()->routeIs('users.create') : null"
        icon="user-plus"
        :title="__('Criar novo utilizador.')"
    >
        {{ __('Novo utilizador') }}
    </x-dynamic-component>
    @if ($user->isAdmin())
        <x-dynamic-component
            :component="$linkComponent"
            :href="route('users.sessions.index')"
            :active="$variant === 'mobile' ? request()->routeIs('users.sessions.*') : null"
            icon="computer-desktop"
            :title="__('Sessões ativas na aplicação.')"
        >
            {{ __('Sessões ativas') }}
        </x-dynamic-component>
    @endif
@endif
