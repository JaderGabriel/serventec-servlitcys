<x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="user-circle" :title="__('Editar perfil e foto.')">
    {{ __('Perfil') }}
</x-responsive-nav-link>

@include('layouts.partials.user-nav-groups', ['variant' => 'mobile'])

<div class="my-1 border-t border-gray-200/90 dark:border-gray-600/90 mx-3" role="separator"></div>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <x-responsive-nav-link :href="route('logout')" icon="arrow-right-start-rectangle"
            onclick="event.preventDefault(); this.closest('form').submit();">
        {{ __('Sair') }}
    </x-responsive-nav-link>
</form>
