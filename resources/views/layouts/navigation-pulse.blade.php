{{-- Barra superior do Pulse: sem menu hamburger; período e tema ao lado do utilizador. --}}
<nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 py-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:gap-x-6">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3 min-w-0">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 shrink-0 group" title="{{ config('app.name') }}">
                    <x-application-logo class="block h-9 w-[3.25rem] shrink-0 text-indigo-600 dark:text-indigo-400 group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition" />
                </a>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-2">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition {{ request()->routeIs('dashboard') ? 'ring-2 ring-indigo-500' : '' }}">
                        {{ __('Painel') }}
                    </a>
                    <a href="{{ route('dashboard.analytics') }}" class="inline-flex items-center justify-center px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition {{ request()->routeIs('dashboard.analytics') ? 'ring-2 ring-indigo-500' : '' }}">
                        {{ __('Análise') }}
                    </a>
                    @if (Auth::user()->is_admin)
                        <a href="{{ route('cities.index') }}" class="inline-flex items-center justify-center px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition {{ request()->routeIs('cities.*') ? 'ring-2 ring-indigo-500' : '' }}">
                            {{ __('Cidades') }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-start lg:justify-end gap-3 sm:gap-4 shrink-0">
                <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <livewire:pulse.period-selector />
                    <x-pulse::theme-switcher />
                </div>
                <div class="flex items-center border-s border-gray-200 dark:border-gray-600 ps-3 sm:ps-4">
                    <x-dropdown align="right" width="w-64">
                        <x-slot name="trigger">
                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                <span class="truncate max-w-[10rem] sm:max-w-xs">{{ Auth::user()->name }}</span>
                                <div class="ms-1 shrink-0">
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
                                <x-dropdown-link :href="route('admin.geo-sync.index')">
                                    {{ __('Geográficas') }}
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.pedagogical-sync.index')">
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
                                <x-dropdown-link :href="route('settings.mail.edit')">
                                    {{ __('E-mail (SMTP)') }}
                                </x-dropdown-link>
                            @endif

                            <div class="border-t border-gray-200 dark:border-gray-600"></div>

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
            </div>
        </div>
    </div>
</nav>
