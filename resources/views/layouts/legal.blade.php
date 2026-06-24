<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $title ?? __('Política de privacidade') }} — {{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @include('partials.theme-init')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen font-sans antialiased text-slate-800 dark:text-slate-200">
        <div class="min-h-screen flex flex-col bg-slate-100 dark:bg-slate-950">
            <header class="serv-nav-brand sticky top-0 z-50 border-b border-slate-200/90 dark:border-slate-700/90 shadow-sm">
                <div class="serv-page-shell flex items-center justify-between gap-3 py-3">
                    <a href="{{ url('/') }}" class="group flex min-w-0 items-center gap-2.5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-sky-700 shadow-sm">
                            <x-application-logo class="h-4 w-4 shrink-0" />
                        </span>
                        <span class="truncate font-display text-sm font-semibold text-slate-900 dark:text-white">{{ config('app.name') }}</span>
                    </a>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-theme-toggle />
                        @auth
                            <a href="{{ Auth::user()->homeUrl() }}" class="serv-link text-sm font-medium">{{ __('Voltar ao painel') }}</a>
                        @else
                            <a href="{{ url('/') }}" class="serv-link text-sm font-medium">{{ __('Início') }}</a>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="flex-1 py-8 sm:py-10">
                <div class="serv-page-shell max-w-3xl">
                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-slate-200/90 bg-white/90 py-4 dark:border-slate-700/90 dark:bg-slate-900/90">
                <div class="serv-page-shell text-center text-xs text-slate-500 dark:text-slate-400">
                    © {{ date('Y') }} {{ config('app.name') }}
                </div>
            </footer>

            <x-scroll-to-top />
        </div>
    </body>
</html>
