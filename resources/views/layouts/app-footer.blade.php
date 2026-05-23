{{-- Rodapé alinhado ao painel autenticado (tipografia e espaçamento Tailwind do restante da app). --}}
@php
    $pulseFooter = $pulseFooter ?? false;
@endphp
<footer @class(['border-t border-slate-200/90 dark:border-slate-700/90 bg-white/95 dark:bg-slate-900/90 backdrop-blur-sm', 'mt-auto' => ! ($pulseFooter ?? false)])>
    <div @class([
        'mx-auto py-4 sm:py-5',
        'max-w-[min(100%,100rem)] px-4 sm:px-6 lg:px-10 xl:px-12' => $pulseFooter,
        'max-w-7xl px-4 sm:px-6 lg:px-8' => ! $pulseFooter,
    ])>
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
                <nav class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm" aria-label="{{ __('Módulos da plataforma') }}">
                    <a href="{{ route('dashboard.analytics') }}" @class(['serv-link text-sm inline-flex items-center gap-1.5', 'font-semibold underline' => request()->routeIs('dashboard.analytics*')])>
                        <x-ui.icon name="chart-bar" class="h-4 w-4 shrink-0 opacity-80" />
                        {{ __('Análise') }}
                    </a>
                    <a href="{{ route('dashboard.rx') }}" @class(['serv-link text-sm inline-flex items-center gap-1.5', 'font-semibold underline' => request()->routeIs('dashboard.rx*')])>
                        <x-ui.icon name="clipboard-document-list" class="h-4 w-4 shrink-0 opacity-80" />
                        RX
                    </a>
                </nav>
            </div>
        @endif
    </div>
</footer>
