{{-- Rodapé alinhado ao painel autenticado (tipografia e espaçamento Tailwind do restante da app). --}}
@php
    $pulseFooter = $pulseFooter ?? false;
@endphp
<footer class="mt-auto border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/90">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
        @if ($pulseFooter)
            <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                <p>
                    © {{ date('Y') }}
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ config('app.name') }}</span>
                </p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    {{ __('Acesso autenticado. Os dados de monitorização (Pulse) são agregados conforme a configuração do servidor.') }}
                </p>
            </div>
        @else
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <p>
                        © {{ date('Y') }}
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ config('app.name') }}</span>
                    </p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        {{ __('Acesso autenticado. Os dados de monitorização (Pulse) são agregados conforme a configuração do servidor.') }}
                    </p>
                </div>
                <nav class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm" aria-label="{{ __('Links rápidos') }}">
                    <a href="{{ route('dashboard') }}" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        {{ __('Painel') }}
                    </a>
                    <a href="{{ route('dashboard.analytics') }}" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        {{ __('Análise educacional') }}
                    </a>
                    @auth
                        @if (Auth::user()->is_admin)
                            <a href="{{ route('pulse') }}" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition {{ request()->routeIs('pulse') ? 'font-semibold text-indigo-600 dark:text-indigo-400' : '' }}">
                                {{ __('Monitorização') }}
                            </a>
                            <a href="{{ route('cities.index') }}" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                {{ __('Cidades') }}
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>
        @endif
    </div>
</footer>
