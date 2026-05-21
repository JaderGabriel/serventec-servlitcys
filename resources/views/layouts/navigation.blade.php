<nav x-data="{ open: false }" class="serv-nav-brand">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ Auth::user()->homeUrl() }}" class="flex items-center gap-2 group" title="{{ config('app.name') }}">
                        <x-application-logo class="block h-9 w-[3.25rem] shrink-0 text-teal-700 dark:text-teal-400 group-hover:text-teal-900 dark:group-hover:text-teal-300 transition" />
                    </a>
                </div>

                <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
                    @if (Auth::user()->canViewAdminDashboard())
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
                            {{ __('Painel') }}
                        </x-nav-link>
                    @endif
                    <x-nav-link :href="route('dashboard.analytics')" :active="request()->routeIs('dashboard.analytics*')" icon="chart-bar">
                        @if (Auth::user()->canViewAdminDashboard())
                            {{ __('Consultoria municipal') }}
                        @else
                            {{ __('Meu município') }}
                        @endif
                    </x-nav-link>
                    @if (Auth::user()->isAdmin())
                        <x-nav-link :href="route('pulse')" :active="request()->routeIs('pulse')" icon="signal">
                            {{ __('Monitorização') }}
                        </x-nav-link>
                        <x-nav-link :href="route('cities.index')" :active="request()->routeIs('cities.*')" icon="map-pin">
                            {{ __('Cidades') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-1 sm:gap-3">
                <x-theme-toggle />
                <x-notification-bell />
                <x-dropdown align="right" width="w-72" class="shrink-0">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex max-w-full items-center gap-2 rounded-lg border border-transparent px-2 py-1.5 text-sm leading-4 font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/80 hover:text-gray-800 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-teal-500/40 transition ease-in-out duration-150">
                            <x-user-avatar :user="Auth::user()" size="md" />
                            <span class="truncate max-w-[8rem] lg:max-w-[12rem]">{{ Auth::user()->name }}</span>
                            <svg class="fill-current h-4 w-4 shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @include('layouts.partials.user-menu-dropdown')
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center gap-1 sm:hidden">
                <x-theme-toggle />
                <button @click="open = ! open" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 transition duration-150 ease-in-out" :aria-expanded="open">
                    <span class="sr-only">{{ __('Abrir menu') }}</span>
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-slate-900/95">
        <div class="pt-2 pb-3 space-y-1">
            @if (Auth::user()->canViewAdminDashboard())
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
                    {{ __('Painel') }}
                </x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route('dashboard.analytics')" :active="request()->routeIs('dashboard.analytics*')" icon="chart-bar">
                @if (Auth::user()->canViewAdminDashboard())
                    {{ __('Consultoria municipal') }}
                @else
                    {{ __('Meu município') }}
                @endif
            </x-responsive-nav-link>
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('pulse')" :active="request()->routeIs('pulse')" icon="signal">
                    {{ __('Monitorização') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('cities.index')" :active="request()->routeIs('cities.*')" icon="map-pin">
                    {{ __('Cidades') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4 flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1 flex items-center gap-3">
                    <x-user-avatar :user="Auth::user()" size="lg" class="ring-2" />
                    <div class="min-w-0">
                        <div class="font-medium text-base text-gray-800 dark:text-gray-200 truncate">{{ Auth::user()->name }}</div>
                        <div class="font-medium text-sm text-gray-500 truncate">{{ Auth::user()->email }}</div>
                    </div>
                </div>
                <div class="shrink-0">
                    <x-notification-bell />
                </div>
            </div>

            <div class="mt-3 space-y-1 pb-3">
                <x-responsive-nav-link :href="route('profile.edit')" icon="user-circle">
                    {{ __('Perfil') }}
                </x-responsive-nav-link>

                @if (Auth::user()->canImportOrConfigure())
                    <div class="px-4 py-2">
                        <p class="flex items-center gap-2 text-xs font-semibold text-teal-800 dark:text-teal-200 uppercase tracking-wider">
                            <x-ui.icon name="squares-2x2" class="h-4 w-4" />
                            {{ __('Sincronizações') }}
                        </p>
                    </div>
                    <x-responsive-nav-link :href="route('admin.geo-sync.index')" :active="request()->routeIs('admin.geo-sync.*')" icon="map">
                        {{ __('Geográficas') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.pedagogical-sync.index')" :active="request()->routeIs('admin.pedagogical-sync.*')" icon="academic-cap">
                        {{ __('Pedagógicas') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.ieducar-compatibility.index')" :active="request()->routeIs('admin.ieducar-compatibility.*')" icon="circle-stack">
                        {{ __('Compatibilidade i-Educar') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.artisan-commands.index')" :active="request()->routeIs('admin.artisan-commands.*')" icon="command-line">
                        {{ __('Comandos Artisan') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.sync-queue.index')" :active="request()->routeIs('admin.sync-queue.*')" icon="queue-list">
                        {{ __('Fila de sincronização') }}
                    </x-responsive-nav-link>
                @endif

                @if (Auth::user()->canManageUsers())
                    <div class="border-t border-gray-200 dark:border-gray-600 my-2"></div>
                    <div class="px-4 py-2">
                        <p class="flex items-center gap-2 text-xs font-semibold text-indigo-800 dark:text-indigo-200 uppercase tracking-wider">
                            <x-ui.icon name="users" class="h-4 w-4" />
                            {{ __('Usuários') }}
                        </p>
                    </div>
                    <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.index') || request()->routeIs('users.edit')" icon="users">
                        {{ __('Lista e gestão') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('users.create')" :active="request()->routeIs('users.create')" icon="user-plus">
                        {{ __('Novo utilizador') }}
                    </x-responsive-nav-link>
                    @if (Auth::user()->isAdmin())
                        <x-responsive-nav-link :href="route('users.sessions.index')" :active="request()->routeIs('users.sessions.*')" icon="computer-desktop">
                            {{ __('Sessões ativas') }}
                        </x-responsive-nav-link>
                    @endif
                @endif

                @if (Auth::user()->isAdmin())
                    <div class="border-t border-gray-200 dark:border-gray-600 my-2"></div>
                    <div class="px-4 py-2">
                        <p class="flex items-center gap-2 text-xs font-semibold text-amber-900 dark:text-amber-100 uppercase tracking-wider">
                            <x-ui.icon name="document-text" class="h-4 w-4" />
                            {{ __('Documentação') }}
                        </p>
                    </div>
                    <x-responsive-nav-link :href="route('admin.documentation.index')" :active="request()->routeIs('admin.documentation.*')" icon="document-text">
                        {{ __('Documentação do sistema') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('settings.mail.edit')" :active="request()->routeIs('settings.mail.*')" icon="envelope">
                        {{ __('E-mail (SMTP)') }}
                    </x-responsive-nav-link>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" icon="arrow-right-start-rectangle"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Sair') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
