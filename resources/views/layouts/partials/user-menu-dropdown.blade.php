<x-dropdown-link :href="route('profile.edit')" icon="user-circle" :title="__('Editar perfil e foto.')">
    {{ __('Perfil') }}
</x-dropdown-link>

@if (Auth::user()->canImportOrConfigure())
    <div class="border-t border-gray-200/90 dark:border-gray-600/90 my-0.5"></div>
    <x-dropdown-section icon="squares-2x2" tone="teal">{{ __('Sync') }}</x-dropdown-section>
    <x-dropdown-link :href="route('admin.geo-sync.index')" icon="map" :title="__('Sincronizações geográficas: i-Educar, INEP, microdados.')">
        {{ __('Geo') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.pedagogical-sync.index')" icon="academic-cap" :title="__('Sincronização pedagógica SAEB.')">
        {{ __('SAEB') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.ieducar-compatibility.index')" icon="circle-stack" :title="__('Compatibilidade i-Educar e FUNDEB.')">
        {{ __('i-Educar') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.artisan-commands.index')" icon="command-line" :title="__('Comandos Artisan: geo, SAEB, FUNDEB.')">
        {{ __('CLI') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('admin.sync-queue.index')" icon="queue-list" :title="__('Fila admin-sync.')">
        {{ __('Fila') }}
    </x-dropdown-link>
@endif

@if (Auth::user()->canManageUsers())
    <div class="border-t border-gray-200/90 dark:border-gray-600/90 my-0.5"></div>
    <x-dropdown-section icon="users" tone="indigo">{{ __('Contas') }}</x-dropdown-section>
    <x-dropdown-link :href="route('users.index')" icon="users" :title="__('Gerir utilizadores.')">
        {{ __('Lista') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('users.create')" icon="user-plus" :title="__('Novo utilizador.')">
        {{ __('Novo') }}
    </x-dropdown-link>
    @if (Auth::user()->isAdmin())
        <x-dropdown-link :href="route('users.sessions.index')" icon="computer-desktop" :title="__('Sessões ativas.')">
            {{ __('Sessões') }}
        </x-dropdown-link>
    @endif
@endif

@if (Auth::user()->isAdmin())
    <div class="border-t border-gray-200/90 dark:border-gray-600/90 my-0.5"></div>
    <x-dropdown-section icon="document-text" tone="amber">{{ __('Sistema') }}</x-dropdown-section>
    <x-dropdown-link :href="route('admin.documentation.index')" icon="document-text" :title="__('Documentação técnica.')">
        {{ __('Docs') }}
    </x-dropdown-link>
    <x-dropdown-link :href="route('settings.mail.edit')" icon="envelope" :title="__('Configuração SMTP.')">
        {{ __('SMTP') }}
    </x-dropdown-link>
@endif

<div class="border-t border-gray-200/90 dark:border-gray-600/90 my-0.5"></div>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <x-dropdown-link :href="route('logout')" icon="arrow-right-start-rectangle"
            onclick="event.preventDefault(); this.closest('form').submit();">
        {{ __('Sair') }}
    </x-dropdown-link>
</form>
