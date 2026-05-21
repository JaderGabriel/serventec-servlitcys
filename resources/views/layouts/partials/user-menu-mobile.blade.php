<x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="user-circle" :title="__('Editar perfil e foto.')">
    {{ __('Perfil') }}
</x-responsive-nav-link>

@if (Auth::user()->canImportOrConfigure())
    <div class="my-1 border-t border-gray-200/90 dark:border-gray-600/90 mx-3" role="separator"></div>
    <x-responsive-nav-section icon="squares-2x2" tone="teal">{{ __('Sincronização') }}</x-responsive-nav-section>
    <x-responsive-nav-link :href="route('admin.geo-sync.index')" :active="request()->routeIs('admin.geo-sync.*')" icon="map" :title="__('Sincronizações geográficas.')">
        {{ __('Geográficas') }}
    </x-responsive-nav-link>
    <x-responsive-nav-link :href="route('admin.pedagogical-sync.index')" :active="request()->routeIs('admin.pedagogical-sync.*')" icon="academic-cap" :title="__('Sincronização pedagógica SAEB.')">
        {{ __('Pedagógicas') }}
    </x-responsive-nav-link>
    <x-responsive-nav-link :href="route('admin.ieducar-compatibility.index')" :active="request()->routeIs('admin.ieducar-compatibility.*')" icon="circle-stack" :title="__('Compatibilidade i-Educar.')">
        {{ __('Compat. i-Educar') }}
    </x-responsive-nav-link>
    <x-responsive-nav-link :href="route('admin.artisan-commands.index')" :active="request()->routeIs('admin.artisan-commands.*')" icon="command-line" :title="__('Comandos Artisan.')">
        {{ __('Comandos') }}
    </x-responsive-nav-link>
    <x-responsive-nav-link :href="route('admin.sync-queue.index')" :active="request()->routeIs('admin.sync-queue.*')" icon="queue-list" :title="__('Fila admin-sync.')">
        {{ __('Fila de sync') }}
    </x-responsive-nav-link>
@endif

@if (Auth::user()->canManageUsers())
    <div class="my-1 border-t border-gray-200/90 dark:border-gray-600/90 mx-3" role="separator"></div>
    <x-responsive-nav-section icon="users" tone="indigo">{{ __('Utilizadores') }}</x-responsive-nav-section>
    <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.index') || request()->routeIs('users.edit')" icon="users" :title="__('Gerir utilizadores.')">
        {{ __('Gerir') }}
    </x-responsive-nav-link>
    <x-responsive-nav-link :href="route('users.create')" :active="request()->routeIs('users.create')" icon="user-plus" :title="__('Novo utilizador.')">
        {{ __('Novo') }}
    </x-responsive-nav-link>
    @if (Auth::user()->isAdmin())
        <x-responsive-nav-link :href="route('users.sessions.index')" :active="request()->routeIs('users.sessions.*')" icon="computer-desktop" :title="__('Sessões ativas.')">
            {{ __('Sessões') }}
        </x-responsive-nav-link>
    @endif
@endif

@if (Auth::user()->isAdmin())
    <div class="my-1 border-t border-gray-200/90 dark:border-gray-600/90 mx-3" role="separator"></div>
    <x-responsive-nav-section icon="document-text" tone="amber">{{ __('Administração') }}</x-responsive-nav-section>
    <x-responsive-nav-link :href="route('admin.documentation.index')" :active="request()->routeIs('admin.documentation.*')" icon="document-text" :title="__('Documentação técnica.')">
        {{ __('Documentação') }}
    </x-responsive-nav-link>
    <x-responsive-nav-link :href="route('settings.mail.edit')" :active="request()->routeIs('settings.mail.*')" icon="envelope" :title="__('Configuração SMTP.')">
        {{ __('E-mail') }}
    </x-responsive-nav-link>
@endif

<div class="my-1 border-t border-gray-200/90 dark:border-gray-600/90 mx-3" role="separator"></div>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <x-responsive-nav-link :href="route('logout')" icon="arrow-right-start-rectangle"
            onclick="event.preventDefault(); this.closest('form').submit();">
        {{ __('Sair') }}
    </x-responsive-nav-link>
</form>
