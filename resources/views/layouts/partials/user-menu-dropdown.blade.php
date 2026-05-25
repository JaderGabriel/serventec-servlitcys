<x-dropdown-link :href="route('profile.edit')" icon="user-circle" :title="__('Editar perfil e foto.')">
    {{ __('Perfil') }}
</x-dropdown-link>

<x-dropdown-link :href="route('notifications.index')" icon="bell" :title="__('Centro de notificações.')">
    {{ __('Notificações') }}
</x-dropdown-link>

@include('layouts.partials.user-nav-groups', ['variant' => 'dropdown'])

<div class="my-1 border-t border-slate-200/90 dark:border-gray-600/90" role="separator"></div>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <x-dropdown-link :href="route('logout')" icon="arrow-right-start-rectangle"
            onclick="event.preventDefault(); this.closest('form').submit();">
        {{ __('Sair') }}
    </x-dropdown-link>
</form>
