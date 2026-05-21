<x-dropdown-link :href="route('profile.edit')" icon="user-circle" :title="__('Editar perfil e foto.')">
    {{ __('Perfil') }}
</x-dropdown-link>

@if (Auth::user()->canImportOrConfigure())
    <div class="my-1 border-t border-slate-200/90 dark:border-gray-600/90" role="separator"></div>
    <x-dropdown-section icon="squares-2x2" tone="teal">{{ __('Sincronização') }}</x-dropdown-section>
    <x-dropdown-link :href="route('admin.geo-sync.index')" icon="map" :title="__('Coordenadas i-Educar, INEP e microdados.')">
        {{ __('Geográficas') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.pedagogical-sync.index')" icon="academic-cap" :title="__('Importação SAEB por município.')">
        {{ __('Pedagógicas') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.ieducar-compatibility.index')" icon="circle-stack" :title="__('Probe de schema i-Educar e FUNDEB.')">
        {{ __('Compat. i-Educar') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.artisan-commands.index')" icon="command-line" :title="__('Referência de comandos Artisan.')">
        {{ __('Comandos') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.sync-queue.index')" icon="queue-list" :title="__('Fila admin-sync e logs.')">
        {{ __('Fila de sync') }}
    </x-dropdown-link>
@endif

@if (Auth::user()->canManageUsers())
    <div class="my-1 border-t border-slate-200/90 dark:border-gray-600/90" role="separator"></div>
    <x-dropdown-section icon="users" tone="indigo">{{ __('Utilizadores') }}</x-dropdown-section>
    <x-dropdown-link :href="route('users.index')" icon="users" :title="__('Lista e gestão de contas.')">
        {{ __('Gerir') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('users.create')" icon="user-plus" :title="__('Criar novo utilizador.')">
        {{ __('Novo') }}
    </x-dropdown-link>
    @if (Auth::user()->isAdmin())
        <x-dropdown-link :href="route('users.sessions.index')" icon="computer-desktop" :title="__('Sessões ativas na aplicação.')">
            {{ __('Sessões') }}
        </x-dropdown-link>
    @endif
@endif

@if (Auth::user()->isAdmin())
    <div class="my-1 border-t border-slate-200/90 dark:border-gray-600/90" role="separator"></div>
    <x-dropdown-section icon="circle-stack" tone="slate">{{ __('Conexões') }}</x-dropdown-section>
    <x-dropdown-link :href="route('admin.connections.index')" icon="circle-stack" :title="__('Testar ligação i-Educar e estatísticas da aplicação.')">
        {{ __('Ligações i-Educar') }}
    </x-dropdown-link>

    <div class="my-1 border-t border-slate-200/90 dark:border-gray-600/90" role="separator"></div>
    <x-dropdown-section icon="document-text" tone="amber">{{ __('Administração') }}</x-dropdown-section>
    <x-dropdown-link :href="route('admin.documentation.index')" icon="document-text" :title="__('Documentação técnica do sistema.')">
        {{ __('Documentação') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('settings.mail.edit')" icon="envelope" :title="__('Configuração de e-mail SMTP.')">
        {{ __('E-mail') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.analytics-diagnostics')" icon="signal" :title="__('Bateria de testes do painel analítico (erro 500 / timeout). Requer ANALYTICS_DIAGNOSTICS_FORCE=true em produção.')">
        {{ __('Diagnóstico analítico') }}
    </x-dropdown-link>
@endif

<div class="my-1 border-t border-slate-200/90 dark:border-gray-600/90" role="separator"></div>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <x-dropdown-link :href="route('logout')" icon="arrow-right-start-rectangle"
            onclick="event.preventDefault(); this.closest('form').submit();">
        {{ __('Sair') }}
    </x-dropdown-link>
</form>
