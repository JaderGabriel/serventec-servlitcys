<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 group" title="{{ config('app.name') }}">
                        <x-application-logo class="block h-9 w-[3.25rem] shrink-0 text-indigo-600 dark:text-indigo-400 group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Painel') }}
                    </x-nav-link>
                    <x-nav-link :href="route('dashboard.analytics')" :active="request()->routeIs('dashboard.analytics')">
                        {{ __('Análise') }}
                    </x-nav-link>
                    @if (Auth::user()->is_admin)
                        <x-nav-link :href="route('cities.index')" :active="request()->routeIs('cities.*')">
                            {{ __('Cidades') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="w-64">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Perfil') }}
                        </x-dropdown-link>

                        @if (Auth::user()->is_admin)
                            <div class="border-t border-gray-200 dark:border-gray-600"></div>
                            <div class="px-4 py-2">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Sincronizações') }}</p>
                            </div>
                            <x-dropdown-link :href="route('admin.geo-sync.index')" :title="__('Coordenadas i-Educar, INEP ArcGIS, microdados e pipeline; saída dos comandos na mesma página.')">
                                {{ __('Geográficas') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('admin.pedagogical-sync.index')" :title="__('Sincronização SAEB por IBGE ou URL; dados reais, sem ficheiros de exemplo.')">
                                {{ __('Pedagógicas') }}
                            </x-dropdown-link>
                            <div class="border-t border-gray-200 dark:border-gray-600"></div>
                            <div class="px-4 py-2">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Usuários') }}</p>
                            </div>
                            <x-dropdown-link :href="route('users.index')">
                                {{ __('Gerenciar') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('users.create')">
                                {{ __('Novo') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('users.sessions.index')">
                                {{ __('Sessões') }}
                            </x-dropdown-link>
                            <div class="border-t border-gray-200 dark:border-gray-600"></div>
                            <x-dropdown-link :href="route('pulse')">
                                {{ __('Monitorização (Pulse)') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('settings.mail.edit')">
                                {{ __('E-mail (SMTP)') }}
                            </x-dropdown-link>
                        @endif

                        <div class="border-t border-gray-200 dark:border-gray-600"></div>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Sair') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Painel') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('dashboard.analytics')" :active="request()->routeIs('dashboard.analytics')">
                {{ __('Análise') }}
            </x-responsive-nav-link>
            @if (Auth::user()->is_admin)
                <x-responsive-nav-link :href="route('cities.index')" :active="request()->routeIs('cities.*')">
                    {{ __('Cidades') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Perfil') }}
                </x-responsive-nav-link>

                @if (Auth::user()->is_admin)
                    <div class="px-4 py-2">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Sincronizações') }}</p>
                    </div>
                    <x-responsive-nav-link :href="route('admin.geo-sync.index')" :active="request()->routeIs('admin.geo-sync.*')" :title="__('Sincronização geográfica (i-Educar, INEP, pipeline).')">
                        {{ __('Geográficas') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.pedagogical-sync.index')" :active="request()->routeIs('admin.pedagogical-sync.*')" :title="__('Sincronização pedagógica (SAEB / JSON).')">
                        {{ __('Pedagógicas') }}
                    </x-responsive-nav-link>
                    <div class="border-t border-gray-200 dark:border-gray-600 my-2"></div>
                    <div class="px-4 py-2">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Usuários') }}</p>
                    </div>
                    <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.index') || request()->routeIs('users.create') || request()->routeIs('users.edit')">
                        {{ __('Lista e gestão') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('users.create')" :active="request()->routeIs('users.create')">
                        {{ __('Novo utilizador') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('users.sessions.index')" :active="request()->routeIs('users.sessions.*')">
                        {{ __('Sessões ativas') }}
                    </x-responsive-nav-link>
                    <div class="border-t border-gray-200 dark:border-gray-600 my-2"></div>
                    <x-responsive-nav-link :href="route('pulse')" :active="request()->routeIs('pulse')">
                        {{ __('Monitorização (Pulse)') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('settings.mail.edit')" :active="request()->routeIs('settings.mail.*')">
                        {{ __('E-mail (SMTP)') }}
                    </x-responsive-nav-link>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Sair') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
