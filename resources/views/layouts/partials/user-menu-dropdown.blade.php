<x-dropdown-link :href="route('profile.edit')" icon="user-circle">
    {{ __('Perfil') }}
</x-dropdown-link>

@if (Auth::user()->canImportOrConfigure())
    <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
    <x-dropdown-section icon="squares-2x2" tone="teal">{{ __('Sincronizações') }}</x-dropdown-section>
    <x-dropdown-link :href="route('admin.geo-sync.index')" icon="map" :title="__('Coordenadas i-Educar, INEP ArcGIS, microdados e pipeline.')">
        {{ __('Geográficas') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.pedagogical-sync.index')" icon="academic-cap" :title="__('Sincronização SAEB por IBGE ou URL.')">
        {{ __('Pedagógicas') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.ieducar-compatibility.index')" icon="circle-stack" :title="__('Probe de schema i-Educar e FUNDEB.')">
        {{ __('Compatibilidade i-Educar') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.artisan-commands.index')" icon="command-line" :title="__('Referência CLI: geo, SAEB, FUNDEB.')">
        {{ __('Comandos Artisan') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.sync-queue.index')" icon="queue-list" :title="__('Fila admin-sync com log de andamento.')">
        {{ __('Fila de sincronização') }}
    </x-dropdown-link>
@endif

@if (Auth::user()->canManageUsers())
    <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
    <x-dropdown-section icon="users" tone="indigo">{{ __('Usuários') }}</x-dropdown-section>
    <x-dropdown-link :href="route('users.index')" icon="users">
        {{ __('Gerenciar') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('users.create')" icon="user-plus">
        {{ __('Novo') }}
    </x-dropdown-link>
    @if (Auth::user()->isAdmin())
        <x-dropdown-link :href="route('users.sessions.index')" icon="computer-desktop">
            {{ __('Sessões') }}
        </x-dropdown-link>
    @endif
@endif

@if (Auth::user()->isAdmin())
    <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
    <x-dropdown-section icon="document-text" tone="amber">{{ __('Documentação') }}</x-dropdown-section>
    <x-dropdown-link :href="route('admin.documentation.index')" icon="document-text" :title="__('Índice da documentação técnica.')">
        {{ __('Documentação do sistema') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('settings.mail.edit')" icon="envelope">
        {{ __('E-mail (SMTP)') }}
    </x-dropdown-link>
@endif

<div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <x-dropdown-link :href="route('logout')" icon="arrow-right-start-rectangle"
            onclick="event.preventDefault(); this.closest('form').submit();">
        {{ __('Sair') }}
    </x-dropdown-link>
</form>
