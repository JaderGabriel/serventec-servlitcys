{{-- Barra superior do Pulse: logo/links à esquerda; período, tema e utilizador à direita. --}}
<nav class="border-b border-indigo-200/60 bg-gradient-to-r from-white via-indigo-50/40 to-white shadow-sm dark:border-indigo-900/50 dark:from-gray-800 dark:via-indigo-950/35 dark:to-gray-800">
    <div class="mx-auto max-w-[min(100%,100rem)] px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="pulse-nav-shell min-h-14 py-3 sm:min-h-16 sm:py-4">
            <div class="pulse-nav-primary">
                <a href="{{ Auth::user()->homeUrl() }}" class="flex shrink-0 items-center gap-2 group" title="{{ config('app.name') }}">
                    <x-application-logo class="block h-9 w-[3.25rem] shrink-0 text-indigo-600 dark:text-indigo-400 group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition" />
                </a>
                <div class="pulse-nav-links">
                    @if (Auth::user()->canViewAdminDashboard())
                        <x-pulse-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
                            {{ __('Painel') }}
                        </x-pulse-nav-link>
                    @endif
                    <x-pulse-nav-link :href="route('dashboard.analytics')" :active="request()->routeIs('dashboard.analytics*')" icon="chart-bar">
                        {{ __('Análise') }}
                    </x-pulse-nav-link>
                    <x-pulse-nav-link :href="route('pulse')" :active="request()->routeIs('pulse')" icon="signal">
                        {{ __('Monitorização') }}
                    </x-pulse-nav-link>
                    @if (Auth::user()->isAdmin())
                        <x-pulse-nav-link :href="route('cities.index')" :active="request()->routeIs('cities.*')" icon="map-pin">
                            {{ __('Cidades') }}
                        </x-pulse-nav-link>
                    @endif
                </div>
            </div>

            <div class="pulse-nav-actions">
                <div class="flex flex-nowrap items-center gap-2 sm:gap-3">
                    <livewire:pulse.period-selector />
                    <x-theme-toggle />
                </div>
                <div class="pulse-nav-user">
                    <x-notification-bell />
                    <x-dropdown align="right" width="w-72" class="shrink-0">
                        <x-slot name="trigger">
                            <button type="button" class="inline-flex max-w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm leading-4 font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/80 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition ease-in-out duration-150">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 ring-1 ring-indigo-200/80 dark:ring-indigo-800/80">
                                    <x-ui.icon name="user-circle" class="h-5 w-5" />
                                </span>
                                <span class="truncate max-w-[8rem] sm:max-w-[10rem] lg:max-w-xs">{{ Auth::user()->name }}</span>
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
            </div>
        </div>
    </div>
</nav>
