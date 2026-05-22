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
                            {{ __('Início') }}
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
                <x-dropdown align="right" width="w-64" contentClasses="py-1 bg-white dark:bg-gray-800" class="shrink-0">
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
        <div class="pt-1.5 pb-2 space-y-0">
            @if (Auth::user()->canViewAdminDashboard())
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
                    {{ __('Início') }}
                </x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route('dashboard.analytics')" :active="request()->routeIs('dashboard.analytics*')" icon="chart-bar">
                @if (Auth::user()->canViewAdminDashboard())
                    {{ __('Consultoria municipal') }}
                @else
                    {{ __('Meu município') }}
                @endif
            </x-responsive-nav-link>
            <p class="px-4 pt-3 pb-1 text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ __('Mais opções no menu do usuário (submenus expansíveis).') }}
            </p>
        </div>

        <div class="pt-2 pb-2 border-t border-gray-200 dark:border-gray-600">
            <div class="px-3 flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1 flex items-center gap-3">
                    <x-user-avatar :user="Auth::user()" size="md" />
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ Auth::user()->name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ Auth::user()->email }}</div>
                    </div>
                </div>
                <div class="shrink-0">
                    <x-notification-bell />
                </div>
            </div>

            <div class="mt-1 space-y-0 pb-1">
                @include('layouts.partials.user-menu-mobile')
            </div>
        </div>
    </div>
</nav>
